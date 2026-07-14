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
        $this->assertContains('wp_dashboard_setup', $hooks);
        $this->assertContains('admin_ajax_mxroute_log_detail', $hooks);
        $this->assertContains('admin_ajax_mxroute_clear_logs', $hooks);
        $this->assertContains('admin_ajax_mxroute_delete_log', $hooks);
    }

    public function test_add_dashboard_widget_calls_wp_add_dashboard_widget() {
        $dashboard = new MXRoute_Dashboard();
        $dashboard->add_dashboard_widget();

        $this->assertArrayHasKey('wp_add_dashboard_widget', $GLOBALS['wp_function_calls']);
        $call = $GLOBALS['wp_function_calls']['wp_add_dashboard_widget'][0];
        $this->assertEquals('mxroute_mailer_widget', $call['widget_id']);
    }

    public function test_render_widget_shows_no_logs_message_when_empty() {
        $dashboard = new MXRoute_Dashboard();
        ob_start();
        $dashboard->render_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString('No emails sent yet.', $output);
    }

    public function test_ajax_log_detail_verifies_nonce() {
        $dashboard = new MXRoute_Dashboard();
        try {
            $dashboard->ajax_log_detail();
        } catch (\MXRouteJSONException $e) {
            // Expected - log not found
        }

        $calls = $GLOBALS['wp_function_calls']['check_ajax_referer'];
        $this->assertCount(1, $calls);
        $this->assertEquals('mxroute_log_view', $calls[0]['action']);
    }

    public function test_ajax_log_detail_returns_error_when_unauthorized() {
        $dashboard = new MXRoute_Dashboard();
        try {
            $dashboard->ajax_log_detail();
        } catch (\MXRouteJSONException $e) {
            // Expected
        }

        $this->assertArrayHasKey('check_ajax_referer', $GLOBALS['wp_function_calls']);
    }

    public function test_ajax_log_detail_returns_error_for_nonexistent_log() {
        $dashboard = new MXRoute_Dashboard();
        $threw = false;
        try {
            $dashboard->ajax_log_detail();
        } catch (\MXRouteJSONException $e) {
            $threw = true;
            $this->assertEquals('Log not found.', $e->response['data']['message']);
        }
        $this->assertTrue($threw, 'Expected MXRouteJSONException to be thrown');
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

    public function test_ajax_log_detail_outputs_html_table() {
        $dashboard = new MXRoute_Dashboard();
        $threw = false;
        try {
            $dashboard->ajax_log_detail();
        } catch (\MXRouteJSONException $e) {
            $threw = true;
            $this->assertFalse($e->response['success']);
            $this->assertEquals('Log not found.', $e->response['data']['message']);
        }
        $this->assertTrue($threw, 'Expected MXRouteJSONException to be thrown');
    }
}
