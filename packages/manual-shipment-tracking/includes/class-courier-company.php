<?php
/**
 * Contains the Courier_Company abstract class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

require_once 'courier-companies/include-couriers.php';

/**
 * Courier_Company abstract class.
 */
abstract class Courier_Company {
	/**
	 * ID.
	 * 
	 * @var string
	 */
	public static $id;

	/**
	 * Returns the title.
	 * 
	 * @param int|string $order_id Order ID.
	 * 
	 * @return string
	 */
	abstract public static function get_title( $order_id = '' );

	/**
	 * Creates tracking URL.
	 * 
	 * @param string $tracking_number Tracking number.
	 * 
	 * @return string
	 */
	abstract public static function create_tracking_url( $tracking_number);
}
