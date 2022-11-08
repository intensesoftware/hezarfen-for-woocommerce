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
	const COURIER_COMPANY_KEY = 'in_hez_mst_courier_company';
	const TRACKING_NUM_KEY    = 'in_hez_mst_tracking_number';

	/**
	 * Returns courier companies array.
	 * 
	 * @return array<string, string>
	 */
	public static function courier_companies() {
		return array(
			''                 => __( 'Please choose a courier company', 'hezarfen-for-woocommerce' ),
			'Aras Kargo'       => __( 'Aras Kargo', 'hezarfen-for-woocommerce' ),
			'MNG Kargo'        => __( 'MNG Kargo', 'hezarfen-for-woocommerce' ),
			'Yurtiçi Kargo'    => __( 'Yurtiçi Kargo', 'hezarfen-for-woocommerce' ),
			'PTT Kargo'        => __( 'PTT Kargo', 'hezarfen-for-woocommerce' ),
			'UPS Kargo'        => __( 'UPS Kargo', 'hezarfen-for-woocommerce' ),
			'SÜRAT Kargo'      => __( 'SÜRAT Kargo', 'hezarfen-for-woocommerce' ),
			'hepsiJET'         => __( 'hepsiJET', 'hezarfen-for-woocommerce' ),
			'Trendyol Express' => __( 'Trendyol Express', 'hezarfen-for-woocommerce' ),
			'Kargoist'         => __( 'Kargoist', 'hezarfen-for-woocommerce' ),
			'Jetizz'           => __( 'Jetizz', 'hezarfen-for-woocommerce' ),
			'Gelal'            => __( 'Gelal', 'hezarfen-for-woocommerce' ),
			'Birgünde Kargo'   => __( 'Birgünde Kargo', 'hezarfen-for-woocommerce' ),
			'Scotty'           => __( 'Scotty', 'hezarfen-for-woocommerce' ),
			'PackUpp'          => __( 'PackUpp', 'hezarfen-for-woocommerce' ),
			'Kolay Gelsin'     => __( 'Kolay Gelsin', 'hezarfen-for-woocommerce' ),
			'CDEK'             => __( 'CDEK', 'hezarfen-for-woocommerce' ),
			'FedEx'            => __( 'FedEx', 'hezarfen-for-woocommerce' ),
			'Horoz Lojistik'   => __( 'Horoz Lojistik', 'hezarfen-for-woocommerce' ),
			'Kargo Türk'       => __( 'Kargo Türk', 'hezarfen-for-woocommerce' ),
			'Kurye'            => __( 'Kurye', 'hezarfen-for-woocommerce' ),
			'DHL'              => __( 'DHL', 'hezarfen-for-woocommerce' ),
			'TNT'              => __( 'TNT', 'hezarfen-for-woocommerce' ),
			'BRINKS'           => __( 'BRINKS Kargo', 'hezarfen-for-woocommerce' ),
			'Sendeo'           => __( 'Sendeo Kargo', 'hezarfen-for-woocommerce' ),
		);
	}

	/**
	 * Returns courier company of the order.
	 * 
	 * @param int|string $order_id Order ID.
	 * 
	 * @return string|false
	 */
	public static function get_courier_company( $order_id ) {
		return get_post_meta( $order_id, self::COURIER_COMPANY_KEY, true );
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
}
