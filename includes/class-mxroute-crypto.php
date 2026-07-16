<?php
/**
 * MXRoute Mailer encryption helpers.
 *
 * Provides reversible encryption for sensitive option values such as the
 * MXRoute API password. Uses AES-256-GCM with a key derived from the site's
 * WordPress auth salt. If OpenSSL is unavailable the helper falls back to
 * returning the value unchanged.
 *
 * @package MXRoute_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles reversible encryption for sensitive plugin settings.
 */
class MXRoute_Crypto {

	/**
	 * Encryption cipher.
	 *
	 * @var string
	 */
	private static $cipher = 'AES-256-GCM';

	/**
	 * Length of the GCM nonce in bytes.
	 *
	 * @var int
	 */
	private static $iv_length = 12;

	/**
	 * Length of the GCM authentication tag in bytes.
	 *
	 * @var int
	 */
	private static $tag_length = 16;

	/**
	 * Get the encryption key derived from the WordPress auth salt.
	 *
	 * @return string 32-byte key.
	 */
	private static function get_key() {
		$salt = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : '';
		if ( strlen( $salt ) < 32 ) {
			$salt = str_pad( $salt, 32, '\0' );
		}
		return substr( $salt, 0, 32 );
	}

	/**
	 * Encrypt a plaintext string.
	 *
	 * @param string $plain Plaintext value.
	 * @return string Encrypted base64 value, or plaintext on failure.
	 */
	public static function encrypt( $plain ) {
		if ( ! function_exists( 'openssl_encrypt' ) || '' === $plain ) {
			return $plain;
		}

		$key = self::get_key();
		$iv  = random_bytes( self::$iv_length );
		$tag = '';

		$cipher_raw = openssl_encrypt(
			$plain,
			self::$cipher,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			'',
			self::$tag_length
		);

		if ( false === $cipher_raw ) {
			return $plain;
		}

		return base64_encode( $iv . $tag . $cipher_raw ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt an encrypted string.
	 *
	 * Falls back to returning the input unchanged if it is not a valid
	 * encrypted value, preserving backwards compatibility with plaintext
	 * passwords stored before this feature was added.
	 *
	 * @param string $encrypted Encrypted base64 value.
	 * @return string Decrypted plaintext, or input on failure.
	 */
	public static function decrypt( $encrypted ) {
		if ( ! function_exists( 'openssl_decrypt' ) || '' === $encrypted ) {
			return $encrypted;
		}

		$raw = base64_decode( $encrypted, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw || strlen( $raw ) < self::$iv_length + self::$tag_length ) {
			return $encrypted;
		}

		$iv     = substr( $raw, 0, self::$iv_length );
		$tag    = substr( $raw, self::$iv_length, self::$tag_length );
		$cipher = substr( $raw, self::$iv_length + self::$tag_length );

		$key   = self::get_key();
		$plain = openssl_decrypt(
			$cipher,
			self::$cipher,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		return ( false === $plain ) ? $encrypted : $plain;
	}

	/**
	 * Retrieve and decrypt the stored MXRoute password.
	 *
	 * @return string Decrypted password, or empty string.
	 */
	public static function get_password() {
		return self::decrypt( get_option( 'mxroute_mailer_password', '' ) );
	}
}
