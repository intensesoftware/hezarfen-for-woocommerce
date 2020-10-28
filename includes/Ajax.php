<?php

namespace Hezarfen\Inc;

defined('ABSPATH') || exit();

use Hezarfen\Inc\Services\MahalleIO;

class Ajax
{
	function __construct()
	{
		add_action('wp_ajax_wc_hezarfen_get_districts', [
			$this,
			'get_districts',
		]);
		add_action('wp_ajax_nopriv_wc_hezarfen_get_districts', [
			$this,
			'get_districts',
		]);

		add_action('wp_ajax_wc_hezarfen_get_neighborhoods', [
			$this,
			'get_neighborhoods',
		]);
		add_action('wp_ajax_nopriv_wc_hezarfen_get_neighborhoods', [
			$this,
			'get_neighborhoods',
		]);

		add_action('wp_ajax_wc_hezarfen_neighborhood_changed', [
			$this,
			'neighborhood_changed',
		]);
		add_action('wp_ajax_nopriv_wc_hezarfen_neighborhood_changed', [
			$this,
			'neighborhood_changed',
		]);
	}

	function get_districts()
	{
		$city_plate_number_with_TR = $_POST['city_plate_number'];

		$city_plate_number = explode("TR", $city_plate_number_with_TR);

		$city_plate_number = $city_plate_number[1];

		$get_districts_response = MahalleIO::get_districts($city_plate_number);

		// if get_districts failed, return empty array.
		/**
		 * Todo: fire a notification about failed mahalle.io connection
		 */
		if (is_wp_error($get_districts_response)) {
			$districts = [];
		} else {
			$districts = $get_districts_response;
		}

		// return result
		echo wp_json_encode($districts);

		wp_die();
	}

	function get_neighborhoods()
	{
		$district_data = $_POST['district_id'];

		$district_data_array = explode(":", $district_data);

		$district_id = $district_data_array[0];

		$get_neighborhoods_response = MahalleIO::get_neighborhoods(
			$district_id
		);

		// if get_neighborhoods failed, return empty array.
		/**
		 * Todo: fire a notification about failed mahalle.io connection
		 */
		if (is_wp_error($get_neighborhoods_response)) {
			$neighborhoods = [];
		} else {
			$neighborhoods = $get_neighborhoods_response;
		}

		// return result
		echo wp_json_encode($neighborhoods);

		wp_die();
	}

	function neighborhood_changed()
	{
		$neighborhood_data = $_POST["neighborhood_data"];

		$neighborhood_data_arr = explode(":", $neighborhood_data);

		$neighborhood_id = $neighborhood_data_arr[0];

		$neighborhood_name = $neighborhood_data_arr[1];

		do_action(
			'hezarfen_checkout_neighborhood_changed',
			$neighborhood_id,
			$neighborhood_name
		);

		$args = [
			'update_checkout' => true,
		];

		echo wp_json_encode(
			apply_filters(
				'hezarfen_checkout_neighborhood_changed_output_args',
				$args,
				$neighborhood_id,
				$neighborhood_name
			)
		);

		wp_die();
	}
}

new Ajax();
