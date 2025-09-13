<?php
/**
 * Contains the Courier_Hepsijet_Entegrasyon class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Courier_Hepsijet_Entegrasyon class for integration version.
 */
class Courier_Hepsijet_Entegrasyon extends Courier_Company {
	/**
	 * ID.
	 * 
	 * @var string
	 */
	public static $id = 'hepsijet-entegrasyon';

	/**
	 * Filename of the logo.
	 * 
	 * @var string
	 */
	public static $logo = 'hepsijet-logo.svg';

	/**
	 * Returns the title.
	 * 
	 * @param int|string $order_id Order ID.
	 * 
	 * @return string
	 */
	public static function get_title( $order_id = '' ) {
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
		if ( ! $tracking_number ) {
			return '';
		}

		return 'https://www.hepsijet.com/gonderi-takibi/' . $tracking_number;
	}
}