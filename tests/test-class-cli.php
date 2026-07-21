<?php
/**
 * Tests for MXRoute_CLI_Commands class.
 *
 * @package MXRoute_Mailer
 */

if ( ! defined( 'WP_CLI' ) ) {
	define( 'WP_CLI', true );
}

if ( ! class_exists( 'WP_CLI' ) ) {
	/**
	 * Mock WP_CLI namespace for testing.
	 */
	class WP_CLI {

		public static $log_output   = array();
		public static $error_output = array();
		public static $success_output = array();

		public static function log( $message ) {
			self::$log_output[] = $message;
		}

		public static function error( $message, $exit = true ) {
			self::$error_output[] = $message;
			throw new \MXRouteCLIException( $message );
		}

		public static function success( $message ) {
			self::$success_output[] = $message;
		}

		public static function add_command( $name, $class ) {
			// No-op in tests.
		}
	}

	/**
	 * Exception thrown by WP_CLI::error() in tests.
	 */
	class MXRouteCLIException extends \Exception {}
}

if ( ! class_exists( 'WP_CLI_Command' ) ) {
	/**
	 * Mock WP_CLI_Command base class for testing.
	 */
	class WP_CLI_Command {}
}

// Load WP_CLI\Utils mock (must be in a namespaced file).
require_once __DIR__ . '/mock-wp-cli-utils.php';

// Load the CLI class.
require_once dirname( __DIR__ ) . '/includes/class-mxroute-cli.php';

/**
 * Tests for MXRoute_CLI_Commands class.
 */
