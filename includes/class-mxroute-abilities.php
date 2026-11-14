<?php
/**
 * MXRoute_Abilities — registers MXRoute Mailer capabilities with the
 * WordPress Abilities API so they are discoverable by the MCP Adapter
 * and AI agents (AI phase A2).
 *
 * Contract (the A0 lesson): execute_callback AND permission_callback,
 * both present and callable — anything less registers nothing.
 * Registration is two-phase: categories on wp_abilities_api_categories_init,
 * abilities on wp_abilities_api_init. An ability whose category is not
 * registered is silently rejected.
 *
 * Three abilities: mxroute/queue-status (read), mxroute/requeue-failed
 * (write — the claim-aware bulk requeue), mxroute/send-test (write —
 * the existing test-send path). Mail is admin territory: every ability
 * is gated by mxroute_mailer_can_manage(). Missing configuration or
 * unlistable data returns explicit WP_Errors — absence of evidence is
 * never a state.
 */

defined( 'ABSPATH' ) || exit;

class MXRoute_Abilities {

	/**
	 * Mail abilities are for administrators (multisite-aware).
	 *
	 * @return bool|WP_Error
	 */
	public function can_use() {
		if ( function_exists( 'mxroute_mailer_can_manage' ) && mxroute_mailer_can_manage() ) {
			return true;
		}
		return new WP_Error(
			'mxroute_cannot_use_ability',
			__( 'You are not allowed to use this ability.', 'mxroute-mailer' )
		);
	}

	public function register_hooks(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return; // Abilities API not available (pre-6.9).
		}
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_categories' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register the "site" category before any ability claims it —
	 * registration is silently rejected otherwise. Self-sufficient:
	 * harmless if MetaManager (or anyone) registered it first.
	 */
	public function register_categories(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		if ( class_exists( 'WP_Ability_Categories_Registry' ) ) {
			$registry = WP_Ability_Categories_Registry::get_instance();
			if ( $registry && method_exists( $registry, 'is_registered' ) && $registry->is_registered( 'site' ) ) {
				return;
			}
		}
		wp_register_ability_category( 'site', array(
			'label'       => __( 'Site', 'mxroute-mailer' ),
			'description' => 'Site content, mail, and server status reports.',
		) );
	}

