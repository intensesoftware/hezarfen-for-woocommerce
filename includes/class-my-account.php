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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
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

		$customer_province_code              = $address[ $province_key ]['value'];
		$customer_district                   = $address[ $district_key ]['value'];
		$address[ $district_key ]['options'] = Helper::select2_option_format( Mahalle_Local::get_districts( $customer_province_code ) );
		$address[ $nbrhood_key ]['options']  = Helper::select2_option_format( Mahalle_Local::get_neighborhoods( $customer_province_code, $customer_district, false ) );

		return $address;
	}

	/**
	 * Enqueues scripts.
	 * 
	 * @return void
	 */
	public function enqueue_scripts() {
		global $wp;

		if ( is_account_page() && ! empty( $wp->query_vars['edit-address'] ) ) {
			wp_enqueue_script( 'wc_hezarfen_my_account_addresses_js', plugins_url( 'assets/js/my-account-addresses.js', WC_HEZARFEN_FILE ), array( 'jquery', 'wc_hezarfen_mahalle_helper_js' ), WC_HEZARFEN_VERSION, true );
		}
	}
}

new My_Account();
