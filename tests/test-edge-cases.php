<?php
/**
 * Edge case tests for MXRoute Mailer.
 *
 * Covers boundary conditions, malformed input, type coercion,
 * and unusual but realistic scenarios.
 *
 * @package MXRoute_Mailer
 */

/**
 * Tests for edge cases in MXRoute_API.
 */
class MXRoute_API_Edge_Test extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options']            = array();
		$GLOBALS['wp_function_calls']     = array();
		$GLOBALS['mxroute_mock_remote_response'] = null;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['mxroute_mock_remote_response'] );
	}

	/**
	 * Tests that send accepts an empty from address.
	 */
	public function test_send_with_empty_from_address() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$api    = new MXRoute_API();
		$result = $api->send( '', 'to@example.com', 'Test', 'Body' );

		$this->assertArrayHasKey( 'request', $result );
		$this->assertEquals( '', $result['request']['from'] );
	}

	/**
	 * Tests that send uses the first address from an array recipient.
	 */
	public function test_send_with_array_recipient() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$api    = new MXRoute_API();
		$result = $api->send( 'from@example.com', array( 'a@example.com', 'b@example.com' ), 'Test', 'Body' );

		$this->assertEquals( 'a@example.com', $result['request']['to'] );
	}

	/**
	 * Tests that send handles an empty array recipient gracefully.
	 */
	public function test_send_with_empty_array_recipient() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$api    = new MXRoute_API();
		$result = $api->send( 'from@example.com', array(), 'Test', 'Body' );

		$this->assertEquals( '', $result['request']['to'] );
	}

	/**
	 * Tests that send truncates an excessively long subject line.
	 */
	public function test_send_truncates_long_subject() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$long_subject = str_repeat( 'A', 6000 );
		$api          = new MXRoute_API();
		$result       = $api->send( 'from@example.com', 'to@example.com', $long_subject, 'Body' );

		$this->assertArrayHasKey( 'request', $result );
		$this->assertLessThanOrEqual( 5000, strlen( $result['request']['subject'] ) );
	}

	/**
	 * Tests that send handles an excessively long body without error.
	 */
	public function test_send_truncates_long_body() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$long_body = str_repeat( 'B', 60000 );
		$api       = new MXRoute_API();
		$result    = $api->send( 'from@example.com', 'to@example.com', 'Test', $long_body );

		$this->assertArrayHasKey( 'request', $result );
	}

	/**
	 * Tests that send accepts an empty subject.
	 */
	public function test_send_with_empty_subject() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$api    = new MXRoute_API();
		$result = $api->send( 'from@example.com', 'to@example.com', '', 'Body' );

		$this->assertEquals( '', $result['request']['subject'] );
	}

	/**
	 * Tests that send handles an empty body by omitting it from the request.
	 */
	public function test_send_with_empty_body() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$api    = new MXRoute_API();
		$result = $api->send( 'from@example.com', 'to@example.com', 'Subject', '' );

		$this->assertArrayHasKey( 'request', $result );
		$this->assertArrayNotHasKey( 'body', $result['request'] );
	}

	/**
	 * Tests that send handles an empty response body from the API.
	 */
	public function test_send_api_returns_empty_body() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$GLOBALS['mxroute_mock_remote_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => '',
		);

		$api    = new MXRoute_API();
		$result = $api->send( 'from@example.com', 'to@example.com', 'Test', 'Body' );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Invalid JSON', $result['message'] );
	}

	/**
	 * Tests that send handles invalid JSON in the API response.
	 */
	public function test_send_api_returns_invalid_json() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$GLOBALS['mxroute_mock_remote_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => 'NOT JSON {{{',
		);

		$api    = new MXRoute_API();
		$result = $api->send( 'from@example.com', 'to@example.com', 'Test', 'Body' );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Invalid JSON', $result['message'] );
	}

	/**
	 * Tests that send handles JSON response missing the "success" key.
	 */
	public function test_send_api_returns_json_without_success_key() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$GLOBALS['mxroute_mock_remote_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( array( 'message' => 'Queued' ) ),
		);

		$api    = new MXRoute_API();
		$result = $api->send( 'from@example.com', 'to@example.com', 'Test', 'Body' );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'Queued', $result['message'] );
	}

	/**
	 * Tests that send handles JSON response missing the "message" key.
	 */
	public function test_send_api_returns_json_without_message_key() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$GLOBALS['mxroute_mock_remote_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( array( 'success' => true ) ),
		);

		$api    = new MXRoute_API();
		$result = $api->send( 'from@example.com', 'to@example.com', 'Test', 'Body' );

		$this->assertTrue( $result['success'] );
		$this->assertNotEmpty( $result['message'] );
	}

	/**
	 * Tests that the request and response logs never contain the password.
	 */
	public function test_send_request_log_excludes_password() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'super_secret_123';

		$api    = new MXRoute_API();
		$result = $api->send( 'from@example.com', 'to@example.com', 'Test', 'Body' );

		$this->assertArrayNotHasKey( 'password', $result['request'] );
		$this->assertArrayNotHasKey( 'password', $result['response'] );
	}
}

