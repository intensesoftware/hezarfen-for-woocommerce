<?php
/**
 * Contains the Courier_Gelal class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Courier_Gelal class.
 */
class Courier_Gelal extends Courier_Company {
	/**
	 * ID.
	 * 
	 * @var string
	 */
	public static $id = 'gelal';

	/**
	 * Filename of the logo.
	 * 
	 * @var string
	 */
	public static $logo = 'gelal-logo.png';

	/**
	 * Returns the title.
	 * 
	 * @param int|string $order_id Order ID.
	 * 
	 * @return string
	 */
	public static function get_title( $order_id = '' ) {
		return __( 'Gelal', 'hezarfen-for-woocommerce' );
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

		return 'https://gelal.com/api/map/v1/map/' . $tracking_number;
	}
}
