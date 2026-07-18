<?php
/**
 * Tests for coverage gaps: process_queue, mark_sent/mark_failed,
 * send_via_smtp success, Logger extra params, help tabs, updater.
 *
 * @package MXRoute_Mailer
 */

/**
 * Tests for MXRoute_Mailer::process_queue() flow.
 */
class MXRoute_Process_Queue_Test extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options']            = array();
		$GLOBALS['wp_function_calls']     = array();
		$GLOBALS['wp_db_inserts']         = array();
		$GLOBALS['wp_db_queries']         = array();
		$GLOBALS['wp_db_results']         = null;
		$GLOBALS['wp_scheduled_events']   = array();
		$GLOBALS['mxroute_mock_remote_response'] = null;
		$GLOBALS['mxroute_phpmailer_succeed_port'] = null;
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['wp_db_results'],
			$GLOBALS['mxroute_mock_remote_response'],
			$GLOBALS['mxroute_phpmailer_succeed_port']
		);
	}

	/**
	 * Tests that process_queue sends each pending item and calls mark_sent on success.
	 */
	public function test_process_queue_sends_pending_items() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$item = (object) array(
			'id'          => 1,
			'from_email'  => 'from@example.com',
			'to_email'    => 'to@example.com',
			'subject'     => 'Queue Test',
			'message'     => 'Body',
			'reply_to'    => '',
			'attachments' => '[]',
			'transport'   => '',
		);
		$GLOBALS['wp_db_results'] = array( $item );

		$mailer = MXRoute_Mailer::instance();
		$mailer->process_queue();

		$updates = $GLOBALS['wp_function_calls']['$wpdb->update'] ?? array();
		$this->assertNotEmpty( $updates, 'Expected mark_sent or mark_failed to call $wpdb->update' );

		$sent_update = null;
		foreach ( $updates as $update ) {
			if ( isset( $update['data']['success'] ) && 1 === $update['data']['success'] ) {
				$sent_update = $update;
				break;
			}
		}
		$this->assertNotNull( $sent_update, 'Expected mark_sent with success=1. Got: ' . wp_json_encode( array_map( function( $u ) { return $u['data']['success'] ?? 'N/A'; }, $updates ) ) );

		$log_inserts = array_filter( $GLOBALS['wp_db_inserts'], function ( $insert ) {
			return isset( $insert['data']['from_email'] );
		} );
		$this->assertNotEmpty( $log_inserts, 'Expected logger to insert a log entry' );
	}

	/**
	 * Tests that process_queue calls mark_failed when send fails.
	 */
	public function test_process_queue_marks_failed_on_error() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = '';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = '';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = '';

		$item = (object) array(
			'id'          => 2,
			'from_email'  => 'from@example.com',
			'to_email'    => 'to@example.com',
			'subject'     => 'Fail Test',
			'message'     => 'Body',
			'reply_to'    => '',
			'attachments' => '[]',
			'transport'   => '',
		);
		$GLOBALS['wp_db_results'] = array( $item );

		$mailer = MXRoute_Mailer::instance();
		$mailer->process_queue();

		$updates = $GLOBALS['wp_function_calls']['$wpdb->update'] ?? array();
		$failed_update = null;
		foreach ( $updates as $update ) {
			if ( isset( $update['data']['success'] ) && -1 === $update['data']['success'] ) {
				$failed_update = $update;
				break;
			}
		}
		$this->assertNotNull( $failed_update, 'Expected a mark_failed update with success=-1' );
	}

	/**
	 * Tests that process_queue handles empty queue gracefully.
	 */
	public function test_process_queue_returns_early_on_empty_queue() {
		$GLOBALS['wp_db_results'] = array();

		$mailer = MXRoute_Mailer::instance();
		$mailer->process_queue();

		$this->assertEmpty( $GLOBALS['wp_db_inserts'] );
		$this->assertEmpty( $GLOBALS['wp_function_calls']['$wpdb->update'] ?? array() );
	}
}

/**
 * Tests for MXRoute_Queue::mark_sent() and mark_failed().
 */
