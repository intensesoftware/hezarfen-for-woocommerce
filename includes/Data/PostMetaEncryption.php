<?php
/**
 * Class PostMetaEncryption.
 * 
 * @package Hezarfen\Inc\Data
 */

namespace Hezarfen\Inc\Data;

defined( 'ABSPATH' ) || exit();

use Hezarfen\Inc\Data\Abstracts\Abstract_Encryption;

/**
 * PostMetaEncryption
 */
class PostMetaEncryption extends Abstract_Encryption {
	
	/**
	 * The encryption key
	 *
	 * @var mixed
	 */
	protected $encryption_key;
	
	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		$this->setEncryptionKey();
	}
	
	/**
	 * Check hezarfen encryption key is generated before.
	 *
	 * @return bool
	 */
	public function is_encryption_key_generated() {
		$is_encryption_key_generated = get_option(
			'hezarfen_encryption_key_generated',
			'no'
		);

		return 'yes' == $is_encryption_key_generated;
	}

	/**
	 * Health check for the Encryption Key.
	 * Step 1 - Is encryption key generated before?
	 * Step 2 - Is encrpytion key placed the wp-config.php as correctly?
	 *
	 * @return bool
	 */
	public function health_check() {
		if (
			! $this->is_encryption_key_generated() ||
			! defined( 'HEZARFEN_ENCRYPTION_KEY' )
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
	public function test_the_encryption_key() {
		if ( ! $this->health_check() ) {
			return false;
		}

		$cipher_text = get_option( 'hezarfen_encryption_tester_text', true );

		return $this->decrypt( $cipher_text ) == 'Istanbul';
	}

	/**
	 * The method creates a new encryption tester text.
	 *
	 * @return bool
	 */
	public function create_encryption_tester_text() {
		if ( get_option( 'hezarfen_encryption_tester_text' ) ) {
			return false;
		}

		// Encryption key değerinin ileride değişip değişmediğini anlayabilmek için bir encryption yap ve options tablosuna yaz :).
		return update_option(
			'hezarfen_encryption_tester_text',
			$this->encrypt( 'Istanbul' )
		);
	}

	/**
	 * Set hezarfen encryption key if exists.
	 *
	 * @return void
	 */
	public function setEncryptionKey() {
		if ( $this->health_check() ) {
			$this->encryption_key = HEZARFEN_ENCRYPTION_KEY;
		}
	}

	/**
	 * Creates a new securely random key.
	 *
	 * @return string
	 */
	public function create_random_key() {
		return base64_encode( openssl_random_pseudo_bytes( 64 ) );
	}
}
