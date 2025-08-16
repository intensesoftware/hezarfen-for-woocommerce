<?php
/**
 * Working Checkout Block Integration
 *
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

use Hezarfen\Inc\Mahalle_Local;
use Hezarfen\Inc\Helper;

/**
 * Working Checkout Block Integration
 * 
 * This transforms the actual WooCommerce fields for Turkish addresses:
 * - billing_city -> District dropdown
 * - billing_address_1 -> Neighborhood dropdown
 */
class Checkout_Block_Simple_Working {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_working_fields' ) );
		add_action( 'init', array( $this, 'register_working_fields_fallback' ), 25 );
		
		// Add AJAX handlers for dynamic field updates
		add_action( 'wp_ajax_hezarfen_get_districts_working', array( $this, 'ajax_get_districts' ) );
		add_action( 'wp_ajax_nopriv_hezarfen_get_districts_working', array( $this, 'ajax_get_districts' ) );
		add_action( 'wp_ajax_hezarfen_get_neighborhoods_working', array( $this, 'ajax_get_neighborhoods' ) );
		add_action( 'wp_ajax_nopriv_hezarfen_get_neighborhoods_working', array( $this, 'ajax_get_neighborhoods' ) );
		
		// Enqueue scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Register working fields that transform actual WooCommerce fields
	 */
	public function register_working_fields() {
		static $registered = false;
		if ( $registered ) {
			return;
		}

		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			error_log( 'Hezarfen Working: woocommerce_register_additional_checkout_field not available' );
			return;
		}

		error_log( 'Hezarfen Working: Registering working fields' );
		$registered = true;