class MXRoute_Queue_Mark_Test extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options']        = array();
		$GLOBALS['wp_function_calls'] = array();
		$GLOBALS['wp_db_inserts']     = array();
	}

	/**
	 * Tests that mark_sent calls $wpdb->update with success=1.
	 */
	public function test_mark_sent_sets_success_1() {
		$queue = new MXRoute_Queue();
		$queue->mark_sent( 42, array( 'from' => 'a@b.com' ), array( 'success' => true ) );

		$updates = $GLOBALS['wp_function_calls']['$wpdb->update'];
		$this->assertCount( 1, $updates );

		$data = $updates[0]['data'];
		$this->assertEquals( 1, $data['success'] );
		$this->assertEquals( '{"from":"a@b.com"}', $data['api_request'] );
		$this->assertEquals( '{"success":true}', $data['api_response'] );
		$this->assertNotEmpty( $data['processed_at'] );
	}

	/**
	 * Tests that mark_failed calls $wpdb->update with success=-1.
	 */
	public function test_mark_failed_sets_success_minus_1() {
		$queue = new MXRoute_Queue();
		$queue->mark_failed( 99, array(), array( 'error' => 'fail' ) );

		$updates = $GLOBALS['wp_function_calls']['$wpdb->update'];
		$this->assertCount( 1, $updates );

		$data = $updates[0]['data'];
		$this->assertEquals( -1, $data['success'] );
		$this->assertEquals( '{"error":"fail"}', $data['api_response'] );
	}

	/**
	 * Tests that mark_sent passes correct ID in WHERE clause.
	 */
	public function test_mark_sent_uses_correct_id() {
		$queue = new MXRoute_Queue();
		$queue->mark_sent( 7 );

		$updates = $GLOBALS['wp_function_calls']['$wpdb->update'];
		$this->assertEquals( array( 'id' => 7 ), $updates[0]['where'] );
	}
}

/**
 * Tests for MXRoute_API::send_via_smtp() success path.
 */
class MXRoute_API_SMTP_Success_Test extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options']            = array();
		$GLOBALS['wp_function_calls']     = array();
		$GLOBALS['mxroute_mock_remote_response'] = null;
		$GLOBALS['mxroute_phpmailer_succeed_port'] = null;
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['mxroute_mock_remote_response'],
			$GLOBALS['mxroute_phpmailer_succeed_port']
		);
	}

	/**
	 * Tests that send routes to SMTP and succeeds when PHPMailer succeeds on port 465.
	 */
	public function test_send_via_smtp_succeeds_on_port_465() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';
		$GLOBALS['mxroute_phpmailer_succeed_port'] = 465;

		$tmp = tempnam( sys_get_temp_dir(), 'mxroute_test_' );
		file_put_contents( $tmp, 'test content' );

		$api    = new MXRoute_API();
		$result = $api->send(
			'from@example.com',
			'to@example.com',
			'Success Test',
			'Body',
			'',
			array( $tmp )
		);

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 'smtp', $result['response']['transport'] );
		$this->assertEquals( 465, $result['response']['port'] );

		$phpmailer_calls = $GLOBALS['wp_function_calls']['phpmailer_send'] ?? array();
		$this->assertNotEmpty( $phpmailer_calls );
		$this->assertEquals( 465, $phpmailer_calls[0]['port'] );

		$this->assertEmpty( $GLOBALS['wp_function_calls']['wp_remote_post'] ?? array() );

		unlink( $tmp );
	}

	/**
	 * Tests that send_via_smtp skips to port 587 when 465 fails.
	 */
	public function test_send_via_smtp_falls_back_to_587() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';
		$GLOBALS['mxroute_phpmailer_succeed_port'] = 587;

		$tmp = tempnam( sys_get_temp_dir(), 'mxroute_test_' );
		file_put_contents( $tmp, 'test content' );

		$api    = new MXRoute_API();
		$result = $api->send(
			'from@example.com',
			'to@example.com',
			'Fallback Test',
			'Body',
			'',
			array( $tmp )
		);

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 587, $result['response']['port'] );

		$phpmailer_calls = $GLOBALS['wp_function_calls']['phpmailer_send'];
		$this->assertEquals( 465, $phpmailer_calls[0]['port'] );
		$this->assertEquals( 587, $phpmailer_calls[1]['port'] );

		unlink( $tmp );
	}

	/**
	 * Tests that send_via_smtp returns failure when all ports fail.
	 */
	public function test_send_via_smtp_fails_all_ports() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';
		$GLOBALS['mxroute_phpmailer_succeed_port'] = null;

		$tmp = tempnam( sys_get_temp_dir(), 'mxroute_test_' );
		file_put_contents( $tmp, 'test content' );

		$api    = new MXRoute_API();
		$result = $api->send(
			'from@example.com',
			'to@example.com',
			'All Fail Test',
			'Body',
			'',
			array( $tmp )
		);

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'SMTP', $result['message'] );

		$phpmailer_calls = $GLOBALS['wp_function_calls']['phpmailer_send'];
		$this->assertCount( 3, $phpmailer_calls );
		$this->assertEquals( 465, $phpmailer_calls[0]['port'] );
		$this->assertEquals( 587, $phpmailer_calls[1]['port'] );
		$this->assertEquals( 2525, $phpmailer_calls[2]['port'] );

		unlink( $tmp );
	}

	/**
	 * Tests that send_via_smtp passes reply_to to PHPMailer.
	 */
	public function test_send_via_smtp_passes_reply_to() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';
		$GLOBALS['mxroute_phpmailer_succeed_port'] = 465;

		$tmp = tempnam( sys_get_temp_dir(), 'mxroute_test_' );
		file_put_contents( $tmp, 'test' );

		$api    = new MXRoute_API();
		$result = $api->send(
			'from@example.com',
			'to@example.com',
			'Reply-To Test',
			'Body',
			'replyto@example.com',
			array( $tmp )
		);

		$this->assertTrue( $result['success'] );

		$phpmailer_calls = $GLOBALS['wp_function_calls']['phpmailer_send'];
		$this->assertNotEmpty( $phpmailer_calls );

		unlink( $tmp );
	}
}

