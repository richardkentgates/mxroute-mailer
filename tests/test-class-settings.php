<?php
/**
 * Tests for MXRoute_Settings class.
 *
 * @package MXRoute_Mailer
 */
class MXRoute_Settings_Test extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        $GLOBALS['wp_options'] = array();
        $GLOBALS['wp_function_calls'] = array();
    }

    /**
     * Tests that the constructor registers admin_menu, admin_init, and admin_enqueue_scripts hooks.
     */
    public function test_constructor_registers_hooks() {
        $settings = new MXRoute_Settings();

        $this->assertArrayHasKey('add_action', $GLOBALS['wp_function_calls']);
        $hooks = array_column($GLOBALS['wp_function_calls']['add_action'], 'hook');
        $this->assertContains('admin_menu', $hooks);
        $this->assertContains('admin_init', $hooks);
        $this->assertContains('admin_enqueue_scripts', $hooks);
    }

    /**
     * Tests that register_settings registers all expected option names.
     */
    public function test_register_settings_registers_all_options() {
        $settings = new MXRoute_Settings();
        $settings->register_settings();

        $calls = $GLOBALS['wp_function_calls']['register_setting'];
        $option_names = array_column($calls, 'option_name');

        $this->assertContains('mxroute_mailer_server', $option_names);
        $this->assertContains('mxroute_mailer_username', $option_names);
        $this->assertContains('mxroute_mailer_password', $option_names);
        $this->assertContains('mxroute_mailer_logging_enabled', $option_names);
        $this->assertContains('mxroute_mailer_keep_data', $option_names);
    }

    /**
     * Tests that all settings are grouped under the mxroute_mailer_settings option group.
     */
    public function test_register_settings_groups_all_under_mxroute_mailer_settings() {
        $settings = new MXRoute_Settings();
        $settings->register_settings();

        $calls = $GLOBALS['wp_function_calls']['register_setting'];
        foreach ($calls as $call) {
            $this->assertEquals('mxroute_mailer_settings', $call['option_group']);
        }
    }

    /**
     * Tests that sanitize_checkbox returns 1 for truthy values.
     */
    public function test_sanitize_checkbox_returns_1_for_truthy() {
        $settings = new MXRoute_Settings();
        $this->assertEquals(1, $settings->sanitize_checkbox(true));
        $this->assertEquals(1, $settings->sanitize_checkbox(1));
        $this->assertEquals(1, $settings->sanitize_checkbox('yes'));
    }

    /**
     * Tests that sanitize_checkbox returns 0 for falsy values.
     */
    public function test_sanitize_checkbox_returns_0_for_falsy() {
        $settings = new MXRoute_Settings();
        $this->assertEquals(0, $settings->sanitize_checkbox(false));
        $this->assertEquals(0, $settings->sanitize_checkbox(0));
        $this->assertEquals(0, $settings->sanitize_checkbox(''));
        $this->assertEquals(0, $settings->sanitize_checkbox(null));
    }

    /**
     * Tests that enqueue_assets does not load assets on non-plugin pages.
     */
    public function test_enqueue_assets_returns_early_on_wrong_hook() {
        $settings = new MXRoute_Settings();
        $settings->enqueue_assets('post.php');

        $this->assertEmpty($GLOBALS['wp_function_calls']['wp_enqueue_style'] ?? array());
        $this->assertEmpty($GLOBALS['wp_function_calls']['wp_enqueue_script'] ?? array());
    }

    /**
     * Tests that enqueue_assets loads CSS on the settings page.
     */
    public function test_enqueue_assets_loads_css_on_settings_page() {
        $settings = new MXRoute_Settings();
        $settings->enqueue_assets('settings_page_mxroute-mailer');

        $this->assertArrayHasKey('wp_enqueue_style', $GLOBALS['wp_function_calls']);
        $this->assertEmpty($GLOBALS['wp_function_calls']['wp_enqueue_script'] ?? array());
    }

    /**
     * Tests that enqueue_assets loads both CSS and JS on the logs page.
     */
    public function test_enqueue_assets_loads_on_logs_page() {
        $settings = new MXRoute_Settings();
        $settings->enqueue_assets('tools_page_mxroute-logs');

        $this->assertArrayHasKey('wp_enqueue_style', $GLOBALS['wp_function_calls']);
        $this->assertArrayHasKey('wp_enqueue_script', $GLOBALS['wp_function_calls']);
    }

    /**
     * Tests that enqueue_assets localizes script data on the logs page.
     */
    public function test_enqueue_assets_localizes_on_logs_page() {
        $settings = new MXRoute_Settings();
        $settings->enqueue_assets('tools_page_mxroute-logs');

        $calls = $GLOBALS['wp_function_calls']['wp_localize_script'];
        $this->assertCount(1, $calls);
        $this->assertEquals('mxroute-mailer-admin', $calls[0]['handle']);
        $this->assertEquals('mxrouteMailer', $calls[0]['object_name']);
        $this->assertArrayHasKey('ajaxUrl', $calls[0]['l10n']);
        $this->assertArrayHasKey('logManageNonce', $calls[0]['l10n']);
        $this->assertArrayHasKey('i18n', $calls[0]['l10n']);
    }

    /**
     * Tests that enqueue_assets uses the plugin version for cache busting.
     */
    public function test_enqueue_assets_uses_plugin_version() {
        $settings = new MXRoute_Settings();
        $settings->enqueue_assets('settings_page_mxroute-mailer');

        $style_calls = $GLOBALS['wp_function_calls']['wp_enqueue_style'];
        $this->assertEquals(MXROUTE_MAILER_VERSION, $style_calls[0]['ver']);
    }

    /**
     * Tests that add_menu_pages registers the MXRoute Mailer options page.
     */
    public function test_add_menu_pages_calls_add_options_page() {
        $settings = new MXRoute_Settings();
        $settings->add_menu_pages();

        $this->assertArrayHasKey('add_options_page', $GLOBALS['wp_function_calls']);
        $call = $GLOBALS['wp_function_calls']['add_options_page'][0];
        $this->assertEquals('MXRoute Mailer', $call['page_title']);
        $this->assertEquals('manage_options', $call['capability']);
        $this->assertEquals('mxroute-mailer', $call['menu_slug']);
    }

    /**
     * Tests that sanitize_password encrypts a plaintext password.
     */
    public function test_sanitize_password_encrypts_plaintext() {
        $settings = new MXRoute_Settings();
        $plain = 'my_secret_password';
        $encrypted = $settings->sanitize_password($plain);

        $this->assertNotEquals($plain, $encrypted);
        $this->assertEquals($plain, MXRoute_Crypto::decrypt($encrypted));
    }

    /**
     * Tests that sanitize_password returns empty string when no value is provided.
     */
    public function test_sanitize_password_returns_empty_when_empty() {
        $settings = new MXRoute_Settings();
        $result = $settings->sanitize_password('');
        $this->assertEquals('', $result);
    }

    /**
     * Tests that the test email form queues the email for processing.
     */
    public function test_test_email_form_queues_email() {
        $GLOBALS['wp_options']['mxroute_mailer_server'] = 'server.example.com';
        $GLOBALS['wp_options']['mxroute_mailer_username'] = 'from@example.com';
        $GLOBALS['wp_options']['mxroute_mailer_password'] = MXRoute_Crypto::encrypt('test_form_secret');
        $_POST['mxroute_test_email_nonce'] = wp_create_nonce('mxroute_test_email');
        $_POST['mxroute_test_to'] = 'to@example.com';
        $_POST['mxroute_test_subject'] = 'Subject';
        $_POST['mxroute_test_body'] = 'Body';

        $mailer = MXRoute_Mailer::instance();
        $mailer->handle_test_email();

        $result = $GLOBALS['wp_transients']['mxroute_test_email_result'];
        $this->assertTrue($result['success']);
        $this->assertTrue($result['queued']);
        $this->assertNotEmpty($GLOBALS['wp_db_inserts']);

        unset($_POST['mxroute_test_email_nonce']);
        unset($_POST['mxroute_test_to']);
        unset($_POST['mxroute_test_subject']);
        unset($_POST['mxroute_test_body']);
    }

    /**
     * Tests that add_menu_pages registers the email logs management page.
     */
    public function test_add_menu_pages_calls_add_management_page() {
        $settings = new MXRoute_Settings();
        $settings->add_menu_pages();

        $this->assertArrayHasKey('add_management_page', $GLOBALS['wp_function_calls']);
        $call = $GLOBALS['wp_function_calls']['add_management_page'][0];
        $this->assertEquals('MXRoute Email Logs', $call['page_title']);
        $this->assertEquals('mxroute-logs', $call['menu_slug']);
    }

    /**
     * Tests that sanitize_batch_size clamps values below 1 to 1.
     */
    public function test_sanitize_batch_size_clamps_below_minimum() {
        $settings = new MXRoute_Settings();
        $this->assertEquals( 1, $settings->sanitize_batch_size( 0 ) );
        $this->assertEquals( 1, $settings->sanitize_batch_size( -5 ) );
    }

    /**
     * Tests that sanitize_batch_size clamps values above 50 to 50.
     */
    public function test_sanitize_batch_size_clamps_above_maximum() {
        $settings = new MXRoute_Settings();
        $this->assertEquals( 50, $settings->sanitize_batch_size( 51 ) );
        $this->assertEquals( 50, $settings->sanitize_batch_size( 9999 ) );
    }

    /**
     * Tests that sanitize_batch_size passes valid values through.
     */
    public function test_sanitize_batch_size_passes_valid_values() {
        $settings = new MXRoute_Settings();
        $this->assertEquals( 1, $settings->sanitize_batch_size( 1 ) );
        $this->assertEquals( 25, $settings->sanitize_batch_size( 25 ) );
        $this->assertEquals( 50, $settings->sanitize_batch_size( 50 ) );
    }

    /**
     * Tests that sanitize_batch_size converts non-integer input.
     */
    public function test_sanitize_batch_size_casts_non_integer() {
        $settings = new MXRoute_Settings();
        $this->assertEquals( 25, $settings->sanitize_batch_size( '25abc' ) );
        $this->assertEquals( 1, $settings->sanitize_batch_size( 'abc' ) );
    }

    /**
     * Tests that sanitize_username_local strips the domain and uses home_url host.
     */
    public function test_sanitize_username_local_strips_domain() {
        $settings = new MXRoute_Settings();
        $result = $settings->sanitize_username_local( 'user@example.com' );
        $this->assertEquals( 'user@example.com', $result );
    }

    /**
     * Tests that sanitize_username_local handles local part only input.
     */
    public function test_sanitize_username_local_handles_local_part_only() {
        $settings = new MXRoute_Settings();
        $result = $settings->sanitize_username_local( 'user' );
        $this->assertEquals( 'user@example.com', $result );
    }

    /**
     * Tests that sanitize_username_local strips angle brackets and extra parts.
     */
    public function test_sanitize_username_local_strips_angle_brackets() {
        $settings = new MXRoute_Settings();
        $result = $settings->sanitize_username_local( 'User <user@example.com>' );
        $this->assertStringNotContainsString( '<', $result );
        $this->assertStringNotContainsString( '>', $result );
    }
}
