<?php
/**
 * Contains the Courier_Kurye class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Courier_Kurye class.
 */
class Courier_Kurye extends Courier_Company {
	/**
	 * ID.
	 * 
	 * @var string
	 */
	public static $id = 'kurye';

	/**
	 * Returns the title.
	 * 
	 * @return string
	 */
	public static function get_title() {
		return __( 'Kurye', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Creates tracking URL.
	 * 
	 * @param string $tracking_number Tracking number.
	 * 
	 * @return string
	 */
	public static function create_tracking_url( $tracking_number ) {
		return '';
	}
}
