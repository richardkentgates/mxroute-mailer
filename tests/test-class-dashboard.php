<?php
/**
 * Tests for MXRoute_Dashboard class.
 *
 * @package MXRoute_Mailer
 */
class MXRoute_Dashboard_Test extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        $GLOBALS['wp_options'] = array();
        $GLOBALS['wp_function_calls'] = array();
        $GLOBALS['wp_db_inserts'] = array();
        $GLOBALS['wp_db_queries'] = array();
    }

    /**
     * Tests that the constructor registers the expected AJAX action hooks.
     */
    public function test_constructor_registers_hooks() {
        $dashboard = new MXRoute_Dashboard();

        $hooks = array_column($GLOBALS['wp_function_calls']['add_action'], 'hook');
        $this->assertContains('admin_ajax_mxroute_clear_logs', $hooks);
        $this->assertContains('admin_ajax_mxroute_delete_log', $hooks);
        $this->assertContains('admin_ajax_mxroute_bulk_delete_logs', $hooks);
        $this->assertContains('admin_ajax_mxroute_requeue_log', $hooks);
        $this->assertContains('admin_ajax_mxroute_bulk_requeue_logs', $hooks);
        $this->assertContains('admin_ajax_mxroute_add_to_queue', $hooks);
    }

    /**
     * Tests that ajax_clear_logs verifies the nonce before proceeding.
     */
    public function test_ajax_clear_logs_verifies_nonce() {
        $dashboard = new MXRoute_Dashboard();
        try {
            $dashboard->ajax_clear_logs();
        } catch (\MXRouteJSONException $e) {
            // Expected
        }

        $calls = $GLOBALS['wp_function_calls']['check_ajax_referer'];
        $this->assertCount(1, $calls);
        $this->assertEquals('mxroute_log_manage', $calls[0]['action']);
    }

    /**
     * Tests that ajax_clear_logs sends a success JSON response.
     */
    public function test_ajax_clear_logs_sends_success() {
        $dashboard = new MXRoute_Dashboard();
        $threw = false;
        try {
            $dashboard->ajax_clear_logs();
        } catch (\MXRouteJSONException $e) {
            $threw = true;
            $this->assertTrue($e->response['success']);
        }
        $this->assertTrue($threw, 'Expected MXRouteJSONException to be thrown');
    }

    /**
     * Tests that ajax_delete_log verifies the nonce before proceeding.
     */
    public function test_ajax_delete_log_verifies_nonce() {
        $dashboard = new MXRoute_Dashboard();
        try {
            $dashboard->ajax_delete_log();
        } catch (\MXRouteJSONException $e) {
            // Expected
        }

        $calls = $GLOBALS['wp_function_calls']['check_ajax_referer'];
        $this->assertCount(1, $calls);
        $this->assertEquals('mxroute_log_manage', $calls[0]['action']);
    }

    /**
     * Tests that ajax_delete_log sends a success JSON response.
     */
    public function test_ajax_delete_log_sends_success() {
        $dashboard = new MXRoute_Dashboard();
        $threw = false;
        try {
            $dashboard->ajax_delete_log();
        } catch (\MXRouteJSONException $e) {
            $threw = true;
            $this->assertTrue($e->response['success']);
        }
        $this->assertTrue($threw, 'Expected MXRouteJSONException to be thrown');
    }

    /**
     * Tests that ajax_bulk_delete_logs verifies the nonce before proceeding.
     */
    public function test_ajax_bulk_delete_logs_verifies_nonce() {
        $dashboard = new MXRoute_Dashboard();
        try {
            $dashboard->ajax_bulk_delete_logs();
        } catch (\MXRouteJSONException $e) {
            // Expected
        }

        $calls = $GLOBALS['wp_function_calls']['check_ajax_referer'];
        $this->assertCount(1, $calls);
        $this->assertEquals('mxroute_log_manage', $calls[0]['action']);
    }

    /**
     * Tests that ajax_bulk_delete_logs sends an error when no log IDs are provided.
     */
    public function test_ajax_bulk_delete_logs_sends_error_when_no_ids() {
        $dashboard = new MXRoute_Dashboard();
        $threw = false;
        try {
            $dashboard->ajax_bulk_delete_logs();
        } catch (\MXRouteJSONException $e) {
            $threw = true;
            $this->assertFalse($e->response['success']);
        }
        $this->assertTrue($threw, 'Expected MXRouteJSONException to be thrown');
    }

    /**
     * Tests that ajax_bulk_delete_logs sends a success response when valid IDs are provided.
     */
    public function test_ajax_bulk_delete_logs_sends_success_with_ids() {
        $GLOBALS['wp_function_calls']['wp_create_nonce'] = array( array( 'result' => 'nonce' ) );
        $_POST['log_ids'] = array( 1, 2, 3 );

        $dashboard = new MXRoute_Dashboard();
        $threw = false;
        try {
            $dashboard->ajax_bulk_delete_logs();
        } catch (\MXRouteJSONException $e) {
            $threw = true;
            $this->assertTrue($e->response['success']);
        }
        $this->assertTrue($threw, 'Expected MXRouteJSONException to be thrown');

        unset( $_POST['log_ids'] );
    }

    /**
     * Tests that ajax_requeue_log verifies the nonce before proceeding.
     */
    public function test_ajax_requeue_log_verifies_nonce() {
        $dashboard = new MXRoute_Dashboard();
        try {
            $dashboard->ajax_requeue_log();
        } catch (\MXRouteJSONException $e) {
            // Expected
        }

        $calls = $GLOBALS['wp_function_calls']['check_ajax_referer'];
        $this->assertCount(1, $calls);
        $this->assertEquals('mxroute_log_manage', $calls[0]['action']);
    }

    /**
     * Tests that ajax_requeue_log sends an error when log_id is invalid.
     */
    public function test_ajax_requeue_log_sends_error_when_invalid_id() {
        $dashboard = new MXRoute_Dashboard();
        $threw = false;
        try {
            $dashboard->ajax_requeue_log();
        } catch (\MXRouteJSONException $e) {
            $threw = true;
            $this->assertFalse($e->response['success']);
        }
        $this->assertTrue($threw, 'Expected MXRouteJSONException to be thrown');
    }

    /**
     * Tests that ajax_bulk_requeue_logs verifies the nonce before proceeding.
     */
    public function test_ajax_bulk_requeue_logs_verifies_nonce() {
        $dashboard = new MXRoute_Dashboard();
        try {
            $dashboard->ajax_bulk_requeue_logs();
        } catch (\MXRouteJSONException $e) {
            // Expected
        }

        $calls = $GLOBALS['wp_function_calls']['check_ajax_referer'];
        $this->assertCount(1, $calls);
        $this->assertEquals('mxroute_log_manage', $calls[0]['action']);
    }

    /**
     * Tests that ajax_bulk_requeue_logs sends an error when no log IDs are provided.
     */
    public function test_ajax_bulk_requeue_logs_sends_error_when_no_ids() {
        $dashboard = new MXRoute_Dashboard();
        $threw = false;
        try {
            $dashboard->ajax_bulk_requeue_logs();
        } catch (\MXRouteJSONException $e) {
            $threw = true;
            $this->assertFalse($e->response['success']);
        }
        $this->assertTrue($threw, 'Expected MXRouteJSONException to be thrown');
    }

    /**
     * Tests that ajax_bulk_requeue_logs sends a success response when valid IDs are provided.
     */
    public function test_ajax_bulk_requeue_logs_sends_success_with_ids() {
        $_POST['log_ids'] = array( 1, 2, 3 );

        $dashboard = new MXRoute_Dashboard();
        $threw = false;
        try {
            $dashboard->ajax_bulk_requeue_logs();
        } catch (\MXRouteJSONException $e) {
            $threw = true;
            $this->assertTrue($e->response['success']);
        }
        $this->assertTrue($threw, 'Expected MXRouteJSONException to be thrown');

        unset( $_POST['log_ids'] );
    }

    /**
     * Tests that ajax_add_to_queue verifies the nonce before proceeding.
     */
    public function test_ajax_add_to_queue_verifies_nonce() {
        $dashboard = new MXRoute_Dashboard();
        try {
            $dashboard->ajax_add_to_queue();
        } catch (\MXRouteJSONException $e) {
            // Expected
        }

        $calls = $GLOBALS['wp_function_calls']['check_ajax_referer'];
        $this->assertCount(1, $calls);
        $this->assertEquals('mxroute_log_manage', $calls[0]['action']);
    }

    /**
     * Tests that ajax_add_to_queue sends an error when fields are missing.
     */
    public function test_ajax_add_to_queue_sends_error_when_missing_fields() {
        $dashboard = new MXRoute_Dashboard();
        $threw = false;
        try {
            $dashboard->ajax_add_to_queue();
        } catch (\MXRouteJSONException $e) {
            $threw = true;
            $this->assertFalse($e->response['success']);
        }
        $this->assertTrue($threw, 'Expected MXRouteJSONException to be thrown');
    }

    /**
     * Tests that ajax_add_to_queue sends a success response when valid data is provided.
     */
    public function test_ajax_add_to_queue_sends_success_with_valid_data() {
        $_POST['from_email'] = 'from@example.com';
        $_POST['to_email']   = 'to@example.com';
        $_POST['subject']    = 'Test Subject';
        $_POST['message']    = 'Test body content';

        $dashboard = new MXRoute_Dashboard();
        $threw = false;
        try {
            $dashboard->ajax_add_to_queue();
        } catch (\MXRouteJSONException $e) {
            $threw = true;
            $this->assertTrue($e->response['success']);
        }
        $this->assertTrue($threw, 'Expected MXRouteJSONException to be thrown');

        unset( $_POST['from_email'], $_POST['to_email'], $_POST['subject'], $_POST['message'] );
    }
}
