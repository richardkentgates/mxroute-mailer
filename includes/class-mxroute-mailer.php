<?php
/**
 * MXRoute Mailer main plugin class.
 *
 * @package MXRoute_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class — singleton that intercepts wp_mail, queues emails,
 * and processes the queue via WP-Cron.
 */
class MXRoute_Mailer {

	/**
	 * Singleton instance.
	 *
	 * @var MXRoute_Mailer|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return MXRoute_Mailer
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required files.
	 *
	 * @return void
	 */
	private function includes() {
		require_once MXROUTE_MAILER_PLUGIN_DIR . 'includes/class-mxroute-api.php';
		require_once MXROUTE_MAILER_PLUGIN_DIR . 'includes/class-mxroute-settings.php';
		require_once MXROUTE_MAILER_PLUGIN_DIR . 'includes/class-mxroute-logger.php';
		require_once MXROUTE_MAILER_PLUGIN_DIR . 'includes/class-mxroute-dashboard.php';
		require_once MXROUTE_MAILER_PLUGIN_DIR . 'includes/class-mxroute-queue.php';
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * Priority 999 ensures this runs after other pre_wp_mail handlers so the
	 * MXRoute send takes precedence when the plugin is configured.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_filter( 'pre_wp_mail', array( $this, 'intercept_wp_mail' ), 999, 2 );
		add_action( 'load-settings_page_mxroute-mailer', array( $this, 'handle_test_email' ) );
		add_action( 'mxroute_mailer_process_queue', array( $this, 'process_queue' ) );
	}

