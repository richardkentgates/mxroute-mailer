<?php
/**
 * Tests for MXRoute_API class.
 *
 * @package MXRoute_Mailer
 */
class MXRoute_API_Test extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        // Reset global state
        $GLOBALS['wp_options'] = array();
        $GLOBALS['wp_function_calls'] = array();
    }

    /**
     * Tests that send returns an error when no credentials are configured.
     */
    public function test_send_returns_error_when_credentials_not_configured() {
        $api = new MXRoute_API();
        $result = $api->send('from@example.com', 'to@example.com', 'Test', 'Body');

        $this->assertFalse($result['success']);
        $this->assertEquals('MXRoute credentials not configured.', $result['message']);
        $this->assertEmpty($result['request']);
        $this->assertEmpty($result['response']);
    }

    /**
     * Tests that send returns an error when the server is not configured.
     */
    public function test_send_returns_error_when_server_not_configured() {
        $GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
        $GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

        $api = new MXRoute_API();
        $result = $api->send('from@example.com', 'to@example.com', 'Test', 'Body');

        $this->assertFalse($result['success']);
        $this->assertEquals('MXRoute credentials not configured.', $result['message']);
    }

    /**
     * Tests that send returns an error when the username is not configured.
     */
    public function test_send_returns_error_when_username_not_configured() {
        $GLOBALS['wp_options']['mxroute_mailer_server'] = 'server.example.com';
        $GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

        $api = new MXRoute_API();
        $result = $api->send('from@example.com', 'to@example.com', 'Test', 'Body');

        $this->assertFalse($result['success']);
        $this->assertEquals('MXRoute credentials not configured.', $result['message']);
    }

    /**
     * Tests that send returns an error when the password is not configured.
     */
    public function test_send_returns_error_when_password_not_configured() {
        $GLOBALS['wp_options']['mxroute_mailer_server'] = 'server.example.com';
        $GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';

        $api = new MXRoute_API();
        $result = $api->send('from@example.com', 'to@example.com', 'Test', 'Body');

        $this->assertFalse($result['success']);
        $this->assertEquals('MXRoute credentials not configured.', $result['message']);
    }

    /**
     * Tests that send builds the correct API request payload.
     */
    public function test_send_builds_correct_payload() {
        $GLOBALS['wp_options']['mxroute_mailer_server'] = 'server.example.com';
        $GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
        $GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

        $api = new MXRoute_API();
        $result = $api->send('from@example.com', 'to@example.com', 'Test Subject', 'Test Body');

        // wp_remote_post mock returns success by default
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('request', $result);
        $this->assertEquals('server.example.com', $result['request']['server']);
        $this->assertEquals('user@example.com', $result['request']['username']);
        $this->assertEquals('from@example.com', $result['request']['from']);
        $this->assertEquals('to@example.com', $result['request']['to']);
        $this->assertEquals('Test Subject', $result['request']['subject']);
    }

    /**
     * Tests that send correctly handles an array of recipients.
     */
    public function test_send_handles_array_recipient() {
        $GLOBALS['wp_options']['mxroute_mailer_server'] = 'server.example.com';
        $GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
        $GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

        $api = new MXRoute_API();
        $result = $api->send('from@example.com', array('to@example.com'), 'Test', 'Body');

        $this->assertEquals('to@example.com', $result['request']['to']);
    }

    /**
     * Tests that send includes all required fields in the request.
     */
    public function test_send_includes_all_required_fields() {
        $GLOBALS['wp_options']['mxroute_mailer_server'] = 'server.example.com';
        $GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
        $GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

        $api = new MXRoute_API();
        $result = $api->send('from@example.com', 'to@example.com', 'Test', 'Body');

        $this->assertArrayHasKey('server', $result['request']);
        $this->assertArrayHasKey('username', $result['request']);
        $this->assertArrayHasKey('from', $result['request']);
        $this->assertArrayHasKey('to', $result['request']);
        $this->assertArrayHasKey('subject', $result['request']);
    }

    /**
     * Tests that send does not include the password in the request log.
     */
    public function test_send_does_not_include_password_in_request_log() {
        $GLOBALS['wp_options']['mxroute_mailer_server'] = 'server.example.com';
        $GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
        $GLOBALS['wp_options']['mxroute_mailer_password'] = 'secret_password';

        $api = new MXRoute_API();
        $result = $api->send('from@example.com', 'to@example.com', 'Test', 'Body');

        $this->assertArrayNotHasKey('password', $result['request']);
    }

    /**
     * Tests that send handles a WP_Error response from wp_remote_post.
     */
    public function test_send_handles_remote_post_error() {
        $GLOBALS['wp_options']['mxroute_mailer_server'] = 'server.example.com';
        $GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
        $GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

        // Mock wp_remote_post to return a WP_Error
        $GLOBALS['mxroute_mock_remote_response'] = new WP_Error('http_error', 'Connection refused');

        $api = new MXRoute_API();
        $result = $api->send('from@example.com', 'to@example.com', 'Test', 'Body');

        $this->assertFalse($result['success']);
        $this->assertEquals('HTTP request failed.', $result['message']);
        $this->assertArrayNotHasKey('curl_error', $result['response']);

        // Reset mock
        unset($GLOBALS['mxroute_mock_remote_response']);
    }
}
