<?php
/**
 * Class Ajax.
 * 
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();


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
	 * An event that releases when neighborhood data is changed.
	 *
	 * @return void
	 */
	public function neighborhood_changed() {
		check_ajax_referer( 'mahalle-io-get-data', 'security' );

		/** That is specify expresses the checkout form part. $type can be billing|shipping */
		$type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : '';

		$city_plate_number = isset( $_POST['cityPlateNumber'] ) ? sanitize_text_field( $_POST['cityPlateNumber'] ) : '';
		$district          = isset( $_POST['district'] ) ? sanitize_text_field( $_POST['district'] ) : '';
		$neighborhood      = isset( $_POST['neighborhood'] ) ? sanitize_text_field( $_POST['neighborhood'] ) : '';

		$neighborhood_id = Mahalle_Local::get_neighborhood_id( $city_plate_number, $district, $neighborhood );

		if ( ! $neighborhood_id ) {
			echo wp_json_encode( array() );
			wp_die();
		}

		do_action(
			'hezarfen_checkout_neighborhood_changed',
			$neighborhood_id,
			$neighborhood,
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
				$neighborhood
			)
		);

		wp_die();
	}
}

new Ajax();