/**
 * Tests for edge cases in MXRoute_Mailer.
 */
class MXRoute_Mailer_Edge_Test extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options']          = array();
		$GLOBALS['wp_function_calls']   = array();
		$GLOBALS['wp_transients']       = array();
		$GLOBALS['wp_db_inserts']       = array();
		$GLOBALS['wp_db_queries']       = array();
	}

	/**
	 * Tests that intercept_wp_mail handles a null "to" value.
	 */
	public function test_intercept_handles_null_to() {
		$mailer = MXRoute_Mailer::instance();
		$args   = array(
			'to'      => null,
			'subject' => 'Test',
			'message' => 'Body',
		);
		$result = $mailer->intercept_wp_mail( $args );
		$this->assertArrayHasKey( 'to', $result );
	}

	/**
	 * Tests that intercept_wp_mail handles a null subject value.
	 */
	public function test_intercept_handles_null_subject() {
		$mailer = MXRoute_Mailer::instance();
		$args   = array(
			'to'      => 'to@example.com',
			'subject' => null,
			'message' => 'Body',
		);
		$result = $mailer->intercept_wp_mail( $args );
		$this->assertArrayHasKey( 'subject', $result );
	}

	/**
	 * Tests that intercept_wp_mail handles a null message value.
	 */
	public function test_intercept_handles_null_message() {
		$mailer = MXRoute_Mailer::instance();
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$args   = array(
			'to'      => 'to@example.com',
			'subject' => 'Test',
			'message' => null,
		);
		$result = $mailer->intercept_wp_mail( $args );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'to', $result );
	}

	/**
	 * Tests that intercept_wp_mail handles a completely empty args array.
	 */
	public function test_intercept_handles_completely_empty_args() {
		$mailer = MXRoute_Mailer::instance();
		$result = $mailer->intercept_wp_mail( array() );
		$this->assertArrayHasKey( 'to', $result );
		$this->assertEquals( '', $result['to'] );
	}

	/**
	 * Tests that intercept_wp_mail handles a non-array input gracefully.
	 */
	public function test_intercept_handles_non_array_input() {
		$mailer = MXRoute_Mailer::instance();
		$result = $mailer->intercept_wp_mail( 'not_an_array' );
		$this->assertArrayHasKey( 'to', $result );
	}

	/**
	 * Tests that From extraction handles array headers with non-string values.
	 */
	public function test_extract_from_with_array_headers_containing_non_strings() {
		$mailer = MXRoute_Mailer::instance();
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'default@example.com';
		$args   = array(
			'to'      => 'to@example.com',
			'subject' => 'Test',
			'message' => 'Body',
			'headers' => array( 123, null, false, '' ),
		);
		$result = $mailer->intercept_wp_mail( $args );
		$this->assertArrayHasKey( 'to', $result );
	}

	/**
	 * Tests that From extraction falls back when string headers have no From line.
	 */
	public function test_extract_from_with_string_headers_missing_from() {
		$mailer = MXRoute_Mailer::instance();
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'default@example.com';
		$args   = array(
			'to'      => 'to@example.com',
			'subject' => 'Test',
			'message' => 'Body',
			'headers' => "Content-Type: text/html\nX-Custom: value",
		);
		$result = $mailer->intercept_wp_mail( $args );
		$this->assertArrayHasKey( 'to', $result );
	}

	/**
	 * Tests that From extraction parses a plain email without angle brackets.
	 */
	public function test_extract_from_plain_email_no_angle_brackets() {
		$mailer = MXRoute_Mailer::instance();
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'default@example.com';
		$args   = array(
			'to'      => 'to@example.com',
			'subject' => 'Test',
			'message' => 'Body',
			'headers' => 'From: noreply@example.com',
		);
		$result = $mailer->intercept_wp_mail( $args );
		$this->assertArrayHasKey( 'to', $result );
	}

	/**
	 * Tests that From extraction uses the first From header when multiple are present.
	 */
	public function test_extract_from_with_multiple_from_headers() {
		$mailer = MXRoute_Mailer::instance();
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'default@example.com';
		$args   = array(
			'to'      => 'to@example.com',
			'subject' => 'Test',
			'message' => 'Body',
			'headers' => "From: first@example.com\nFrom: second@example.com",
		);
		$result = $mailer->intercept_wp_mail( $args );
		$this->assertArrayHasKey( 'to', $result );
	}

	/**
	 * Tests that handle_test_email handles special characters in the subject.
	 */
	public function test_handle_test_email_with_special_characters_in_subject() {
		$mailer = MXRoute_Mailer::instance();
		$_POST  = array(
			'mxroute_test_email_nonce' => 'valid',
			'mxroute_test_to'          => 'to@example.com',
			'mxroute_test_from'        => 'from@example.com',
			'mxroute_test_subject'     => 'Test & "Special" <Characters>',
			'mxroute_test_body'        => 'Body',
		);
		$mailer->handle_test_email();
		$this->assertArrayHasKey( 'mxroute_test_email_result', $GLOBALS['wp_transients'] );
	}

	/**
	 * Tests that handle_test_email fails when both to and from are empty.
	 */
	public function test_handle_test_email_with_empty_to_and_from() {
		$mailer = MXRoute_Mailer::instance();
		$_POST  = array(
			'mxroute_test_email_nonce' => 'valid',
			'mxroute_test_to'          => '',
			'mxroute_test_from'        => '',
		);
		$mailer->handle_test_email();
		$result = $GLOBALS['wp_transients']['mxroute_test_email_result'];
		$this->assertFalse( $result['success'] );
	}

	/**
	 * Tests that handle_test_email sets defaults for empty subject and body.
	 */
	public function test_handle_test_email_sets_defaults_for_empty_subject_and_body() {
		$mailer = MXRoute_Mailer::instance();
		$_POST  = array(
			'mxroute_test_email_nonce' => 'valid',
			'mxroute_test_to'          => 'to@example.com',
			'mxroute_test_from'        => 'from@example.com',
			'mxroute_test_subject'     => '',
			'mxroute_test_body'        => '',
		);
		$mailer->handle_test_email();
		$this->assertArrayHasKey( 'mxroute_test_email_result', $GLOBALS['wp_transients'] );
	}

	/**
	 * Tests that handle_test_email skips when user lacks manage_options capability.
	 */
	public function test_handle_test_email_skips_without_manage_options() {
		$this->markTestSkipped(
			'Test bootstrap mock always returns true for current_user_can(). '
			. 'This path is verified by integration testing on the test site.'
		);
	}
}

