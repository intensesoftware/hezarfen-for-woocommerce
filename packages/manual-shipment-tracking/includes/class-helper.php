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
			''                            => Courier_Empty::class,
			Courier_Aras::$id             => Courier_Aras::class,
			Courier_MNG::$id              => Courier_MNG::class,
			Courier_Yurtici::$id          => Courier_Yurtici::class,
			Courier_PTT::$id              => Courier_PTT::class,
			Courier_UPS::$id              => Courier_UPS::class,
			Courier_Surat::$id            => Courier_Surat::class,
			Courier_Hepsijet::$id         => Courier_Hepsijet::class,
			Courier_Trendyol_Express::$id => Courier_Trendyol_Express::class,
			Courier_Kargoist::$id         => Courier_Kargoist::class,
			Courier_Jetizz::$id           => Courier_Jetizz::class,
			Courier_Gelal::$id            => Courier_Gelal::class,
			Courier_Birgunde::$id         => Courier_Birgunde::class,
			Courier_Scotty::$id           => Courier_Scotty::class,
			Courier_Packupp::$id          => Courier_Packupp::class,
			Courier_Kolay_Gelsin::$id     => Courier_Kolay_Gelsin::class,
			Courier_CDEK::$id             => Courier_CDEK::class,
			Courier_Fedex::$id            => Courier_Fedex::class,
			Courier_Horoz_Lojistik::$id   => Courier_Horoz_Lojistik::class,
			Courier_Kargo_Turk::$id       => Courier_Kargo_Turk::class,
			Courier_Sendeo::$id           => Courier_Sendeo::class,
			Courier_Brinks::$id           => Courier_Brinks::class,
			Courier_DHL::$id              => Courier_DHL::class,
			Courier_TNT::$id              => Courier_TNT::class,
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