	public function register_abilities(): void {
		$read_only_input = array(
			'type'       => 'object',
			'properties' => new stdClass(),
		);

		wp_register_ability( 'mxroute/queue-status', array(
			'label'         => __( 'Get Mail Queue Status', 'mxroute-mailer' ),
			'description'   => 'Read the MXRoute mail queue: pending, sent, and failed counts, plus the most recent failures with their errors.',
			'category'      => 'site',
			'input_schema'  => $read_only_input,
			'output_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'pending' => array( 'type' => 'integer' ),
					'sent'    => array( 'type' => 'integer' ),
					'failed'  => array( 'type' => 'integer' ),
					'errors'  => array(
						'type'        => 'array',
						'description' => 'The most recent failures: id, to, subject, at, error.',
					),
				),
			),
			'execute_callback'    => array( $this, 'ability_queue_status' ),
			'permission_callback' => array( $this, 'can_use' ),
			'meta'                => array( 'mcp.public' => true ),
		) );

		wp_register_ability( 'mxroute/requeue-failed', array(
			'label'         => __( 'Requeue Failed Mail', 'mxroute-mailer' ),
			'description'   => 'Move failed mail back to the pending queue for delivery. Claim-aware: messages currently being processed are never touched.',
			'category'      => 'site',
			'input_schema'  => array(
				'type'       => 'object',
				'properties' => array(
					'ids' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'description' => 'Optional explicit queue ids. Omit to requeue every failed message.',
					),
				),
			),
			'output_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'requeued' => array( 'type' => 'integer' ),
				),
			),
			'execute_callback'    => array( $this, 'ability_requeue_failed' ),
			'permission_callback' => array( $this, 'can_use' ),
			'meta'                => array( 'mcp.public' => true ),
		) );

		wp_register_ability( 'mxroute/send-test', array(
			'label'         => __( 'Send Test Email', 'mxroute-mailer' ),
			'description'   => 'Send a test message through the configured MXRoute transport to verify mail delivery.',
			'category'      => 'site',
			'input_schema'  => array(
				'type'       => 'object',
				'properties' => array(
					'to'      => array( 'type' => 'string', 'description' => 'Recipient address.' ),
					'subject' => array( 'type' => 'string' ),
					'body'    => array( 'type' => 'string' ),
				),
				'required' => array( 'to' ),
			),
			'output_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'sent'   => array( 'type' => 'boolean', 'description' => 'Accepted into the delivery pipeline (queued), not yet confirmed delivered.' ),
					'queued' => array( 'type' => 'boolean' ),
					'log_id' => array( 'type' => 'integer', 'description' => 'Queue log row ID; delivery status is recorded against it.' ),
					'to'     => array( 'type' => 'string' ),
					'from'   => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => array( $this, 'ability_send_test' ),
			'permission_callback' => array( $this, 'can_use' ),
			'meta'                => array( 'mcp.public' => true ),
		) );
	}

	// ------------------------------------------------------------------
	// mxroute/queue-status — counts exactly as the status writer reports
	// them, plus the most recent failures.
	// ------------------------------------------------------------------

	/**
	 * @return array
	 */
	public function ability_queue_status( array $input = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'mxroute_mailer_logs';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		$pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE success = 0 AND processed_at IS NULL" );
		$sent    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE success = 1" );
		$failed  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE success = -1" );
		$rows    = $wpdb->get_results(
			"SELECT id, to_email, subject, timestamp, api_response FROM {$table} WHERE success = -1 ORDER BY id DESC LIMIT 5"
		);
		// phpcs:enable

		$errors = array();
		foreach ( (array) $rows as $row ) {
			$errors[] = array(
				'id'      => (int) ( $row->id ?? 0 ),
				'to'      => (string) ( $row->to_email ?? '' ),
				'subject' => (string) ( $row->subject ?? '' ),
				'at'      => (string) ( $row->timestamp ?? '' ),
				'error'   => mb_substr( trim( (string) ( $row->api_response ?? '' ) ), 0, 200 ),
			);
		}

		return array(
			'pending' => $pending,
			'sent'    => $sent,
			'failed'  => $failed,
			'errors'  => $errors,
		);
	}

	// ------------------------------------------------------------------
	// mxroute/requeue-failed — claim-aware bulk requeue.
	// ------------------------------------------------------------------

	/**
	 * @param array{ids?: int[]} $input
	 * @return array
	 */
	public function ability_requeue_failed( array $input = array() ) {
		$ids = null;
		if ( ! empty( $input['ids'] ) && is_array( $input['ids'] ) ) {
			$ids = array_map( 'intval', $input['ids'] );
		}
		$queue = new MXRoute_Queue();
		$count = $queue->requeue_failed( 'ability:mxroute/requeue-failed', $ids );
		return array( 'requeued' => (int) $count );
	}

	// ------------------------------------------------------------------
	// mxroute/send-test — the existing test-send path.
	// ------------------------------------------------------------------

	/**
	 * @param array{to: string, subject?: string, body?: string} $input
	 * @return array|WP_Error
	 */
	public function ability_send_test( array $input = array() ) {
		$to = sanitize_email( $input['to'] ?? '' );
		if ( '' === $to ) {
			return new WP_Error( 'invalid_input', 'A recipient address is required.' );
		}
		$from = (string) get_option( 'mxroute_mailer_username', '' );
		if ( '' === $from ) {
			return new WP_Error(
				'missing_config',
				'Missing sender address — the mail transport is not configured.'
			);
		}
		$subject = sanitize_text_field( $input['subject'] ?? '' );
		if ( '' === $subject ) {
			$subject = __( 'MXRoute Mailer Test', 'mxroute-mailer' );
		}
		$body = sanitize_textarea_field( $input['body'] ?? '' );
		if ( '' === $body ) {
			$body = __( 'This is a test email from MXRoute Mailer.', 'mxroute-mailer' );
		}

		// Route through the plugin's real pipeline (the same one wp_mail
		// and the dashboard test form use): the queue logs the message and
		// the cron processor delivers it. A direct API call here would
		// report success regardless of the outcome and leave no trace.
		$queue  = new MXRoute_Queue();
		$log_id = $queue->add( $from, $to, $subject, $body, '', array(), '' );
		if ( false === $log_id ) {
			return new WP_Error( 'send_failed', 'The test message could not be queued.' );
		}
		return array(
			'sent'   => true,
			'queued' => true,
			'log_id' => (int) $log_id,
			'to'     => $to,
			'from'   => $from,
		);
	}
}
