<?php
/**
 * Class Checkout.
 * 
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

use Hezarfen\Inc\Services\MahalleIO;
use Hezarfen\Inc\Data\PostMetaEncryption;

/**
 * Checkout
 */
class Checkout {
	
	/**
	 * Should tax fields be shown on the checkout form?
	 *
	 * @var bool
	 */
	protected $hezarfen_show_hezarfen_checkout_tax_fields;
	
	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		$this->hezarfen_show_hezarfen_checkout_tax_fields = ( get_option( 'hezarfen_show_hezarfen_checkout_tax_fields' ) == 'yes' ) ? true : false;

		if ( $this->hezarfen_show_hezarfen_checkout_tax_fields ) {
			add_filter( 'woocommerce_checkout_fields', array( $this, 'add_tax_fields' ), 110, 1 );
		}

		$hide_postcode_field       = get_option( 'hezarfen_hide_checkout_postcode_fields', 'no' ) == 'yes';
		$checkout_fields_auto_sort = get_option( 'hezarfen_checkout_fields_auto_sort', 'no' ) == 'yes';

		if ( $checkout_fields_auto_sort ) {
			add_filter( 'woocommerce_checkout_fields', array( $this, 'auto_sort_checkout_fields' ), 999999, 1 );

			add_filter(
				'woocommerce_default_address_fields',
				array(
					$this,
					'sort_address_fields',
				),
				100000,
				1
			);
		}

		if ( $hide_postcode_field ) {
			add_filter( 'woocommerce_checkout_fields', array( $this, 'hide_postcode_fields' ), 90 );
		}

		// TODO: review the logic, if it's possible; define all fields in a single function.

		add_filter(
			'woocommerce_checkout_fields',
			array(
				$this,
				'add_district_and_neighborhood_fields',
			),
			100,
			1
		);

		add_filter(
			'woocommerce_checkout_fields',
			array(
				$this,
				'make_address2_required_and_update_the_label',
			),
			999998,
			1
		);

		add_filter(
			'woocommerce_default_address_fields',
			array(
				$this,
				'make_address2_required_default_address_field',
			),
			99999,
			1
		);

		add_action(
			'woocommerce_checkout_posted_data',
			array(
				$this,
				'override_posted_data',
			)
		);

		add_action(
			'woocommerce_before_checkout_process',
			array(
				$this,
				'update_field_required_statuses_before_checkout_process',
			)
		);

