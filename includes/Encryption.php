<?php

namespace Hezarfen\Inc;

defined('ABSPATH') || exit();

use Hezarfen\Inc;

class Encryption
{
	/**
	 * Check HEZARFEN_ENCRYPTION_KEY is generated before.
	 */
	public static function is_encryption_key_generated()
	{
		$is_encryption_key_generated = get_option(
			'_hezarfen_encryption_key_generated',
			'no'
		);

		return $is_encryption_key_generated == 'yes';
	}

	/**
	 * Health check for the Encryption Key.
	 * Step 1 - Is encryption key generated before?
	 * Step 2 - Is encrpytion key placed the wp-config.php as correctly?
	 *
	 * @return bool
	 */
	public static function health_check()
	{
		if (
			!self::is_encryption_key_generated() ||
			!defined('HEZARFEN_ENCRYPTION_KEY')
		) {
			return false;
		}

		return true;
	}

	/**
	 * Test the encryption key is correct?
	 * The method tries to decrypt the encrypted text generated before.
	 * If decrypted key is Istanbul, it's success.
	 *
	 * @return bool
	 */
	public static function test_the_encryption_key()
	{
		if (!self::health_check()) {
			return false;
		}

		$cipher_text = get_option('_hezarfen_encryption_tester_text', true);

		return self::decrypt($cipher_text) == 'Istanbul';
	}

	/**
	 * The method creates a new encryption tester text.
	 *
	 * @return void
	 */
	public static function create_encryption_tester_text()
	{
		if (get_option('_hezarfen_encryption_tester_text')) {
			return false;
		}

		// Encryption key değerinin ileride değişip değişmediğini anlayabilmek için bir encryption yap ve options tablosuna yaz :)
		return update_option(
			'_hezarfen_encryption_tester_text',
			self::encrypt("Istanbul")
		);
	}

	/**
	 * Get HEZARFEN_ENCRYPTION_KEY if exists.
	 *
	 * @return string|bool
	 */
	public static function getEncryptionKey()
	{
		if (!self::health_check()) {
			return self::health_check();
		}

		return HEZARFEN_ENCRYPTION_KEY;
	}

	/**
	 * Encrypts the given string.
	 *
	 * @param  mixed $plaintext
	 * @return string|bool
	 */
	public static function encrypt($plaintext)
	{
		if (is_wp_error(self::getEncryptionKey())) {
			return self::getEncryptionKey();
		}

		if( ! extension_loaded( 'openssl' ) ) {
			return $plaintext;
		}

		$ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC");
		$iv = openssl_random_pseudo_bytes($ivlen);
		$ciphertext_raw = openssl_encrypt(
			$plaintext,
			$cipher,
			self::getEncryptionKey(),
			$options = OPENSSL_RAW_DATA,
			$iv
		);
		$hmac = hash_hmac(
			'sha256',
			$ciphertext_raw,
			self::getEncryptionKey(),
			$as_binary = true
		);
		$ciphertext = base64_encode($iv . $hmac . $ciphertext_raw);
		return $ciphertext;
	}

	/**
	 * Decrypts the given cipher string.
	 *
	 * @param  mixed $ciphertext
	 * @return string|bool
	 */
	public static function decrypt($ciphertext)
	{
		if (is_wp_error(self::getEncryptionKey())) {
			return self::getEncryptionKey();
		}

		if( ! extension_loaded( 'openssl' ) ) {
			return $ciphertext;
		}

		$c = base64_decode($ciphertext);
		$ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC");
		$iv = substr($c, 0, $ivlen);
		$hmac = substr($c, $ivlen, $sha2len = 32);
		$ciphertext_raw = substr($c, $ivlen + $sha2len);
		$original_plaintext = openssl_decrypt(
			$ciphertext_raw,
			$cipher,
			self::getEncryptionKey(),
			$options = OPENSSL_RAW_DATA,
			$iv
		);
		$calcmac = hash_hmac(
			'sha256',
			$ciphertext_raw,
			self::getEncryptionKey(),
			$as_binary = true
		);
		if (hash_equals($hmac, $calcmac)) {
			//PHP 5.6+ timing attack safe comparison
			return $original_plaintext;
		}
	}

	/**
	 * Creates a new securely random key.
	 *
	 * @return string
	 */
	public static function create_random_key()
	{
		return bin2hex(random_bytes(16));
	}
}
