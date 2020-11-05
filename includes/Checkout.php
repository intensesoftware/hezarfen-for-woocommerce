<?php

namespace Hezarfen\Inc;

defined('ABSPATH') || exit();

use Hezarfen\Inc\Services\MahalleIO;
use Hezarfen\Inc\Data\PostMetaEncryption;

class Checkout
{
	protected $hezarfen_show_hezarfen_checkout_tax_fields;

	public function __construct()
	{
		$this->hezarfen_show_hezarfen_checkout_tax_fields = ( get_option( 'hezarfen_show_hezarfen_checkout_tax_fields' ) == 'yes' ) ? true : false;

		if( $this->hezarfen_show_hezarfen_checkout_tax_fields )
		{
			add_filter('woocommerce_checkout_fields', [$this, 'add_tax_fields']);
		}

		add_filter('woocommerce_checkout_fields', [
			$this,
			'add_district_and_neighborhood_fields',
		]);

		add_action('woocommerce_checkout_posted_data', [
			$this,
			'override_posted_data',
		]);

		add_action('woocommerce_before_checkout_process', [
			$this,
			'update_field_required_statuses_before_checkout_process',
		]);

		add_action('default_checkout_billing_hez_TC_number', [
			$this,
			'override_billing_hez_TC_number',
		], 10, 2);
	}

	/**
	 * Override billing TC Number.
	 *
	 * @param  mixed $value
	 * @param  mixed $input
	 * @return string
	 */
	public function override_billing_hez_TC_number( $value, $input )
	{
		if( $input == 'billing_hez_TC_number' )
		{
			// if the value encrypted, decrypt the value.
			return ( new PostMetaEncryption() )->decrypt( $value );
		}

		return $value;
	}

	/**
	 * Should show TC Identity field on checkout?.
	 * Default: false
	 * @return boolean
	 */
	public static function is_show_TC_field_on_checkout()
	{
		$show =  get_option('hezarfen_checkout_show_TC_identity_field', false) ==
			'yes'
			? true
			: false;

		if( ! $show || ! ( new PostMetaEncryption() )->test_the_encryption_key() )
			return false;

		return true;
	}

	/**
	 * Is TC Identity Number field required?
	 * @return bool
	 */
	public static function is_TC_identity_number_field_required()
	{
		return get_option(
			'hezarfen_checkout_is_TC_identity_number_field_required',
			false
		) == 'yes'
			? true
			: false;
	}

	/**
	 * Make non-required tax_number and tax_office fields.
	 *
	 * @param $fields
	 * @return array
	 */
	public function update_fields_required_options_for_invoice_type_person(
		$fields
	) {
		unset($fields['billing']['billing_hez_tax_number']);
		unset($fields['billing']['billing_hez_tax_office']);

		return $fields;
	}

	/**
	 * Make non-required TC_number field.
	 *
	 * @param $fields
	 * @return array
	 */
	public function update_fields_required_options_for_invoice_type_company(
		$fields
	) {
		if ( !$this->hezarfen_show_hezarfen_checkout_tax_fields || !self::is_show_TC_field_on_checkout()) {
			return $fields;
		}

		unset($fields['billing']['billing_hez_TC_number']);

		return $fields;
	}

	/**
	 * Update tax field required statuses according to the invoice type selection when checkout submit (before checkout processed.).
	 */
	public function update_field_required_statuses_before_checkout_process()
	{
		$hezarfen_invoice_type = $_POST['billing_hez_invoice_type'];

		if ($hezarfen_invoice_type == 'person') {
			add_filter('woocommerce_checkout_fields', [
				$this,
				'update_fields_required_options_for_invoice_type_person',
			]);
		} elseif ($hezarfen_invoice_type == 'company') {
			add_filter('woocommerce_checkout_fields', [
				$this,
				'update_fields_required_options_for_invoice_type_company',
			]);
		}
	}

	/**
	 * Add tax fields (person or company selection and tax informations)
	 */
	public function add_tax_fields($fields)
	{
		$fields['billing']['billing_hez_invoice_type'] = [
			'id' => 'hezarfen_invoice_type',
			'label' => __('Invoice Type', 'hezarfen-for-woocommerce'),
			'type' => 'select',
			'required' => true,
			'class' => ['form-row-wide'],
			'options' => [
				'person' => __('Personal', 'hezarfen-for-woocommerce'),
				'company' => __('Company', 'hezarfen-for-woocommerce'),
			],
		];

		if (self::is_show_TC_field_on_checkout()) {
			$fields['billing']['billing_hez_TC_number'] = [
				'id' => 'hezarfen_TC_number',
				'label' => __(
					'T.C. Identity Number',
					'hezarfen-for-woocommerce'
				),
				'required' => self::is_TC_identity_number_field_required(),
				'class' => ['form-row-wide'],
			];
		}

		$fields['billing']['billing_hez_tax_number'] = [
			'id' => 'hezarfen_tax_number',
			'label' => __('Tax Number', 'hezarfen-for-woocommerce'),
			'required' => true,
			'class' => ['form-row-wide', 'hezarfen-hide-form-field'],
		];

		$fields['billing']['billing_hez_tax_office'] = [
			'id' => 'hezarfen_tax_office',
			'label' => __('TAX Office', 'hezarfen-for-woocommerce'),
			'required' => true,
			'class' => ['form-row-wide', 'hezarfen-hide-form-field'],
		];

		return $fields;
	}

