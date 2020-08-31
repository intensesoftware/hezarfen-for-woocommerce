<?php

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit;

use Hezarfen\Inc\Services\MahalleIO;

class Checkout
{

	public function __construct()
	{

		add_filter('woocommerce_checkout_fields', array($this, 'add_district_and_neighborhood_fields'));

		add_action('woocommerce_checkout_update_order_meta', array($this, 'update_data'));

		add_action('woocommerce_checkout_posted_data', array( $this, 'override_posted_data' ) );

	}


	/**
	 *
	 * Update district and neighborhood data after checkout submit
	 *
	 * @param $data
	 * @return array
	 */
	function override_posted_data( $data ){



		$types = ['shipping', 'billing'];

		foreach( $types as $type ) {


			$city_field_name = sprintf('%s_city', $type);

			$neighborhood_field_name = sprintf('%s_address_1', $type);


			if (array_key_exists( $city_field_name, $data )) {

				$value = $data[ $city_field_name ];

				if( $value ){

					$district_data_arr = explode(":", $value);

					$district_id   = $district_data_arr[0];
					$district_name = $district_data_arr[1];

					$data[ $city_field_name ] = $district_name;

				}

			}


			if ( array_key_exists( $neighborhood_field_name, $data ) ) {

				$value = $data[ $neighborhood_field_name ];

				if( $value ){
					
					$neighborhood_data_arr = explode(":", $value );

					$neighborhood_id   = $neighborhood_data_arr[0];
					$neighborhood_name = $neighborhood_data_arr[1];

					$data[ $neighborhood_field_name ] = $neighborhood_name;

				}

			}


		}


		return $data;


	}

	/**
	 * Show district and neighborhood fields on checkout page.
	 *
	 * @param $fields
	 * @return array
	 */
	function add_district_and_neighborhood_fields($fields){


		$types = ['shipping', 'billing'];

		$district_options = [""=>__('Lütfen seçiniz', 'woocommerce')];
		$neighborhood_options = [""=>__('Lütfen seçiniz', 'woocommerce')];

		global $woocommerce;

		foreach($types as $type){

			$city_field_name = sprintf('%s_city', $type);
			$neighborhood_field_name = sprintf('%s_address_1', $type);

			$get_city_function = "get_" . $type . "_state";

			$current_city_plate_number_with_TR = $woocommerce->customer->$get_city_function();

			$districts_response = $this->get_districts( $current_city_plate_number_with_TR );


			/**
			 * Todo: fire a notification about failed mahalle.io connection
			 */
			// if get_districts failed, return empty array and disable mahalle.io - Hezarfen customizations.
			
			if( is_wp_error( $districts_response ) )
				continue;
			else
				$districts = $districts_response;

			// remove WooCommerce default district field on checkout
			unset($fields[ $type ][ $city_field_name ]);

			// update array keys for id:name format
			$districts = hezarfen_wc_checkout_select2_option_format( $districts );


			$fields[ $type ][ $city_field_name ] = array(

				'id' => 'wc_hezarfen_'.$type.'_district',
				'type' => 'select',
				'label' => __('İlçe', 'woocommerce'),
				'required' => true,
				'class' => ['form-row-wide'],
				'clear' => true,
				'priority' => $fields[ $type ][ $type . '_state' ]['priority'] + 1,
				'options' => $district_options + $districts

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


	/**
	 * Get districts from mahalle.io
	 *
	 * @param $city_plate_number_with_TR
	 * @return array|bool
	 */
	private function get_districts($city_plate_number_with_TR){

		$city_plate_number = explode("TR", $city_plate_number_with_TR);

		$city_plate_number = $city_plate_number[1];

		$districts = MahalleIO::get_districts( $city_plate_number );

		return $districts;

	}



}

new Checkout();