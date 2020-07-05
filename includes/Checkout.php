<?php

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit;

class Checkout
{

	public function __construct()
	{

		add_filter('woocommerce_checkout_fields', array($this, 'add_district_and_neighborhood_fields'));

	}



	function add_district_and_neighborhood_fields($fields){


		$types = ['shipping', 'billing'];

		$district_options = [""=>__('Lütfen seçiniz', 'woocommerce')];
		$neighborhood_options = [""=>__('Lütfen seçiniz', 'woocommerce')];

		foreach($types as $type){

			$fields[ $type ]['district'] = array(

				'type' => 'select',
				'label' => __('İlçe', 'woocommerce'),
				'required' => true,
				'class' => ['form-row-wide'],
				'clear' => true,
				'options' => $district_options

			);

			$fields[ $type ]['neighborhood'] = array(

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