/**
 * Tests for MXRoute_Logger::log() with extra params.
 */
class MXRoute_Logger_Extra_Params_Test extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options']        = array();
		$GLOBALS['wp_db_inserts']     = array();
		$GLOBALS['wp_function_calls'] = array();
	}

	/**
	 * Tests that log stores headers in the database.
	 */
	public function test_log_stores_headers() {
		$logger = new MXRoute_Logger();
		$logger->log(
			'from@example.com',
			'to@example.com',
			'Subject',
			'Body',
			array(),
			array(),
			true,
			'',
			'X-Custom: test-header'
		);

		$insert = $GLOBALS['wp_db_inserts'][0]['data'];
		$this->assertEquals( 'X-Custom: test-header', $insert['headers'] );
	}

	/**
	 * Tests that log JSON-encodes array headers.
	 */
	public function test_log_json_encodes_array_headers() {
		$logger = new MXRoute_Logger();
		$logger->log(
			'from@example.com',
			'to@example.com',
			'Subject',
			'Body',
			array(),
			array(),
			true,
			'',
			array( 'X-Custom' => 'value' )
		);

		$insert = $GLOBALS['wp_db_inserts'][0]['data'];
		$this->assertEquals( '{"X-Custom":"value"}', $insert['headers'] );
	}

	/**
	 * Tests that log stores attachments as JSON.
	 */
	public function test_log_stores_attachments_as_json() {
		$logger = new MXRoute_Logger();
		$logger->log(
			'from@example.com',
			'to@example.com',
			'Subject',
			'Body',
			array(),
			array(),
			true,
			'',
			'',
			array( '/path/to/file.pdf', '/path/to/image.png' )
		);

		$insert = $GLOBALS['wp_db_inserts'][0]['data'];
		$atts   = json_decode( $insert['attachments'], true );
		$this->assertCount( 2, $atts );
		$this->assertEquals( '/path/to/file.pdf', $atts[0] );
	}

	/**
	 * Tests that log stores transport value.
	 */
	public function test_log_stores_transport() {
		$logger = new MXRoute_Logger();
		$logger->log(
			'from@example.com',
			'to@example.com',
			'Subject',
			'Body',
			array(),
			array(),
			true,
			'',
			'',
			array(),
			'smtp'
		);

		$insert = $GLOBALS['wp_db_inserts'][0]['data'];
		$this->assertEquals( 'smtp', $insert['transport'] );
	}

	/**
	 * Tests that log defaults transport to 'api' for invalid values.
	 */
	public function test_log_defaults_transport_to_api() {
		$logger = new MXRoute_Logger();
		$logger->log(
			'from@example.com',
			'to@example.com',
			'Subject',
			'Body',
			array(),
			array(),
			true,
			'',
			'',
			array(),
			'invalid'
		);

		$insert = $GLOBALS['wp_db_inserts'][0]['data'];
		$this->assertEquals( 'api', $insert['transport'] );
	}

	/**
	 * Tests that log stores reply_to address.
	 */
	public function test_log_stores_reply_to() {
		$logger = new MXRoute_Logger();
		$logger->log(
			'from@example.com',
			'to@example.com',
			'Subject',
			'Body',
			array(),
			array(),
			true,
			'replyto@example.com'
		);

		$insert = $GLOBALS['wp_db_inserts'][0]['data'];
		$this->assertEquals( 'replyto@example.com', $insert['reply_to'] );
	}

	/**
	 * Tests that log does not insert when logging is disabled.
	 */
	public function test_log_skips_when_logging_disabled() {
		$GLOBALS['wp_options']['mxroute_mailer_logging_enabled'] = 0;

		$logger = new MXRoute_Logger();
		$logger->log(
			'from@example.com',
			'to@example.com',
			'Subject',
			'Body',
			array(),
			array(),
			true
		);

		$this->assertEmpty( $GLOBALS['wp_db_inserts'] );
	}
}

