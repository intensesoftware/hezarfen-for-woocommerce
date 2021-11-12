<?php
/**
 * Class MahalleIO.
 * 
 * @package Hezarfen\Inc\Services
 */

namespace Hezarfen\Inc\Services;

defined( 'ABSPATH' ) || exit();

use Hezarfen\Inc\Data\ServiceCredentialEncryption;

/**
 * MahalleIO
 */
class MahalleIO {

	/**
	 *
	 * Check: Is MahalleIO service activated?
	 *
	 * @return bool
	 */
	public static function is_active() {
		return self::get_api_token() ? true : false;
	}

	/**
	 *
	 * Get registered API Token
	 *
	 * @return string|false
	 */
	public static function get_api_token() {
		$encrypted_api_key = get_option( 'hezarfen_mahalle_io_api_key', null );

		return ! is_null( $encrypted_api_key ) ? ( new ServiceCredentialEncryption() )->decrypt( $encrypted_api_key ) : false;
	}

	/**
	 * Make HTTP - GET Request to mahalle.io
	 *
	 * @param string $url the endpoint URL to make a request.
	 * @return mixed
	 */
	private static function http( $url ) {
		$args = array(
			'headers' => array(
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . self::get_api_token(),
			),
		);

		$result = wp_remote_get( $url, $args );

		$status_code = wp_remote_retrieve_response_code( $result );

		$response = json_decode( wp_remote_retrieve_body( $result ) );

		// return error if exists any error
		if ( $status_code != 200 || ! isset( $response->data ) ) {
			return new \WP_Error(
				'connection_failed',
				__( 'mahalle.io connection failed' )
			);
		}

		return $response;
	}

	/**
	 * Get cities from mahalle.io
	 *
	 * @return array
	 */
	public static function get_cities() {
		$url = 'https://api.mahalle.io/v2/il';

		$result = self::http( $url );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$cities = array();

		foreach ( $result->data as $city ) {
			$cities[ $city->plaka_kodu ] = $city->il_adi;
		}

		return $cities;
	}

	/**
	 * Get districts by TR city plate number from mahalle.io
	 *
	 * @param $city_plate_number
	 * @return array
	 */
	public static function get_districts( $city_plate_number ) {
		$url = sprintf(
			'https://api.mahalle.io/v2/ilce?sorgu_tipi=plaka_kodu&plaka_kodu=%s',
			$city_plate_number
		);

		$result = self::http( $url );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$districts = array();

		foreach ( $result->data as $district ) {
			$districts[ $district->id ] = $district->ilce_adi;
		}

		return $districts;
	}

	/**
	 * Get neighborhood from mahalle.io by district id
	 *
	 * @param $district_id
	 * @return array
	 */
	public static function get_neighborhoods( $district_id ) {
		$url = sprintf(
			'https://api.mahalle.io/v2/mahalle?ilce_id=%d',
			$district_id
		);

		$result = self::http( $url );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$neighborhoods = array();

		foreach ( $result->data as $semt ) {
			foreach ( $semt->mahalleler as $neighborhood ) {
				$neighborhoods[ $neighborhood->id ] = $neighborhood->mahalle_adi;
			}
		}

		return $neighborhoods;
	}
}
