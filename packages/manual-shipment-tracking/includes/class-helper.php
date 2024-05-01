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
	 * Add new order shipment data
	 *
	 * @param  \WC_Order $order Order.
	 * @param  int       $id Shipment ID uniq in the shipment of the order.
	 * @param  string    $new_courier_id Shipping Company ID.
	 * @param  string    $new_tracking_num Tracking Number.
	 * @param  int|null  $meta_id = null
	 * @return void
	 */
	public static function new_order_shipment_data( $order, $deprecated, $new_courier_id, $new_tracking_num, $meta_id = null ) {
		$order_id = $order->get_id();

		$new_courier  = self::get_courier_class( $new_courier_id );
		$current_data = self::get_shipment_data_by_id( $meta_id, $order_id, true );

		if ( ! $current_data ) {
			$new_data = new Shipment_Data(
				array(
					'id'            => null,
					'order_id'      => $order_id,
					'courier_id'    => $new_courier_id,
					'courier_title' => $new_courier::get_title(),
					'tracking_num'  => $new_tracking_num,
					'tracking_url'  => $new_courier::create_tracking_url( $new_tracking_num ),
				),
				null
			);

			$new_data->save( true );
			do_action( 'hezarfen_mst_shipment_data_saved', $order, $new_data );

			return;
		}

		if ( $current_data->courier_id === $new_courier_id && $current_data->tracking_num === $new_tracking_num ) {
			return;
		}

		$current_data->courier_id    = $new_courier_id;
		$current_data->courier_title = $new_courier::get_title();
		$current_data->tracking_num  = $new_tracking_num;
		$current_data->tracking_url  = $new_courier::create_tracking_url( $new_tracking_num );

		$result = $current_data->save();

		if ( true === $result ) {
			do_action( 'hezarfen_mst_shipment_data_saved', $order, $current_data );
		}
	}

	/**
	 * Sends notification.
	 *
	 * @param \WC_Order $order Order instance.
	 * @param  Shipment_Data $shipment_data Shipment data.
	 *
	 * @return void
	 */
	public static function send_notification( $order, $shipment_data) {
		$notification_provider = Manual_Shipment_Tracking::instance()->active_notif_provider;
		if ( $notification_provider ) {
			$notification_provider->send( $order, $shipment_data );
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
	 * @param bool $first_item_blank Is first item must be blank?.
	 * 
	 * @return array<string, string>
	 */
	public static function courier_company_options( $first_item_blank = false ) {
		// prepare the "ID => Courier title" array.
		foreach ( Manual_Shipment_Tracking::courier_companies() as $id => $courier_class ) {
			$options[ $id ] = $courier_class::get_title();
		}

		if ( $first_item_blank ) {
			$options[''] = '';
		} else {
			$options[''] = __( 'Choose a courier company', 'hezarfen-for-woocommerce' );
		}

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
	 * @param int|null $meta_id Meta ID.
	 * @param int|string $order_id Order ID.
	 * @param bool       $bypass_filters Whether to bypass filters.
	 * 
	 * @return Shipment_Data|null
	 */
	public static function get_shipment_data_by_id( $meta_id, $order_id, $bypass_filters = false ) {
		$shipment_data = self::get_all_shipment_data( $order_id, $bypass_filters );

		foreach ( $shipment_data as $data ) {
			if ( $data->meta_id === (int) $meta_id ) {
				return $data;
			}
		}

		return null;
	}

	/**
	 * Returns all shipment data of the given order.
	 * 
	 * @param int|string $order_id Order ID.
	 * @param bool       $bypass_filters Whether to bypass filters.
	 * 
	 * @return Shipment_Data[]
	 */
	public static function get_all_shipment_data( $order_id, $bypass_filters = false ) {
		$order = wc_get_order( $order_id );

		if( ! $order ) {
			return array();
		}

		$all_data = $order->get_meta( Manual_Shipment_Tracking::SHIPMENT_DATA_KEY, false );

		if ( ! $all_data && ! $bypass_filters ) {
			return apply_filters( 'hezarfen_mst_get_shipment_data', array(), $order_id );
		}

		$stack = array();

		foreach( $all_data as $data ){
			$stack[] = new Shipment_Data( $data->value, $data->id );
		}

		return $stack;
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