/**
 * Tests for MXRoute_Settings help tab registration.
 */
class MXRoute_Settings_Help_Test extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options']        = array();
		$GLOBALS['wp_function_calls'] = array();
		$GLOBALS['mxroute_mock_screen'] = null;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['mxroute_mock_screen'] );
	}

	/**
	 * Tests that add_settings_help_tabs registers help tabs when screen matches.
	 */
	public function test_add_settings_help_tabs_registers_tabs() {
		$mock_screen = new MockScreen();
		$GLOBALS['mxroute_mock_screen'] = $mock_screen;

		$settings = new MXRoute_Settings();
		$settings->add_settings_help_tabs();

		$this->assertNotEmpty( $mock_screen->help_tabs );
	}

	/**
	 * Tests that add_logs_help_tabs registers help tabs when screen matches.
	 */
	public function test_add_logs_help_tabs_registers_tabs() {
		$mock_screen = new MockScreen();
		$GLOBALS['mxroute_mock_screen'] = $mock_screen;

		$settings = new MXRoute_Settings();
		$settings->add_logs_help_tabs();

		$this->assertNotEmpty( $mock_screen->help_tabs );
	}

	/**
	 * Tests that add_queue_help_tabs registers help tabs when screen matches.
	 */
	public function test_add_queue_help_tabs_registers_tabs() {
		$mock_screen = new MockScreen();
		$GLOBALS['mxroute_mock_screen'] = $mock_screen;

		$settings = new MXRoute_Settings();
		$settings->add_queue_help_tabs();

		$this->assertNotEmpty( $mock_screen->help_tabs );
	}
}

/**
 * Tests for MXRoute_Updater::check_update() and plugins_api().
 */
