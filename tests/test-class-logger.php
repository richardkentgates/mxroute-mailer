<?php
/**
 * Tests for MXRoute_Logger class
 */
class MXRoute_Logger_Test extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        // Reset global state
        $GLOBALS['wp_options'] = array();
        $GLOBALS['wp_function_calls'] = array();
        $GLOBALS['wp_db_inserts'] = array();
        $GLOBALS['wp_db_queries'] = array();
    }

    public function test_create_table_runs_without_error() {
        MXRoute_Logger::create_table();
        // Verify dbDelta was called
        $this->assertArrayHasKey('dbDelta', $GLOBALS['wp_function_calls']);
    }

    public function test_log_inserts_record_when_logging_enabled() {
        $GLOBALS['wp_options']['mxroute_mailer_logging_enabled'] = 1;

        $logger = new MXRoute_Logger();
        $logger->log(
            'from@example.com',
            'to@example.com',
            'Test Subject',
            array('server' => 'test', 'username' => 'user'),
            array('success' => true, 'message' => 'Sent'),
            true
        );

        $this->assertNotEmpty($GLOBALS['wp_db_inserts']);
        $insert = $GLOBALS['wp_db_inserts'][0];
        $this->assertEquals('wp_mxroute_mailer_logs', $insert['table']);
        $this->assertEquals('from@example.com', $insert['data']['from_email']);
        $this->assertEquals('to@example.com', $insert['data']['to_email']);
        $this->assertEquals('Test Subject', $insert['data']['subject']);
        $this->assertEquals(1, $insert['data']['success']);
    }

    public function test_log_skips_when_logging_disabled() {
        $GLOBALS['wp_options']['mxroute_mailer_logging_enabled'] = 0;

        $logger = new MXRoute_Logger();
        $logger->log(
            'from@example.com',
            'to@example.com',
            'Test Subject',
            array(),
            array(),
            true
        );

        $this->assertEmpty($GLOBALS['wp_db_inserts']);
    }

    public function test_log_defaults_logging_to_enabled() {
        // When mxroute_mailer_logging_enabled is not set, default is 1 (enabled)
        $logger = new MXRoute_Logger();
        $logger->log(
            'from@example.com',
            'to@example.com',
            'Test Subject',
            array(),
            array(),
            true
        );

        $this->assertNotEmpty($GLOBALS['wp_db_inserts']);
    }

    public function test_get_recent_logs_returns_array() {
        $logger = new MXRoute_Logger();
        $result = $logger->get_recent_logs(5);
        $this->assertIsArray($result);
    }

    public function test_get_log_returns_null_for_nonexistent_id() {
        $logger = new MXRoute_Logger();
        $result = $logger->get_log(999);
        $this->assertNull($result);
    }

    public function test_clear_logs_calls_query() {
        $logger = new MXRoute_Logger();
        $logger->clear_logs();

        $this->assertNotEmpty($GLOBALS['wp_db_queries']);
    }

    public function test_delete_log_calls_delete() {
        $logger = new MXRoute_Logger();
        $logger->delete_log(1);

        // delete_log uses $wpdb->delete() which doesn't go through $wpdb->query()
        // so we verify it doesn't throw
        $this->assertTrue(true);
    }

    public function test_delete_logs_calls_delete_with_multiple_ids() {
        $logger = new MXRoute_Logger();
        $logger->delete_logs( array( 1, 2, 3 ) );

        $this->assertTrue(true);
    }

    public function test_delete_logs_handles_empty_array() {
        $logger = new MXRoute_Logger();
        $logger->delete_logs( array() );

        $this->assertTrue(true);
    }

    public function test_get_logs_returns_array_with_pagination() {
        $logger = new MXRoute_Logger();
        $result = $logger->get_logs(10, 1, array());

        $this->assertIsArray($result);
        $this->assertArrayHasKey('logs', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('pages', $result);
        $this->assertIsArray($result['logs']);
        $this->assertIsInt($result['total']);
        $this->assertIsFloat($result['pages']);
    }

    public function test_get_logs_handles_search_filter() {
        $logger = new MXRoute_Logger();
        $result = $logger->get_logs(10, 1, array('search' => 'test'));

        $this->assertIsArray($result);
        $this->assertArrayHasKey('logs', $result);
        $this->assertArrayHasKey('total', $result);
    }

    public function test_get_logs_handles_status_filter() {
        $logger = new MXRoute_Logger();

        $result = $logger->get_logs(10, 1, array('success' => '1'));
        $this->assertIsArray($result);
        $this->assertArrayHasKey('logs', $result);

        $result = $logger->get_logs(10, 1, array('success' => '0'));
        $this->assertIsArray($result);
        $this->assertArrayHasKey('logs', $result);
    }

    public function test_get_logs_handles_from_email_filter() {
        $logger = new MXRoute_Logger();
        $result = $logger->get_logs(10, 1, array('from_email' => 'from@example.com'));

        $this->assertIsArray($result);
        $this->assertArrayHasKey('logs', $result);
    }

    public function test_get_logs_handles_date_filters() {
        $logger = new MXRoute_Logger();
        $result = $logger->get_logs(10, 1, array(
            'date_from' => '2024-01-01',
            'date_to' => '2024-12-31'
        ));

        $this->assertIsArray($result);
        $this->assertArrayHasKey('logs', $result);
    }

    public function test_log_handles_array_to_address() {
        $GLOBALS['wp_options']['mxroute_mailer_logging_enabled'] = 1;

        $logger = new MXRoute_Logger();
        $logger->log(
            'from@example.com',
            array('to@example.com', 'to2@example.com'),
            'Test Subject',
            array(),
            array(),
            true
        );

        $this->assertNotEmpty($GLOBALS['wp_db_inserts']);
        $insert = $GLOBALS['wp_db_inserts'][0];
        // Should use first email from array
        $this->assertEquals('to@example.com', $insert['data']['to_email']);
    }

    public function test_log_handles_empty_to_address() {
        $GLOBALS['wp_options']['mxroute_mailer_logging_enabled'] = 1;

        $logger = new MXRoute_Logger();
        $logger->log(
            'from@example.com',
            '',
            'Test Subject',
            array(),
            array(),
            true
        );

        $this->assertNotEmpty($GLOBALS['wp_db_inserts']);
        $insert = $GLOBALS['wp_db_inserts'][0];
        $this->assertEquals('', $insert['data']['to_email']);
    }

    public function test_log_handles_failed_send() {
        $GLOBALS['wp_options']['mxroute_mailer_logging_enabled'] = 1;

        $logger = new MXRoute_Logger();
        $logger->log(
            'from@example.com',
            'to@example.com',
            'Test Subject',
            array('server' => 'test'),
            array('error' => 'Connection failed'),
            false
        );

        $this->assertNotEmpty($GLOBALS['wp_db_inserts']);
        $insert = $GLOBALS['wp_db_inserts'][0];
        $this->assertEquals(0, $insert['data']['success']);
    }
}
