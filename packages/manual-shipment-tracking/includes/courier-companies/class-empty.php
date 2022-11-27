<?php
/**
 * Contains the Courier_Empty class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Empty courier company class.
 */
class Courier_Empty extends Courier_Company {
	/**
	 * ID.
	 * 
	 * @var string
	 */
	public static $id = '';

	/**
	 * Returns the title.
	 * 
	 * @return string
	 */
	public static function get_title() {
		return '';
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
