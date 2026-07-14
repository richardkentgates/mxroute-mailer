<?php
/**
 * Tests for MXRoute_Dashboard class
 */
class MXRoute_Dashboard_Test extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        $GLOBALS['wp_options'] = array();
        $GLOBALS['wp_function_calls'] = array();
        $GLOBALS['wp_db_inserts'] = array();
        $GLOBALS['wp_db_queries'] = array();
    }

    public function test_constructor_registers_hooks() {
        $dashboard = new MXRoute_Dashboard();

        $hooks = array_column($GLOBALS['wp_function_calls']['add_action'], 'hook');
        $this->assertContains('admin_ajax_mxroute_clear_logs', $hooks);
        $this->assertContains('admin_ajax_mxroute_delete_log', $hooks);
        $this->assertContains('admin_ajax_mxroute_bulk_delete_logs', $hooks);
    }

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
}
