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
	 * Reset the singleton instance for testing.
	 *
	 * Only available when MXROUTE_MAILER_DEBUG is enabled to prevent
	 * accidental use in production.
	 *
	 * @return void
	 */
	public static function reset() {
		if ( defined( 'MXROUTE_MAILER_DEBUG' ) && MXROUTE_MAILER_DEBUG ) {
			self::$instance = null;
		}
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
		add_action( 'init', array( $this, 'schedule_queue_processor' ) );
	}

	/**
	 * Schedule the recurring queue processor event.
	 *
	 * Ensures the recurring cron event is always active. The interval
	 * is 60 seconds (the minimum WP-Cron supports).
	 *
	 * @return void
	 */
	public function schedule_queue_processor() {
		if ( ! wp_next_scheduled( 'mxroute_mailer_process_queue' ) ) {
			wp_schedule_event( time(), 'mxroute_mailer_interval', 'mxroute_mailer_process_queue' );
		}
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

		// Another plugin already handled this email via pre_wp_mail.
		if ( null !== $pre ) {
			return $pre;
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

		if ( is_array( $args['to'] ) ) {
			$recipients = array_values( array_filter( $args['to'] ) );
		} else {
			$recipients = array_filter( array_map( 'trim', explode( ',', $args['to'] ) ) );
		}

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
		$queue   = new MXRoute_Queue();
		$api     = new MXRoute_API();
		$batch   = absint( get_option( 'mxroute_mailer_batch_size', 5 ) );
		$pending = $queue->claim_pending( $batch );

		if ( empty( $pending ) ) {
			return;
		}

		foreach ( $pending as $item ) {
			$attachments     = $queue->resolve_attachments( $item->attachments );
			$item_transport  = $api->get_transport( $attachments );

			try {
				$result = $api->send(
					$item->from_email,
					$item->to_email,
					$item->subject,
					$item->message,
					$item->reply_to,
					$attachments
				);
			} catch ( \Throwable $e ) {
				$result = array(
					'success'  => false,
					'message'  => $e->getMessage(),
					'request'  => array(),
					'response' => array( 'error' => $e->getMessage() ),
				);
			}

			if ( $result['success'] ) {
				$queue->mark_sent( $item->id, $result['request'], $result['response'], $item_transport );
			} else {
				$queue->mark_failed( $item->id, $result['request'], $result['response'], $item_transport );
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
	 * Queues the email for background processing via WP-Cron so it follows the
	 * same path as real emails. When the attachment checkbox is checked, three
	 * distinct attachment types are included to exercise all storage paths:
	 * media library ID, persistent file path, and temp file (copied to storage).
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

		if ( ! mxroute_mailer_can_manage() ) {
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

		$attachments = array();
		if ( ! empty( $_POST['mxroute_test_attachment'] ) ) {
			$upload_dir = wp_upload_dir();

			// Media library ID — re-resolved from WordPress at send time.
			// Uses its own file so all three attachment types are distinct.
			$media_dir  = trailingslashit( $upload_dir['basedir'] ) . 'mxroute-test';
			$media_file = trailingslashit( $media_dir ) . 'test-attachment-media.txt';
			if ( ! file_exists( $media_file ) ) {
				if ( ! is_dir( $media_dir ) ) {
					wp_mkdir_p( $media_dir );
				}
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				file_put_contents( $media_file, 'MXRoute Mailer test attachment (media library ID test).' );
			}
			if ( file_exists( $media_file ) ) {
				$attachment_id = wp_insert_attachment(
					array(
						'post_title'     => 'MXRoute Test Media Attachment',
						'post_mime_type' => 'text/plain',
						'post_status'    => 'attachment',
						'post_content'   => '',
					),
					$media_file
				);
				if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
					$attachments[] = $attachment_id;
				}
			}

			// Persistent file path — referenced as-is, no copy needed.
			$persistent_file = plugin_dir_path( __DIR__ ) . 'assets/test-attachment.txt';
			if ( file_exists( $persistent_file ) ) {
				$attachments[] = $persistent_file;
			}

			// Temp file — copied to persistent storage before queue processing.
			$temp_dir = @sys_get_temp_dir();
			if ( $temp_dir ) {
				$temp_file = tempnam( $temp_dir, 'mxroute_test_' );
				if ( $temp_file ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
					file_put_contents( $temp_file, 'MXRoute Mailer test attachment (temp file copy test).' );
					$attachments[] = $temp_file;
				}
			}
		}

		// Queue the test email so it goes through the same path as real emails.
		$queue = new MXRoute_Queue();
		$queue->add( $from, $to, $subject, $body, '', $attachments, '' );

		set_transient(
			'mxroute_test_email_result',
			array(
				'success' => true,
				'queued'  => true,
				'message' => __( 'Test email queued for sending.', 'mxroute-mailer' ),
			),
			60
		);
	}
}
