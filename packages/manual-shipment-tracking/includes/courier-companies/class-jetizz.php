<?php
/**
 * Contains the Courier_Jetizz class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Courier_Jetizz class.
 */
class Courier_Jetizz extends Courier_Company {
	/**
	 * ID.
	 * 
	 * @var string
	 */
	public static $id = 'jetizz';

	/**
	 * Filename of the logo.
	 * 
	 * @var string
	 */
	public static $logo = 'jetizz-logo.png';

	/**
	 * Returns the title.
	 * 
	 * @param int|string $order_id Order ID.
	 * 
	 * @return string
	 */
	public static function get_title( $order_id = '' ) {
		return __( 'Jetizz', 'hezarfen-for-woocommerce' );
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

		return 'https://app.jetizz.com/gonderi-takip';
	}
}
