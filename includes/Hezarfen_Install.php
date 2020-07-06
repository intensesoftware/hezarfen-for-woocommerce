<?php

defined( 'ABSPATH' ) || exit;

class Hezarfen_Install
{

	public static function install(){


		self::update_version();

		self::update_db_version();


	}


	public static function update_version(){


		delete_option( 'hezarfen_version' );
		add_option( 'hezarfen_version', WC_HEZARFEN_VERSION );


	}


	public static function update_db_version(){


		delete_option( 'hezarfen_db_version' );
		add_option( 'hezarfen_db_version', WC_HEZARFEN_VERSION );


	}


}