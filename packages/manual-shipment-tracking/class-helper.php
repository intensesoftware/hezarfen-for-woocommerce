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
			''                 => 'Kargo Firmasını Seçiniz',
			'Aras Kargo'       => 'Aras Kargo',
			'MNG Kargo'        => 'MNG Kargo',
			'Yurtiçi Kargo'    => 'Yurtiçi Kargo',
			'PTT Kargo'        => 'PTT Kargo',
			'UPS Kargo'        => 'UPS Kargo',
			'SÜRAT Kargo'      => 'SÜRAT Kargo',
			'hepsiJET'         => 'hepsiJET',
			'Trendyol Express' => 'Trendyol Express',
			'Kargoist'         => 'Kargoist',
			'Jetizz'           => 'Jetizz',
			'Gelal'            => 'Gelal',
			'Birgünde Kargo'   => 'Birgünde Kargo',
			'Scotty'           => 'Scotty',
			'PackUpp'          => 'PackUpp',
			'Kolay Gelsin'     => 'Kolay Gelsin',
			'CDEK'             => 'CDEK',
			'FedEx'            => 'FedEx',
			'Horoz Lojistik'   => 'Horoz Lojistik',
			'Kargo Türk'       => 'Kargo Türk',
			'Kurye'            => 'Kurye',
			'DHL'              => 'DHL',
			'TNT'              => 'TNT',
			'BRINKS'           => 'BRINKS Kargo',
			'Sendeo'           => 'Sendeo Kargo',
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
