<?php
/**
 * Tests for MXRoute_Abilities (AI phase A2) and MXRoute_Queue::requeue_failed.
 *
 * Contract under test:
 * - the three abilities register with execute_callback + permission_callback
 *   (the A0 regression shape), category registered first
 * - queue-status reports the counts the status writer reports + last errors
 * - requeue-failed moves failed rows back to pending and NEVER touches
 *   rows in flight (the claim guard)
 * - send-test requires its configuration and uses the existing transport
 *
 * @package MXRoute_Mailer
 */

/** MockWPDB that answers the three count queries distinctly. */
class QueueAwareWPDB extends MockWPDB {
    public function get_var( $query = null, $x = 0, $y = 0 ) {
        if ( strpos( (string) $query, 'success = -1' ) !== false ) {
            return 3;
        }
        if ( strpos( (string) $query, 'success = 1' ) !== false ) {
            return 7;
        }
        return 2;
    }
}

class MXRoute_Abilities_Test extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        $GLOBALS['wp_options'] = array();
        $GLOBALS['wp_function_calls'] = array();
        $GLOBALS['wp_current_user_can_value'] = true;
        $GLOBALS['wp_registered_abilities'] = array();
        $GLOBALS['wp_registered_ability_categories'] = array();
        $GLOBALS['wpdb'] = new MockWPDB();
    }

    // ------------------------------------------------------------------
    // Registration contract
    // ------------------------------------------------------------------

    public function test_registers_all_three_with_callbacks_and_category() {
        $a = new MXRoute_Abilities();
        $a->register_categories();
        $a->register_abilities();

        $this->assertArrayHasKey( 'site', $GLOBALS['wp_registered_ability_categories'] );

        foreach ( array( 'mxroute/queue-status', 'mxroute/requeue-failed', 'mxroute/send-test' ) as $name ) {
            $this->assertArrayHasKey( $name, $GLOBALS['wp_registered_abilities'], "$name must be registered" );
            $args = $GLOBALS['wp_registered_abilities'][ $name ];
            $this->assertIsCallable( $args['execute_callback'], "$name execute_callback" );
            $this->assertIsCallable( $args['permission_callback'], "$name permission_callback" );
            $this->assertEquals( 'site', $args['category'], "$name category" );
        }
    }

    public function test_permission_is_admin_only() {
        $a = new MXRoute_Abilities();
        $GLOBALS['wp_current_user_can_value'] = true;
        $this->assertTrue( $a->can_use() );

        $GLOBALS['wp_current_user_can_value'] = false;
        $result = $a->can_use();
        $this->assertInstanceOf( 'WP_Error', $result );
    }

    // ------------------------------------------------------------------
    // mxroute/queue-status
    // ------------------------------------------------------------------

    public function test_queue_status_reports_counts_and_last_errors() {
        $GLOBALS['wpdb'] = new QueueAwareWPDB();
        $GLOBALS['wp_db_results'] = array(
            (object) array(
                'id' => 9,
                'to_email' => 'client@example.test',
                'subject' => 'Invoice',
                'timestamp' => '2026-11-11 12:00:00',
                'api_response' => '  550 invalid recipient  ',
            ),
        );

        $result = ( new MXRoute_Abilities() )->ability_queue_status();

        $this->assertEquals( 2, $result['pending'] );
        $this->assertEquals( 7, $result['sent'] );
        $this->assertEquals( 3, $result['failed'] );
        $this->assertCount( 1, $result['errors'] );
        $this->assertEquals( 9, $result['errors'][0]['id'] );
        $this->assertEquals( 'client@example.test', $result['errors'][0]['to'] );
        $this->assertEquals( '550 invalid recipient', $result['errors'][0]['error'] );
    }

    // ------------------------------------------------------------------
    // mxroute/requeue-failed — the claim-aware bulk requeue
    // ------------------------------------------------------------------

    public function test_requeue_failed_moves_failed_rows_and_is_claim_aware() {
        $GLOBALS['wp_db_col'] = array( 5, 6 );
        $GLOBALS['wp_db_affected_rows'] = 1;

        $count = ( new MXRoute_Abilities() )->ability_requeue_failed();

        $this->assertEquals( 2, $count['requeued'] );
        $queries = implode( "\n", $GLOBALS['wp_db_queries'] ?? array() );
        $this->assertStringContainsString( 'UPDATE', $queries );
        // The claim guard: in-flight rows (processed, success = 0) must
        // never be requeued.
        $this->assertStringContainsString( 'processed_at IS NULL OR success != 0', $queries );
        // Actor recorded in the row history.
        $this->assertStringContainsString( 'ability:mxroute/requeue-failed', $queries );
    }

    public function test_requeue_failed_with_explicit_ids() {
        $GLOBALS['wp_db_affected_rows'] = 1;
        $result = ( new MXRoute_Abilities() )->ability_requeue_failed( array( 'ids' => array( 5, 6, 7 ) ) );
        $this->assertEquals( 3, $result['requeued'] );
    }

    public function test_requeue_failed_with_nothing_to_do() {
        $GLOBALS['wp_db_col'] = array();
        $result = ( new MXRoute_Abilities() )->ability_requeue_failed();
        $this->assertEquals( 0, $result['requeued'] );
        $this->assertEmpty( $GLOBALS['wp_db_queries'] ?? array() );
    }

    public function test_requeue_failed_skips_in_flight_rows() {
        // rows_affected = 0 means the claim guard rejected the row
        // (it was in flight) — the count must reflect that.
        $GLOBALS['wp_db_col'] = array( 5 );
        $GLOBALS['wp_db_affected_rows'] = 0;
        $result = ( new MXRoute_Abilities() )->ability_requeue_failed();
        $this->assertEquals( 0, $result['requeued'] );
    }

    // ------------------------------------------------------------------
    // mxroute/send-test
    // ------------------------------------------------------------------

    public function test_send_test_requires_recipient() {
        $result = ( new MXRoute_Abilities() )->ability_send_test( array() );
        $this->assertInstanceOf( 'WP_Error', $result );
        $this->assertEquals( 'invalid_input', $result->get_error_code() );
    }

    public function test_send_test_requires_configured_sender() {
        $GLOBALS['wp_options'] = array();
        $result = ( new MXRoute_Abilities() )->ability_send_test( array( 'to' => 'client@example.test' ) );
        $this->assertInstanceOf( 'WP_Error', $result );
        $this->assertEquals( 'missing_config', $result->get_error_code() );
    }

    public function test_send_test_sends_through_the_configured_transport() {
        $GLOBALS['wp_options'] = array( 'mxroute_mailer_username' => 'no-reply@example.test' );

        $result = ( new MXRoute_Abilities() )->ability_send_test( array(
            'to'      => 'client@example.test',
            'subject' => 'Test',
            'body'    => 'Hello',
        ) );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['sent'] );
        $this->assertEquals( 'client@example.test', $result['to'] );
        $this->assertEquals( 'no-reply@example.test', $result['from'] );
        $this->assertArrayHasKey( 'wp_remote_post', $GLOBALS['wp_function_calls'] );
    }
}