class MXRoute_Updater_API_Test extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options']            = array();
		$GLOBALS['wp_transients']         = array();
		$GLOBALS['wp_function_calls']     = array();
		$GLOBALS['mxroute_mock_remote_get_response'] = null;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['mxroute_mock_remote_get_response'] );
	}

	/**
	 * Tests that check_update returns unchanged transient when no newer version.
	 */
	public function test_check_update_returns_unchanged_when_up_to_date() {
		$release = array(
			'tag_name'  => 'v1.0.0',
			'assets'    => array(),
			'html_url'  => '',
			'body'      => '',
		);
		$GLOBALS['mxroute_mock_remote_get_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( $release ),
		);

		$updater = new MXRoute_Updater( '/fake/path/mxroute-mailer.php', 'richardkentgates/mxroute-mailer', '1.0.0' );
		$transient = new \stdClass();
		$transient->response = array();

		$result = $updater->check_update( $transient );

		$this->assertEmpty( $result->response );
	}

	/**
	 * Tests that check_update adds update when newer version exists.
	 */
	public function test_check_update_adds_update_for_newer_version() {
		$release = array(
			'tag_name'  => 'v2.0.0',
			'assets'    => array(
				array( 'name' => 'mxroute-mailer.zip', 'browser_download_url' => 'https://example.com/release.zip' ),
			),
			'html_url'  => 'https://github.com/richardkentgates/mxroute-mailer/releases/tag/v2.0.0',
			'body'      => 'Release notes',
		);
		$GLOBALS['mxroute_mock_remote_get_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( $release ),
		);

		$tmp_dir = sys_get_temp_dir() . '/mxroute_updater_test_' . uniqid();
		mkdir( $tmp_dir . '/mxroute-mailer', 0755, true );
		$plugin_file = $tmp_dir . '/mxroute-mailer/mxroute-mailer.php';
		file_put_contents( $plugin_file, '<?php' );

		$updater = new MXRoute_Updater( $plugin_file, 'richardkentgates/mxroute-mailer', '1.0.0' );
		$transient = new \stdClass();
		$transient->response = array();

		$result = $updater->check_update( $transient );

		$basename = plugin_basename( $plugin_file );
		$this->assertArrayHasKey( $basename, $result->response );
		$this->assertEquals( '2.0.0', $result->response[ $basename ]->new_version );

		// Cleanup.
		@unlink( $plugin_file );
		@rmdir( $tmp_dir . '/mxroute-mailer' );
		@rmdir( $tmp_dir );
	}

	/**
	 * Tests that check_update returns unchanged when transient is not an object.
	 */
	public function test_check_update_returns_unchanged_for_non_object() {
		$updater = new MXRoute_Updater( '/fake/path/mxroute-mailer.php', 'richardkentgates/mxroute-mailer', '1.0.0' );

		$result = $updater->check_update( 'not an object' );

		$this->assertEquals( 'not an object', $result );
	}

	/**
	 * Tests that plugins_api returns default result for non-matching action.
	 */
	public function test_plugins_api_returns_default_for_wrong_action() {
		$updater = new MXRoute_Updater( '/fake/path/mxroute-mailer.php', 'richardkentgates/mxroute-mailer', '1.0.0' );

		$result = $updater->plugins_api( 'default', 'other_action', new \stdClass() );

		$this->assertEquals( 'default', $result );
	}

	/**
	 * Tests that plugins_api returns plugin data for matching slug.
	 */
	public function test_plugins_api_returns_data_for_matching_slug() {
		$release = array(
			'tag_name' => 'v2.0.0',
			'body'     => 'Release notes',
			'assets'   => array(
				array( 'name' => 'mxroute-mailer.zip', 'browser_download_url' => 'https://example.com/release.zip' ),
			),
		);
		$GLOBALS['mxroute_mock_remote_get_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( $release ),
		);

		$tmp_dir = sys_get_temp_dir() . '/mxroute_updater_api_test_' . uniqid();
		mkdir( $tmp_dir . '/mxroute-mailer', 0755, true );
		$plugin_file = $tmp_dir . '/mxroute-mailer/mxroute-mailer.php';
		file_put_contents( $plugin_file, '<?php' );

		$updater = new MXRoute_Updater( $plugin_file, 'richardkentgates/mxroute-mailer', '1.0.0' );

		$args = new \stdClass();
		$args->slug = 'mxroute-mailer';

		$result = $updater->plugins_api( 'default', 'plugin_information', $args );

		$this->assertIsObject( $result );
		$this->assertEquals( 'MXRoute Mailer', $result->name );
		$this->assertEquals( '2.0.0', $result->version );

		// Cleanup.
		@unlink( $plugin_file );
		@rmdir( $tmp_dir . '/mxroute-mailer' );
		@rmdir( $tmp_dir );
	}

	/**
	 * Tests that plugins_api returns default for non-matching slug.
	 */
	public function test_plugins_api_returns_default_for_wrong_slug() {
		$tmp_dir = sys_get_temp_dir() . '/mxroute_updater_wrong_slug_' . uniqid();
		mkdir( $tmp_dir . '/mxroute-mailer', 0755, true );
		$plugin_file = $tmp_dir . '/mxroute-mailer/mxroute-mailer.php';
		file_put_contents( $plugin_file, '<?php' );

		$updater = new MXRoute_Updater( $plugin_file, 'richardkentgates/mxroute-mailer', '1.0.0' );

		$args = new \stdClass();
		$args->slug = 'other-plugin';

		$result = $updater->plugins_api( 'default', 'plugin_information', $args );

		$this->assertEquals( 'default', $result );

		@unlink( $plugin_file );
		@rmdir( $tmp_dir . '/mxroute-mailer' );
		@rmdir( $tmp_dir );
	}
}

/**
 * Mock screen object for help tab tests.
 */
class MockScreen {
	public $id = '';
	public $help_tabs = array();
	public $help_sidebar = '';

	public function add_help_tab( $args ) {
		$this->help_tabs[] = $args;
	}

	public function set_help_sidebar( $html ) {
		$this->help_sidebar = $html;
	}
}
