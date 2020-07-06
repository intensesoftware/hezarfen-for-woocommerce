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

		foreach($types as $type){

			$city_field_name = sprintf('%s_city', $type);
			$neighborhood_field_name = sprintf('%s_neighborhood', $type);


			// remove WooCommerce default district field on checkout
			unset($fields[ $type ][ $city_field_name ]);


			$fields[ $type ][ $city_field_name ] = array(

				'id' => 'wc_hezarfen_'.$type.'_district',
				'type' => 'select',
				'label' => __('İlçe', 'woocommerce'),
				'required' => true,
				'class' => ['form-row-wide'],
				'clear' => true,
				'options' => $district_options

			);

			$fields[ $type ][ $neighborhood_field_name ] = array(

				'id' => 'wc_hezarfen_'.$type.'_neighborhood',
				'type' => 'select',
				'label' => __('Mahalle', 'woocommerce'),
				'required' => true,
				'class' => ['form-row-wide'],
				'clear' => true,
				'options' => $neighborhood_options

			);

		}



		return $fields;


	}



}

new Checkout();