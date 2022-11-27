<?php
/**
 * Contains the Courier_Trendyol_Express class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Courier_Trendyol_Express class.
 */
class Courier_Trendyol_Express extends Courier_Company {
	/**
	 * ID.
	 * 
	 * @var string
	 */
	public static $id = 'trendyol-express';

	/**
	 * Returns the title.
	 * 
	 * @return string
	 */
	public static function get_title() {
		return __( 'Trendyol Express', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Creates tracking URL.
	 * 
	 * @param string $tracking_number Tracking number.
	 * 
	 * @return string
	 */
	public static function create_tracking_url( $tracking_number ) {
		return 'https://kargotakip.trendyol.com/?orderNumber=' . $tracking_number;
	}
}