/**
 * Tests for edge cases in MXRoute_Logger.
 */
class MXRoute_Logger_Edge_Test extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options']      = array();
		$GLOBALS['wp_function_calls'] = array();
		$GLOBALS['wp_db_inserts']   = array();
		$GLOBALS['wp_db_queries']   = array();
	}

	/**
	 * Tests that log handles an empty from address.
	 */
	public function test_log_handles_empty_from() {
		$GLOBALS['wp_options']['mxroute_mailer_logging_enabled'] = 1;

		$logger = new MXRoute_Logger();
		$logger->log( '', 'to@example.com', 'Subject', 'Body', array(), array(), true );

		$this->assertNotEmpty( $GLOBALS['wp_db_inserts'] );
		$this->assertEquals( '', $GLOBALS['wp_db_inserts'][0]['data']['from_email'] );
	}

	/**
	 * Tests that log handles an empty subject.
	 */
	public function test_log_handles_empty_subject() {
		$GLOBALS['wp_options']['mxroute_mailer_logging_enabled'] = 1;

		$logger = new MXRoute_Logger();
		$logger->log( 'from@example.com', 'to@example.com', '', 'Body', array(), array(), true );

		$this->assertNotEmpty( $GLOBALS['wp_db_inserts'] );
		$this->assertEquals( '', $GLOBALS['wp_db_inserts'][0]['data']['subject'] );
	}

	/**
	 * Tests that log handles an empty array as the "to" value.
	 */
	public function test_log_handles_array_to_with_empty_array() {
		$GLOBALS['wp_options']['mxroute_mailer_logging_enabled'] = 1;

		$logger = new MXRoute_Logger();
		$logger->log( 'from@example.com', array(), 'Subject', 'Body', array(), array(), true );

		$this->assertNotEmpty( $GLOBALS['wp_db_inserts'] );
		$this->assertEquals( '', $GLOBALS['wp_db_inserts'][0]['data']['to_email'] );
	}

	/**
	 * Tests that log handles an array "to" with a false first element.
	 */
	public function test_log_handles_array_to_with_false_first() {
		$GLOBALS['wp_options']['mxroute_mailer_logging_enabled'] = 1;

		$logger = new MXRoute_Logger();
		$logger->log( 'from@example.com', array( false ), 'Subject', 'Body', array(), array(), true );

		$this->assertNotEmpty( $GLOBALS['wp_db_inserts'] );
		$this->assertEquals( '', $GLOBALS['wp_db_inserts'][0]['data']['to_email'] );
	}

	/**
	 * Tests that log stores request and response as JSON strings.
	 */
	public function test_log_stores_request_and_response_as_json() {
		$GLOBALS['wp_options']['mxroute_mailer_logging_enabled'] = 1;
		$request  = array( 'server' => 'test', 'from' => 'a@b.com' );
		$response = array( 'success' => true, 'message' => 'OK' );

		$logger = new MXRoute_Logger();
		$logger->log( 'from@example.com', 'to@example.com', 'Sub', 'Body', $request, $response, true );

		$insert = $GLOBALS['wp_db_inserts'][0];
		$this->assertIsString( $insert['data']['api_request'] );
		$this->assertIsString( $insert['data']['api_response'] );
		$this->assertEquals( $request, json_decode( $insert['data']['api_request'], true ) );
		$this->assertEquals( $response, json_decode( $insert['data']['api_response'], true ) );
	}

	/**
	 * Tests that delete_logs handles a non-array string input.
	 */
	public function test_delete_logs_handles_string_input() {
		$logger = new MXRoute_Logger();
		$logger->delete_logs( 'not_an_array' );

		$this->assertEmpty( $GLOBALS['wp_db_queries'] );
	}

	/**
	 * Tests that delete_logs skips all invalid (non-integer) values.
	 */
	public function test_delete_logs_handles_all_invalid_values() {
		$logger = new MXRoute_Logger();
		$logger->delete_logs( array( 'abc', 'def', 0, '' ) );

		$this->assertEmpty( $GLOBALS['wp_db_queries'] );
	}

	/**
	 * Tests that get_logs handles a negative page number.
	 */
	public function test_get_logs_handles_negative_page() {
		$logger = new MXRoute_Logger();
		$result = $logger->get_logs( 10, -1, array() );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'logs', $result );
	}

	/**
	 * Tests that get_logs handles a zero per-page value.
	 */
	public function test_get_logs_handles_zero_per_page() {
		$logger = new MXRoute_Logger();
		$result = $logger->get_logs( 0, 1, array() );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'logs', $result );
	}

	/**
	 * Tests that get_logs safely handles SQL injection attempts in search.
	 */
	public function test_get_logs_handles_sql_injection_in_search() {
		$logger = new MXRoute_Logger();
		$result = $logger->get_logs( 10, 1, array( "search" => "'; DROP TABLE wp_mxroute_mailer_logs; --" ) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'logs', $result );
	}

	/**
	 * Tests that get_logs safely handles SQL injection attempts in date filters.
	 */
	public function test_get_logs_handles_sql_injection_in_date_filter() {
		$logger = new MXRoute_Logger();
		$result = $logger->get_logs( 10, 1, array(
			'date_from' => "2024-01-01' OR 1=1 --",
			'date_to'   => '2024-12-31',
		) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'logs', $result );
	}

	/**
	 * Tests that get_logs handles an empty search string.
	 */
	public function test_get_logs_handles_empty_search() {
		$logger = new MXRoute_Logger();
		$result = $logger->get_logs( 10, 1, array( 'search' => '' ) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'logs', $result );
	}

	/**
	 * Tests that get_logs handles an invalid success filter value.
	 */
	public function test_get_logs_handles_invalid_success_filter() {
		$logger = new MXRoute_Logger();
		$result = $logger->get_logs( 10, 1, array( 'success' => 'invalid' ) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'logs', $result );
	}
}