	/**
	 * Intercept wp_mail to queue emails for background processing.
	 *
	 * This is attached to the `pre_wp_mail` filter. Returning a non-null value
	 * short-circuits WordPress's default mailer, preventing duplicate sends
	 * through server mailers such as sendmail or ssmtp.
	 *
	 * Each recipient gets its own queue entry for independent processing.
	 *
	 * @param mixed $pre  Value passed to pre_wp_mail, normally null.
	 * @param array $args wp_mail arguments.
	 * @return mixed|null true on success, false on failure, $pre to let default mailer run.
	 */
	public function intercept_wp_mail( $pre, $args = null ) {
		// Support direct calls with a single args array.
		if ( null === $args ) {
			$args = $pre;
			$pre  = null;
		}

		$defaults = array(
			'to'          => '',
			'subject'     => '',
			'message'     => '',
			'headers'     => '',
			'attachments' => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		if ( empty( $args['to'] ) || empty( $args['subject'] ) ) {
			return $pre;
		}

		$server   = get_option( 'mxroute_mailer_server', '' );
		$username = get_option( 'mxroute_mailer_username', '' );
		$password = get_option( 'mxroute_mailer_password', '' );

		if ( empty( $server ) || empty( $username ) || empty( $password ) ) {
			return $pre;
		}

		$from     = $username;
		$reply_to = $this->extract_from_address( $args['headers'] );
		if ( sanitize_email( $reply_to ) === sanitize_email( $from ) ) {
			$reply_to = '';
		}

		$attachments = $this->normalize_attachments( $args['attachments'] );

		$queue = new MXRoute_Queue();

		$recipients = is_array( $args['to'] ) ? array_values( array_filter( $args['to'] ) ) : array( $args['to'] );

		$queued = 0;
		foreach ( $recipients as $recipient ) {
			$queue->add(
				$from,
				$recipient,
				$args['subject'],
				$args['message'],
				$args['headers'],
				$attachments,
				$reply_to
			);
			++$queued;
		}

		if ( 0 === $queued ) {
			return $pre;
		}

		// Schedule the queue processor if not already scheduled.
		if ( ! wp_next_scheduled( 'mxroute_mailer_process_queue' ) ) {
			wp_schedule_single_event( time(), 'mxroute_mailer_process_queue' );
		}

		return true;
	}

	/**
	 * Process pending emails in the queue.
	 *
	 * Reads a batch of pending items from the queue, sends each via the
	 * MXRoute API, and updates the row with the result.
	 *
	 * @return void
	 */
	public function process_queue() {
		$queue    = new MXRoute_Queue();
		$api      = new MXRoute_API();
		$batch    = absint( get_option( 'mxroute_mailer_batch_size', 50 ) );
		$pending  = $queue->get_pending( $batch );

		if ( empty( $pending ) ) {
			return;
		}

		$logger = new MXRoute_Logger();

		foreach ( $pending as $item ) {
			$attachments = json_decode( $item->attachments, true );
			if ( ! is_array( $attachments ) ) {
				$attachments = array();
			}
			$attachments = $this->normalize_attachments( $attachments );

			$result = $api->send(
				$item->from_email,
				$item->to_email,
				$item->subject,
				$item->message,
				$item->reply_to,
				$attachments
			);

			if ( $result['success'] ) {
				$queue->mark_sent( $item->id, $result['request'], $result['response'] );
			} else {
				$queue->mark_failed( $item->id, $result['request'], $result['response'] );
			}
		}
	}

	/**
	 * Normalize an attachments array to resolve WordPress attachment IDs to file paths.
	 *
	 * WordPress wp_mail() accepts attachments as file paths (strings) or
	 * attachment IDs (integers from the media library). This method resolves
	 * IDs to file paths using get_attached_file() and filters out invalid entries.
	 *
	 * @param array $attachments Array of file paths or attachment IDs.
	 * @return array Normalized array of file paths.
	 */
	private function normalize_attachments( $attachments ) {
		$normalized = array();

		foreach ( (array) $attachments as $attachment ) {
			if ( is_int( $attachment ) || ( is_string( $attachment ) && ctype_digit( $attachment ) ) ) {
				$attachment_id = absint( $attachment );
				$file_path     = get_attached_file( $attachment_id );
				if ( $file_path && file_exists( $file_path ) && is_readable( $file_path ) ) {
					$normalized[] = $file_path;
				}
			} elseif ( is_string( $attachment ) && '' !== $attachment ) {
				if ( file_exists( $attachment ) && is_readable( $attachment ) ) {
					$normalized[] = $attachment;
				}
			}
		}

		return $normalized;
	}

	/**
	 * Extract the From address from wp_mail headers.
	 *
	 * @param string|array $headers wp_mail headers.
	 * @return string From email address.
	 */
	private function extract_from_address( $headers ) {
		$default_from = get_option(
			'mxroute_mailer_username',
			get_option( 'admin_email', 'admin@example.com' )
		);

		if ( empty( $headers ) ) {
			return $default_from;
		}

		if ( is_string( $headers ) ) {
			$lines = explode( "\n", $headers );
			foreach ( $lines as $line ) {
				if ( 0 === stripos( $line, 'From:' ) ) {
					$from = trim( substr( $line, 5 ) );
				if ( preg_match( '/<(.+?)>/', $from, $matches ) ) {
					return sanitize_email( $matches[1] );
				}
				return sanitize_email( $from );
				}
			}
		}

		if ( is_array( $headers ) ) {
			foreach ( $headers as $header ) {
				if ( is_string( $header ) && 0 === stripos( $header, 'From:' ) ) {
					$from = trim( substr( $header, 5 ) );
				if ( preg_match( '/<(.+?)>/', $from, $matches ) ) {
					return sanitize_email( $matches[1] );
				}
				return sanitize_email( $from );
				}
			}
		}

		return sanitize_email( $default_from );
	}

	/**
	 * Handle test email submission.
	 *
	 * Test emails bypass the queue and send synchronously for instant feedback.
	 *
	 * @return void
	 */
	public function handle_test_email() {
		if (
			! isset( $_POST['mxroute_test_email_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['mxroute_test_email_nonce'] ) ),
				'mxroute_test_email'
			)
		) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$to      = sanitize_email( wp_unslash( $_POST['mxroute_test_to'] ?? '' ) );
		$from    = get_option( 'mxroute_mailer_username', '' );
		$subject = sanitize_text_field( wp_unslash( $_POST['mxroute_test_subject'] ?? '' ) );
		$body    = sanitize_textarea_field( wp_unslash( $_POST['mxroute_test_body'] ?? '' ) );

		if ( '' === $subject ) {
			$subject = __( 'MXRoute Mailer Test', 'mxroute-mailer' );
		}
		if ( '' === $body ) {
			$body = __( 'This is a test email from MXRoute Mailer.', 'mxroute-mailer' );
		}

		if ( empty( $to ) || empty( $from ) ) {
			set_transient(
				'mxroute_test_email_result',
				array(
					'success' => false,
					'message' => __( 'Missing recipient or sender address.', 'mxroute-mailer' ),
				),
				60
			);
			return;
		}

		$api    = new MXRoute_API();
		$result = $api->send( $from, $to, $subject, $body );

		$logger = new MXRoute_Logger();
		$logger->log(
			$from,
			$to,
			$subject,
			$body,
			$result['request'],
			$result['response'],
			$result['success']
		);

		set_transient( 'mxroute_test_email_result', $result, 60 );
	}
}
