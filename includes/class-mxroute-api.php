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
	 * @param string       $from     Sender email address.
	 * @param string|array $to       Recipient email address(es).
	 * @param string       $subject  Email subject.
	 * @param string       $body     Email body.
	 * @param string       $reply_to Optional Reply-To email address.
	 * @return array {
	 *     Response data.
	 *
	 *     @type bool   $success  Whether the send was successful.
	 *     @type string $message  Server message.
	 *     @type array  $request  Request data sent to API.
	 *     @type array  $response Raw API response.
	 * }
	 */
	public function send( $from, $to, $subject, $body, $reply_to = '' ) {
		$server   = get_option( 'mxroute_mailer_server', '' );
		$username = get_option( 'mxroute_mailer_username', '' );
		$password = get_option( 'mxroute_mailer_password', '' );

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
			$to_single = $matches[1];
		}

		$payload = array(
			'server'   => $server,
			'username' => $username,
			'password' => $password,
			'from'     => substr( $from, 0, self::$max_field_length ),
			'to'       => substr( $to_single, 0, self::$max_field_length ),
			'subject'  => substr( $subject, 0, self::$max_field_length ),
			'body'     => substr( $body, 0, self::$max_field_length * 10 ),
		);

		if ( ! empty( $reply_to ) ) {
			$payload['headers'] = 'Reply-To: ' . substr( $reply_to, 0, self::$max_field_length );
		}

		$request = array(
			'server'   => $server,
			'username' => $username,
			'from'     => $payload['from'],
			'to'       => $payload['to'],
			'subject'  => $payload['subject'],
		);

		$auth_header = 'Basic ' . base64_encode( $username . ':' . $password ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

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

		if ( json_last_error() !== JSON_ERROR_NONE ) {
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
}