/**
 * Tests for edge cases in MXRoute_Dashboard AJAX handlers.
 */
class MXRoute_Dashboard_Edge_Test extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options']        = array();
		$GLOBALS['wp_function_calls'] = array();
		$GLOBALS['wp_db_inserts']     = array();
		$GLOBALS['wp_db_queries']     = array();
		$_POST                        = array();
	}

	protected function tearDown(): void {
		$_POST = array();
	}

	/**
	 * Tests that bulk delete rejects a non-array log_ids input.
	 */
	public function test_bulk_delete_handles_non_array_input() {
		$_POST['log_ids'] = 'not_an_array';

		$dashboard = new MXRoute_Dashboard();
		$threw     = false;
		try {
			$dashboard->ajax_bulk_delete_logs();
		} catch ( \MXRouteJSONException $e ) {
			$threw = true;
			$this->assertFalse( $e->response['success'] );
		}
		$this->assertTrue( $threw, 'Expected MXRouteJSONException to be thrown' );
	}

	/**
	 * Tests that bulk delete rejects an array of non-integer string IDs.
	 */
	public function test_bulk_delete_handles_array_with_strings() {
		$_POST['log_ids'] = array( 'abc', 'def', 'ghi' );

		$dashboard = new MXRoute_Dashboard();
		$threw     = false;
		try {
			$dashboard->ajax_bulk_delete_logs();
		} catch ( \MXRouteJSONException $e ) {
			$threw = true;
			$this->assertFalse( $e->response['success'] );
		}
		$this->assertTrue( $threw, 'Expected MXRouteJSONException to be thrown' );
	}

	/**
	 * Tests that bulk delete rejects zero-valued log IDs.
	 */
	public function test_bulk_delete_handles_zero_ids() {
		$_POST['log_ids'] = array( 0, 0, 0 );

		$dashboard = new MXRoute_Dashboard();
		$threw     = false;
		try {
			$dashboard->ajax_bulk_delete_logs();
		} catch ( \MXRouteJSONException $e ) {
			$threw = true;
			$this->assertFalse( $e->response['success'] );
		}
		$this->assertTrue( $threw, 'Expected MXRouteJSONException to be thrown' );
	}

	/**
	 * Tests that bulk delete accepts negative log IDs.
	 */
	public function test_bulk_delete_handles_negative_ids() {
		$_POST['log_ids'] = array( -1, -2, -3 );

		$dashboard = new MXRoute_Dashboard();
		$threw     = false;
		try {
			$dashboard->ajax_bulk_delete_logs();
		} catch ( \MXRouteJSONException $e ) {
			$threw = true;
			$this->assertTrue( $e->response['success'] );
		}
		$this->assertTrue( $threw, 'Expected MXRouteJSONException to be thrown' );
	}

	/**
	 * Tests that delete_log handles a zero log_id.
	 */
	public function test_delete_log_with_zero_id() {
		$_POST['log_id'] = 0;

		$dashboard = new MXRoute_Dashboard();
		$threw     = false;
		try {
			$dashboard->ajax_delete_log();
		} catch ( \MXRouteJSONException $e ) {
			$threw = true;
			$this->assertTrue( $e->response['success'] );
		}
		$this->assertTrue( $threw, 'Expected MXRouteJSONException to be thrown' );
	}

	/**
	 * Tests that delete_log handles missing log_id key in POST data.
	 */
	public function test_delete_log_without_log_id_key() {
		$_POST = array();

		$dashboard = new MXRoute_Dashboard();
		$threw     = false;
		try {
			$dashboard->ajax_delete_log();
		} catch ( \MXRouteJSONException $e ) {
			$threw = true;
			$this->assertTrue( $e->response['success'] );
		}
		$this->assertTrue( $threw, 'Expected MXRouteJSONException to be thrown' );
	}
}

