<?php
/**
 * Class Abstract_Encryption.
 * 
 * @package Hezarfen\Inc\Data\Abstracts
 */

namespace Hezarfen\Inc\Data\Abstracts;

defined( 'ABSPATH' ) || exit();

/**
 * Abstract_Encryption
 */
class Abstract_Encryption {
	
	/**
	 * The encryption key
	 *
	 * @var mixed
	 */
	protected $encryption_key;
	
	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		$this->setEncryptionKey();
	}

	/**
	 * Encrypts the given string.
	 *
	 * @param  mixed $plaintext that plain text.
	 * @return string|bool
	 */
	public function encrypt( $plaintext ) {
		if ( is_wp_error( $this->encryption_key ) ) {
			return $this->encryption_key;
		}

		if ( ! extension_loaded( 'openssl' ) ) {
			return $plaintext;
		}

		$cipher         = 'AES-128-CBC';
		$ivlen          = openssl_cipher_iv_length( $cipher );
		$iv             = openssl_random_pseudo_bytes( $ivlen );
		$ciphertext_raw = openssl_encrypt(
			$plaintext,
			$cipher,
			$this->encryption_key,
			$options    = OPENSSL_RAW_DATA,
			$iv
		);
		$hmac          = hash_hmac(
			'sha256',
			$ciphertext_raw,
			$this->encryption_key,
			$as_binary = true
		);
		$ciphertext = base64_encode( $iv . $hmac . $ciphertext_raw );
		return $ciphertext;
	}

	/**
	 * Decrypts the given cipher string.
	 *
	 * @param  mixed $ciphertext that will be decrypted.
	 * @return string|bool
	 */
	public function decrypt( $ciphertext ) {
		if ( is_wp_error( $this->encryption_key ) ) {
			return $this->encryption_key;
		}

		if ( ! extension_loaded( 'openssl' ) ) {
			return $ciphertext;
		}

		$c                  = base64_decode( $ciphertext );
		$cipher             = 'AES-128-CBC';
		$ivlen              = openssl_cipher_iv_length( $cipher );
		$iv                 = substr( $c, 0, $ivlen );
		$hmac               = substr( $c, $ivlen, $sha2len = 32 );
		$ciphertext_raw     = substr( $c, $ivlen + $sha2len );
		$original_plaintext = openssl_decrypt(
			$ciphertext_raw,
			$cipher,
			$this->encryption_key,
			$options        = OPENSSL_RAW_DATA,
			$iv
		);
		$calcmac       = hash_hmac(
			'sha256',
			$ciphertext_raw,
			$this->encryption_key,
			$as_binary = true
		);
		if ( hash_equals( $hmac, $calcmac ) ) {
			// PHP 5.6+ timing attack safe comparison.
			return $original_plaintext;
		}
	}
}
