<?php
/**
 * Class Checkout.
 * 
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

use Hezarfen\Inc\Mahalle_Local;
use Hezarfen\Inc\Helper;
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

		add_filter(
			'woocommerce_default_address_fields',
			array(
				$this,
				'override_labels',
			),
			99999
		);

		add_filter(
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

		add_filter(
			'default_checkout_billing_hez_TC_number',
			array(
				$this,
				'override_billing_hez_identity_number',
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
		$fields['address_2']['required'] = true;

		return $fields;
	}

	/**
	 * Overrides default address fields' labels.
	 * 
	 * @param array $fields Default address fields.
	 * 
	 * @return array
	 */
	public function override_labels( $fields ) {
		$fields['city']['label']      = __( 'Town / City', 'hezarfen-for-woocommerce' );
		$fields['address_1']['label'] = __( 'Neighborhood', 'hezarfen-for-woocommerce' );
		return $fields;
	}

	/**
	 * Make address2 fields required.
	 *
	 * @param  array $fields current default address fields.
	 * @return array
	 */
	public function make_address2_required_and_update_the_label( $fields ) {
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

		if ( self::is_show_identity_field_on_checkout() ) {
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
	 * @param  string $value the current value of the input.
	 * @param  string $input the input field name.
	 * @return string
	 */
	public function override_billing_hez_identity_number( $value, $input ) {
		if ( 'billing_hez_TC_number' == $input && null !== $value ) {
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
	public static function is_show_identity_field_on_checkout() {
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
	public static function is_identity_number_field_required() {
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
	 * @param array $fields the current WooCommerce checkout fields.
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
	 * @param array $fields current WooCommerce checkout fields.
	 * @return array
	 */
	public function update_fields_required_options_for_invoice_type_company(
		$fields
	) {
		if ( ! $this->hezarfen_show_hezarfen_checkout_tax_fields || ! self::is_show_identity_field_on_checkout() ) {
			return $fields;
		}

		unset( $fields['billing']['billing_hez_TC_number'] );

		return $fields;
	}

	/**
	 * Update tax field required statuses according to the invoice type selection when checkout submit (before checkout processed.).
	 */
	public function update_field_required_statuses_before_checkout_process() {
		// nonce verification phpcs error ignored since WooCommerce already doing the nonce verification before the woocommerce_before_checkout_process hook release.
		//phpcs:ignore WordPress.Security.NonceVerification.Missing
		$hezarfen_invoice_type = isset( $_POST['billing_hez_invoice_type'] ) ? sanitize_key( $_POST['billing_hez_invoice_type'] ) : '';

		if ( 'person' == $hezarfen_invoice_type ) {
			add_filter(
				'woocommerce_checkout_fields',
				array(
					$this,
					'update_fields_required_options_for_invoice_type_person',
				),
				999999,
				1
			);
		} elseif ( 'company' == $hezarfen_invoice_type ) {
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
	 * Add tax fields (person or company selection and tax informations).
	 *
	 * @param  array $fields the current WooCommerce checkout fields.
	 * @return array
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

		if ( self::is_show_identity_field_on_checkout() ) {
			$fields['billing']['billing_hez_TC_number'] = array(
				'id'          => 'hezarfen_TC_number',
				'placeholder' => __( 'Enter T.C. Identity Number', 'hezarfen-for-woocommerce' ),
				'label'       => __(
					'T.C. Identity Number',
					'hezarfen-for-woocommerce'
				),
				'required'    => self::is_identity_number_field_required(),
				// TODO: review the WP filter name table and if possible rename that as lowercase also remove phpcs ignore.
				'class'       => apply_filters( 'hezarfen_checkout_fields_class_billing_hez_TC_number', array( 'form-row-wide' ) ), //phpcs:ignore WordPress.NamingConventions.ValidHookName.NotLowercase
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
		if ( 'person' == $invoice_type_value ) {
			$fields['billing']['billing_company']['class'][]        = 'hezarfen-hide-form-field';
			$fields['billing']['billing_hez_tax_office']['class'][] = 'hezarfen-hide-form-field';
			$fields['billing']['billing_hez_tax_number']['class'][] = 'hezarfen-hide-form-field';
		} elseif ( 'company' == $invoice_type_value ) {
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
	 * Update necessary data after checkout submit
	 *
	 * @param array $data the posted checkout data.
	 * @return array
	 */
	public function override_posted_data( $data ) {
		// Check if the T.C. Identitiy Field is active.
		if ( $this->hezarfen_show_hezarfen_checkout_tax_fields && self::is_show_identity_field_on_checkout() ) {
			if (
				( new PostMetaEncryption() )->health_check() &&
				( new PostMetaEncryption() )->test_the_encryption_key()
			) {
				// Encrypt the T.C. Identity fields.
				$data['billing_hez_TC_number'] = ( new PostMetaEncryption() )->encrypt(
					$data['billing_hez_TC_number']
				);
			} else {
				// do not save the T.C. identitiy fields.
				$data['billing_hez_TC_number'] = '******';
			}
		}

		return $data;
	}

	/**
	 * Show district and neighborhood fields on checkout page.
	 *
	 * @param array $fields the current checkout fields.
	 * @return array
	 */
	public function add_district_and_neighborhood_fields( $fields ) {
		$types = array( 'shipping', 'billing' );

		$district_options = array( '' => __( 'Select an option', 'hezarfen-for-woocommerce' ) );

		global $woocommerce;

		foreach ( $types as $type ) {
			$city_field_name         = sprintf( '%s_city', $type );
			$neighborhood_field_name = sprintf( '%s_address_1', $type );

			$get_city_function     = 'get_' . $type . '_state';
			$get_district_function = 'get_' . $type . '_city';

			// the value has TR prefix such as TR18.
			$current_city_plate_number_prefixed = $woocommerce->customer->$get_city_function();
			$current_district                   = $woocommerce->customer->$get_district_function();

			$districts = $this->get_districts(
				$current_city_plate_number_prefixed
			);

			if ( ! $districts ) {
				continue;
			}

			// remove WooCommerce default district field on checkout.
			unset( $fields[ $type ][ $city_field_name ] );

			// update array for name => name format.
			$districts = Helper::hezarfen_wc_checkout_select2_option_format( $districts );

			$fields[ $type ][ $city_field_name ] = array(
				'type'         => 'select',
				'label'        => __( 'Town / City', 'hezarfen-for-woocommerce' ),
				'required'     => true,
				'class'        => apply_filters( 'hezarfen_checkout_fields_class_wc_hezarfen_' . $type . '_district', array( 'form-row-wide' ) ),
				'clear'        => true,
				'autocomplete' => 'address-level2',
				'priority'     => $fields[ $type ][ $type . '_state' ]['priority'] + 1,
				'options'      => $district_options + $districts,
			);

			$fields[ $type ][ $neighborhood_field_name ] = array(
				'type'         => 'select',
				'label'        => __( 'Neighborhood', 'hezarfen-for-woocommerce' ),
				'required'     => true,
				'class'        => apply_filters( 'hezarfen_checkout_fields_class_wc_hezarfen_' . $type . '_neighborhood', array( 'form-row-wide' ) ),
				'clear'        => true,
				'autocomplete' => 'address-level3',
				'priority'     => $fields[ $type ][ $type . '_state' ]['priority'] + 2,
				'options'      => $this->get_neighborhood_options( $current_city_plate_number_prefixed, $current_district ),
			);
		}

		return $fields;
	}

	/**
	 * Get districts
	 *
	 * @param string $city_plate_with_prefix that begins with TR prefix such as TR18.
	 *
	 * @return array
	 */
	private function get_districts( $city_plate_with_prefix ) {
		if ( ! $city_plate_with_prefix ) {
			return array();
		}

		$districts = Mahalle_Local::get_districts( $city_plate_with_prefix );

		return $districts;
	}

	/**
	 * Returns neighborhood options.
	 * 
	 * @param string $city_plate_with_prefix that begins with TR prefix such as TR18.
	 * @param string $district District.
	 * 
	 * @return array
	 */
	private function get_neighborhood_options( $city_plate_with_prefix, $district ) {
		$neighborhood_options = array( '' => __( 'Select an option', 'hezarfen-for-woocommerce' ) );

		if ( ! $city_plate_with_prefix || ! $district ) {
			return $neighborhood_options;
		}

		$neighborhoods = Mahalle_Local::get_neighborhoods( $city_plate_with_prefix, $district );

		foreach ( $neighborhoods as $neighborhood ) {
			$neighborhood_options[ $neighborhood ] = $neighborhood;
		}

		return $neighborhood_options;
	}
}

new Checkout();
