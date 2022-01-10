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
