<?php
/**
 * Helper class
 * 
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

/**
 * Helper class
 */
class Helper {
	/**
	 *
	 * Update array keys for select option values
	 *
	 * @param array $arr array of the districts.
	 * @return array
	 */
	public static function hezarfen_wc_checkout_select2_option_format( $arr ) {
		$values = array();

		foreach ( $arr as $key => $value ) {
			$values[ $value ] = $value;
		}

		return $values;
	}
}
