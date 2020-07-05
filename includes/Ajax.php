<?php

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit;

class Ajax
{

	function __construct()
	{

		add_action('wp_ajax_wc_hezarfen_get_districts', array( $this, 'get_districts' ) );
		add_action('wp_ajax_nopriv_wc_hezarfen_get_districts', array( $this, 'get_districts' ) );

	}

	function get_districts(){

		$city_name = $_POST['city_name'];


		$url = sprintf( 'http://api.mahalle.io/v1/ilce?sorgu_tipi=il_adi&il_adi=%s', $city_name );

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

}

new Ajax();