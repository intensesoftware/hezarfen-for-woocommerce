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
	 * @return string
	 */
	public static function get_title() {
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
