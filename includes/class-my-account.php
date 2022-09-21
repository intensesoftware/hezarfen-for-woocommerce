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
		$province_key = $load_address . '_state';
		$district_key = $load_address . '_city';
		$nbrhood_key  = $load_address . '_address_1';

		$address[ $district_key ]['type']       = 'select';
		$address[ $nbrhood_key ]['type']        = 'select';
		$address[ $nbrhood_key ]['placeholder'] = __( 'Neighborhood', 'hezarfen-for-woocommerce' );

		$customer_province_code              = $address[ $province_key ]['value'];
		$customer_district                   = $address[ $district_key ]['value'];
		$address[ $district_key ]['options'] = Helper::select2_option_format( Mahalle_Local::get_districts( $customer_province_code ) );
		$address[ $nbrhood_key ]['options']  = Helper::select2_option_format( Mahalle_Local::get_neighborhoods( $customer_province_code, $customer_district, false ) );

		return $address;
	}
}

new My_Account();