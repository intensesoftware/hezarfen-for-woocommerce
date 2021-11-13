<?php
/**
 * Helpers
 * 
 * @package Hezarfen\Inc
 */

defined( 'ABSPATH' ) || exit();

/**
 *
 * Update array keys for select option values
 *
 * @param array $arr array of the districts.
 * @return array
 */
function hezarfen_wc_checkout_select2_option_format( $arr ) {
	$values = array();

	foreach ( $arr as $key => $value ) {
		$values[ sprintf( '%d:%s', $key, $value ) ] = $value;
	}

	return $values;
}
