<?php
/**
 * Contains the Courier_Fedex class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Courier_Fedex class.
 */
class Courier_Fedex extends Courier_Company {
	/**
	 * ID.
	 * 
	 * @var string
	 */
	public static $id = 'fedex';

	/**
	 * Filename of the logo.
	 * 
	 * @var string
	 */
	public static $logo = 'fedex-logo.png';

	/**
	 * Returns the title.
	 * 
	 * @param int|string $order_id Order ID.
	 * 
	 * @return string
	 */
	public static function get_title( $order_id = '' ) {
		return __( 'FedEx', 'hezarfen-for-woocommerce' );
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

		return 'https://www.fedex.com/tr-tr/home.html';
	}
}