class MXRoute_CLI_Test extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options']          = array();
		$GLOBALS['wp_function_calls']   = array();
		$GLOBALS['wp_db_inserts']       = array();
		$GLOBALS['wp_db_queries']       = array();
		$GLOBALS['wp_db_results']       = array();
		$GLOBALS['wp_db_col']           = array();
		$GLOBALS['wp_db_row']           = null;
		$GLOBALS['wp_scheduled_events'] = array();
		$GLOBALS['wp_cli_format_items'] = null;

		WP_CLI::$log_output     = array();
		WP_CLI::$error_output   = array();
		WP_CLI::$success_output = array();

		$this->cli = new MXRoute_CLI_Commands();
	}

	/**
	 * Tests that option with no args defaults to 'get' all settings.
	 */
	public function test_option_no_args_defaults_to_get_all() {
		$this->cli->option( array(), array() );

		$this->assertNotEmpty( $GLOBALS['wp_cli_format_items'] );
		$this->assertEquals( 'table', $GLOBALS['wp_cli_format_items']['format'] );
		$this->assertArrayHasKey( 'server', $GLOBALS['wp_cli_format_items']['items'] );
		$this->assertArrayHasKey( 'username', $GLOBALS['wp_cli_format_items']['items'] );
	}

	/**
	 * Tests that option get returns a specific setting value.
	 */
	public function test_option_get_specific_key() {
		$GLOBALS['wp_options']['mxroute_mailer_server'] = 'chocobo.mxrouting.net';

		$this->cli->option( array( 'get', 'server' ), array() );

		$this->assertContains( 'chocobo.mxrouting.net', WP_CLI::$log_output );
	}

	/**
	 * Tests that option get masks the password.
	 */
	public function test_option_get_masks_password() {
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'encrypted-value';

		$this->cli->option( array( 'get', 'password' ), array() );

		$this->assertContains( '********', WP_CLI::$log_output );
		$this->assertNotContains( 'encrypted-value', WP_CLI::$log_output );
	}

	/**
	 * Tests that option get shows '(not set)' for empty password.
	 */
	public function test_option_get_empty_password() {
		$this->cli->option( array( 'get', 'password' ), array() );

		$this->assertContains( '(not set)', WP_CLI::$log_output );
	}

	/**
	 * Tests that option get converts boolean settings to true/false strings.
	 */
	public function test_option_get_boolean_settings() {
		$GLOBALS['wp_options']['mxroute_mailer_logging_enabled'] = 1;
		$GLOBALS['wp_options']['mxroute_mailer_keep_data']       = 0;

		$this->cli->option( array( 'get', 'logging_enabled' ), array() );
		$this->assertContains( 'true', WP_CLI::$log_output );

		WP_CLI::$log_output = array();
		$this->cli->option( array( 'get', 'keep_data' ), array() );
		$this->assertContains( 'false', WP_CLI::$log_output );
	}

	/**
	 * Tests that option get errors on invalid key.
	 */
	public function test_option_get_invalid_key() {
		$this->expectException( \MXRouteCLIException::class );
		$this->cli->option( array( 'get', 'invalid_key' ), array() );
	}

	/**
	 * Tests that option set updates a setting.
	 */
	public function test_option_set_updates_setting() {
		$this->cli->option( array( 'set', 'server', 'new.server.com' ), array() );

		$this->assertEquals( 'new.server.com', $GLOBALS['wp_options']['mxroute_mailer_server'] );
		$this->assertNotEmpty( WP_CLI::$success_output );
	}

	/**
	 * Tests that option set encrypts the password.
	 */
	public function test_option_set_encrypts_password() {
		$this->cli->option( array( 'set', 'password', 'my-secret-pw' ), array() );

		$stored = $GLOBALS['wp_options']['mxroute_mailer_password'];
		$this->assertNotEquals( 'my-secret-pw', $stored );
		$decrypted = \MXRoute_Crypto::decrypt( $stored );
		$this->assertEquals( 'my-secret-pw', $decrypted );
	}

	/**
	 * Tests that option set rejects empty password.
	 */
	public function test_option_set_rejects_empty_password() {
		$this->expectException( \MXRouteCLIException::class );
		$this->cli->option( array( 'set', 'password', '' ), array() );
	}

	/**
	 * Tests that option set validates batch_size range.
	 */
	public function test_option_set_batch_size_validates_range() {
		$this->expectException( \MXRouteCLIException::class );
		$this->cli->option( array( 'set', 'batch_size', '100' ), array() );
	}

	/**
	 * Tests that option set accepts valid batch_size.
	 */
	public function test_option_set_batch_size_valid() {
		$this->cli->option( array( 'set', 'batch_size', '10' ), array() );
		$this->assertEquals( 10, $GLOBALS['wp_options']['mxroute_mailer_batch_size'] );
	}

	/**
	 * Tests that option set converts boolean settings.
	 */
	public function test_option_set_converts_boolean() {
		$this->cli->option( array( 'set', 'logging_enabled', '1' ), array() );
		$this->assertEquals( 1, $GLOBALS['wp_options']['mxroute_mailer_logging_enabled'] );

		$this->cli->option( array( 'set', 'logging_enabled', '' ), array() );
		$this->assertEquals( 0, $GLOBALS['wp_options']['mxroute_mailer_logging_enabled'] );
	}

	/**
	 * Tests that option set errors on invalid key.
	 */
	public function test_option_set_invalid_key() {
		$this->expectException( \MXRouteCLIException::class );
		$this->cli->option( array( 'set', 'nonexistent', 'value' ), array() );
	}

	/**
	 * Tests that option errors on invalid action.
	 */
	public function test_option_invalid_action() {
		$this->expectException( \MXRouteCLIException::class );
		$this->cli->option( array( 'delete', 'server' ), array() );
	}

	/**
	 * Tests that logs list returns no-logs message when empty.
	 */
	public function test_logs_list_empty() {
		$this->cli->logs( array( 'list' ), array() );

		$this->assertContains( 'No logs found.', WP_CLI::$log_output );
	}

	/**
	 * Tests that logs list displays results when logs exist.
	 */
	public function test_logs_list_with_results() {
		$log = new \stdClass();
		$log->id         = 1;
		$log->timestamp  = '2026-01-01 12:00:00';
		$log->from_email = 'from@example.com';
		$log->to_email   = 'to@example.com';
		$log->subject    = 'Test Subject';
		$log->success    = 1;
		$log->transport  = 'api';

		$GLOBALS['wp_db_results'] = array( $log );

		$this->cli->logs( array( 'list' ), array() );

		$this->assertNotEmpty( $GLOBALS['wp_cli_format_items'] );
		$this->assertCount( 1, $GLOBALS['wp_cli_format_items']['items'] );
	}

	/**
	 * Tests that logs list filters by status.
	 */
	public function test_logs_list_filter_by_status() {
		$log = new \stdClass();
		$log->id         = 1;
		$log->timestamp  = '2026-01-01 12:00:00';
		$log->from_email = 'from@example.com';
		$log->to_email   = 'to@example.com';
		$log->subject    = 'Test Subject';
		$log->success    = 1;
		$log->transport  = 'api';

		$GLOBALS['wp_db_results'] = array( $log );

		$this->cli->logs( array( 'list' ), array( 'status' => '1' ) );

		$this->assertNotEmpty( $GLOBALS['wp_cli_format_items'] );
	}

	/**
	 * Tests that logs view requires an ID.
	 */
	public function test_logs_view_requires_id() {
		$this->expectException( \MXRouteCLIException::class );
		$this->cli->logs( array( 'view' ), array() );
	}

	/**
	 * Tests that logs view errors when log not found.
	 */
	public function test_logs_view_not_found() {
		$this->expectException( \MXRouteCLIException::class );
		$this->cli->logs( array( 'view', '999' ), array() );
	}

	/**
	 * Tests that logs view displays a log entry.
	 */
	public function test_logs_view_displays_log() {
		$log = new \stdClass();
		$log->id           = 1;
		$log->timestamp    = '2026-01-01 12:00:00';
		$log->success      = 1;
		$log->from_email   = 'from@example.com';
		$log->reply_to     = '';
		$log->to_email     = 'to@example.com';
		$log->subject      = 'Test';
		$log->transport    = 'api';
		$log->created_at   = '2026-01-01 12:00:00';
		$log->processed_at = '2026-01-01 12:00:01';
		$log->message      = 'Hello';
		$log->api_request  = '{}';
		$log->api_response = '{}';

		$GLOBALS['wp_db_row'] = $log;

		$this->cli->logs( array( 'view', '1' ), array() );

		$this->assertNotEmpty( $GLOBALS['wp_cli_format_items'] );
		$this->assertContains( '--- Message ---', WP_CLI::$log_output );
	}

	/**
	 * Tests that logs delete requires an ID.
	 */
	public function test_logs_delete_requires_id() {
		$this->expectException( \MXRouteCLIException::class );
		$this->cli->logs( array( 'delete' ), array() );
	}

	/**
	 * Tests that logs delete errors when log not found.
	 */
	public function test_logs_delete_not_found() {
		$this->expectException( \MXRouteCLIException::class );
		$this->cli->logs( array( 'delete', '999' ), array() );
	}

	/**
	 * Tests that logs delete removes a log entry.
	 */
	public function test_logs_delete_removes_entry() {
		$log = new \stdClass();
		$log->id = 1;

		$GLOBALS['wp_db_row'] = $log;

		$this->cli->logs( array( 'delete', '1' ), array() );

		$this->assertNotEmpty( WP_CLI::$success_output );
		$this->assertStringContainsString( 'deleted', WP_CLI::$success_output[0] );
	}

	/**
	 * Tests that logs clear removes all logs.
	 */
	public function test_logs_clear() {
		$this->cli->logs( array( 'clear' ), array() );

		$this->assertNotEmpty( WP_CLI::$success_output );
		$this->assertStringContainsString( 'cleared', WP_CLI::$success_output[0] );
	}

	/**
	 * Tests that logs errors on invalid action.
	 */
	public function test_logs_invalid_action() {
		$this->expectException( \MXRouteCLIException::class );
		$this->cli->logs( array( 'invalid' ), array() );
	}

	/**
	 * Tests that queue count returns zero when empty.
	 */
	public function test_queue_count_empty() {
		$this->cli->queue( array( 'count' ), array() );

		$this->assertContains( '0', WP_CLI::$log_output );
	}

	/**
	 * Tests that queue clear removes pending items.
	 */
	public function test_queue_clear() {
		$this->cli->queue( array( 'clear' ), array() );

		$this->assertNotEmpty( WP_CLI::$success_output );
	}

	/**
	 * Tests that queue list returns no-items message when empty.
	 */
	public function test_queue_list_empty() {
		$this->cli->queue( array( 'list' ), array() );

		$this->assertContains( 'No pending items in queue.', WP_CLI::$log_output );
	}

	/**
	 * Tests that queue list defaults to 'list' action.
	 */
	public function test_queue_default_action() {
		$this->cli->queue( array(), array() );

		$this->assertContains( 'No pending items in queue.', WP_CLI::$log_output );
	}

	/**
	 * Tests that queue errors on invalid action.
	 */
	public function test_queue_invalid_action() {
		$this->expectException( \MXRouteCLIException::class );
		$this->cli->queue( array( 'invalid' ), array() );
	}

	/**
	 * Tests that send errors on empty recipient.
	 */
	public function test_send_requires_recipient() {
		$this->expectException( \MXRouteCLIException::class );
		$this->cli->send( array( '' ), array() );
	}

	/**
	 * Tests that send errors when no sender configured.
	 */
	public function test_send_requires_sender() {
		$this->expectException( \MXRouteCLIException::class );
		$this->cli->send( array( 'to@example.com' ), array() );
	}

	/**
	 * Tests that send succeeds with valid inputs.
	 */
	public function test_send_success() {
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'from@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'encrypted';

		$this->cli->send( array( 'to@example.com', 'Subject', 'Body' ), array() );

		$this->assertNotEmpty( WP_CLI::$success_output );
	}

	/**
	 * Tests that test command requires recipient.
	 */
	public function test_test_requires_recipient() {
		$this->expectException( \MXRouteCLIException::class );
		$this->cli->test( array( '' ), array() );
	}

	/**
	 * Tests that test command requires configured username.
	 */
	public function test_test_requires_username() {
		$this->expectException( \MXRouteCLIException::class );
		$this->cli->test( array( 'to@example.com' ), array() );
	}

	/**
	 * Tests that test command queues an email successfully.
	 */
	public function test_test_queues_email() {
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'from@example.com';

		$this->cli->test( array( 'to@example.com' ), array() );

		$this->assertNotEmpty( WP_CLI::$success_output );
		$this->assertStringContainsString( 'queued', WP_CLI::$success_output[0] );
	}

	/**
	 * Tests that test command accepts custom subject and message.
	 */
	public function test_test_custom_subject_message() {
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'from@example.com';

		$this->cli->test( array( 'to@example.com' ), array( 'subject' => 'Custom', 'message' => 'Body' ) );

		$this->assertNotEmpty( WP_CLI::$success_output );
	}
}
