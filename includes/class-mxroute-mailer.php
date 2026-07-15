<?php
/**
 * MXRoute Mailer main plugin class.
 *
 * @package MXRoute_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class — singleton that intercepts wp_mail and routes through MXRoute.
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
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * Priority 999 ensures this runs after all other wp_mail filters so the
	 * final API call replaces any earlier modifications.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_filter( 'wp_mail', array( $this, 'intercept_wp_mail' ), 999 );
		add_action( 'load-settings_page_mxroute-mailer', array( $this, 'handle_test_email' ) );
	}

	/**
	 * Intercept wp_mail to route through MXRoute API.
	 *
	 * @param array $args wp_mail arguments.
	 * @return array|false Array of args on skip, false on send.
	 */
	public function intercept_wp_mail( $args ) {
		$defaults = array(
			'to'          => '',
			'subject'     => '',
			'message'     => '',
			'headers'     => '',
			'attachments' => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		if ( empty( $args['to'] ) || empty( $args['subject'] ) ) {
			return $args;
		}

		$server   = get_option( 'mxroute_mailer_server', '' );
		$username = get_option( 'mxroute_mailer_username', '' );
		$password = get_option( 'mxroute_mailer_password', '' );

		if ( empty( $server ) || empty( $username ) || empty( $password ) ) {
			return $args;
		}

		$from   = $this->extract_from_address( $args['headers'] );
		$api    = new MXRoute_API();
		$logger = new MXRoute_Logger();

		$recipients = is_array( $args['to'] ) ? array_values( array_filter( $args['to'] ) ) : array( $args['to'] );

		$all_success = true;
		$messages    = array();

		foreach ( $recipients as $recipient ) {
			$result = $api->send(
				$from,
				$recipient,
				$args['subject'],
				$args['message']
			);

			$logger->log(
				$from,
				$recipient,
				$args['subject'],
				$args['message'],
				$result['request'],
				$result['response'],
				$result['success']
			);

			if ( ! $result['success'] ) {
				$all_success = false;
				$messages[]  = $result['message'];
			}
		}

		if ( ! $all_success ) {
			$message = implode( '; ', $messages );
			if ( empty( $message ) ) {
				$message = __( 'MXRoute API send failed.', 'mxroute-mailer' );
			}
			$error = new WP_Error(
				'mxroute_send_failed',
				$message,
				$args
			);
			do_action( 'wp_mail_failed', $error );
		}

		return $args;
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
						return $matches[1];
					}
					return $from;
				}
			}
		}

		if ( is_array( $headers ) ) {
			foreach ( $headers as $header ) {
				if ( is_string( $header ) && 0 === stripos( $header, 'From:' ) ) {
					$from = trim( substr( $header, 5 ) );
					if ( preg_match( '/<(.+?)>/', $from, $matches ) ) {
						return $matches[1];
					}
					return $from;
				}
			}
		}

		return $default_from;
	}

	/**
	 * Handle test email submission.
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
