<?php
/**
 * Contains the helper class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Helper class.
 */
class Helper {
	/**
	 * Sends notification.
	 * 
	 * @param \WC_Order $order Order instance.
	 * 
	 * @return void
	 */
	public static function send_notification( $order ) {
		$notification_provider = Manual_Shipment_Tracking::instance()->active_notif_provider;
		if ( $notification_provider ) {
			$notification_provider->send( $order, Manual_Shipment_Tracking::DB_SHIPPED_ORDER_STATUS );
		}
	}

	/**
	 * Returns the providers that are not ready to be used as a notification provider.
	 * 
	 * @return string[]
	 */
	public static function get_not_ready_providers() {
		$not_ready = array();
		foreach ( Manual_Shipment_Tracking::notification_providers() as $id => $class ) {
			if ( ! $class::is_plugin_ready() ) {
				$not_ready[] = $id;
			}
		}

		return $not_ready;
	}

	/**
	 * Returns courier companies array for using as select options.
	 * 
	 * @return array<string, string>
	 */
	public static function courier_company_options() {
		// prepare the "ID => Courier title" array.
		foreach ( Manual_Shipment_Tracking::courier_companies() as $id => $courier_class ) {
			$options[ $id ] = $courier_class::get_title();
		}

		$options[''] = __( 'Please choose a courier company', 'hezarfen-for-woocommerce' );

		return $options;
	}

	/**
	 * Returns the courier company class.
	 * 
	 * @param int|string $id Order ID or Courier ID.
	 * 
	 * @return string
	 */
	public static function get_courier_class( $id ) {
		$courier_companies = Manual_Shipment_Tracking::courier_companies();

		if ( is_numeric( $id ) ) { // $id is an oder ID.
			return $courier_companies[ self::get_courier_id( $id ) ] ?? $courier_companies[''];
		} else { // $id is a courier company ID.
			return $courier_companies[ $id ] ?? $courier_companies[''];
		}
	}

	/**
	 * Returns the courier company ID of the order.
	 * 
	 * @param int|string $order_id Order ID.
	 * 
	 * @return string
	 */
	public static function get_courier_id( $order_id ) {
		return get_post_meta( $order_id, Manual_Shipment_Tracking::COURIER_COMPANY_ID_KEY, true );
	}

	/**
	 * Returns the default courier company ID.
	 * 
	 * @return string
	 */
	public static function get_default_courier_id() {
		return get_option( 'hezarfen_mst_default_courier_company', '' );
	}

	/**
	 * Returns tracking number of the order.
	 * 
	 * @param int|string $order_id Order ID.
	 * 
	 * @return string|false
	 */
	public static function get_tracking_num( $order_id ) {
		return get_post_meta( $order_id, Manual_Shipment_Tracking::TRACKING_NUM_KEY, true );
	}

	/**
	 * Returns tracking URL of the order.
	 * 
	 * @param int|string $order_id Order ID.
	 * 
	 * @return string|false
	 */
	public static function get_tracking_url( $order_id ) {
		return get_post_meta( $order_id, Manual_Shipment_Tracking::TRACKING_URL_KEY, true );
	}
}