		add_action(
			'default_checkout_billing_hez_TC_number',
			array(
				$this,
				'override_billing_hez_TC_number',
			),
			10,
			2
		);
	}

	/**
	 * Sort address fields forcelly.
	 *
	 * @param  array $fields current default address fields.
	 * @return array
	 */
	public function sort_address_fields( $fields ) {
		$fields['state']['priority']     = 6;
		$fields['city']['priority']      = 7;
		$fields['address_1']['priority'] = 8;
		$fields['address_2']['priority'] = 9;

		return $fields;
	}

	/**
	 * Make address 2 fields required.
	 *
	 * @param  array $fields current default address fields.
	 * @return array
	 */
	public function make_address2_required_default_address_field( $fields ) {
		// if Mahalle.io not activated, return.
		if ( ! MahalleIO::is_active() ) {
			return $fields;
		}

		$fields['address_2']['required'] = true;

		return $fields;
	}

	/**
	 * Make address2 fields required.
	 *
	 * @param  array $fields current default address fields.
	 * @return array
	 */
	public function make_address2_required_and_update_the_label( $fields ) {
		// if Mahalle.io not activated, return.
		if ( ! MahalleIO::is_active() ) {
			return $fields;
		}

		// if mahalle.io is active, make address2 required.
		$fields['billing']['billing_address_2']['required']      = true;
		$fields['billing']['billing_address_2']['label']         = 'Adresiniz';
		$fields['billing']['billing_address_2']['placeholder']   = 'Cadde, sokak, bina, daire no bilgilerinizi giriniz';
		$fields['shipping']['shipping_address_2']['required']    = true;
		$fields['shipping']['shipping_address_2']['label']       = 'Adresiniz';
		$fields['shipping']['shipping_address_2']['placeholder'] = 'Cadde, sokak, bina, daire no bilgilerinizi giriniz';

		return $fields;
	}

	/**
	 * Auto Sort the Checkout Form Fields.
	 *
	 * @param  array $fields woocommerce checkout fields.
	 * @return array
	 */
	public function auto_sort_checkout_fields( $fields ) {
		$fields['billing']['billing_first_name']['priority']       = 1;
		$fields['billing']['billing_last_name']['priority']        = 2;
		$fields['billing']['billing_phone']['priority']            = 3;
		$fields['billing']['billing_email']['priority']            = 4;
		$fields['billing']['billing_country']['priority']          = 5;
		$fields['billing']['billing_state']['priority']            = 6;
		$fields['billing']['billing_city']['priority']             = 7;
		$fields['billing']['billing_address_1']['priority']        = 8;
		$fields['billing']['billing_address_2']['priority']        = 9;
		$fields['billing']['billing_hez_invoice_type']['priority'] = 10;

		if ( self::is_show_TC_field_on_checkout() ) {
			$fields['billing']['billing_hez_TC_number']['priority'] = 11;
		}

		$fields['billing']['billing_company']['priority']        = 12;
		$fields['billing']['billing_hez_tax_number']['priority'] = 13;
		$fields['billing']['billing_hez_tax_office']['priority'] = 14;

		$fields['shipping']['shipping_company']['priority']    = 0;
		$fields['shipping']['shipping_first_name']['priority'] = 1;
		$fields['shipping']['shipping_last_name']['priority']  = 2;
		$fields['shipping']['shipping_country']['priority']    = 5;
		$fields['shipping']['shipping_state']['priority']      = 6;
		$fields['shipping']['shipping_city']['priority']       = 7;
		$fields['shipping']['shipping_address_1']['priority']  = 8;
		$fields['shipping']['shipping_address_2']['priority']  = 9;

		return $fields;
	}
	
	/**
	 * Hide Post Code Fields where in the checkout form.
	 *
	 * @param  array $fields current checkout fields.
	 * @return array
	 */
	public function hide_postcode_fields( $fields ) {
		unset( $fields['billing']['billing_postcode'] );
		unset( $fields['shipping']['shipping_postcode'] );

		return $fields;
	}

	/**
	 * Override billing TC Number.
	 *
	 * @param  mixed $value
	 * @param  mixed $input
	 * @return string
	 */
	public function override_billing_hez_TC_number( $value, $input ) {
		if ( $input == 'billing_hez_TC_number' && $value !== null ) {
			// if the value encrypted, decrypt the value.
			return ( new PostMetaEncryption() )->decrypt( $value );
		}

		return $value;
	}

	/**
	 * Should show TC Identity field on checkout?.
	 * Default: false
	 *
	 * @return boolean
	 */
	public static function is_show_TC_field_on_checkout() {
		 $show = get_option( 'hezarfen_checkout_show_TC_identity_field', false ) ==
			'yes'
			? true
			: false;

		if ( ! $show || ! ( new PostMetaEncryption() )->test_the_encryption_key() ) {
			return false;
		}

		return true;
	}

	/**
	 * Is TC Identity Number field required?
	 *
	 * @return bool
	 */
	public static function is_TC_identity_number_field_required() {
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
		unset( $fields['billing']['billing_hez_tax_number'] );
		unset( $fields['billing']['billing_hez_tax_office'] );
		unset( $fields['billing']['billing_company'] );

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
		if ( ! $this->hezarfen_show_hezarfen_checkout_tax_fields || ! self::is_show_TC_field_on_checkout() ) {
			return $fields;
		}

		unset( $fields['billing']['billing_hez_TC_number'] );

		return $fields;
	}

	/**
	 * Update tax field required statuses according to the invoice type selection when checkout submit (before checkout processed.).
	 */
	public function update_field_required_statuses_before_checkout_process() {
		$hezarfen_invoice_type = sanitize_key( $_POST['billing_hez_invoice_type'] );

		if ( $hezarfen_invoice_type == 'person' ) {
			add_filter(
				'woocommerce_checkout_fields',
				array(
					$this,
					'update_fields_required_options_for_invoice_type_person',
				),
				999999,
				1
			);
		} elseif ( $hezarfen_invoice_type == 'company' ) {
			add_filter(
				'woocommerce_checkout_fields',
				array(
					$this,
					'update_fields_required_options_for_invoice_type_company',
				),
				999999,
				1
			);
		}
	}

	/**
	 * Add tax fields (person or company selection and tax informations)
	 */
	public function add_tax_fields( $fields ) {
		$invoice_type_value = ( new \WC_Checkout() )->get_value( 'billing_hez_invoice_type' );

		$fields['billing']['billing_hez_invoice_type'] = array(
			'id'       => 'hezarfen_invoice_type',
			'label'    => __( 'Invoice Type', 'hezarfen-for-woocommerce' ),
			'type'     => 'select',
			'required' => true,
			'class'    => apply_filters( 'hezarfen_checkout_fields_class_billing_hez_invoice_type', array( 'form-row-wide' ) ),
			'options'  => array(
				'person'  => __( 'Personal', 'hezarfen-for-woocommerce' ),
				'company' => __( 'Company', 'hezarfen-for-woocommerce' ),
			),
			'priority' => $fields['billing']['billing_email']['priority'] + 1,
		);

		if ( self::is_show_TC_field_on_checkout() ) {
			$fields['billing']['billing_hez_TC_number'] = array(
				'id'          => 'hezarfen_TC_number',
				'placeholder' => __( 'Enter T.C. Identity Number', 'hezarfen-for-woocommerce' ),
				'label'       => __(
					'T.C. Identity Number',
					'hezarfen-for-woocommerce'
				),
				'required'    => self::is_TC_identity_number_field_required(),
				'class'       => apply_filters( 'hezarfen_checkout_fields_class_billing_hez_TC_number', array( 'form-row-wide' ) ),
				'priority'    => $fields['billing']['billing_hez_invoice_type']['priority'] + 1,
			);
		}

		$fields['billing']['billing_company'] = array(
			'label'       => __( 'Title', 'hezarfen-for-woocommerce' ),
			'placeholder' => __( 'Enter invoice title', 'hezarfen-for-woocommerce' ),
			'required'    => true,
			'priority'    => $fields['billing']['billing_hez_invoice_type']['priority'] + 1,
		);

		$fields['billing']['billing_hez_tax_number'] = array(
			'id'          => 'hezarfen_tax_number',
			'label'       => __( 'Tax Number', 'hezarfen-for-woocommerce' ),
			'placeholder' => __( 'Enter tax number', 'hezarfen-for-woocommerce' ),
			'required'    => true,
			'class'       => apply_filters( 'hezarfen_checkout_fields_class_billing_hez_tax_number', array( 'form-row-wide' ) ),
			'priority'    => $fields['billing']['billing_company']['priority'] + 1,
		);

		$fields['billing']['billing_hez_tax_office'] = array(
			'id'          => 'hezarfen_tax_office',
			'label'       => __( 'TAX Office', 'hezarfen-for-woocommerce' ),
			'placeholder' => __( 'Enter tax office', 'hezarfen-for-woocommerce' ),
			'required'    => true,
			'class'       => apply_filters( 'hezarfen_checkout_fields_class_billing_hez_tax_office', array( 'form-row-wide' ) ),
			'priority'    => $fields['billing']['billing_hez_tax_number']['priority'] + 1,
		);

		// set the hidden tax fields according to the invoice_type value.
		if ( $invoice_type_value == 'person' ) {
			$fields['billing']['billing_company']['class'][]        = 'hezarfen-hide-form-field';
			$fields['billing']['billing_hez_tax_office']['class'][] = 'hezarfen-hide-form-field';
			$fields['billing']['billing_hez_tax_number']['class'][] = 'hezarfen-hide-form-field';
		} elseif ( $invoice_type_value == 'company' ) {
			$fields['billing']['billing_hez_TC_number']['class'][] = 'hezarfen-hide-form-field';
		} else {
			$fields['billing']['billing_company']['class'][]        = 'hezarfen-hide-form-field';
			$fields['billing']['billing_hez_tax_office']['class'][] = 'hezarfen-hide-form-field';
			$fields['billing']['billing_hez_tax_number']['class'][] = 'hezarfen-hide-form-field';
		}

		return $fields;
	}

	/**
	 *
	 * Update district and neighborhood data after checkout submit
	 *
	 * @param $data
	 * @return array
	 */
	function override_posted_data( $data ) {
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
		if ( MahalleIO::is_active() ) {
			$types = array( 'shipping', 'billing' );

			foreach ( $types as $type ) {
				$city_field_name = sprintf( '%s_city', $type );

				$neighborhood_field_name = sprintf( '%s_address_1', $type );

				if ( array_key_exists( $city_field_name, $data ) ) {
					$value = $data[ $city_field_name ];

					if ( $value && strpos( $value, ':' ) !== false ) {
						$district_data_arr = explode( ':', $value );

						$district_id   = $district_data_arr[0];
						$district_name = $district_data_arr[1];

						$data[ $city_field_name ] = $district_name;
					}
				}

				if ( array_key_exists( $neighborhood_field_name, $data ) ) {
					$value = $data[ $neighborhood_field_name ];

					if ( $value && strpos( $value, ':' ) !== false ) {
						$neighborhood_data_arr = explode( ':', $value );

						$neighborhood_id   = $neighborhood_data_arr[0];
						$neighborhood_name = $neighborhood_data_arr[1];

						$data[ $neighborhood_field_name ] = $neighborhood_name;
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
	function add_district_and_neighborhood_fields( $fields ) {
		// if Mahalle.io not activated, return.
		if ( ! MahalleIO::is_active() ) {
			return $fields;
		}

		$types = array( 'shipping', 'billing' );

		$district_options     = array( '' => __( 'Lütfen seçiniz', 'woocommerce' ) );
		$neighborhood_options = array( '' => __( 'Lütfen seçiniz', 'woocommerce' ) );

		global $woocommerce;

		foreach ( $types as $type ) {
			$city_field_name         = sprintf( '%s_city', $type );
			$neighborhood_field_name = sprintf( '%s_address_1', $type );

			$get_city_function = 'get_' . $type . '_state';

			$current_city_plate_number_with_TR = $woocommerce->customer->$get_city_function();

			$districts_response = $this->get_districts(
				$current_city_plate_number_with_TR
			);

			/**
			 * Todo: fire a notification about failed mahalle.io connection
			 */
			// if get_districts failed, return empty array and disable mahalle.io - Hezarfen customizations.

			if ( is_wp_error( $districts_response ) ) {
				continue;
			} else {
				$districts = $districts_response;
			}

			// remove WooCommerce default district field on checkout
			unset( $fields[ $type ][ $city_field_name ] );

			// update array keys for id:name format
			$districts = hezarfen_wc_checkout_select2_option_format( $districts );

			$fields[ $type ][ $city_field_name ] = array(
				'id'       => 'wc_hezarfen_' . $type . '_district',
				'type'     => 'select',
				'label'    => __( 'İlçe', 'woocommerce' ),
				'required' => true,
				'class'    => apply_filters( 'hezarfen_checkout_fields_class_' . 'wc_hezarfen_' . $type . '_district', array( 'form-row-wide' ) ),
				'clear'    => true,
				'priority' => $fields[ $type ][ $type . '_state' ]['priority'] + 1,
				'options'  => $district_options + $districts,
			);

			$fields[ $type ][ $neighborhood_field_name ] = array(
				'id'       => 'wc_hezarfen_' . $type . '_neighborhood',
				'type'     => 'select',
				'label'    => __( 'Mahalle', 'woocommerce' ),
				'required' => true,
				'class'    => apply_filters( 'hezarfen_checkout_fields_class_' . 'wc_hezarfen_' . $type . '_neighborhood', array( 'form-row-wide' ) ),
				'clear'    => true,
				'priority' => $fields[ $type ][ $type . '_state' ]['priority'] + 2,
				'options'  => $neighborhood_options,
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
	private function get_districts( $city_plate_number_with_TR ) {
		if ( ! $city_plate_number_with_TR ) {
			return array();
		}

		$city_plate_number = explode( 'TR', $city_plate_number_with_TR );

		$city_plate_number = $city_plate_number[1];

		$districts = MahalleIO::get_districts( $city_plate_number );

		return $districts;
	}
}

new Checkout();
