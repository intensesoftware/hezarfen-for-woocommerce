<?php
/**
 * Class Ajax.
 * 
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

use Hezarfen\Inc\Services\MahalleIO;
use Hezarfen\Inc\Mahalle_Local;

/**
 * The class handles AJAX operations.
 */
class Ajax {
	
	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
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
	
	/**
	 * Get Districts AJAX Endpoint
	 *
	 * @return void
	 */
	public function get_districts() {
		check_ajax_referer( 'mahalle-io-get-data', 'security' );

		// the variable begins with TR prefix.
		$city_plate_number_with_prefix = isset( $_POST['city_plate_number'] ) ? sanitize_text_field( $_POST['city_plate_number'] ) : '';

		if ( $city_plate_number_with_prefix ) {
			$districts = Mahalle_Local::get_districts( $city_plate_number_with_prefix );

			echo wp_json_encode( $districts );
		} else {
			echo wp_json_encode( array() );
		}

		wp_die();
	}
	
	/**
	 * Get Neighborhoods AJAX endpoint
	 *
	 * @return void
	 */
	public function get_neighborhoods() {
		check_ajax_referer( 'mahalle-io-get-data', 'security' );

		$district_data = isset( $_POST['district_id'] ) ? sanitize_text_field( $_POST['district_id'] ) : '';

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

		echo wp_json_encode( $neighborhoods );

		wp_die();
	}
	
	/**
	 * An event that releases when neighborhood data is changed.
	 *
	 * @return void
	 */
	public function neighborhood_changed() {
		check_ajax_referer( 'mahalle-io-get-data', 'security' );

		/** That is specify expresses the checkout form part. $type can be billing|shipping */
		$type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : '';

		$neighborhood_data = isset( $_POST['neighborhood_data'] ) ? sanitize_text_field( $_POST['neighborhood_data'] ) : '';

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
