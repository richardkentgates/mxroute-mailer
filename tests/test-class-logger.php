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
     * Tests that requeue_log executes a query.
     */
    public function test_requeue_log_executes_queries() {
        $logger = new MXRoute_Logger();
        $GLOBALS['wp_db_affected_rows'] = 1;
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
    public function test_requeue_logs_handles_empty_array_gracefully() {
        $logger = new MXRoute_Logger();
        $count = $logger->requeue_logs( array() );

        $this->assertEquals( 0, $count );
    }

    /**
     * Tests that a successful requeue appends a requeue event to history.
     */
    public function test_requeue_log_appends_requeue_history() {
        $logger = new MXRoute_Logger();
        $GLOBALS['wp_db_affected_rows'] = 1;
        $GLOBALS['wp_db_var'] = null;

        $result = $logger->requeue_log( 1 );

        $this->assertTrue( $result );

        $history_written = null;
        $updates = $GLOBALS['wp_function_calls']['$wpdb->update'] ?? array();
        foreach ( $updates as $update ) {
            if ( isset( $update['data']['history'] ) ) {
                $history_written = $update['data']['history'];
            }
        }
        $this->assertNotNull( $history_written, 'Expected history update after requeue' );

        $events = json_decode( $history_written, true );
        $this->assertEquals( 'requeue', end( $events )['event'] );
    }

    /**
     * Tests that requeue_log records the actor in the history event.
     */
    public function test_requeue_log_records_actor() {
        $logger = new MXRoute_Logger();
        $GLOBALS['wp_db_affected_rows'] = 1;
        $GLOBALS['wp_db_var'] = null;

        $logger->requeue_log( 1, 'admin' );

        $updates = $GLOBALS['wp_function_calls']['$wpdb->update'] ?? array();
        $last    = end( $updates );
        $events  = json_decode( $last['data']['history'], true );
        $this->assertEquals( 'admin', end( $events )['by'] );
    }

    /**
     * Tests that append_history preserves existing events.
     */
    public function test_append_history_preserves_existing_events() {
        $GLOBALS['wp_db_var'] = '[{"event":"queued","at":"2026-01-01 00:00:00"}]';

        MXRoute_Logger::append_history( 10, array( 'event' => 'requeue' ) );

        $updates = $GLOBALS['wp_function_calls']['$wpdb->update'] ?? array();
        $last    = end( $updates );
        $events  = json_decode( $last['data']['history'], true );

        $this->assertCount( 2, $events );
        $this->assertEquals( 'queued', $events[0]['event'] );
        $this->assertEquals( 'requeue', $events[1]['event'] );
        $this->assertNotEmpty( $events[1]['at'] );
    }

    /**
     * Tests that append_history starts fresh when stored history is invalid JSON.
     */
    public function test_append_history_resets_invalid_json() {
        $GLOBALS['wp_db_var'] = 'not-valid-json';

        MXRoute_Logger::append_history( 11, array( 'event' => 'attempt', 'success' => true ) );

        $updates = $GLOBALS['wp_function_calls']['$wpdb->update'] ?? array();
        $last    = end( $updates );
        $events  = json_decode( $last['data']['history'], true );

        $this->assertCount( 1, $events );
        $this->assertEquals( 'attempt', $events[0]['event'] );
    }

    /**
     * Tests that append_history ignores invalid IDs.
     */
    public function test_append_history_ignores_invalid_id() {
        MXRoute_Logger::append_history( 0, array( 'event' => 'requeue' ) );

        $this->assertEmpty( $GLOBALS['wp_function_calls']['$wpdb->update'] ?? array() );
    }
}
