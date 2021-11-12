<?php
/**
 * The class provides encrpytion support for the service credentials.
 * 
 * @package Hezarfen\Inc\Data
 */

namespace Hezarfen\Inc\Data;

defined( 'ABSPATH' ) || exit();

use Hezarfen\Inc\Data\Abstracts\Abstract_Encryption;

/**
 * ServiceCredentialEncryption
 */
class ServiceCredentialEncryption extends Abstract_Encryption {
	
	/**
	 * Encryption key
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
	 * The method is a setter method for the encryption_key property.
	 *
	 * @return void
	 */
	public function setEncryptionKey() {
		if ( defined( 'LOGGED_IN_KEY' ) && LOGGED_IN_KEY !== '' ) {
			$this->encryption_key = LOGGED_IN_KEY;
		}
	}
}
