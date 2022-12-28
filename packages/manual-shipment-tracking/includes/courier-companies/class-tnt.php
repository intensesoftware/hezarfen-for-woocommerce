<?php
/**
 * Contains the Courier_TNT class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Courier_TNT class.
 */
class Courier_TNT extends Courier_Company {
	/**
	 * ID.
	 * 
	 * @var string
	 */
	public static $id = 'tnt';

	/**
	 * Filename of the logo.
	 * 
	 * @var string
	 */
	public static $logo = 'tnt-logo.svg';

	/**
	 * Returns the title.
	 * 
	 * @param int|string $order_id Order ID.
	 * 
	 * @return string
	 */
	public static function get_title( $order_id = '' ) {
		return __( 'TNT', 'hezarfen-for-woocommerce' );
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
