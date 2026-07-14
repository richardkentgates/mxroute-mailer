<?php
/**
 * Tests for MXRoute_Settings class
 */
class MXRoute_Settings_Test extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        $GLOBALS['wp_options'] = array();
        $GLOBALS['wp_function_calls'] = array();
    }

    public function test_constructor_registers_hooks() {
        $settings = new MXRoute_Settings();

        $this->assertArrayHasKey('add_action', $GLOBALS['wp_function_calls']);
        $hooks = array_column($GLOBALS['wp_function_calls']['add_action'], 'hook');
        $this->assertContains('admin_menu', $hooks);
        $this->assertContains('admin_init', $hooks);
        $this->assertContains('admin_enqueue_scripts', $hooks);
    }

    public function test_register_settings_registers_all_options() {
        $settings = new MXRoute_Settings();
        $settings->register_settings();

        $calls = $GLOBALS['wp_function_calls']['register_setting'];
        $option_names = array_column($calls, 'option_name');

        $this->assertContains('mxroute_mailer_server', $option_names);
        $this->assertContains('mxroute_mailer_username', $option_names);
        $this->assertContains('mxroute_mailer_password', $option_names);
        $this->assertContains('mxroute_mailer_default_from', $option_names);
        $this->assertContains('mxroute_mailer_logging_enabled', $option_names);
        $this->assertContains('mxroute_mailer_keep_data', $option_names);
    }

    public function test_register_settings_groups_all_under_mxroute_mailer_settings() {
        $settings = new MXRoute_Settings();
        $settings->register_settings();

        $calls = $GLOBALS['wp_function_calls']['register_setting'];
        foreach ($calls as $call) {
            $this->assertEquals('mxroute_mailer_settings', $call['option_group']);
        }
    }

    public function test_sanitize_checkbox_returns_1_for_truthy() {
        $settings = new MXRoute_Settings();
        $this->assertEquals(1, $settings->sanitize_checkbox(true));
        $this->assertEquals(1, $settings->sanitize_checkbox(1));
        $this->assertEquals(1, $settings->sanitize_checkbox('yes'));
    }

    public function test_sanitize_checkbox_returns_0_for_falsy() {
        $settings = new MXRoute_Settings();
        $this->assertEquals(0, $settings->sanitize_checkbox(false));
        $this->assertEquals(0, $settings->sanitize_checkbox(0));
        $this->assertEquals(0, $settings->sanitize_checkbox(''));
        $this->assertEquals(0, $settings->sanitize_checkbox(null));
    }

    public function test_enqueue_assets_returns_early_on_wrong_hook() {
        $settings = new MXRoute_Settings();
        $settings->enqueue_assets('post.php');

        $this->assertEmpty($GLOBALS['wp_function_calls']['wp_enqueue_style'] ?? array());
        $this->assertEmpty($GLOBALS['wp_function_calls']['wp_enqueue_script'] ?? array());
    }

    public function test_enqueue_assets_loads_on_settings_page() {
        $settings = new MXRoute_Settings();
        $settings->enqueue_assets('settings_page_mxroute-mailer');

        $this->assertArrayHasKey('wp_enqueue_style', $GLOBALS['wp_function_calls']);
        $this->assertArrayHasKey('wp_enqueue_script', $GLOBALS['wp_function_calls']);
    }

    public function test_enqueue_assets_loads_on_logs_page() {
        $settings = new MXRoute_Settings();
        $settings->enqueue_assets('tools_page_mxroute-logs');

        $this->assertArrayHasKey('wp_enqueue_style', $GLOBALS['wp_function_calls']);
        $this->assertArrayHasKey('wp_enqueue_script', $GLOBALS['wp_function_calls']);
    }

    public function test_enqueue_assets_localizes_ajax_url() {
        $settings = new MXRoute_Settings();
        $settings->enqueue_assets('settings_page_mxroute-mailer');

        $calls = $GLOBALS['wp_function_calls']['wp_localize_script'];
        $this->assertCount(1, $calls);
        $this->assertEquals('mxroute-mailer-admin', $calls[0]['handle']);
        $this->assertEquals('mxrouteMailer', $calls[0]['object_name']);
        $this->assertArrayHasKey('ajaxUrl', $calls[0]['l10n']);
        $this->assertArrayHasKey('nonce', $calls[0]['l10n']);
    }

    public function test_enqueue_assets_uses_plugin_version() {
        $settings = new MXRoute_Settings();
        $settings->enqueue_assets('settings_page_mxroute-mailer');

        $style_calls = $GLOBALS['wp_function_calls']['wp_enqueue_style'];
        $this->assertEquals(MXROUTE_MAILER_VERSION, $style_calls[0]['ver']);
    }

    public function test_add_menu_pages_calls_add_options_page() {
        $settings = new MXRoute_Settings();
        $settings->add_menu_pages();

        $this->assertArrayHasKey('add_options_page', $GLOBALS['wp_function_calls']);
        $call = $GLOBALS['wp_function_calls']['add_options_page'][0];
        $this->assertEquals('MXRoute Mailer', $call['page_title']);
        $this->assertEquals('manage_options', $call['capability']);
        $this->assertEquals('mxroute-mailer', $call['menu_slug']);
    }

    public function test_add_menu_pages_calls_add_management_page() {
        $settings = new MXRoute_Settings();
        $settings->add_menu_pages();

        $this->assertArrayHasKey('add_management_page', $GLOBALS['wp_function_calls']);
        $call = $GLOBALS['wp_function_calls']['add_management_page'][0];
        $this->assertEquals('MXRoute Email Logs', $call['page_title']);
        $this->assertEquals('mxroute-logs', $call['menu_slug']);
    }
}
