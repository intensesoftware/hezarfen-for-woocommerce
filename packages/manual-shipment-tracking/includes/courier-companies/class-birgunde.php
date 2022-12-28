<?php
/**
 * Contains the Courier_Birgunde class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Courier_Birgunde class.
 */
class Courier_Birgunde extends Courier_Company {
	/**
	 * ID.
	 * 
	 * @var string
	 */
	public static $id = 'birgunde';

	/**
	 * Returns the title.
	 * 
	 * @param int|string $order_id Order ID.
	 * 
	 * @return string
	 */
	public static function get_title( $order_id = '' ) {
		return __( 'Birgünde Kargo', 'hezarfen-for-woocommerce' );
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

		$response = wp_remote_post(
			'https://birgundekargo.com/tr-TR/KargoApi/Gonder',
			array(
				'body'    => wp_json_encode( array( 'takipno' => $tracking_number ) ),
				'headers' => array( 'Content-Type' => 'application/json' ),
			)
		);
		$body     = json_decode( wp_remote_retrieve_body( $response ), true );

		return ! empty( $body['url'] ) ? $body['url'] : 'https://www.birgundekargo.com/online-takip';
	}
}