	/**
	 *
	 * Update district and neighborhood data after checkout submit
	 *
	 * @param $data
	 * @return array
	 */
	function override_posted_data($data)
	{
		// Check the T.C. Identitiy Field is active
		if ( $this->hezarfen_show_hezarfen_checkout_tax_fields && self::is_show_TC_field_on_checkout() ) {
			if (
				( new PostMetaEncryption() )->health_check() &&
				( new PostMetaEncryption() )->test_the_encryption_key()
			) {
				// Encrypt the T.C. Identity fields
				$data['billing_hez_TC_number'] = ( new PostMetaEncryption() )->encrypt(
					$data['billing_hez_TC_number']
				);
			} else {
				// do not save the T.C. identitiy fields.
				$data['billing_hez_TC_number'] = '******';
			}
		}

		// if Mahalle.io activated, update neighborhood fields.
		if (MahalleIO::is_active()) {
			$types = ['shipping', 'billing'];

			foreach ($types as $type) {
				$city_field_name = sprintf('%s_city', $type);

				$neighborhood_field_name = sprintf('%s_address_1', $type);

				if (array_key_exists($city_field_name, $data)) {
					$value = $data[$city_field_name];

					if ($value && strpos($value, ':') !== false) {
						$district_data_arr = explode(":", $value);

						$district_id = $district_data_arr[0];
						$district_name = $district_data_arr[1];

						$data[$city_field_name] = $district_name;
					}
				}

				if (array_key_exists($neighborhood_field_name, $data)) {
					$value = $data[$neighborhood_field_name];

					if ($value && strpos($value, ':') !== false) {
						$neighborhood_data_arr = explode(":", $value);

						$neighborhood_id = $neighborhood_data_arr[0];
						$neighborhood_name = $neighborhood_data_arr[1];

						$data[$neighborhood_field_name] = $neighborhood_name;
					}
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
	function add_district_and_neighborhood_fields($fields)
	{
		// if Mahalle.io not activated, return.
		if (!MahalleIO::is_active()) {
			return $fields;
		}

		$types = ['shipping', 'billing'];

		$district_options = ["" => __('Lütfen seçiniz', 'woocommerce')];
		$neighborhood_options = ["" => __('Lütfen seçiniz', 'woocommerce')];

		global $woocommerce;

		foreach ($types as $type) {
			$city_field_name = sprintf('%s_city', $type);
			$neighborhood_field_name = sprintf('%s_address_1', $type);

			$get_city_function = "get_" . $type . "_state";

			$current_city_plate_number_with_TR = $woocommerce->customer->$get_city_function();

			$districts_response = $this->get_districts(
				$current_city_plate_number_with_TR
			);

			/**
			 * Todo: fire a notification about failed mahalle.io connection
			 */
			// if get_districts failed, return empty array and disable mahalle.io - Hezarfen customizations.

			if (is_wp_error($districts_response)) {
				continue;
			} else {
				$districts = $districts_response;
			}

			// remove WooCommerce default district field on checkout
			unset($fields[$type][$city_field_name]);

			// update array keys for id:name format
			$districts = hezarfen_wc_checkout_select2_option_format($districts);

			$fields[$type][$city_field_name] = [
				'id' => 'wc_hezarfen_' . $type . '_district',
				'type' => 'select',
				'label' => __('İlçe', 'woocommerce'),
				'required' => true,
				'class' => ['form-row-wide'],
				'clear' => true,
				'priority' => $fields[$type][$type . '_state']['priority'] + 1,
				'options' => $district_options + $districts,
			];

			$fields[$type][$neighborhood_field_name] = [
				'id' => 'wc_hezarfen_' . $type . '_neighborhood',
				'type' => 'select',
				'label' => __('Mahalle', 'woocommerce'),
				'required' => true,
				'class' => ['form-row-wide'],
				'clear' => true,
				'priority' => $fields[$type][$type . '_state']['priority'] + 2,
				'options' => $neighborhood_options,
			];
		}

		return $fields;
	}

	/**
	 * Get districts from mahalle.io
	 *
	 * @param $city_plate_number_with_TR
	 * @return array|bool
	 */
	private function get_districts($city_plate_number_with_TR)
	{
		if (!$city_plate_number_with_TR) {
			return [];
		}

		$city_plate_number = explode("TR", $city_plate_number_with_TR);

		$city_plate_number = $city_plate_number[1];

		$districts = MahalleIO::get_districts($city_plate_number);

		return $districts;
	}
}

new Checkout();
