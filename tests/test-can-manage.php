<?php
/**
 * Tests for mxroute_mailer_can_manage() function.
 *
 * @package MXRoute_Mailer
 */
class MXRoute_Can_Manage_Test extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options']        = array();
		$GLOBALS['wp_function_calls'] = array();
		$GLOBALS['is_multisite_value'] = false;
		$GLOBALS['wp_current_user_can_value'] = true;
	}

	/**
	 * Tests that mxroute_mailer_can_manage checks manage_options on single site.
	 */
	public function test_single_site_checks_manage_options() {
		$GLOBALS['is_multisite_value'] = false;

		$result = mxroute_mailer_can_manage();

		$this->assertTrue( $result );
		$can_calls = $GLOBALS['wp_function_calls']['current_user_can'] ?? array();
		$this->assertNotEmpty( $can_calls );
		$this->assertEquals( 'manage_options', $can_calls[0]['capability'] );
	}

	/**
	 * Tests that mxroute_mailer_can_manage returns false when user lacks capability.
	 */
	public function test_single_site_returns_false_when_unauthorized() {
		$GLOBALS['is_multisite_value']     = false;
		$GLOBALS['wp_current_user_can_value'] = false;

		$result = mxroute_mailer_can_manage();

		$this->assertFalse( $result );
	}

	/**
	 * Tests that mxroute_mailer_can_manage checks manage_network_options on multisite.
	 */
	public function test_multisite_checks_manage_network_options() {
		$GLOBALS['is_multisite_value'] = true;

		$result = mxroute_mailer_can_manage();

		$this->assertTrue( $result );
		$can_calls = $GLOBALS['wp_function_calls']['current_user_can'] ?? array();
		$this->assertNotEmpty( $can_calls );
		$this->assertEquals( 'manage_network_options', $can_calls[0]['capability'] );
	}

	/**
	 * Tests that mxroute_mailer_can_manage returns false on multisite when user lacks capability.
	 */
	public function test_multisite_returns_false_when_unauthorized() {
		$GLOBALS['is_multisite_value']     = true;
		$GLOBALS['wp_current_user_can_value'] = false;

		$result = mxroute_mailer_can_manage();

		$this->assertFalse( $result );
	}
}
