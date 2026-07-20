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

    protected function tearDown(): void {
        unset( $GLOBALS['wp_mock_current_user_can'] );
        unset( $GLOBALS['wp_mock_ajax_referer'] );
        unset( $GLOBALS['wp_db_row'] );
        unset( $GLOBALS['wp_db_col'] );
    }

    /**
     * Tests that the constructor registers the expected AJAX action hooks.
     */
    public function test_constructor_registers_hooks() {
        $dashboard = new MXRoute_Dashboard();

        $hooks = array_column($GLOBALS['wp_function_calls']['add_action'], 'hook');
        $this->assertContains('wp_ajax_mxroute_clear_logs', $hooks);
        $this->assertContains('wp_ajax_mxroute_delete_log', $hooks);
        $this->assertContains('wp_ajax_mxroute_bulk_delete_logs', $hooks);
        $this->assertContains('wp_ajax_mxroute_requeue_log', $hooks);
        $this->assertContains('wp_ajax_mxroute_bulk_requeue_logs', $hooks);
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
     * Tests that ajax_delete_log sends error when log_id is invalid.
     */
    public function test_ajax_delete_log_sends_success() {
        $GLOBALS['wp_db_results'] = array();
        $dashboard = new MXRoute_Dashboard();
        $_POST['log_id'] = 999;
        $threw = false;
        try {
            $dashboard->ajax_delete_log();
        } catch (\MXRouteJSONException $e) {
            $threw = true;
            $this->assertFalse($e->response['success']);
        }
        $this->assertTrue($threw, 'Expected MXRouteJSONException to be thrown');
        unset($_POST['log_id']);
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
     * Tests that ajax_clear_logs sends error when user lacks manage_options.
     */
    public function test_ajax_clear_logs_denies_unauthorized_user() {
        $GLOBALS['wp_mock_current_user_can'] = false;
        $dashboard = new MXRoute_Dashboard();
        try {
            $dashboard->ajax_clear_logs();
            $this->fail( 'Expected MXRouteJSONException' );
        } catch ( \MXRouteJSONException $e ) {
            $this->assertFalse( $e->response['success'] );
        }
    }

    /**
     * Tests that ajax_delete_log sends error when user lacks manage_options.
     */
    public function test_ajax_delete_log_denies_unauthorized_user() {
        $GLOBALS['wp_mock_current_user_can'] = false;
        $dashboard = new MXRoute_Dashboard();
        try {
            $dashboard->ajax_delete_log();
            $this->fail( 'Expected MXRouteJSONException' );
        } catch ( \MXRouteJSONException $e ) {
            $this->assertFalse( $e->response['success'] );
        }
    }

    /**
     * Tests that ajax_bulk_delete_logs sends error when user lacks manage_options.
     */
    public function test_ajax_bulk_delete_logs_denies_unauthorized_user() {
        $GLOBALS['wp_mock_current_user_can'] = false;
        $dashboard = new MXRoute_Dashboard();
        try {
            $dashboard->ajax_bulk_delete_logs();
            $this->fail( 'Expected MXRouteJSONException' );
        } catch ( \MXRouteJSONException $e ) {
            $this->assertFalse( $e->response['success'] );
        }
    }

    /**
     * Tests that ajax_requeue_log sends error when user lacks manage_options.
     */
    public function test_ajax_requeue_log_denies_unauthorized_user() {
        $GLOBALS['wp_mock_current_user_can'] = false;
        $dashboard = new MXRoute_Dashboard();
        try {
            $dashboard->ajax_requeue_log();
            $this->fail( 'Expected MXRouteJSONException' );
        } catch ( \MXRouteJSONException $e ) {
            $this->assertFalse( $e->response['success'] );
        }
    }

    /**
     * Tests that ajax_bulk_requeue_logs sends error when user lacks manage_options.
     */
    public function test_ajax_bulk_requeue_logs_denies_unauthorized_user() {
        $GLOBALS['wp_mock_current_user_can'] = false;
        $dashboard = new MXRoute_Dashboard();
        try {
            $dashboard->ajax_bulk_requeue_logs();
            $this->fail( 'Expected MXRouteJSONException' );
        } catch ( \MXRouteJSONException $e ) {
            $this->assertFalse( $e->response['success'] );
        }
    }

    /**
     * Tests that the constructor registers the check_queue hook.
     */
    public function test_constructor_registers_check_queue_hook() {
        $dashboard = new MXRoute_Dashboard();

        $hooks = array_column($GLOBALS['wp_function_calls']['add_action'], 'hook');
        $this->assertContains('wp_ajax_mxroute_check_queue', $hooks);
    }

    /**
     * Tests that ajax_check_queue verifies the nonce before proceeding.
     */
    public function test_ajax_check_queue_verifies_nonce() {
        $dashboard = new MXRoute_Dashboard();
        try {
            $dashboard->ajax_check_queue();
        } catch (\MXRouteJSONException $e) {
            // Expected
        }

        $calls = $GLOBALS['wp_function_calls']['check_ajax_referer'];
        $this->assertCount(1, $calls);
        $this->assertEquals('mxroute_log_manage', $calls[0]['action']);
    }

    /**
     * Tests that ajax_check_queue sends error when user lacks manage_options.
     */
    public function test_ajax_check_queue_denies_unauthorized_user() {
        $GLOBALS['wp_mock_current_user_can'] = false;
        $dashboard = new MXRoute_Dashboard();
        try {
            $dashboard->ajax_check_queue();
            $this->fail( 'Expected MXRouteJSONException' );
        } catch ( \MXRouteJSONException $e ) {
            $this->assertFalse( $e->response['success'] );
        }
    }

    /**
     * Tests that ajax_check_queue returns empty processed when no IDs provided.
     */
    public function test_ajax_check_queue_returns_empty_when_no_ids() {
        $dashboard = new MXRoute_Dashboard();
        $threw = false;
        try {
            $dashboard->ajax_check_queue();
        } catch (\MXRouteJSONException $e) {
            $threw = true;
            $this->assertTrue($e->response['success']);
            $this->assertEquals(array(), $e->response['data']['processed']);
        }
        $this->assertTrue($threw, 'Expected MXRouteJSONException to be thrown');
    }

    /**
     * Tests that ajax_check_queue returns processed IDs when some are no longer pending.
     */
    public function test_ajax_check_queue_returns_processed_ids() {
        $GLOBALS['wp_db_col'] = array( '1' ); // Only ID 1 is still pending.
        $_POST['ids'] = array( 1, 2, 3 );

        $dashboard = new MXRoute_Dashboard();
        $threw = false;
        try {
            $dashboard->ajax_check_queue();
        } catch (\MXRouteJSONException $e) {
            $threw = true;
            $this->assertTrue($e->response['success']);
            $processed = $e->response['data']['processed'];
            $this->assertContains(2, $processed);
            $this->assertContains(3, $processed);
            $this->assertNotContains(1, $processed);
        }
        $this->assertTrue($threw, 'Expected MXRouteJSONException to be thrown');

        unset($_POST['ids']);
        unset($GLOBALS['wp_db_col']);
    }

    /**
     * Tests that ajax_delete_log succeeds when log exists.
     */
    public function test_ajax_delete_log_succeeds_when_log_exists() {
        $GLOBALS['wp_db_row'] = (object) array( 'id' => 42, 'subject' => 'Test' );
        $dashboard = new MXRoute_Dashboard();
        $_POST['log_id'] = 42;
        $threw = false;
        try {
            $dashboard->ajax_delete_log();
        } catch (\MXRouteJSONException $e) {
            $threw = true;
            $this->assertTrue($e->response['success']);
            $this->assertEquals('Log deleted.', $e->response['data']['message']);
        }
        $this->assertTrue($threw, 'Expected MXRouteJSONException to be thrown');
        unset($_POST['log_id']);
        unset($GLOBALS['wp_db_row']);
    }
}
