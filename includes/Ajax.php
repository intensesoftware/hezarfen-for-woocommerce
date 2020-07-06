<?php

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit;

class Ajax
{

	function __construct()
	{

		add_action('wp_ajax_wc_hezarfen_get_districts', array( $this, 'get_districts' ) );
		add_action('wp_ajax_nopriv_wc_hezarfen_get_districts', array( $this, 'get_districts' ) );

		add_action('wp_ajax_wc_hezarfen_get_neighborhoods', array( $this, 'get_neighborhoods' ) );
		add_action('wp_ajax_nopriv_wc_hezarfen_get_neighborhoods', array( $this, 'get_neighborhoods' ) );

		add_action('wp_ajax_wc_hezarfen_neighborhood_changed', array( $this, 'neighborhood_changed' ) );
		add_action('wp_ajax_nopriv_wc_hezarfen_neighborhood_changed', array( $this, 'neighborhood_changed' ) );

	}

	function get_districts(){

		$city_plate_number_with_TR = $_POST['city_plate_number'];

		$city_plate_number = explode("TR", $city_plate_number_with_TR);

		$city_plate_number = $city_plate_number[1];


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


		// return result
		echo wp_json_encode($districts);


		wp_die();

	}



	function get_neighborhoods(){

		$district_data = $_POST['district_id'];

		$district_data_array = explode(":", $district_data);

		$district_id = $district_data_array[0];

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


		// return result
		echo wp_json_encode($neighborhoods);


		wp_die();

	}



	function neighborhood_changed(){

		$neighborhood_data = $_POST["neighborhood_data"];

		$neighborhood_data_arr = explode(":", $neighborhood_data);

		$neighborhood_id = $neighborhood_data_arr[0];

		$neighborhood_name = $neighborhood_data_arr[1];

		do_action('hezarfen_checkout_neighborhood_changed', $neighborhood_id, $neighborhood_name);

		$args = [

			'update_checkout' => true

		];

		echo wp_json_encode( apply_filters( 'hezarfen_checkout_neighborhood_changed_output_args', $args, $neighborhood_id, $neighborhood_name ) );

		wp_die();

	}

}

new Ajax();