<?php

namespace Hezarfen\Inc\Services;

defined( 'ABSPATH' ) || exit;

class MahalleIO
{


	/**
	 *
	 * Check: Is MahalleIO service activated?
	 *
	 * @return bool
	 */
	public static function is_active()
	{

		return self::get_api_token() ? true : false;

	}


	/**
	 *
	 * Get registered API Token
	 *
	 * @return string|false
	 */
	public static function get_api_token(){

		return get_option('hezarfen_mahalle_io_api_key', false);

	}


	/**
	 * Make HTTP - GET Request to mahalle.io
	 *
	 * @param $url
	 * @return mixed
	 */
	public static function HTTP( $url ){

		$args = [

			'headers' => [

				'Accept' => 'application/json',
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer '.self::get_api_token()

			]

		];

		$result = wp_remote_get( $url, $args );

		$status_code = wp_remote_retrieve_response_code($result);

		$response = json_decode(wp_remote_retrieve_body($result));

		// return error if exists any error
		if($status_code!=200 || !isset($response->data))
			return new \WP_Error( 'connection_failed', __( 'mahalle.io connection failed' ) );

		return $response;

	}

	/**
	 * Get districts by TR city plate number from mahalle.io
	 *
	 * @param $city_plate_number
	 * @return array
	 */
	public static function get_districts( $city_plate_number )
	{

		$url = sprintf( 'https://api.mahalle.io/v2/ilce?sorgu_tipi=plaka_kodu&plaka_kodu=%s', $city_plate_number );

		$result = self::HTTP( $url );

		if( is_wp_error( $result ) )
			return $result;

		$districts = [];

		foreach($result->data as $district){

			$districts[$district->id] = $district->ilce_adi;

		}

		return $districts;

	}


	/**
	 * Get neighborhood from mahalle.io by district id
	 *
	 * @param $district_id
	 * @return array
	 */
	public static function get_neighborhoods( $district_id )
	{

		$url = sprintf( 'https://api.mahalle.io/v2/mahalle?ilce_id=%d', $district_id );

		$result = self::HTTP( $url );

		if( is_wp_error( $result ) )
			return $result;

		$neighborhoods = [];

		foreach($result->data as $semt){

			foreach($semt->mahalleler as $neighborhood){

				$neighborhoods[$neighborhood->id] = $neighborhood->mahalle_adi;

			}

		}

		return $neighborhoods;

	}

}