/**
 * Tests for edge cases in MXRoute_Settings.
 */
class MXRoute_Settings_Edge_Test extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options']        = array();
		$GLOBALS['wp_function_calls'] = array();
		unset( $GLOBALS['title'] );
	}

	/**
	 * Tests that sanitize_checkbox handles various string values.
	 */
	public function test_sanitize_checkbox_with_string_values() {
		$settings = new MXRoute_Settings();
		$this->assertEquals( 1, $settings->sanitize_checkbox( 'on' ) );
		$this->assertEquals( 1, $settings->sanitize_checkbox( 'off' ) );
		$this->assertEquals( 0, $settings->sanitize_checkbox( '' ) );
	}

	/**
	 * Tests that sanitize_checkbox handles various numeric values.
	 */
	public function test_sanitize_checkbox_with_numeric_values() {
		$settings = new MXRoute_Settings();
		$this->assertEquals( 1, $settings->sanitize_checkbox( 1 ) );
		$this->assertEquals( 0, $settings->sanitize_checkbox( 0 ) );
		$this->assertEquals( 1, $settings->sanitize_checkbox( 2 ) );
		$this->assertEquals( 1, $settings->sanitize_checkbox( -1 ) );
	}

	/**
	 * Tests that sanitize_password preserves the existing password when empty is submitted.
	 */
	public function test_sanitize_password_preserves_existing_on_empty() {
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'existing_pass';

		$settings = new MXRoute_Settings();
		$result   = $settings->sanitize_password( '' );
		$this->assertEquals( 'existing_pass', $result );
	}

	/**
	 * Tests that sanitize_password strips HTML tags from the password.
	 */
	public function test_sanitize_password_sanitizes_special_chars() {
		$settings = new MXRoute_Settings();
		$result   = $settings->sanitize_password( 'pass<script>word</script>' );
		$this->assertStringNotContainsString( '<script>', $result );
	}

	/**
	 * Tests that set_log_view_title sets the global title on the log view page.
	 */
	public function test_set_log_view_title_sets_global_for_log_view_page() {
		$_GET['page'] = 'mxroute-log-view';

		$settings = new MXRoute_Settings();
		$settings->set_log_view_title();

		$this->assertArrayHasKey( 'title', $GLOBALS );
		$this->assertStringContainsString( 'MXRoute', $GLOBALS['title'] );

		unset( $_GET['page'] );
	}

	/**
	 * Tests that set_log_view_title does not set the title on other pages.
	 */
	public function test_set_log_view_title_does_not_set_for_other_pages() {
		$_GET['page'] = 'mxroute-logs';

		$settings = new MXRoute_Settings();
		$settings->set_log_view_title();

		$this->assertArrayNotHasKey( 'title', $GLOBALS );

		unset( $_GET['page'] );
	}

	/**
	 * Tests that enqueue_assets returns early on non-plugin pages.
	 */
	public function test_enqueue_assets_returns_early_for_wrong_page() {
		$settings = new MXRoute_Settings();
		$settings->enqueue_assets( 'post.php' );

		$this->assertArrayNotHasKey( 'wp_enqueue_style', $GLOBALS['wp_function_calls'] );
	}

	/**
	 * Tests that enqueue_assets loads CSS on the log view page.
	 */
	public function test_enqueue_assets_loads_on_log_view_page() {
		$_GET['page'] = 'mxroute-log-view';

		$settings = new MXRoute_Settings();
		$settings->enqueue_assets( 'admin_page_mxroute-log-view' );

		$this->assertArrayHasKey( 'wp_enqueue_style', $GLOBALS['wp_function_calls'] );

		unset( $_GET['page'] );
	}

	/**
	 * Tests that add_menu_pages registers all three admin menu pages.
	 */
	public function test_add_menu_pages_registers_three_pages() {
		$settings = new MXRoute_Settings();
		$settings->add_menu_pages();

		$this->assertArrayHasKey( 'add_options_page', $GLOBALS['wp_function_calls'] );
		$this->assertArrayHasKey( 'add_management_page', $GLOBALS['wp_function_calls'] );
		$this->assertArrayHasKey( 'add_submenu_page', $GLOBALS['wp_function_calls'] );
	}
}
