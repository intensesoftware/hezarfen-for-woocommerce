<?php

namespace Hezarfen\Inc\Data;

defined('ABSPATH') || exit();

use Hezarfen\Inc\Data\Abstracts\Abstract_Encryption;

class ServiceCredentialEncryption extends Abstract_Encryption
{
	protected $encryption_key;

	public function __construct()
	{
		$this->setEncryptionKey();
	}

	/**
	 * setEncryptionKey
	 *
	 * @return void
	 */
	public function setEncryptionKey()
	{
		if( defined( 'LOGGED_IN_KEY' ) && LOGGED_IN_KEY !== '' )
		{
			$this->encryption_key = LOGGED_IN_KEY;
		}
	}
}
