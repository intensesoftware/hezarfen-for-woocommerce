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
	 * Encrypts the given string.
	 *
	 * @param  string $plaintext that plain text.
	 * @return string|bool|\WP_Error
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
	 * @param  string $ciphertext that will be decrypted.
	 * @return string|bool|\WP_Error
	 */
	public function decrypt( $ciphertext ) {
		if ( is_wp_error( $this->encryption_key ) ) {
			return $this->encryption_key;
		}

		if ( ! extension_loaded( 'openssl' ) ) {
			return $ciphertext;
		}

		$c       = base64_decode( (string) $ciphertext, true );
		$cipher  = 'AES-128-CBC';
		$ivlen   = openssl_cipher_iv_length( $cipher );
		$sha2len = 32;

		// Şifrelenmemiş değerler (eski siparişler, içe aktarma, REST) buraya
		// düz metin olarak gelebilir. IV + HMAC + en az bir blok taşımayan
		// gövde şifreli olamaz; openssl_decrypt()'e kısa IV verip uyarı
		// üretmek yerine değer olduğu gibi döner.
		if ( false === $c || strlen( $c ) < $ivlen + $sha2len + 16 ) {
			return $ciphertext;
		}

		$iv             = substr( $c, 0, $ivlen );
		$hmac           = substr( $c, $ivlen, $sha2len );
		$ciphertext_raw = substr( $c, $ivlen + $sha2len );
		$calcmac        = hash_hmac(
			'sha256',
			$ciphertext_raw,
			$this->encryption_key,
			$as_binary = true
		);

		// PHP 5.6+ timing attack safe comparison.
		if ( ! hash_equals( $hmac, $calcmac ) ) {
			return $ciphertext;
		}

		return openssl_decrypt(
			$ciphertext_raw,
			$cipher,
			$this->encryption_key,
			$options = OPENSSL_RAW_DATA,
			$iv
		);
	}
}
