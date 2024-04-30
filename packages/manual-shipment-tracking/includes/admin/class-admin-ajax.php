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
	const NEW_SHIPMENT_DATA_ACTION 	  = 'hezarfen_mst_new_shipment_data';
	const NEW_SHIPMENT_DATA_NONCE     = 'hezarfen_mst_new_shipment_data';
	const REMOVE_SHIPMENT_DATA_ACTION = 'hezarfen_mst_remove_shipment_data';
	const REMOVE_SHIPMENT_DATA_NONCE  = 'hezarfen-mst-remove-shipment-data';

	const DATA_ARRAY_KEY         = 'hezarfen_mst_shipment_data';
	const COURIER_HTML_NAME      = 'courier_company';
	const TRACKING_NUM_HTML_NAME = 'tracking_number';

	/**
	 * Initialization method.
	 * 
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_' . self::GET_SHIPMENT_DATA_ACTION, array( __CLASS__, 'get_shipment_data' ) );
		add_action( 'wp_ajax_' . self::NEW_SHIPMENT_DATA_ACTION, array( __CLASS__, 'new_shipment_data' ) );
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
	 * Adds new shipment tracking number/company.
	 * 
	 * @return void
	 */
	public static function new_shipment_data() {
		check_ajax_referer( self::NEW_SHIPMENT_DATA_NONCE );

		if ( empty( $_POST['order_id'] ) ) {
			wp_send_json_error( null, 400 );
		}

		$order_id = absint( $_POST['order_id'] );

		$order = wc_get_order( $order_id );

		$new_courier_id   = ! empty( $_POST[ self::COURIER_HTML_NAME ] ) ? sanitize_text_field( $_POST[ self::COURIER_HTML_NAME ] ) : '';
		$new_tracking_num = ! empty( $_POST[ self::TRACKING_NUM_HTML_NAME ] ) ? sanitize_text_field( $_POST[ self::TRACKING_NUM_HTML_NAME ] ) : '';

		if ( ! $new_courier_id || ( Courier_Kurye::$id !== $new_courier_id && ! $new_tracking_num ) ) {
			wp_send_json_error( null, 400 );
		}

		Helper::new_order_shipment_data($order, null, $new_courier_id, $new_tracking_num);
	}

	/**
	 * Removes the given shipment data from db.
	 * 
	 * @return void
	 */
	public static function remove_shipment_data() {
		check_ajax_referer( self::REMOVE_SHIPMENT_DATA_NONCE );

		if ( empty( $_POST['meta_id'] ) || empty( $_POST['meta_id'] ) ) {
			wp_send_json_error( null, 400 );
		}

		$data = Helper::get_shipment_data_by_id( intval( $_POST['meta_id'] ), intval( $_POST['order_id'] ), true );

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
