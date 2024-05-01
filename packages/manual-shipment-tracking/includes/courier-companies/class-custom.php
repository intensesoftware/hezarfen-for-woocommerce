<?php
/**
 * Contains the Courier_Custom class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Courier_Custom class.
 */
class Courier_Custom extends Courier_Company {
	/**
	 * ID.
	 * 
	 * @var string
	 */
	public static $id = 'custom';

	/**
	 * Filename of the logo.
	 * 
	 * @var string
	 */
	public static $logo = '';

	/**
	 * Returns the title.
	 * 
	 * @param int|string $order_id Order ID.
	 * 
	 * @return string
	 */
	public static function get_title( $order_id = '' ) {
		if ( ! $order_id ) {
			return '';
		}

		$order = wc_get_order( $order_id );

		return $order->get_meta( get_option( Settings::OPT_COURIER_CUSTOM_META, '' ), true );
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
