<?php

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit;

class Checkout
{

	public function __construct()
	{

		add_filter('woocommerce_checkout_fields', array($this, 'add_district_and_neighborhood_fields'));

		add_action('woocommerce_checkout_update_order_meta', array($this, 'update_data'));

	}


	/**
	 * Override datas.
	 *
	 * @param $order_id
	 */
	function update_data( $order_id ){

		$types = ['shipping', 'billing'];

		foreach( $types as $type ){

			// district, but woocommerce says city
			$city_field_name = sprintf('%s_city', $type);

			$neighborhood_field_name = sprintf('%s_neighborhood', $type);

			if( ! empty( $_POST[ $city_field_name ] ) ){

				$district_data = $_POST[ $city_field_name ];

				$district_data_arr = explode(":", $district_data);

				$district_id = $district_data_arr[0];
				$district_name = $district_data_arr[1];

				update_post_meta( $order_id, '_' . $city_field_name,  $district_name );

			}



			if( ! empty( $_POST[ $neighborhood_field_name ] ) ){

				$neighborhood_data = $_POST[ $neighborhood_field_name ];

				$neighborhood_data_arr = explode(":", $neighborhood_data);

				$neighborhood_id = $neighborhood_data_arr[0];
				$neighborhood_name = $neighborhood_data_arr[1];

				update_post_meta( $order_id, '_' . $neighborhood_field_name,  $neighborhood_name );

			}


		}


	}


	function add_district_and_neighborhood_fields($fields){


		$types = ['shipping', 'billing'];

		$district_options = [""=>__('Lütfen seçiniz', 'woocommerce')];
		$neighborhood_options = [""=>__('Lütfen seçiniz', 'woocommerce')];

		global $woocommerce;

		foreach($types as $type){

			$city_field_name = sprintf('%s_city', $type);
			$neighborhood_field_name = sprintf('%s_neighborhood', $type);


			// remove WooCommerce default district field on checkout
			unset($fields[ $type ][ $city_field_name ]);


			$get_city_function = "get_" . $type . "_state";

			$current_city_plate_number_with_TR = $woocommerce->customer->$get_city_function();

			$districts = $this->get_districts( $current_city_plate_number_with_TR );



			$fields[ $type ][ $city_field_name ] = array(

				'id' => 'wc_hezarfen_'.$type.'_district',
				'type' => 'select',
				'label' => __('İlçe', 'woocommerce'),
				'required' => true,
				'class' => ['form-row-wide'],
				'clear' => true,
				'priority' => $fields[ $type ][ $type . '_state' ]['priority'] + 1,
				'options' => array_merge($district_options, $districts)

			);

			$fields[ $type ][ $neighborhood_field_name ] = array(

				'id' => 'wc_hezarfen_'.$type.'_neighborhood',
				'type' => 'select',
				'label' => __('Mahalle', 'woocommerce'),
				'required' => true,
				'class' => ['form-row-wide'],
				'clear' => true,
				'priority' => $fields[ $type ][ $type . '_state' ]['priority'] + 2,
				'options' => $neighborhood_options

			);

		}



		return $fields;


	}



	private function get_districts($city_plate_number_with_TR){

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

			return false;

		}

		$districts = [];

		foreach($body->data as $district){

			$districts[$district->id] = $district->ilce_adi;

		}

		return $districts;


	}



}

new Checkout();