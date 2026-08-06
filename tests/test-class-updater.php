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

    /**
     * Tests that fix_zip_folder returns expected path when folder already matches.
     */
    public function test_fix_zip_folder_returns_expected_when_folder_matches() {
        $tmp    = sys_get_temp_dir() . '/mxroute_updater_test_' . uniqid();
        $plugin = $tmp . '/mxroute-mailer/plugin.php';
        @mkdir( $tmp . '/mxroute-mailer', 0755, true );
        file_put_contents( $plugin, '<?php' );

        $updater = new MXRoute_Updater( $plugin, 'richardkentgates/mxroute-mailer', '1.0.0' );
        $result  = $updater->fix_zip_folder( $tmp, $tmp, null, array() );

        $this->assertEquals( $tmp . '/mxroute-mailer', $result );

        // Cleanup.
        @unlink( $plugin );
        @rmdir( $tmp . '/mxroute-mailer' );
        @rmdir( $tmp );
    }

    /**
     * Tests that fix_zip_folder renames a mismatched folder.
     */
    public function test_fix_zip_folder_renames_mismatched_folder() {
        $tmp    = sys_get_temp_dir() . '/mxroute_updater_test_' . uniqid();
        $plugin = $tmp . '/mxroute-mailer/plugin.php';
        @mkdir( $tmp . '/mxroute-mailer', 0755, true );
        file_put_contents( $plugin, '<?php' );

        $updater = new MXRoute_Updater( $plugin, 'richardkentgates/mxroute-mailer', '1.0.0' );

        // Create a mismatched source directory.
        $source     = $tmp . '/source';
        $wrong_name = $source . '/wrong-name';
        @mkdir( $wrong_name, 0755, true );
        file_put_contents( $wrong_name . '/file.txt', 'test' );

        $result = $updater->fix_zip_folder( $source, $source, null, array() );

        $this->assertEquals( $source . '/mxroute-mailer', $result );
        $this->assertTrue( is_dir( $source . '/mxroute-mailer' ) );

        // Cleanup.
        @unlink( $source . '/mxroute-mailer/file.txt' );
        @rmdir( $source . '/mxroute-mailer' );
        @rmdir( $source );
        @unlink( $plugin );
        @rmdir( $tmp . '/mxroute-mailer' );
        @rmdir( $tmp );
    }

    /**
     * Tests that fix_zip_folder returns source when no subdirectory exists.
     */
    public function test_fix_zip_folder_returns_source_when_no_subdirectory() {
        $tmp = sys_get_temp_dir() . '/mxroute_updater_test_' . uniqid();
        @mkdir( $tmp, 0755, true );
        file_put_contents( $tmp . '/file1.txt', 'test' );
        file_put_contents( $tmp . '/file2.txt', 'test' );

        // Plugin file lives in a separate directory — not under $tmp.
        $plugin_dir = sys_get_temp_dir() . '/mxroute_updater_plugin_' . uniqid();
        @mkdir( $plugin_dir, 0755, true );
        $plugin = $plugin_dir . '/plugin.php';
        file_put_contents( $plugin, '<?php' );

        $updater = new MXRoute_Updater( $plugin, 'richardkentgates/mxroute-mailer', '1.0.0' );
        $result  = $updater->fix_zip_folder( $tmp, $tmp, null, array() );

        // Source has 2+ files directly, no single subdirectory, so returns $tmp.
        $this->assertEquals( $tmp, $result );

        // Cleanup.
        @unlink( $tmp . '/file1.txt' );
        @unlink( $tmp . '/file2.txt' );
        @rmdir( $tmp );
        @unlink( $plugin );
        @rmdir( $plugin_dir );
    }
}
