<?php
/**
 * Contains the class that adds district and neighborhood select elements to the My Account edit address pages.
 * 
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

/**
 * Adds district and neighborhood select elements to the My Account edit address pages.
 */
class My_Account {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'woocommerce_address_to_edit', array( $this, 'convert_to_select_elements' ), PHP_INT_MAX - 1, 2 );
	}

	/**
	 * Converts district and neighborhood input elements to select elements and supplies necessary select options.
	 * 
	 * @param array  $address Address fields.
	 * @param string $load_address Address type (billing or shipping).
	 * 
	 * @return array
	 */
	public function convert_to_select_elements( $address, $load_address ) {
		$address[ $load_address . '_city' ]['type']      = 'select';
		$address[ $load_address . '_address_1' ]['type'] = 'select';

		$province_code = $address[ $load_address . '_state' ]['value'];

		if ( $province_code ) {
			$districts                                     = Mahalle_Local::get_districts( $province_code );
			$address[ $load_address . '_city' ]['options'] = Helper::select2_option_format( $districts );

			$district = $address[ $load_address . '_city' ]['value'];
			if ( $district ) {
				$nbrhoods = Mahalle_Local::get_neighborhoods( $province_code, $district, false );
				$address[ $load_address . '_address_1' ]['options'] = Helper::select2_option_format( $nbrhoods );
			}
		}       

		return $address;
	}
}

new My_Account();
