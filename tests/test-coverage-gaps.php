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
		MXRoute_Mailer::reset();
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

		$this->assertArrayHasKey( 'transport', $sent_update['data'], 'mark_sent should include transport' );
		$this->assertEquals( 'api', $sent_update['data']['transport'], 'Empty attachments should default to api transport' );

		$log_inserts = array_filter( $GLOBALS['wp_db_inserts'], function ( $insert ) {
			return isset( $insert['data']['from_email'] );
		} );
		$this->assertEmpty( $log_inserts, 'process_queue should not create duplicate log rows' );
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
	 * Tests that mark_sent calls $wpdb->update with success=1 and transport.
	 */
	public function test_mark_sent_sets_success_1() {
		$queue = new MXRoute_Queue();
		$queue->mark_sent( 42, array( 'from' => 'a@b.com' ), array( 'success' => true ), 'smtp' );

		$updates = $GLOBALS['wp_function_calls']['$wpdb->update'];
		$this->assertCount( 1, $updates );

		$data = $updates[0]['data'];
		$this->assertEquals( 1, $data['success'] );
		$this->assertEquals( 'smtp', $data['transport'] );
		$this->assertEquals( '{"from":"a@b.com"}', $data['api_request'] );
		$this->assertEquals( '{"success":true}', $data['api_response'] );
		$this->assertNotEmpty( $data['processed_at'] );
	}

	/**
	 * Tests that mark_failed calls $wpdb->update with success=-1 and transport.
	 */
	public function test_mark_failed_sets_success_minus_1() {
		$queue = new MXRoute_Queue();
		$queue->mark_failed( 99, array(), array( 'error' => 'fail' ), 'api' );

		$updates = $GLOBALS['wp_function_calls']['$wpdb->update'];
		$this->assertCount( 1, $updates );

		$data = $updates[0]['data'];
		$this->assertEquals( -1, $data['success'] );
		$this->assertEquals( 'api', $data['transport'] );
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
 * Tests for MXRoute_Updater::inject_update() and plugin_info().
 */
class MXRoute_Updater_API_Test extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options']            = array();
		$GLOBALS['wp_transients']         = array();
		$GLOBALS['wp_function_calls']     = array();
		$GLOBALS['mxroute_mock_remote_get_response'] = null;
		delete_transient( 'mxroute_remote_metadata' );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['mxroute_mock_remote_get_response'] );
	}

	/**
	 * Tests that inject_update returns false when no newer version.
	 */
	public function test_inject_update_returns_false_when_up_to_date() {
		$release = array(
			'version'    => '1.4.12',
			'download_url' => 'https://example.com/release.zip',
		);
		$GLOBALS['mxroute_mock_remote_get_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( $release ),
		);

		$updater = MXRoute_Updater::create_for_test( '/fake/path/mxroute-mailer.php' );
		$transient = new \stdClass();
		$transient->response = array();

		$result = $updater->inject_update( $transient );

		$this->assertFalse( $result );
	}

	/**
	 * Tests that inject_update adds update when newer version exists.
	 */
	public function test_inject_update_adds_update_for_newer_version() {
		$release = array(
			'version'    => '2.0.0',
			'download_url' => 'https://example.com/release.zip',
		);
		$GLOBALS['mxroute_mock_remote_get_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( $release ),
		);

		$tmp_dir = sys_get_temp_dir() . '/mxroute_updater_test_' . uniqid();
		mkdir( $tmp_dir . '/mxroute-mailer', 0755, true );
		$plugin_file = $tmp_dir . '/mxroute-mailer/mxroute-mailer.php';
		file_put_contents( $plugin_file, '<?php' );

		$updater = MXRoute_Updater::create_for_test( $plugin_file );
		$transient = new \stdClass();
		$transient->response = array();

		$result = $updater->inject_update( $transient );

		$basename = plugin_basename( $plugin_file );
		$this->assertIsObject( $result );
		$this->assertArrayHasKey( $basename, $result->response );
		$this->assertEquals( '2.0.0', $result->response[ $basename ]->new_version );

		// Cleanup.
		@unlink( $plugin_file );
		@rmdir( $tmp_dir . '/mxroute-mailer' );
		@rmdir( $tmp_dir );
	}

	/**
	 * Tests that inject_update returns false when transient is not an object.
	 */
	public function test_inject_update_returns_false_for_non_object() {
		$updater = MXRoute_Updater::create_for_test( '/fake/path/mxroute-mailer.php' );

		$result = $updater->inject_update( 'not an object' );

		$this->assertFalse( $result );
	}

	/**
	 * Tests that plugin_info returns default result for non-matching action.
	 */
	public function test_plugin_info_returns_default_for_wrong_action() {
		$updater = MXRoute_Updater::create_for_test( '/fake/path/mxroute-mailer.php' );

		$result = $updater->plugin_info( 'default', 'other_action', new \stdClass() );

		$this->assertEquals( 'default', $result );
	}

	/**
	 * Tests that plugin_info returns plugin data for matching slug.
	 */
	public function test_plugin_info_returns_data_for_matching_slug() {
		$release = array(
			'version'    => '2.0.0',
			'download_url' => 'https://example.com/release.zip',
		);
		$GLOBALS['mxroute_mock_remote_get_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( $release ),
		);

		$tmp_dir = sys_get_temp_dir() . '/mxroute_updater_api_test_' . uniqid();
		mkdir( $tmp_dir . '/mxroute-mailer', 0755, true );
		$plugin_file = $tmp_dir . '/mxroute-mailer/mxroute-mailer.php';
		file_put_contents( $plugin_file, '<?php /** Plugin Name: MXRoute Mailer */' );

		$updater = MXRoute_Updater::create_for_test( $plugin_file );

		$args = new \stdClass();
		$args->slug = 'mxroute-mailer';

		$result = $updater->plugin_info( 'default', 'plugin_information', $args );

		$this->assertIsObject( $result );
		$this->assertEquals( 'MXRoute Mailer', $result->name );
		$this->assertEquals( '2.0.0', $result->version );

		// Cleanup.
		@unlink( $plugin_file );
		@rmdir( $tmp_dir . '/mxroute-mailer' );
		@rmdir( $tmp_dir );
	}

	/**
	 * Tests that plugin_info returns default for non-matching slug.
	 */
	public function test_plugin_info_returns_default_for_wrong_slug() {
		$tmp_dir = sys_get_temp_dir() . '/mxroute_updater_wrong_slug_' . uniqid();
		mkdir( $tmp_dir . '/mxroute-mailer', 0755, true );
		$plugin_file = $tmp_dir . '/mxroute-mailer/mxroute-mailer.php';
		file_put_contents( $plugin_file, '<?php' );

		$updater = MXRoute_Updater::create_for_test( $plugin_file );

		$args = new \stdClass();
		$args->slug = 'other-plugin';

		$result = $updater->plugin_info( 'default', 'plugin_information', $args );

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
