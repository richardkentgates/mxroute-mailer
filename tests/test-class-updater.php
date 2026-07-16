<?php
/**
 * Tests for MXRoute_Updater class.
 *
 * @package MXRoute_Mailer
 */
class MXRoute_Updater_Test extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        $GLOBALS['wp_options'] = array();
        $GLOBALS['wp_transients'] = array();
        $GLOBALS['wp_function_calls'] = array();
    }

    /**
     * Tests that the http_response filter leaves non-package responses unchanged.
     */
    public function test_verify_download_response_leaves_unrelated_responses_unchanged() {
        $updater = new MXRoute_Updater('/fake/path/mxroute-mailer.php', 'richardkentgates/mxroute-mailer', '1.0.0');
        $response = array(
            'response' => array('code' => 200),
            'body' => '{"success":false,"message":"Message could not be sent."}',
        );

        $result = $updater->verify_download_response($response, array(), 'https://smtpapi.mxroute.com/');

        $this->assertSame($response, $result);
    }

    /**
     * Tests that the http_response filter rejects a package with a mismatched checksum.
     */
    public function test_verify_download_response_rejects_mismatched_checksum() {
        $updater = new MXRoute_Updater('/fake/path/mxroute-mailer.php', 'richardkentgates/mxroute-mailer', '1.0.0');
        $GLOBALS['wp_transients']['mxroute_mailer_package_url'] = 'https://example.com/release.zip';
        $GLOBALS['wp_transients']['mxroute_mailer_package_hash'] = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

        $response = array(
            'response' => array('code' => 200),
            'body' => 'bad zip content',
        );

        $result = $updater->verify_download_response($response, array(), 'https://example.com/release.zip');

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('mxroute_checksum_mismatch', $result->get_error_code());
    }
}
