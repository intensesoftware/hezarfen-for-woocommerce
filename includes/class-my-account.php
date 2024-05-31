<?php
/**
 * Contains the class that adds features related to the My Account edit address pages.
 * 
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

/**
 * Adds features related to the My Account edit address pages.
 */
class My_Account {
	/**
	 * Constructor
	 */
	public function __construct() {
		if( hez_hide_district_neighborhood() ) {
			return;
		}

		add_filter( 'woocommerce_address_to_edit', array( $this, 'convert_to_select_elements' ), PHP_INT_MAX - 1, 2 );
		add_action( 'woocommerce_after_save_address_validation', array( $this, 'save_customer_object' ), PHP_INT_MAX - 1, 4 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		if ( 'yes' === get_option( 'hezarfen_sort_my_account_fields', 'no' ) ) {
			// we need to use an action that fires after the 'posts_selection' action to access the is_account_page() function. (https://woocommerce.com/document/conditional-tags/).
			add_action( 'wp', array( $this, 'sort_address_fields' ) );
		}

		if ( 'yes' === get_option( 'hezarfen_hide_my_account_postcode_fields', 'no' ) ) {
			add_action( 'wp', array( $this, 'hide_postcode_field' ) );
		}
	}

	/**
	 * Converts district and neighborhood input elements to select elements and supplies necessary select options.
	 * 
	 * @param array<string, mixed> $address Address fields.
	 * @param string               $load_address Address type (billing or shipping).
	 * 
	 * @return array<string, mixed>
	 */
	public function convert_to_select_elements( $address, $load_address ) {
		$province_key = $load_address . '_state';
		$district_key = $load_address . '_city';
		$nbrhood_key  = $load_address . '_address_1';

		$address[ $district_key ]['type'] = 'select';
		$address[ $nbrhood_key ]['type']  = 'select';

		$customer_province_code              = $address[ $province_key ]['value'];
		$customer_district                   = $address[ $district_key ]['value'];
		$address[ $district_key ]['options'] = Helper::select2_option_format( Mahalle_Local::get_districts( $customer_province_code ) );
		$address[ $nbrhood_key ]['options']  = Helper::select2_option_format( Mahalle_Local::get_neighborhoods( $customer_province_code, $customer_district, false ) );

		return $address;
	}

	/**
	 * Saves the customer object to prevent problems if there are errors when saving address.
	 *
	 * @param int                  $user_id User ID being saved.
	 * @param string               $load_address Type of address e.g. billing or shipping.
	 * @param array<string, mixed> $address The address fields.
	 * @param \WC_Customer         $customer The customer object being saved.
	 * 
	 * @return void
	 */
	public function save_customer_object( $user_id, $load_address, $address, $customer ) {
		if ( wc_notice_count( 'error' ) > 0 ) {
			$customer->save();
		}
	}

	/**
	 * Sorts the address fields.
	 * 
	 * @return void
	 */
	public function sort_address_fields() {
		if ( Helper::is_edit_address_page() ) {
			Helper::sort_address_fields();
		}
	}

	/**
	 * Hides the postcode field.
	 * 
	 * @return void
	 */
	public function hide_postcode_field() {
		if ( Helper::is_edit_address_page() ) {
			Helper::hide_postcode_field();
		}
	}

	/**
	 * Enqueues scripts.
	 * 
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( Helper::is_edit_address_page() ) {
			wp_enqueue_script( 'wc_hezarfen_my_account_addresses_js', plugins_url( 'assets/js/my-account-addresses.js', WC_HEZARFEN_FILE ), array( 'jquery', 'wc_hezarfen_mahalle_helper_js' ), WC_HEZARFEN_VERSION, true );
		}
	}
}

new My_Account();
