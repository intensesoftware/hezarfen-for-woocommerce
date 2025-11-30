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
		$this->hezarfen_show_hezarfen_checkout_tax_fields = Helper::is_show_tax_fields();

		if ( $this->hezarfen_show_hezarfen_checkout_tax_fields ) {
			add_filter( 'woocommerce_checkout_fields', array( $this, 'add_tax_fields' ), 110, 1 );
		}

		if ( 'yes' === get_option( 'hezarfen_checkout_fields_auto_sort', 'no' ) ) {
			// we need to use an action that fires after the 'posts_selection' action to access the is_checkout() function. (https://woocommerce.com/document/conditional-tags/).
			add_action( 'wp', array( $this, 'sort_checkout_fields' ) );
		}

		if ( 'yes' === get_option( 'hezarfen_hide_checkout_postcode_fields', 'no' ) ) {
			add_action( 'wp', array( $this, 'hide_postcode_fields' ) );
		}

		// TODO: review the logic, if it's possible; define all fields in a single function.

		if ( 'yes' === apply_filters( 'hezarfen_enable_district_neighborhood_fields', get_option( 'hezarfen_enable_district_neighborhood_fields', 'yes' ) ) ) {
			add_filter(
				'woocommerce_checkout_fields',
				array(
					$this,
					'add_district_and_neighborhood_fields',
				),
				100,
				1
			);

			add_filter( 'woocommerce_get_country_locale', array( $this, 'update_address_2_fields_for_tr' ) );

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
					'make_visible_address2_label',
				),
			);
		}

		add_filter(
			'woocommerce_checkout_posted_data',
			array(
				$this,
				'override_posted_data',
			)
		);

		add_action(
			'woocommerce_after_checkout_validation',
			array(
				$this,
				'validate_posted_data',
			),
			10,
			2
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

		add_filter('woocommerce_form_field_text', array(__CLASS__, 'filter_tc_number_field'), 10, 4);
	}

	/**
	 * Filter the TC number field to clear invalid values
	 */
	public static function filter_tc_number_field($field, $key, $args, $value) {
		// Only process the TC number field
		if ($key === 'billing_hez_TC_number') {
			// Get the actual TC number (may need to decrypt it first)
			$tc_id_number = $value;
			// Check if the TC number is invalid
			if ($tc_id_number &&  (11 !== strlen($tc_id_number) || !is_numeric($tc_id_number))) {

				// Replace the value attribute in the HTML with an empty string
				$field = preg_replace('/value="[^"]*"/', 'value=""', $field);
			}
		}

		return $field;
	}

	/**
	 * Sorts the Checkout Form Fields.
	 *
	 * @return void
	 */
	public function sort_checkout_fields() {
		if ( is_checkout() ) {
			Helper::sort_address_fields();
		}
	}

	/**
	 * Update Address 2 Field Labels and make it required for Turkiye.
	 *
	 * @param  array $country_locale_settings Country label settings.
	 * @return array
	 */
	public function update_address_2_fields_for_tr( $country_locale_settings ) {
		if ( ! array_key_exists( 'TR', $country_locale_settings ) ) {
			return $country_locale_settings;
		}

		$country_locale_settings['TR']['address_2']['required']    = true;
		$country_locale_settings['TR']['address_2']['label']       = __( 'Your Address', 'hezarfen-for-woocommerce' );
		$country_locale_settings['TR']['address_2']['placeholder'] = __( 'Enter your street, avenue, building, and apartment number information.', 'hezarfen-for-woocommerce' );
		$country_locale_settings['TR']['address_2']['hidden']      = false;

		return $country_locale_settings;
	}

	/**
	 * Make visible the Address2 Field Label.
	 *
	 * @param  array<string, mixed> $fields current default address fields.
	 * @return array<string, mixed>
	 */
	public function make_visible_address2_label( $fields ) {
		// Check if address_2 field exists and has label_class
		if ( ! isset( $fields['address_2'] ) || ! isset( $fields['address_2']['label_class'] ) ) {
			return $fields;
		}

		$needs_removal_label_class = array_search( 'screen-reader-text', $fields['address_2']['label_class'] );

		if ( false !== $needs_removal_label_class ) {
			unset( $fields['address_2']['label_class'][ $needs_removal_label_class ] );
		}

		return $fields;
	}

	/**
	 * Make address2 fields required.
	 *
	 * @param  array<string, mixed> $fields current default address fields.
	 * @return array<string, mixed>
	 */
	public function make_address2_required_and_update_the_label( $fields ) {
		// Check if billing address_2 field exists before modifying it
		if ( isset( $fields['billing']['billing_address_2'] ) ) {
			$fields['billing']['billing_address_2']['required']      = true;
			$fields['billing']['billing_address_2']['label']         = __( 'Your Address', 'hezarfen-for-woocommerce' );
			$fields['billing']['billing_address_2']['placeholder']   = __( 'Enter your street, avenue, building, and apartment number information.', 'hezarfen-for-woocommerce' );
		}
		
		// Check if shipping address_2 field exists before modifying it
		if ( isset( $fields['shipping']['shipping_address_2'] ) ) {
			$fields['shipping']['shipping_address_2']['required']    = true;
			$fields['shipping']['shipping_address_2']['label']       = __( 'Your Address', 'hezarfen-for-woocommerce' );
			$fields['shipping']['shipping_address_2']['placeholder'] = __( 'Enter your street, avenue, building, and apartment number information.', 'hezarfen-for-woocommerce' );
		}

		return $fields;
	}

	/**
	 * Hides Post Code Fields in the checkout form.
	 *
	 * @return void
	 */
	public function hide_postcode_fields() {
		$wc_ajax = $_GET['wc-ajax'] ?? ''; // phpcs:ignore
		if ( is_checkout() || ( defined( 'WC_DOING_AJAX' ) && 'checkout' === $wc_ajax ) ) {
			Helper::hide_postcode_field();
		}
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

		if ( ! $show || ! ( new PostMetaEncryption() )->health_check() ) {
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
	 * @param array<string, mixed> $fields the current WooCommerce checkout fields.
	 * @return array<string, mixed>
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
	 * @param array<string, mixed> $fields current WooCommerce checkout fields.
	 * @return array<string, mixed>
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
	 * 
	 * @return void
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
	 * @param  array<string, mixed> $fields the current WooCommerce checkout fields.
	 * @return array<string, mixed>
	 */
	public function add_tax_fields( $fields ) {
		$invoice_type_value = ( new \WC_Checkout() )->get_value( 'billing_hez_invoice_type' );

		$address_2_priority = isset( $fields['billing']['billing_address_2']['priority'] ) ? $fields['billing']['billing_address_2']['priority'] : 0;
		$address_1_priority = isset( $fields['billing']['billing_address_1']['priority'] ) ? $fields['billing']['billing_address_1']['priority'] : 0;

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
			'priority' => $address_2_priority ? $address_2_priority + 1 : $address_1_priority + 1,
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
				'input_class' => apply_filters( 'hezarfen_checkout_fields_input_class_billing_hez_tc_number', array() ),
				'priority'    => $fields['billing']['billing_hez_invoice_type']['priority'] + 1,
			);
		}

		$fields['billing']['billing_company'] = array(
			'label'       => __( 'Title', 'hezarfen-for-woocommerce' ),
			'placeholder' => __( 'Enter invoice title', 'hezarfen-for-woocommerce' ),
			'required'    => true,
			'class'       => apply_filters( 'hezarfen_checkout_fields_class_billing_hez_company', array( 'form-row-wide' ) ),
			'input_class' => apply_filters( 'hezarfen_checkout_fields_input_class_billing_hez_company', array() ),
			'priority'    => $fields['billing']['billing_hez_invoice_type']['priority'] + 1,
		);

		$fields['billing']['billing_hez_tax_number'] = array(
			'maxlength'   => '11',
			'id'          => 'hezarfen_tax_number',
			'label'       => __( 'Tax Number', 'hezarfen-for-woocommerce' ),
			'placeholder' => __( 'Enter tax number', 'hezarfen-for-woocommerce' ),
			'required'    => true,
			'class'       => apply_filters( 'hezarfen_checkout_fields_class_billing_hez_tax_number', array( 'form-row-wide' ) ),
			'input_class' => apply_filters( 'hezarfen_checkout_fields_input_class_billing_hez_tax_number', array() ),
			'priority'    => $fields['billing']['billing_company']['priority'] + 1,
		);

		$fields['billing']['billing_hez_tax_office'] = array(
			'id'          => 'hezarfen_tax_office',
			'label'       => __( 'Tax Office', 'hezarfen-for-woocommerce' ),
			'placeholder' => __( 'Enter tax office', 'hezarfen-for-woocommerce' ),
			'required'    => true,
			'class'       => apply_filters( 'hezarfen_checkout_fields_class_billing_hez_tax_office', array( 'form-row-wide' ) ),
			'input_class' => apply_filters( 'hezarfen_checkout_fields_input_class_billing_hez_tax_office', array() ),
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
	 * @param array<string, mixed> $data the posted checkout data.
	 * @return array<string, mixed>
	 */
	public function override_posted_data( $data ) {
		// Check if the T.C. Identitiy Field is active.
		if ( ! empty( $data['billing_hez_TC_number'] ) && $this->hezarfen_show_hezarfen_checkout_tax_fields && self::is_show_identity_field_on_checkout() ) {
			if ( ( new PostMetaEncryption() )->health_check() ) {
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
	 * Validates necessary data after checkout submit.
	 *
	 * @param array<string, mixed> $data the posted checkout data.
	 * @param \WP_Error            $errors Validation errors.
	 *
	 * @return void
	 */
	public function validate_posted_data( $data, $errors ) {
		$tc_id_number = ! empty( $data['billing_hez_TC_number'] ) ? ( new PostMetaEncryption() )->decrypt( $data['billing_hez_TC_number'] ) : '';

		$invoice_type = array_key_exists( 'billing_hez_invoice_type', $_POST ) ? sanitize_key( $_POST['billing_hez_invoice_type'] ) : '';

		// extend here to cover only number validaiton check for the TC ID number.
		if ( 'person' === $invoice_type && $tc_id_number && ( 11 !== strlen( $tc_id_number ) || ! is_numeric( $tc_id_number ) ) ) {
			$errors->add( 'billing_hez_TC_number_validation', '<strong>' . __( 'TC ID number is not valid', 'hezarfen-for-woocommerce' ) . '</strong>', array( 'id' => 'billing_hez_TC_number' ) );
		}

		if( 'company' === $invoice_type ) {
			$tax_number = array_key_exists( 'billing_hez_tax_number', $_POST ) ? sanitize_text_field( $_POST['billing_hez_tax_number'] ) : '';

			if ( ! is_numeric( $tax_number ) || ! in_array( strlen( $tax_number ), array( 10, 11 ), true ) ) {
				$errors->add( 'billing_hez_tax_number_validation', '<strong>' . esc_html__( 'Tax number is not valid', 'hezarfen-for-woocommerce' ) . '</strong>', array( 'id' => 'billing_hez_tax_number' ) );
			}
		}
	}

	/**
	 * Show district and neighborhood fields on checkout page.
	 *
	 * @param array<string, mixed> $fields the current checkout fields.
	 * @return array<string, mixed>
	 */
	public function add_district_and_neighborhood_fields( $fields ) {
		$types = array( 'shipping', 'billing' );

		global $woocommerce;

		foreach ( $types as $type ) {
			$city_field_name         = sprintf( '%s_city', $type );
			$neighborhood_field_name = sprintf( '%s_address_1', $type );

			$get_country_function  = 'get_' . $type . '_country';
			$get_city_function     = 'get_' . $type . '_state';
			$get_district_function = 'get_' . $type . '_city';

			$current_country_code = $woocommerce->customer->$get_country_function();
			if ( $current_country_code && 'TR' !== $current_country_code ) {
				continue;
			}

			// the value has TR prefix such as TR18.
			$current_city_plate_number_prefixed = $woocommerce->customer->$get_city_function();
			$current_district                   = $woocommerce->customer->$get_district_function();

			// remove WooCommerce default district field on checkout.
			unset( $fields[ $type ][ $city_field_name ] );

			$city_class = array();

			if ( array_key_exists( $type, $fields ) && array_key_exists( $city_field_name, $fields[ $type ] ) && array_key_exists( 'class', $fields[ $type ][ $city_field_name ] ) ) {
				$city_class = $fields[ $type ][ $city_field_name ]['class'];
			}

			$fields[ $type ][ $city_field_name ] = array(
				'type'         => 'select',
				'label'        => __( 'Town / City', 'hezarfen-for-woocommerce' ),
				'required'     => true,
				'class'        => apply_filters( 'hezarfen_checkout_fields_class_wc_hezarfen_' . $type . '_district', $city_class ),
				'clear'        => true,
				'autocomplete' => false,
				'priority'     => $fields[ $type ][ $type . '_state' ]['priority'] + 1,
				'options'      => Helper::select2_option_format( Mahalle_Local::get_districts( $current_city_plate_number_prefixed ) ),
			);

			$neighborhood_class = array();

			if ( array_key_exists( $type, $fields ) && array_key_exists( $neighborhood_field_name, $fields[ $type ] ) && array_key_exists( 'class', $fields[ $type ][ $neighborhood_field_name ] ) ) {
				$neighborhood_class = $fields[ $type ][ $neighborhood_field_name ]['class'];
			}

			$fields[ $type ][ $neighborhood_field_name ] = array(
				'type'         => 'select',
				'label'        => __( 'Neighborhood', 'hezarfen-for-woocommerce' ),
				'required'     => true,
				'class'        => apply_filters( 'hezarfen_checkout_fields_class_wc_hezarfen_' . $type . '_neighborhood', $neighborhood_class ),
				'clear'        => true,
				'autocomplete' => false,
				'priority'     => $fields[ $type ][ $type . '_state' ]['priority'] + 2,
				'options'      => Helper::select2_option_format( Mahalle_Local::get_neighborhoods( $current_city_plate_number_prefixed, $current_district, false ) ),
			);
		}

		return $fields;
	}
}

new Checkout();
