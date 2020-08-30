<?php

namespace Hezarfen\Inc\Services;

defined( 'ABSPATH' ) || exit;

class MahalleIO
{

	public static function get_districts( $city_plate_number )
	{

		$url = sprintf( 'https://api.mahalle.io/v1/ilce?sorgu_tipi=plaka_kodu&plaka_kodu=%s', $city_plate_number );

		$args = [

			'headers' => [

				'Accept' => 'application/json',
				'Content-Type' => 'application/json'

			]

		];

		$result = wp_remote_get( $url, $args );

		$status_code = wp_remote_retrieve_response_code($result);

		$body = json_decode(wp_remote_retrieve_body($result));

		// return error if exists any error
		if($status_code!=200 || !isset($body->data)){

			echo wp_json_encode(['message'=>'Server Error']);
			wp_die();

		}

		$districts = [];

		foreach($body->data as $district){

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

		$url = sprintf( 'https://api.mahalle.io/v1/mahalle?ilce_id=%d', $district_id );

		$args = [

			'headers' => [

				'Accept' => 'application/json',
				'Content-Type' => 'application/json'

			]

		];

		$result = wp_remote_get( $url, $args );

		$status_code = wp_remote_retrieve_response_code($result);

		$body = json_decode(wp_remote_retrieve_body($result));

		// return error if exists any error
		if($status_code!=200 || !isset($body->data)){

			echo wp_json_encode(['message'=>'Server Error']);
			wp_die();

		}

		$neighborhoods = [];

		foreach($body->data as $semt){

			foreach($semt->mahalleler as $neighborhood){

				$neighborhoods[$neighborhood->id] = $neighborhood->mahalle_adi;

			}

		}

		return $neighborhoods;

	}

}