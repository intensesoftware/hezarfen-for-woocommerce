<?php
/**
 * The class manages plugin activation processes.
 * 
 * TODO: review this class and separate the logic of activation and installation.
 * 
 * @package Hezarfen\Inc
 */

defined( 'ABSPATH' ) || exit();

/**
 * Hezarfen_Install
 */
class Hezarfen_Install {
	
	/**
	 * Install
	 *
	 * @return void
	 */
	public static function install() {
		self::update_version();

		self::update_db_version();
	}
	
	/**
	 * Update Hezarfen version info on the options table.
	 *
	 * @return void
	 */
	public static function update_version() {
		delete_option( 'hezarfen_version' );
		add_option( 'hezarfen_version', WC_HEZARFEN_VERSION );
	}
	
	/**
	 * Update Hezarfen DB version info on the options table.
	 *
	 * @return void
	 */
	public static function update_db_version() {
		delete_option( 'hezarfen_db_version' );
		add_option( 'hezarfen_db_version', WC_HEZARFEN_VERSION );
	}
}