		try {
			// District field - replaces city field for Turkey
			$result1 = woocommerce_register_additional_checkout_field(
				array(
					'id'       => 'hezarfen/district',
					'label'    => __( 'District (İlçe)', 'hezarfen-for-woocommerce' ),
					'location' => 'address',
					'type'     => 'select',
					'required' => true,
					'options'  => $this->get_district_options(),
					'sanitize_callback' => array( $this, 'sanitize_district' ),
					'validate_callback' => array( $this, 'validate_district' ),
					// Show only for Turkey
					'hidden'   => array(
						'type' => 'object',
						'properties' => array(
							'customer' => array(
								'properties' => array(
									'address' => array(
										'properties' => array(
											'country' => array(
												'not' => array( 'const' => 'TR' )
											)
										)
									)
								)
							)
						)
					),
				)
			);
			error_log( 'Hezarfen Working: District field registered: ' . ( $result1 ? 'SUCCESS' : 'FAILED' ) );

			// Neighborhood field - replaces address_1 field for Turkey
			$result2 = woocommerce_register_additional_checkout_field(
				array(
					'id'       => 'hezarfen/neighborhood',
					'label'    => __( 'Neighborhood (Mahalle)', 'hezarfen-for-woocommerce' ),
					'location' => 'address',
					'type'     => 'select',
					'required' => true,
					'options'  => $this->get_neighborhood_options(),
					'sanitize_callback' => array( $this, 'sanitize_neighborhood' ),
					'validate_callback' => array( $this, 'validate_neighborhood' ),
					// Show only for Turkey
					'hidden'   => array(
						'type' => 'object',
						'properties' => array(
							'customer' => array(
								'properties' => array(
									'address' => array(
										'properties' => array(
											'country' => array(
												'not' => array( 'const' => 'TR' )
											)
										)
									)
								)
							)
						)
					),
				)
			);
			error_log( 'Hezarfen Working: Neighborhood field registered: ' . ( $result2 ? 'SUCCESS' : 'FAILED' ) );

			// Hook into field save to map to standard WooCommerce fields
			add_action( 'woocommerce_set_additional_field_value', array( $this, 'handle_field_save' ), 10, 4 );
			
			// Hook into field retrieval to get values from standard fields
			add_filter( 'woocommerce_get_default_value_for_hezarfen/district', array( $this, 'get_district_default' ), 10, 3 );
			add_filter( 'woocommerce_get_default_value_for_hezarfen/neighborhood', array( $this, 'get_neighborhood_default' ), 10, 3 );

			error_log( 'Hezarfen Working: All fields registered successfully' );
			
		} catch ( Exception $e ) {
			error_log( 'Hezarfen Working: Error registering fields: ' . $e->getMessage() );
		}
	}

	/**
	 * Fallback registration
	 */
	public function register_working_fields_fallback() {
		if ( function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			$this->register_working_fields();
		}
	}

	/**
	 * Get district options
	 */
	private function get_district_options() {
		$options = array(
			array( 'value' => '', 'label' => __( 'Select District', 'hezarfen-for-woocommerce' ) )
		);
		
		// Get current customer's state (city plate number)
		$customer = WC()->customer;
		if ( $customer ) {
			$state = $customer->get_billing_state();
			if ( empty( $state ) ) {
				$state = $customer->get_shipping_state();
			}
			
			if ( ! empty( $state ) ) {
				$districts = Mahalle_Local::get_districts( $state );
				if ( is_array( $districts ) ) {
					foreach ( $districts as $district ) {
						$options[] = array(
							'value' => $district,
							'label' => $district
						);
					}
				}
			}
		}
		
		return $options;
	}

	/**
	 * Get neighborhood options
	 */
	private function get_neighborhood_options() {
		$options = array(
			array( 'value' => '', 'label' => __( 'Select Neighborhood', 'hezarfen-for-woocommerce' ) )
		);
		
		// Get current customer's state and city
		$customer = WC()->customer;
		if ( $customer ) {
			$state = $customer->get_billing_state();
			$city = $customer->get_billing_city();
			
			if ( empty( $state ) ) {
				$state = $customer->get_shipping_state();
				$city = $customer->get_shipping_city();
			}
			
			if ( ! empty( $state ) && ! empty( $city ) ) {
				$neighborhoods = Mahalle_Local::get_neighborhoods( $state, $city, false );
				if ( is_array( $neighborhoods ) ) {
					foreach ( $neighborhoods as $neighborhood ) {
						$options[] = array(
							'value' => $neighborhood,
							'label' => $neighborhood
						);
					}
				}
			}
		}
		
		return $options;
	}

	/**
	 * Handle field save - map to standard WooCommerce fields
	 */
	public function handle_field_save( $key, $value, $group, $wc_object ) {
		// Map district to city field
		if ( 'hezarfen/district' === $key ) {
			$city_key = '_' . $group . '_city';
			$wc_object->update_meta_data( $city_key, $value, true );
			error_log( "Hezarfen Working: Mapped district '$value' to $city_key" );
		}

		// Map neighborhood to address_1 field
		if ( 'hezarfen/neighborhood' === $key ) {
			$address_key = '_' . $group . '_address_1';
			$wc_object->update_meta_data( $address_key, $value, true );
			error_log( "Hezarfen Working: Mapped neighborhood '$value' to $address_key" );
		}
	}

	/**
	 * Get district default value from city field
	 */
	public function get_district_default( $value, $group, $wc_object ) {
		$city_key = '_' . $group . '_city';
		$stored_value = $wc_object->get_meta( $city_key );
		error_log( "Hezarfen Working: Getting district default from $city_key: $stored_value" );
		return $stored_value;
	}

	/**
	 * Get neighborhood default value from address_1 field
	 */
	public function get_neighborhood_default( $value, $group, $wc_object ) {
		$address_key = '_' . $group . '_address_1';
		$stored_value = $wc_object->get_meta( $address_key );
		error_log( "Hezarfen Working: Getting neighborhood default from $address_key: $stored_value" );
		return $stored_value;
	}

	/**
	 * Sanitize district field
	 */
	public function sanitize_district( $value ) {
		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize neighborhood field
	 */
	public function sanitize_neighborhood( $value ) {
		return sanitize_text_field( $value );
	}

	/**
	 * Validate district field
	 */
	public function validate_district( $value ) {
		// Only validate for Turkey
		$customer = WC()->customer;
		if ( ! $customer ) {
			return;
		}

		$country = $customer->get_billing_country();
		if ( empty( $country ) ) {
			$country = $customer->get_shipping_country();
		}

		if ( 'TR' !== $country ) {
			return;
		}

		if ( empty( $value ) ) {
			return new \WP_Error( 'required_district', __( 'District is required for Turkish addresses.', 'hezarfen-for-woocommerce' ) );
		}
	}

	/**
	 * Validate neighborhood field
	 */
	public function validate_neighborhood( $value ) {
		// Only validate for Turkey
		$customer = WC()->customer;
		if ( ! $customer ) {
			return;
		}

		$country = $customer->get_billing_country();
		if ( empty( $country ) ) {
			$country = $customer->get_shipping_country();
		}

		if ( 'TR' !== $country ) {
			return;
		}

		if ( empty( $value ) ) {
			return new \WP_Error( 'required_neighborhood', __( 'Neighborhood is required for Turkish addresses.', 'hezarfen-for-woocommerce' ) );
		}
	}

	/**
	 * AJAX handler to get districts
	 */
	public function ajax_get_districts() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'hezarfen_working_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$city_plate_number = sanitize_text_field( $_POST['city_plate_number'] ?? '' );
		
		if ( empty( $city_plate_number ) ) {
			wp_send_json_error( 'City plate number is required' );
		}

		$districts = Mahalle_Local::get_districts( $city_plate_number );
		$options = array(
			array( 'value' => '', 'label' => __( 'Select District', 'hezarfen-for-woocommerce' ) )
		);

		if ( is_array( $districts ) ) {
			foreach ( $districts as $district ) {
				$options[] = array(
					'value' => $district,
					'label' => $district
				);
			}
		}

		wp_send_json_success( $options );
	}

	/**
	 * AJAX handler to get neighborhoods
	 */
	public function ajax_get_neighborhoods() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'hezarfen_working_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$city_plate_number = sanitize_text_field( $_POST['city_plate_number'] ?? '' );
		$district = sanitize_text_field( $_POST['district'] ?? '' );
		
		if ( empty( $city_plate_number ) || empty( $district ) ) {
			wp_send_json_error( 'City plate number and district are required' );
		}

		$neighborhoods = Mahalle_Local::get_neighborhoods( $city_plate_number, $district, false );
		$options = array(
			array( 'value' => '', 'label' => __( 'Select Neighborhood', 'hezarfen-for-woocommerce' ) )
		);

		if ( is_array( $neighborhoods ) ) {
			foreach ( $neighborhoods as $neighborhood ) {
				$options[] = array(
					'value' => $neighborhood,
					'label' => $neighborhood
				);
			}
		}

		wp_send_json_success( $options );
	}

	/**
	 * Enqueue scripts
	 */
	public function enqueue_scripts() {
		if ( ! is_checkout() || ! has_block( 'woocommerce/checkout' ) ) {
			return;
		}

		wp_enqueue_script(
			'hezarfen-working-checkout-block',
			plugins_url( 'assets/js/checkout-block-working.js', WC_HEZARFEN_FILE ),
			array( 'jquery' ),
			WC_HEZARFEN_VERSION,
			true
		);

		wp_localize_script(
			'hezarfen-working-checkout-block',
			'hezarfen_working',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'hezarfen_working_nonce' ),
			)
		);
	}
}

new Checkout_Block_Simple_Working();