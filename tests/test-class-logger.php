<?php
/**
 * Tests for MXRoute_Logger class.
 *
 * @package MXRoute_Mailer
 */
class MXRoute_Logger_Test extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        // Reset global state
        $GLOBALS['wp_options'] = array();
        $GLOBALS['wp_function_calls'] = array();
        $GLOBALS['wp_db_inserts'] = array();
        $GLOBALS['wp_db_queries'] = array();
    }

    /**
     * Tests that create_table calls dbDelta without errors.
     */
    public function test_create_table_runs_without_error() {
        MXRoute_Logger::create_table();
        // Verify dbDelta was called
        $this->assertArrayHasKey('dbDelta', $GLOBALS['wp_function_calls']);
    }

    /**
     * Tests that log inserts a record into the database when logging is enabled.
     */
    public function test_log_inserts_record_when_logging_enabled() {
        $GLOBALS['wp_options']['mxroute_mailer_logging_enabled'] = 1;

        $logger = new MXRoute_Logger();
        $logger->log(
            'from@example.com',
            'to@example.com',
            'Test Subject',
            'Test body content',
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

    /**
     * Tests that log skips the database insert when logging is disabled.
     */
    public function test_log_skips_when_logging_disabled() {
        $GLOBALS['wp_options']['mxroute_mailer_logging_enabled'] = 0;

        $logger = new MXRoute_Logger();
        $logger->log(
            'from@example.com',
            'to@example.com',
            'Test Subject',
            'Test body content',
            array(),
            array(),
            true
        );

        $this->assertEmpty($GLOBALS['wp_db_inserts']);
    }

    /**
     * Tests that log defaults to enabled when the option is not set.
     */
    public function test_log_defaults_logging_to_enabled() {
        // When mxroute_mailer_logging_enabled is not set, default is 1 (enabled)
        $logger = new MXRoute_Logger();
        $logger->log(
            'from@example.com',
            'to@example.com',
            'Test Subject',
            'Test body content',
            array(),
            array(),
            true
        );

        $this->assertNotEmpty($GLOBALS['wp_db_inserts']);
    }

    /**
     * Tests that get_log returns null for a nonexistent log ID.
     */
    public function test_get_log_returns_null_for_nonexistent_id() {
        $logger = new MXRoute_Logger();
        $result = $logger->get_log(999);
        $this->assertNull($result);
    }

    /**
     * Tests that clear_logs executes a database query.
     */
    public function test_clear_logs_calls_query() {
        $logger = new MXRoute_Logger();
        $logger->clear_logs();

        $this->assertNotEmpty($GLOBALS['wp_db_queries']);
    }

    /**
     * Tests that delete_log runs without error for a single ID.
     */
    public function test_delete_log_calls_delete() {
        $logger = new MXRoute_Logger();
        $logger->delete_log( 1 );

        $deletes = $GLOBALS['wp_function_calls']['$wpdb->delete'] ?? array();
        $this->assertNotEmpty( $deletes );
        $this->assertEquals( 1, $deletes[0]['where']['id'] );
    }

    /**
     * Tests that delete_logs handles multiple IDs without error.
     */
    public function test_delete_logs_calls_delete_with_multiple_ids() {
        $logger = new MXRoute_Logger();
        $logger->delete_logs( array( 1, 2, 3 ) );

        $deletes = $GLOBALS['wp_function_calls']['$wpdb->delete'] ?? array();
        $this->assertNotEmpty( $deletes );
    }

    /**
     * Tests that delete_logs handles an empty array gracefully.
     */
    public function test_delete_logs_handles_empty_array() {
        $logger = new MXRoute_Logger();
        $logger->delete_logs( array() );

        $deletes = $GLOBALS['wp_function_calls']['$wpdb->delete'] ?? array();
        $this->assertEmpty( $deletes );
    }

    /**
     * Tests that get_logs returns a paginated result with logs, total, and pages.
     */
    public function test_get_logs_returns_array_with_pagination() {
        $logger = new MXRoute_Logger();
        $result = $logger->get_logs(10, 1, array());

        $this->assertIsArray($result);
        $this->assertArrayHasKey('logs', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('pages', $result);
        $this->assertIsArray($result['logs']);
        $this->assertIsInt($result['total']);
        $this->assertIsInt($result['pages']);
    }

    /**
     * Tests that get_logs handles the search filter correctly.
     */
    public function test_get_logs_handles_search_filter() {
        $logger = new MXRoute_Logger();
        $result = $logger->get_logs(10, 1, array('search' => 'test'));

        $this->assertIsArray($result);
        $this->assertArrayHasKey('logs', $result);
        $this->assertArrayHasKey('total', $result);
    }

    /**
     * Tests that get_logs handles success/failure status filters.
     */
    public function test_get_logs_handles_status_filter() {
        $logger = new MXRoute_Logger();

        $result = $logger->get_logs(10, 1, array('success' => '1'));
        $this->assertIsArray($result);
        $this->assertArrayHasKey('logs', $result);

        $result = $logger->get_logs(10, 1, array('success' => '0'));
        $this->assertIsArray($result);
        $this->assertArrayHasKey('logs', $result);
    }

    /**
     * Tests that get_logs handles the from_email filter.
     */
    public function test_get_logs_handles_from_email_filter() {
        $logger = new MXRoute_Logger();
        $result = $logger->get_logs(10, 1, array('from_email' => 'from@example.com'));

        $this->assertIsArray($result);
        $this->assertArrayHasKey('logs', $result);
    }

    /**
     * Tests that get_logs handles date range filters.
     */
    public function test_get_logs_handles_date_filters() {
        $logger = new MXRoute_Logger();
        $result = $logger->get_logs(10, 1, array(
            'date_from' => '2024-01-01',
            'date_to' => '2024-12-31'
        ));

        $this->assertIsArray($result);
        $this->assertArrayHasKey('logs', $result);
    }

    /**
     * Tests that log correctly uses the first address from an array "to" value.
     */
    public function test_log_handles_array_to_address() {
        $GLOBALS['wp_options']['mxroute_mailer_logging_enabled'] = 1;

        $logger = new MXRoute_Logger();
        $logger->log(
            'from@example.com',
            array('to@example.com', 'to2@example.com'),
            'Test Subject',
            'Test body content',
            array(),
            array(),
            true
        );

        $this->assertNotEmpty($GLOBALS['wp_db_inserts']);
        $insert = $GLOBALS['wp_db_inserts'][0];
        // Should use first email from array
        $this->assertEquals('to@example.com', $insert['data']['to_email']);
    }

    /**
     * Tests that log handles an empty "to" address without error.
     */
    public function test_log_handles_empty_to_address() {
        $GLOBALS['wp_options']['mxroute_mailer_logging_enabled'] = 1;

        $logger = new MXRoute_Logger();
        $logger->log(
            'from@example.com',
            '',
            'Test Subject',
            'Test body content',
            array(),
            array(),
            true
        );

        $this->assertNotEmpty($GLOBALS['wp_db_inserts']);
        $insert = $GLOBALS['wp_db_inserts'][0];
        $this->assertEquals('', $insert['data']['to_email']);
    }

    /**
     * Tests that log records a failure status when the send fails.
     */
    public function test_log_handles_failed_send() {
        $GLOBALS['wp_options']['mxroute_mailer_logging_enabled'] = 1;

        $logger = new MXRoute_Logger();
        $logger->log(
            'from@example.com',
            'to@example.com',
            'Test Subject',
            'Test body content',
            array('server' => 'test'),
            array('error' => 'Connection failed'),
            false
        );

        $this->assertNotEmpty($GLOBALS['wp_db_inserts']);
        $insert = $GLOBALS['wp_db_inserts'][0];
        $this->assertEquals(-1, $insert['data']['success']);
    }

    /**
     * Tests that requeue_log executes a query.
     */
    public function test_requeue_log_executes_queries() {
        $logger = new MXRoute_Logger();
        $before = count( $GLOBALS['wp_db_queries'] );
        $result = $logger->requeue_log( 1 );

        $this->assertTrue( $result );
        $this->assertGreaterThan( $before, count( $GLOBALS['wp_db_queries'] ) );
    }

    /**
     * Tests that requeue_log returns false for invalid ID.
     */
    public function test_requeue_log_returns_false_for_zero_id() {
        $logger = new MXRoute_Logger();
        $this->assertFalse( $logger->requeue_log( 0 ) );
    }

    /**
     * Tests that requeue_log produces a SQL UPDATE with success=0.
     */
    public function test_requeue_log_produces_update_sql() {
        $logger = new MXRoute_Logger();
        $logger->requeue_log( 5 );

        $found = false;
        foreach ( $GLOBALS['wp_db_queries'] as $query ) {
            if ( false !== strpos( $query, 'SET success = 0' ) ) {
                $found = true;
                break;
            }
        }
        $this->assertTrue( $found );
    }

    /**
     * Tests that requeue_logs handles multiple IDs without error.
     */
    public function test_requeue_logs_handles_multiple_ids() {
        $logger = new MXRoute_Logger();
        $count = $logger->requeue_logs( array( 1, 2, 3 ) );

        $this->assertIsInt( $count );
        $this->assertGreaterThanOrEqual( 0, $count );
    }

    /**
     * Tests that requeue_logs handles an empty array gracefully.
     */
    public function test_requeue_logs_handles_empty_array() {
        $logger = new MXRoute_Logger();
        $count = $logger->requeue_logs( array() );

        $this->assertEquals( 0, $count );
    }
}
