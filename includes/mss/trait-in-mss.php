<?php
/**
 * Yardımcı methodlar
 *
 * @package Intense\MSS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait In_MSS_Utility {
    /**
	 * Hezarfen aktif mi?
	 *
	 * @return void
	 */
	private function hezarfen_aktif() {
		return defined( 'WC_HEZARFEN_VERSION' );
	}
}