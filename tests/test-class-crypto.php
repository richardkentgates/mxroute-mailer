<?php
/**
 * Tests for MXRoute_Crypto class.
 *
 * @package MXRoute_Mailer
 */
class MXRoute_Crypto_Test extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options'] = array();
	}

	/**
	 * Tests that encrypt and decrypt are symmetric.
	 */
	public function test_encrypt_decrypt_roundtrip() {
		$plain = 'super_secret_123';
		$encrypted = MXRoute_Crypto::encrypt( $plain );

		$this->assertNotEquals( $plain, $encrypted );
		$this->assertEquals( $plain, MXRoute_Crypto::decrypt( $encrypted ) );
	}

	/**
	 * Tests that get_password returns the decrypted option value.
	 */
	public function test_get_password_decrypts_option() {
		$plain = 'my_password';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = MXRoute_Crypto::encrypt( $plain );

		$this->assertEquals( $plain, MXRoute_Crypto::get_password() );
	}

	/**
	 * Tests that decrypt falls back to returning plaintext for legacy values.
	 */
	public function test_decrypt_returns_plaintext_for_legacy_values() {
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'legacy_password';

		$this->assertEquals( 'legacy_password', MXRoute_Crypto::get_password() );
	}

	/**
	 * Tests that empty values pass through unchanged.
	 */
	public function test_encrypt_empty_value() {
		$this->assertEquals( '', MXRoute_Crypto::encrypt( '' ) );
		$this->assertEquals( '', MXRoute_Crypto::decrypt( '' ) );
	}
}
