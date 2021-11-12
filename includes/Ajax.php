<?php
/**
 * Class Ajax.
 * 
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

use Hezarfen\Inc\Services\MahalleIO;

/**
 * The class handles AJAX operations.
 */
class Ajax {

	function __construct() {
		add_action(
			'wp_ajax_wc_hezarfen_get_districts',
			array(
				$this,
				'get_districts',
			)
		);
		add_action(
			'wp_ajax_nopriv_wc_hezarfen_get_districts',
			array(
				$this,
				'get_districts',
			)
		);

		add_action(
			'wp_ajax_wc_hezarfen_get_neighborhoods',
			array(
				$this,
				'get_neighborhoods',
			)
		);
		add_action(
			'wp_ajax_nopriv_wc_hezarfen_get_neighborhoods',
			array(
				$this,
				'get_neighborhoods',
			)
		);

		add_action(
			'wp_ajax_wc_hezarfen_neighborhood_changed',
			array(
				$this,
				'neighborhood_changed',
			)
		);
		add_action(
			'wp_ajax_nopriv_wc_hezarfen_neighborhood_changed',
			array(
				$this,
				'neighborhood_changed',
			)
		);
	}

	function get_districts() {
		check_ajax_referer( 'mahalle-io-get-data', 'security' );

		$city_plate_number_with_TR = sanitize_text_field( $_POST['city_plate_number'] );

		$city_plate_number = explode( 'TR', $city_plate_number_with_TR );

		$city_plate_number = intval( $city_plate_number[1] );

		if ( ! $city_plate_number ) {
			echo wp_json_encode( array() );
			wp_die();
		}

		$get_districts_response = MahalleIO::get_districts( $city_plate_number );

		// if get_districts failed, return empty array.
		/**
		 * Todo: fire a notification about failed mahalle.io connection
		 */
		if ( is_wp_error( $get_districts_response ) ) {
			$districts = array();
		} else {
			$districts = $get_districts_response;
		}

		// return result
		echo wp_json_encode( $districts );

		wp_die();
	}

	function get_neighborhoods() {
		check_ajax_referer( 'mahalle-io-get-data', 'security' );

		$district_data = sanitize_text_field( $_POST['district_id'] );

		$district_data_array = explode( ':', $district_data );

		$district_id = intval( $district_data_array[0] );

		if ( ! $district_id ) {
			echo wp_json_encode( array() );
			wp_die();
		}

		$get_neighborhoods_response = MahalleIO::get_neighborhoods(
			$district_id
		);

		// if get_neighborhoods failed, return empty array.
		/**
		 * Todo: fire a notification about failed mahalle.io connection
		 */
		if ( is_wp_error( $get_neighborhoods_response ) ) {
			$neighborhoods = array();
		} else {
			$neighborhoods = $get_neighborhoods_response;
		}

		// return result
		echo wp_json_encode( $neighborhoods );

		wp_die();
	}

	function neighborhood_changed() {
		check_ajax_referer( 'mahalle-io-get-data', 'security' );

		$type = sanitize_key( $_POST['type'] );

		$neighborhood_data = sanitize_text_field( $_POST['neighborhood_data'] );

		$neighborhood_data_arr = explode( ':', $neighborhood_data );

		$neighborhood_id = intval( $neighborhood_data_arr[0] );

		if ( ! $neighborhood_id ) {
			echo wp_json_encode( array() );
			wp_die();
		}

		$neighborhood_name = $neighborhood_data_arr[1];

		do_action(
			'hezarfen_checkout_neighborhood_changed',
			$neighborhood_id,
			$neighborhood_name,
			$type
		);

		$args = array(
			'update_checkout' => true,
		);

		echo wp_json_encode(
			apply_filters(
				'hezarfen_checkout_neighborhood_changed_output_args',
				$args,
				$neighborhood_id,
				$neighborhood_name
			)
		);

		wp_die();
	}
}

new Ajax();
