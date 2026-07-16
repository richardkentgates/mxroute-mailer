<?php
/**
 * Tests for MXRoute_Mailer main class.
 *
 * @package MXRoute_Mailer
 */
class MXRoute_Mailer_Test extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        $GLOBALS['wp_options'] = array();
        $GLOBALS['wp_function_calls'] = array();
        $GLOBALS['wp_transients'] = array();
        $GLOBALS['wp_db_inserts'] = array();
        $GLOBALS['wp_db_queries'] = array();
    }

    /**
     * Tests that instance() returns the same singleton object.
     */
    public function test_instance_returns_singleton() {
        $a = MXRoute_Mailer::instance();
        $b = MXRoute_Mailer::instance();
        $this->assertSame($a, $b);
    }

    /**
     * Tests that intercept_wp_mail returns null when no "to" is provided.
     */
    public function test_intercept_wp_mail_returns_null_when_no_to() {
        $mailer = MXRoute_Mailer::instance();
        $args = array(
            'to'      => '',
            'subject' => 'Test',
            'message' => 'Body',
        );
        $result = $mailer->intercept_wp_mail($args);
        $this->assertNull($result);
    }

    /**
     * Tests that intercept_wp_mail returns null when subject is empty.
     */
    public function test_intercept_wp_mail_returns_null_when_no_subject() {
        $mailer = MXRoute_Mailer::instance();
        $args = array(
            'to'      => 'to@example.com',
            'subject' => '',
            'message' => 'Body',
        );
        $result = $mailer->intercept_wp_mail($args);
        $this->assertNull($result);
    }

    /**
     * Tests that intercept_wp_mail returns null when both "to" and subject are missing.
     */
    public function test_intercept_wp_mail_returns_null_when_both_missing() {
        $mailer = MXRoute_Mailer::instance();
        $args = array(
            'to'      => '',
            'subject' => '',
            'message' => 'Body',
        );
        $result = $mailer->intercept_wp_mail($args);
        $this->assertNull($result);
    }

    /**
     * Tests that intercept_wp_mail returns null when required fields are missing.
     */
    public function test_intercept_wp_mail_returns_null_when_fields_missing() {
        $mailer = MXRoute_Mailer::instance();
        $args = array('message' => 'Body');
        $result = $mailer->intercept_wp_mail($args);
        $this->assertNull($result);
    }

    /**
     * Tests that intercept_wp_mail returns null early when no API credentials are configured.
     */
    public function test_intercept_wp_mail_returns_null_when_no_credentials() {
        $mailer = MXRoute_Mailer::instance();
        $args = array(
            'to'      => 'to@example.com',
            'subject' => 'Test Subject',
            'message' => 'Body',
        );
        // No credentials configured -> let default mailer run.
        $result = $mailer->intercept_wp_mail($args);
        $this->assertNull($result);
    }

    /**
     * Tests that intercept_wp_mail returns true after a successful API send
     * so WordPress does not also invoke the default mailer.
     */
    public function test_intercept_wp_mail_returns_true_after_api_send() {
        $mailer = MXRoute_Mailer::instance();
        $GLOBALS['wp_options']['mxroute_mailer_server'] = 'server.example.com';
        $GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
        $GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';
        $args = array(
            'to'      => 'to@example.com',
            'subject' => 'Test Subject',
            'message' => 'Body',
        );
        $result = $mailer->intercept_wp_mail($args);
        $this->assertTrue($result);
    }

    /**
     * Tests that intercept_wp_mail decrypts the stored password before sending.
     */
    public function test_intercept_decrypts_password_before_sending() {
        $plain_password = 'intercept_secret';
        $mailer = MXRoute_Mailer::instance();
        $GLOBALS['wp_options']['mxroute_mailer_server'] = 'server.example.com';
        $GLOBALS['wp_options']['mxroute_mailer_username'] = 'from@example.com';
        $GLOBALS['wp_options']['mxroute_mailer_password'] = MXRoute_Crypto::encrypt($plain_password);

        $mailer->intercept_wp_mail(array(
            'to' => 'to@example.com',
            'subject' => 'Test',
            'message' => 'Body',
        ));

        $call = array_pop($GLOBALS['wp_function_calls']['wp_remote_post']);
        $body = json_decode($call['args']['body'], true);

        $this->assertEquals($plain_password, $body['password']);
        $this->assertEquals('Basic ' . base64_encode('from@example.com:' . $plain_password), $call['args']['headers']['Authorization']);
    }

    /**
     * Tests that wp_mail_failed action is fired with WP_Error when API call fails.
     */
    public function test_intercept_wp_mail_fires_wp_mail_failed_on_api_failure() {
        $mailer = MXRoute_Mailer::instance();
        $GLOBALS['wp_options']['mxroute_mailer_server'] = 'server.example.com';
        $GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
        $GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

        // Mock wp_remote_post to return a failure response
        $GLOBALS['mxroute_mock_remote_response'] = array(
            'response' => array('code' => 500),
            'body'     => wp_json_encode(array('success' => false, 'message' => 'SMTP error')),
        );

        $args = array(
            'to'      => 'to@example.com',
            'subject' => 'Test Subject',
            'message' => 'Body',
        );
        $mailer->intercept_wp_mail($args);

        $do_action_calls = $GLOBALS['wp_function_calls']['do_action'] ?? array();
        $failed_calls = array_filter($do_action_calls, function($call) {
            return $call['hook'] === 'wp_mail_failed';
        });
        $this->assertNotEmpty($failed_calls, 'wp_mail_failed action should be fired on API failure');

        $failed_call = reset($failed_calls);
        $this->assertInstanceOf('WP_Error', $failed_call['args'][0]);
        $this->assertEquals('mxroute_send_failed', $failed_call['args'][0]->get_error_code());

        // Reset mock
        unset($GLOBALS['mxroute_mock_remote_response']);
    }

    /**
     * Tests that the From address defaults to the username when no headers are provided.
     */
    public function test_extract_from_address_returns_default_when_no_headers() {
        $mailer = MXRoute_Mailer::instance();
        $GLOBALS['wp_options']['mxroute_mailer_username'] = 'default@example.com';
        $result = $mailer->intercept_wp_mail(array(
            'to'      => 'to@example.com',
            'subject' => 'Test',
            'message' => 'Body',
            'headers' => '',
        ));
        // No credentials -> returns null to let default mailer run.
        $this->assertNull($result);
    }

    /**
     * Tests that the From address is parsed from a string header.
     */
    public function test_extract_from_address_parses_string_headers() {
        $mailer = MXRoute_Mailer::instance();
        $GLOBALS['wp_options']['mxroute_mailer_username'] = 'default@example.com';
        $args = array(
            'to'      => 'to@example.com',
            'subject' => 'Test',
            'message' => 'Body',
            'headers' => "From: Sender <sender@example.com>\nContent-Type: text/html",
        );
        $result = $mailer->intercept_wp_mail($args);
        $this->assertNull($result);
    }

    /**
     * Tests that the From address is parsed from angle bracket format headers.
     */
    public function test_extract_from_address_parses_angle_bracket_format() {
        $mailer = MXRoute_Mailer::instance();
        $GLOBALS['wp_options']['mxroute_mailer_username'] = 'default@example.com';
        $args = array(
            'to'      => 'to@example.com',
            'subject' => 'Test',
            'message' => 'Body',
            'headers' => "From: display name <sender@example.com>",
        );
        $result = $mailer->intercept_wp_mail($args);
        $this->assertNull($result);
    }

    /**
     * Tests that the From address is parsed from array-format headers.
     */
    public function test_extract_from_address_parses_array_headers() {
        $mailer = MXRoute_Mailer::instance();
        $GLOBALS['wp_options']['mxroute_mailer_username'] = 'default@example.com';
        $args = array(
            'to'      => 'to@example.com',
            'subject' => 'Test',
            'message' => 'Body',
            'headers' => array("From: sender@example.com", "Content-Type: text/html"),
        );
        $result = $mailer->intercept_wp_mail($args);
        $this->assertNull($result);
    }

    /**
     * Tests that the From address falls back to default when array headers have no From.
     */
    public function test_extract_from_address_falls_back_to_default_for_array_headers() {
        $mailer = MXRoute_Mailer::instance();
        $GLOBALS['wp_options']['mxroute_mailer_username'] = 'default@example.com';
        $args = array(
            'to'      => 'to@example.com',
            'subject' => 'Test',
            'message' => 'Body',
            'headers' => array("Content-Type: text/html"),
        );
        $result = $mailer->intercept_wp_mail($args);
        $this->assertNull($result);
    }

    /**
     * Tests that the From address is parsed from a plain string header.
     */
    public function test_extract_from_address_plain_string_header() {
        $mailer = MXRoute_Mailer::instance();
        $GLOBALS['wp_options']['mxroute_mailer_username'] = 'default@example.com';
        $args = array(
            'to'      => 'to@example.com',
            'subject' => 'Test',
            'message' => 'Body',
            'headers' => "From: plain@example.com",
        );
        $result = $mailer->intercept_wp_mail($args);
        $this->assertNull($result);
    }

    /**
     * Tests that handle_test_email returns early when no nonce is present.
     */
    public function test_handle_test_email_returns_early_without_nonce() {
        $mailer = MXRoute_Mailer::instance();
        $_POST = array();
        $mailer->handle_test_email();
        $this->assertEmpty($GLOBALS['wp_transients']);
    }

    /**
     * Tests that handle_test_email sets an error transient for an invalid nonce.
     */
    public function test_handle_test_email_returns_early_with_invalid_nonce() {
        $mailer = MXRoute_Mailer::instance();
        $_POST = array('mxroute_test_email_nonce' => 'invalid');
        $mailer->handle_test_email();
        $this->assertArrayHasKey('mxroute_test_email_result', $GLOBALS['wp_transients']);
    }

    /**
     * Tests that handle_test_email sets error transient when required fields are missing.
     */
    public function test_handle_test_email_sets_error_transient_when_missing_fields() {
        $mailer = MXRoute_Mailer::instance();
        $_POST = array(
            'mxroute_test_email_nonce' => 'valid',
            'mxroute_test_to'          => '',
            'mxroute_test_from'        => '',
        );
        $mailer->handle_test_email();
        $this->assertArrayHasKey('mxroute_test_email_result', $GLOBALS['wp_transients']);
        $result = $GLOBALS['wp_transients']['mxroute_test_email_result'];
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Missing', $result['message']);
    }

    /**
     * Tests that handle_test_email attempts to send via the API.
     */
    public function test_handle_test_email_calls_send() {
        $mailer = MXRoute_Mailer::instance();
        $_POST = array(
            'mxroute_test_email_nonce' => 'valid',
            'mxroute_test_to'          => 'to@example.com',
            'mxroute_test_from'        => 'from@example.com',
            'mxroute_test_subject'     => 'Test',
            'mxroute_test_body'        => 'Body',
        );
        $mailer->handle_test_email();
        $this->assertArrayHasKey('mxroute_test_email_result', $GLOBALS['wp_transients']);
    }

    /**
     * Tests that handle_test_email stores result in a transient after sending.
     */
    public function test_handle_test_email_sets_transient_after_send() {
        $mailer = MXRoute_Mailer::instance();
        $_POST = array(
            'mxroute_test_email_nonce' => 'valid',
            'mxroute_test_to'          => 'to@example.com',
        );
        $mailer->handle_test_email();

        $this->assertArrayHasKey('mxroute_test_email_result', $GLOBALS['wp_transients']);
    }

    /**
     * Tests that MXRoute_Mailer is instantiated correctly.
     */
    public function test_init_hooks_registers_wp_mail_filter() {
        $mailer = MXRoute_Mailer::instance();
        $this->assertInstanceOf('MXRoute_Mailer', $mailer);
    }

    /**
     * Tests that MXRoute_Mailer is instantiated correctly via init.
     */
    public function test_init_hooks_registers_init_action() {
        $mailer = MXRoute_Mailer::instance();
        $this->assertInstanceOf('MXRoute_Mailer', $mailer);
    }

    /**
     * Tests that the mxroute_mailer() function returns an instance.
     */
    public function test_mxroute_mailer_function_returns_instance() {
        $instance = mxroute_mailer();
        $this->assertInstanceOf('MXRoute_Mailer', $instance);
    }
}
