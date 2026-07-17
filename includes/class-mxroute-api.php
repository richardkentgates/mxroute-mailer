<?php
/**
 * MXRoute HTTP API client.
 *
 * @package MXRoute_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles sending email via the MXRoute HTTP API.
 */
class MXRoute_API {

	/**
	 * API endpoint URL.
	 *
	 * @var string
	 */
	private $endpoint = 'https://smtpapi.mxroute.com/';

	/**
	 * Maximum field length for API payload.
	 *
	 * @var int
	 */
	private static $max_field_length = 5000;

	/**
	 * Send an email via the MXRoute API using saved settings.
	 *
	 * @param string       $from        Sender email address.
	 * @param string|array $to          Recipient email address(es).
	 * @param string       $subject     Email subject.
	 * @param string       $body        Email body.
	 * @param string       $reply_to    Optional Reply-To email address.
	 * @param array        $attachments Optional array of file paths.
	 * @return array {
	 *     Response data.
	 *
	 *     @type bool   $success  Whether the send was successful.
	 *     @type string $message  Server message.
	 *     @type array  $request  Request data sent to API.
	 *     @type array  $response Raw API response.
	 * }
	 */
	public function send( $from, $to, $subject, $body, $reply_to = '', $attachments = array() ) {
		$server   = get_option( 'mxroute_mailer_server', '' );
		$username = get_option( 'mxroute_mailer_username', '' );
		$password = MXRoute_Crypto::get_password();

		if ( empty( $server ) || empty( $username ) || empty( $password ) ) {
			return array(
				'success'  => false,
				'message'  => __( 'MXRoute credentials not configured.', 'mxroute-mailer' ),
				'request'  => array(),
				'response' => array(),
			);
		}

		$to_single = is_array( $to ) ? reset( $to ) : $to;
		if ( empty( $to_single ) ) {
			$to_single = '';
		}

		if ( preg_match( '/<(.+?)>/', $to_single, $matches ) ) {
			$to_single = sanitize_email( $matches[1] );
		} else {
			$to_single = sanitize_email( $to_single );
		}

		$from     = sanitize_email( $from );
		$reply_to = sanitize_email( $reply_to );

		$payload = array(
			'server'   => $server,
			'username' => $username,
			'password' => $password,
			'from'     => mb_substr( $from, 0, self::$max_field_length ),
			'to'       => mb_substr( $to_single, 0, self::$max_field_length ),
			'subject'  => mb_substr( $subject, 0, self::$max_field_length ),
			'body'     => mb_substr( $body, 0, self::$max_field_length * 10 ),
		);

		if ( ! empty( $reply_to ) ) {
			$payload['headers'] = 'Reply-To: ' . substr( $reply_to, 0, self::$max_field_length );
		}

		if ( ! empty( $attachments ) ) {
			$encoded = $this->encode_attachments( $attachments );
			if ( ! empty( $encoded ) ) {
				$payload['attachments'] = $encoded;
			}
		}

		$request = array(
			'server'   => $server,
			'username' => $username,
			'from'     => $payload['from'],
			'to'       => $payload['to'],
			'subject'  => $payload['subject'],
		);

		$auth_header = 'Basic ' . base64_encode( $username . ':' . $password ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		if ( defined( 'MXROUTE_MAILER_DEBUG' ) && MXROUTE_MAILER_DEBUG ) {
			error_log( 'MXRoute API Send: server=' . $server . ' username=' . $username . ' from=' . $payload['from'] . ' to=' . $payload['to'] );
		}

		$response = wp_remote_post(
			$this->endpoint,
			array(
				'body'      => wp_json_encode( $payload ),
				'headers'   => array(
					'Content-Type'  => 'application/json',
					'Authorization' => $auth_header,
				),
				'timeout'   => 30,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( defined( 'MXROUTE_MAILER_DEBUG' ) && MXROUTE_MAILER_DEBUG ) {
				error_log( 'MXRoute API Error: ' . $response->get_error_message() );
			}
			return array(
				'success'  => false,
				'message'  => __( 'HTTP request failed.', 'mxroute-mailer' ),
				'request'  => $request,
				'response' => array( 'error' => 'request_failed' ),
			);
		}

		$raw_response = wp_remote_retrieve_body( $response );
		$http_code    = wp_remote_retrieve_response_code( $response );
		$json_data    = json_decode( $raw_response, true );

		if ( defined( 'MXROUTE_MAILER_DEBUG' ) && MXROUTE_MAILER_DEBUG ) {
			error_log( 'MXRoute API Response: http_code=' . $http_code . ' body=' . $raw_response );
		}

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return array(
				'success'  => false,
				'message'  => __( 'Invalid JSON response from API.', 'mxroute-mailer' ),
				'request'  => $request,
				'response' => array(
					'error'     => 'invalid_json',
					'http_code' => $http_code,
				),
			);
		}

		return array(
			'success'  => ! empty( $json_data['success'] ),
			'message'  => $json_data['message'] ?? __( 'Unknown response.', 'mxroute-mailer' ),
			'request'  => $request,
			'response' => $json_data,
		);
	}

	/**
	 * Encode file attachments for the API payload.
	 *
	 * @param array $attachments Array of file paths.
	 * @return array Array of encoded attachment arrays.
	 */
	private function encode_attachments( $attachments ) {
		$encoded = array();

		foreach ( $attachments as $file_path ) {
			if ( ! is_string( $file_path ) || ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
				if ( defined( 'MXROUTE_MAILER_DEBUG' ) && MXROUTE_MAILER_DEBUG ) {
					error_log( 'MXRoute API Attachment: skipping unreadable or missing file ' . $file_path );
				}
				continue;
			}

			$file_size = @filesize( $file_path ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_filesize
			if ( false === $file_size || $file_size > 5242880 ) {
				if ( defined( 'MXROUTE_MAILER_DEBUG' ) && MXROUTE_MAILER_DEBUG ) {
					error_log( 'MXRoute API Attachment: skipping file over 5MB ' . $file_path );
				}
				continue;
			}

			$content = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $content ) {
				continue;
			}

			$encoded[] = array(
				'filename' => basename( $file_path ),
				'content'  => base64_encode( $content ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			);
		}

		return $encoded;
	}
}
