<?php
/**
 * Tests for the MXRoute Mailer dashboard widget.
 *
 * @package MXRoute_Mailer
 */
class MXRoute_Dashboard_Widget_Test extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options']        = array();
		$GLOBALS['wp_function_calls'] = array();
		$GLOBALS['wp_mock_current_user_can'] = true;
		unset( $GLOBALS['wp_current_user_can_value'], $GLOBALS['is_multisite_value'] );
		$content_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : '/tmp/wordpress/wp-content';
		if ( ! is_dir( $content_dir ) ) {
			mkdir( $content_dir, 0755, true );
		}
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wp_mock_current_user_can'] );
		unset( $GLOBALS['wp_current_user_can_value'], $GLOBALS['is_multisite_value'] );
		$status_file = ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : '/tmp/wordpress/wp-content' ) . '/mxroute-status.json';
		if ( file_exists( $status_file ) ) {
			unlink( $status_file );
		}
	}

	private function write_status( $data ) {
		$status_file = WP_CONTENT_DIR . '/mxroute-status.json';
		file_put_contents( $status_file, wp_json_encode( $data ) );
	}

	/**
	 * Tests that mxroute_mailer_format_last_run formats a valid ISO timestamp.
	 */
	public function test_format_last_run_formats_valid_iso() {
		$result = mxroute_mailer_format_last_run( '2026-10-21T14:30:00Z' );
		$this->assertNotEquals( '—', $result );
		$this->assertMatchesRegularExpression( '/^[A-Z][a-z]{2} \d{1,2}, \d{1,2}:\d{2}(am|pm)$/', $result );
	}

	/**
	 * Tests that mxroute_mailer_format_last_run returns em dash for empty input.
	 */
	public function test_format_last_run_returns_dash_for_empty() {
		$this->assertEquals( '—', mxroute_mailer_format_last_run( '' ) );
		$this->assertEquals( '—', mxroute_mailer_format_last_run( null ) );
	}

	/**
	 * Tests that mxroute_mailer_format_last_run returns em dash for invalid input.
	 */
	public function test_format_last_run_returns_dash_for_invalid() {
		$this->assertEquals( '—', mxroute_mailer_format_last_run( 'not-a-date' ) );
	}

	/**
	 * Tests that register_dashboard_widget registers the widget when user can manage.
	 */
	public function test_register_dashboard_widget_registers_when_authorized() {
		$GLOBALS['wp_mock_current_user_can'] = true;
		mxroute_mailer_register_dashboard_widget();

		$calls = $GLOBALS['wp_function_calls']['wp_add_dashboard_widget'] ?? array();
		$this->assertCount( 1, $calls );
		$this->assertEquals( 'mxroute_mailer_status', $calls[0]['widget_id'] );
		$this->assertEquals( 'mxroute_mailer_render_dashboard_widget', $calls[0]['callback'] );
	}

	/**
	 * Tests that register_dashboard_widget skips when user cannot manage.
	 */
	public function test_register_dashboard_widget_skips_when_unauthorized() {
		$GLOBALS['wp_mock_current_user_can'] = false;
		mxroute_mailer_register_dashboard_widget();

		$calls = $GLOBALS['wp_function_calls']['wp_add_dashboard_widget'] ?? array();
		$this->assertCount( 0, $calls );
	}

	/**
	 * Tests that the render outputs the queue table.
	 */
	public function test_render_outputs_queue_table() {
		$this->write_status( array(
			'pending' => 3,
			'sent'    => 10,
			'failed'  => 1,
			'history' => array(),
		) );

		ob_start();
		mxroute_mailer_render_dashboard_widget();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Pending', $html );
		$this->assertStringContainsString( 'Sent', $html );
		$this->assertStringContainsString( 'Failed', $html );
		$this->assertStringContainsString( '>3</span>', $html );
		$this->assertStringContainsString( '>10</td>', $html );
	}

	/**
	 * Tests that the render always outputs the cron table even with empty history.
	 */
	public function test_render_always_outputs_cron_table() {
		$this->write_status( array(
			'pending' => 0,
			'sent'    => 0,
			'failed'  => 0,
			'history' => array(),
		) );

		ob_start();
		mxroute_mailer_render_dashboard_widget();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'id="mxroute-cron-table"', $html );
		$this->assertStringContainsString( 'No cron data yet.', $html );
	}

	/**
	 * Tests that the render includes status icons matching the JS styling.
	 */
	public function test_render_includes_status_icons() {
		$this->write_status( array(
			'pending' => 0,
			'sent'    => 0,
			'failed'  => 0,
			'history' => array(
				'mxroute_mailer_process_queue' => array(
					'last_run'    => '2026-10-21T14:30:00Z',
					'last_status' => 'pass',
					'pass_count'  => 100,
					'fail_count'  => 0,
				),
			),
		) );

		ob_start();
		mxroute_mailer_render_dashboard_widget();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'dashicons-yes-alt', $html );
		$this->assertStringContainsString( 'mxroute_mailer_process_queue', $html );
	}

	/**
	 * Tests that the render shows a fail icon for a failed last run.
	 */
	public function test_render_includes_fail_icon() {
		$this->write_status( array(
			'pending' => 0,
			'sent'    => 0,
			'failed'  => 0,
			'history' => array(
				'mxroute_write_status_json' => array(
					'last_run'    => '2026-10-21T14:30:00Z',
					'last_status' => 'fail',
					'pass_count'  => 50,
					'fail_count'  => 2,
				),
			),
		) );

		ob_start();
		mxroute_mailer_render_dashboard_widget();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'dashicons-dismiss', $html );
	}

	/**
	 * Tests that the render formats timestamps via mxroute_mailer_format_last_run.
	 */
	public function test_render_uses_format_last_run() {
		$expected = mxroute_mailer_format_last_run( '2026-10-21T14:30:00Z' );
		$this->write_status( array(
			'pending' => 0,
			'sent'    => 0,
			'failed'  => 0,
			'history' => array(
				'mxroute_mailer_process_queue' => array(
					'last_run'    => '2026-10-21T14:30:00Z',
					'last_status' => 'pass',
					'pass_count'  => 1,
					'fail_count'  => 0,
				),
			),
		) );

		ob_start();
		mxroute_mailer_render_dashboard_widget();
		$html = ob_get_clean();

		$this->assertStringContainsString( $expected, $html );
	}

	/**
	 * Tests that ajax_status_refresh includes last_run_display in the response.
	 */
	public function test_ajax_status_refresh_includes_display_format() {
		$this->write_status( array(
			'pending' => 5,
			'sent'    => 20,
			'failed'  => 2,
			'history' => array(
				'mxroute_mailer_process_queue' => array(
					'last_run'    => '2026-10-21T14:30:00Z',
					'last_status' => 'pass',
					'pass_count'  => 100,
					'fail_count'  => 0,
				),
			),
		) );

		$dashboard = new MXRoute_Dashboard();
		$threw     = false;
		try {
			$dashboard->ajax_status_refresh();
		} catch ( \MXRouteJSONException $e ) {
			$threw = true;
			$this->assertTrue( $e->response['success'] );
			$history = $e->response['data']['history'];
			$this->assertArrayHasKey( 'last_run_display', $history['mxroute_mailer_process_queue'] );
			$expected = mxroute_mailer_format_last_run( '2026-10-21T14:30:00Z' );
			$this->assertEquals( $expected, $history['mxroute_mailer_process_queue']['last_run_display'] );
		}
		$this->assertTrue( $threw, 'Expected MXRouteJSONException to be thrown' );
	}

	/**
	 * Tests that ajax_status_refresh verifies the nonce.
	 */
	public function test_ajax_status_refresh_verifies_nonce() {
		$dashboard = new MXRoute_Dashboard();
		try {
			$dashboard->ajax_status_refresh();
		} catch ( \MXRouteJSONException $e ) {
			// Expected
		}

		$calls = $GLOBALS['wp_function_calls']['check_ajax_referer'] ?? array();
		$this->assertCount( 1, $calls );
		$this->assertEquals( 'mxroute_status_refresh', $calls[0]['action'] );
	}
}
