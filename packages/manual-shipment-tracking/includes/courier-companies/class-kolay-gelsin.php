<?php
/**
 * Contains the Courier_Kolay_Gelsin class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Courier_Kolay_Gelsin class.
 */
class Courier_Kolay_Gelsin extends Courier_Company {
	/**
	 * ID.
	 * 
	 * @var string
	 */
	public static $id = 'kolay-gelsin';

	/**
	 * Filename of the logo.
	 * 
	 * @var string
	 */
	public static $logo = 'kolay-gelsin-logo.png';

	/**
	 * Returns the title.
	 * 
	 * @param int|string $order_id Order ID.
	 * 
	 * @return string
	 */
	public static function get_title( $order_id = '' ) {
		return __( 'Kolay Gelsin', 'hezarfen-for-woocommerce' );
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

		return 'https://esube.kolaygelsin.com/shipments?trackingId=' . $tracking_number;
	}
}
