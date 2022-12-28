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
			$notification_provider->send( $order );
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
	 * @param int|string $id Courier ID.
	 * 
	 * @return string
	 */
	public static function get_courier_class( $id ) {
		$courier_companies = Manual_Shipment_Tracking::courier_companies();

		if ( self::is_custom_courier( $id ) ) {
			return Courier_Custom::class;
		}

		return $courier_companies[ $id ] ?? $courier_companies[''];
	}

	/**
	 * Returns the default courier company ID.
	 * 
	 * @return string
	 */
	public static function get_default_courier_id() {
		return get_option( Settings::OPT_DEFAULT_COURIER, '' );
	}

	/**
	 * Checks if courier is the custom courier.
	 * 
	 * @param string $courier_id Courier ID.
	 * 
	 * @return bool
	 */
	public static function is_custom_courier( $courier_id ) {
		return Courier_Custom::$id === $courier_id;
	}


	/**
	 * Returns the shipment data of the given order by shipment data ID.
	 * 
	 * @param int|string $data_id Shipment data ID.
	 * @param int|string $order_id Order ID.
	 * 
	 * @return Shipment_Data|null
	 */
	public static function get_shipment_data_by_id( $data_id, $order_id ) {
		$shipment_data = self::get_all_shipment_data( $order_id );

		foreach ( $shipment_data as $data ) {
			if ( $data->id === (int) $data_id ) {
				return $data;
			}
		}

		return null;
	}

	/**
	 * Returns all shipment data of the given order.
	 * 
	 * @param int|string $order_id Order ID.
	 * 
	 * @return Shipment_Data[]
	 */
	public static function get_all_shipment_data( $order_id ) {
		$all_data = get_post_meta( $order_id, Manual_Shipment_Tracking::SHIPMENT_DATA_KEY );

		if ( ! $all_data ) {
			return apply_filters( 'hezarfen_mst_get_shipment_data', array(), $order_id );
		}

		return array_map(
			function( $data ) {
				return new Shipment_Data( $data );
			},
			$all_data 
		);
	}

	/**
	 * Registers a new order status.
	 * 
	 * @param array<string, mixed> $status_data Status data.
	 * 
	 * @return void
	 */
	public static function register_new_order_status( $status_data ) {
		add_filter(
			'woocommerce_register_shop_order_post_statuses',
			function ( $wc_order_statuses ) use ( $status_data ) {
				$wc_order_statuses[ $status_data['id'] ] = $status_data['data'];
				return $wc_order_statuses;
			} 
		);

		add_filter(
			'wc_order_statuses',
			function ( $wc_order_statuses ) use ( $status_data ) {
				$wc_order_statuses[ $status_data['id'] ] = $status_data['label'];
				return $wc_order_statuses;
			} 
		);
	}
}
