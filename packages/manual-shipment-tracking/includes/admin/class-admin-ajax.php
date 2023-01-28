<?php
/**
 * Contains the Admin_Ajax class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * The Admin_Ajax class.
 */
class Admin_Ajax {
	const GET_SHIPMENT_DATA_ACTION    = 'hezarfen_mst_get_shipment_data';
	const GET_SHIPMENT_DATA_NONCE     = 'hezarfen-mst-get-shipment-data';
	const REMOVE_SHIPMENT_DATA_ACTION = 'hezarfen_mst_remove_shipment_data';
	const REMOVE_SHIPMENT_DATA_NONCE  = 'hezarfen-mst-remove-shipment-data';

	/**
	 * Constructor
	 */
	public static function init() {
		add_action( 'wp_ajax_' . self::GET_SHIPMENT_DATA_ACTION, array( __CLASS__, 'get_shipment_data' ) );
		add_action( 'wp_ajax_' . self::REMOVE_SHIPMENT_DATA_ACTION, array( __CLASS__, 'remove_shipment_data' ) );
	}

	/**
	 * Outputs all shipment data of the given order.
	 * 
	 * @return void
	 */
	public static function get_shipment_data() {
		check_ajax_referer( self::GET_SHIPMENT_DATA_NONCE );

		if ( empty( $_GET['order_id'] ) ) {
			wp_send_json_error( null, 400 );
		}

		wp_send_json_success(
			Helper::get_all_shipment_data( intval( $_GET['order_id'] ) )
		);
	}

	/**
	 * Removes the given shipment data from db.
	 * 
	 * @return void
	 */
	public static function remove_shipment_data() {
		check_ajax_referer( self::REMOVE_SHIPMENT_DATA_NONCE );

		if ( empty( $_POST['order_id'] ) || empty( $_POST['data_id'] ) ) {
			wp_send_json_error( null, 400 );
		}

		$data = Helper::get_shipment_data_by_id( intval( $_POST['data_id'] ), intval( $_POST['order_id'] ), true );

		if ( $data ) {
			if ( $data->remove() ) {
				wp_send_json_success();
			} else {
				wp_send_json_error( null, 500 );
			}
		}

		wp_send_json_success();
	}
}
