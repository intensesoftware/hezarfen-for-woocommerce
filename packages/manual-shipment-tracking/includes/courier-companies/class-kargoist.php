<?php
/**
 * Contains the Courier_Kargoist class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Courier_Kargoist class.
 */
class Courier_Kargoist extends Courier_Company {
	/**
	 * ID.
	 * 
	 * @var string
	 */
	public static $id = 'kargoist';

	/**
	 * Filename of the logo.
	 * 
	 * @var string
	 */
	public static $logo = 'kargoist-logo.svg';

	/**
	 * Returns the title.
	 * 
	 * @param int|string $order_id Order ID.
	 * 
	 * @return string
	 */
	public static function get_title( $order_id = '' ) {
		return __( 'Kargoist', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Creates tracking URL.
	 * 
	 * @param string $tracking_number Tracking number.
	 * 
	 * @return string
	 */
	public static function create_tracking_url( $tracking_number ) {
		if ( ! $tracking_number ) {
			return '';
		}

		return 'https://kargotakip.kargoist.com/tracking?har_kod=' . $tracking_number;
	}
}
