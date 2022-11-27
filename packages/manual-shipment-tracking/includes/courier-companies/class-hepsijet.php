<?php
/**
 * Contains the Courier_Hepsijet class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Courier_Hepsijet class.
 */
class Courier_Hepsijet extends Courier_Company {
	/**
	 * ID.
	 * 
	 * @var string
	 */
	public static $id = 'hepsijet';

	/**
	 * Returns the title.
	 * 
	 * @return string
	 */
	public static function get_title() {
		return __( 'hepsiJET', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Creates tracking URL.
	 * 
	 * @param string $tracking_number Tracking number.
	 * 
	 * @return string
	 */
	public static function create_tracking_url( $tracking_number ) {
		return 'https://www.hepsijet.com/gonderi-takibi/' . $tracking_number;
	}
}
