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
	const DB_SHIPPED_ORDER_STATUS = 'wc-hezarfen-shipped';
	const SHIPPED_ORDER_STATUS    = 'hezarfen-shipped';

	const COURIER_COMPANY_ID_KEY    = 'hezarfen_mst_courier_company_id';
	const COURIER_COMPANY_TITLE_KEY = 'hezarfen_mst_courier_company_title';
	const TRACKING_NUM_KEY          = 'hezarfen_mst_tracking_number';
	const TRACKING_URL_KEY          = 'hezarfen_mst_tracking_url';

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
			$notification_provider->send( $order, self::DB_SHIPPED_ORDER_STATUS );
		}
	}

	/**
	 * Returns notification providers (ID => Title).
	 * 
	 * @return array<string, string>
	 */
	public static function get_notification_providers() {
		return array(
			Netgsm::$id => Netgsm::$title,
		);
	}

	/**
	 * Returns courier companies array for using as select options.
	 * 
	 * @return array<string, string>
	 */
	public static function courier_company_options() {
		// prepare the "ID => Courier title" array.
		foreach ( self::courier_companies() as $id => $courier_class ) {
			$options[ $id ] = $courier_class::get_title();
		}

		$options[''] = __( 'Please choose a courier company', 'hezarfen-for-woocommerce' );

		return $options;
	}

	/**
	 * Returns courier companies array (ID => Class name).
	 * 
	 * @return array<string, string>
	 */
	public static function courier_companies() {
		return array(
			''                            => __NAMESPACE__ . '\Courier_Empty',
			Courier_Aras::$id             => __NAMESPACE__ . '\Courier_Aras',
			Courier_MNG::$id              => __NAMESPACE__ . '\Courier_MNG',
			Courier_Yurtici::$id          => __NAMESPACE__ . '\Courier_Yurtici',
			Courier_PTT::$id              => __NAMESPACE__ . '\Courier_PTT',
			Courier_UPS::$id              => __NAMESPACE__ . '\Courier_UPS',
			Courier_Surat::$id            => __NAMESPACE__ . '\Courier_Surat',
			Courier_Hepsijet::$id         => __NAMESPACE__ . '\Courier_Hepsijet',
			Courier_Trendyol_Express::$id => __NAMESPACE__ . '\Courier_Trendyol_Express',
			Courier_Kargoist::$id         => __NAMESPACE__ . '\Courier_Kargoist',
			Courier_Jetizz::$id           => __NAMESPACE__ . '\Courier_Jetizz',
			Courier_Gelal::$id            => __NAMESPACE__ . '\Courier_Gelal',
			Courier_Birgunde::$id         => __NAMESPACE__ . '\Courier_Birgunde',
			Courier_Scotty::$id           => __NAMESPACE__ . '\Courier_Scotty',
			Courier_Packupp::$id          => __NAMESPACE__ . '\Courier_Packupp',
			Courier_Kolay_Gelsin::$id     => __NAMESPACE__ . '\Courier_Kolay_Gelsin',
			Courier_CDEK::$id             => __NAMESPACE__ . '\Courier_CDEK',
			Courier_Fedex::$id            => __NAMESPACE__ . '\Courier_Fedex',
			Courier_Horoz_Lojistik::$id   => __NAMESPACE__ . '\Courier_Horoz_Lojistik',
			Courier_Kargo_Turk::$id       => __NAMESPACE__ . '\Courier_Kargo_Turk',
			Courier_Sendeo::$id           => __NAMESPACE__ . '\Courier_Sendeo',
			Courier_Brinks::$id           => __NAMESPACE__ . '\Courier_Brinks',
			Courier_DHL::$id              => __NAMESPACE__ . '\Courier_DHL',
			Courier_TNT::$id              => __NAMESPACE__ . '\Courier_TNT',
		);
	}

	/**
	 * Returns the courier company class.
	 * 
	 * @param int|string $id Order ID or Courier ID.
	 * 
	 * @return string
	 */
	public static function get_courier_class( $id ) {
		$courier_companies = self::courier_companies();

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
		return get_post_meta( $order_id, self::COURIER_COMPANY_ID_KEY, true );
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
		return get_post_meta( $order_id, self::TRACKING_NUM_KEY, true );
	}

	/**
	 * Returns tracking URL of the order.
	 * 
	 * @param int|string $order_id Order ID.
	 * 
	 * @return string|false
	 */
	public static function get_tracking_url( $order_id ) {
		return get_post_meta( $order_id, self::TRACKING_URL_KEY, true );
	}
}
