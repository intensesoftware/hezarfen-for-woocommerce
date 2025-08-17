<?php
/**
 * Class Checkout_Block_Integration.
 *
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

use Hezarfen\Inc\Mahalle_Local;
use Hezarfen\Inc\Helper;

/**
 * Checkout Block Integration for Additional Fields
 * 
 * This class handles the integration with WooCommerce Checkout Block
 * to provide the same address transformation functionality that exists
 * in the classic checkout for Turkish addresses.
 */
class Checkout_Block_Integration {

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_additional_checkout_fields' ) );
		add_action( 'init', array( $this, 'register_additional_checkout_fields_fallback' ), 20 );
		add_action( 'woocommerce_set_additional_field_value', array( $this, 'handle_field_save' ), 10, 4 );
		add_filter( 'woocommerce_get_default_value_for_hezarfen/district', array( $this, 'get_district_default_value' ), 10, 3 );
		add_filter( 'woocommerce_get_default_value_for_hezarfen/neighborhood', array( $this, 'get_neighborhood_default_value' ), 10, 3 );
		
		// Add AJAX handlers for dynamic field updates
		add_action( 'wp_ajax_hezarfen_get_districts_for_checkout_block', array( $this, 'ajax_get_districts' ) );
		add_action( 'wp_ajax_nopriv_hezarfen_get_districts_for_checkout_block', array( $this, 'ajax_get_districts' ) );
		add_action( 'wp_ajax_hezarfen_get_neighborhoods_for_checkout_block', array( $this, 'ajax_get_neighborhoods' ) );
		add_action( 'wp_ajax_nopriv_hezarfen_get_neighborhoods_for_checkout_block', array( $this, 'ajax_get_neighborhoods' ) );
		
		// Enqueue scripts for checkout block
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_block_scripts' ) );
		
		// Add admin notice about checkout block support
		add_action( 'admin_notices', array( $this, 'checkout_block_support_notice' ) );
		add_action( 'wp_ajax_hezarfen_dismiss_checkout_block_notice', array( $this, 'dismiss_checkout_block_notice' ) );
	}

	/**
	 * Register additional checkout fields for the checkout block
	 *
	 * @return void
	 */
	public function register_additional_checkout_fields() {
		// Prevent double registration
		static $registered = false;
		if ( $registered ) {
			return;
		}

		// Only register fields if WooCommerce Blocks is available
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			error_log( 'Hezarfen: woocommerce_register_additional_checkout_field function not available' );
			return;
		}

		error_log( 'Hezarfen: Registering additional checkout fields' );
		$registered = true;

		// Temporarily disable complex conditional logic for testing
		if ( false ) {
		// Register district field (replaces city field for Turkey)
		woocommerce_register_additional_checkout_field(
			array(
				'id'            => 'hezarfen/district',
				'label'         => __( 'District', 'hezarfen-for-woocommerce' ),
				'optionalLabel' => __( 'District (optional)', 'hezarfen-for-woocommerce' ),
				'location'      => 'address',
				'type'          => 'select',
				'required'      => array(
					'type'       => 'object',
					'properties' => array(
						'customer' => array(
							'properties' => array(
								'address' => array(
									'properties' => array(
										'country' => array(
											'const' => 'TR'
										)
									)
								)
							)
						)
					)
				),
				'hidden'        => array(
					'type'       => 'object',
					'properties' => array(
						'customer' => array(
							'properties' => array(
								'address' => array(
									'properties' => array(
										'country' => array(
											'not' => array(
												'const' => 'TR'
											)
										)
									)
								)
							)
						)
					)
				),
				'options'       => $this->get_district_options(),
				'sanitize_callback' => array( $this, 'sanitize_district_field' ),
				'validate_callback' => array( $this, 'validate_district_field' ),
			)
		);
		}

		// Temporarily disable complex conditional logic for testing
		if ( false ) {
		// Register neighborhood field (replaces address_1 field for Turkey)
		woocommerce_register_additional_checkout_field(
			array(
				'id'            => 'hezarfen/neighborhood',
				'label'         => __( 'Neighborhood', 'hezarfen-for-woocommerce' ),
				'optionalLabel' => __( 'Neighborhood (optional)', 'hezarfen-for-woocommerce' ),
				'location'      => 'address',
				'type'          => 'select',
				'required'      => array(
					'type'       => 'object',
					'properties' => array(
						'customer' => array(
							'properties' => array(
								'address' => array(
									'properties' => array(
										'country' => array(
											'const' => 'TR'
										)
									)
								)
							)
						)
					)
				),
				'hidden'        => array(
					'type'       => 'object',
					'properties' => array(
						'customer' => array(
							'properties' => array(
								'address' => array(
									'properties' => array(
										'country' => array(
											'not' => array(
												'const' => 'TR'
											)
										)
									)
								)
							)
						)
					)
				),
				'options'       => $this->get_neighborhood_options(),
				'sanitize_callback' => array( $this, 'sanitize_neighborhood_field' ),
				'validate_callback' => array( $this, 'validate_neighborhood_field' ),
			)
		);
		}
	}

	/**
	 * Fallback method to register fields if woocommerce_blocks_loaded doesn't fire
	 *
	 * @return void
	 */
	public function register_additional_checkout_fields_fallback() {
		if ( function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			$this->register_additional_checkout_fields();
		}
	}

	/**
	 * Get district options for the select field
	 *
	 * @return array
	 */
	private function get_district_options() {
		$options = array();
		
		// Get current customer's state (which contains the city plate number)
		$customer = WC()->customer;
		if ( ! $customer ) {
			return $options;
		}

		$current_city_plate_number = $customer->get_billing_state();
		if ( empty( $current_city_plate_number ) ) {
			$current_city_plate_number = $customer->get_shipping_state();
		}

		if ( ! empty( $current_city_plate_number ) ) {
			$districts = Mahalle_Local::get_districts( $current_city_plate_number );
			if ( is_array( $districts ) ) {
				foreach ( $districts as $district ) {
					$options[] = array(
						'value' => $district,
						'label' => $district
					);
				}
			}
		}

		return $options;
	}

	/**
	 * Get neighborhood options for the select field
	 *
	 * @return array
	 */
	private function get_neighborhood_options() {
		$options = array();
		
		// Get current customer's state and city
		$customer = WC()->customer;
		if ( ! $customer ) {
			return $options;
		}

		$current_city_plate_number = $customer->get_billing_state();
		$current_district = $customer->get_billing_city();
		
		if ( empty( $current_city_plate_number ) ) {
			$current_city_plate_number = $customer->get_shipping_state();
			$current_district = $customer->get_shipping_city();
		}

		if ( ! empty( $current_city_plate_number ) && ! empty( $current_district ) ) {
			$neighborhoods = Mahalle_Local::get_neighborhoods( $current_city_plate_number, $current_district, false );
			if ( is_array( $neighborhoods ) ) {
				foreach ( $neighborhoods as $neighborhood ) {
					$options[] = array(
						'value' => $neighborhood,
						'label' => $neighborhood
					);
				}
			}
		}

		return $options;
	}

	/**
	 * Handle field save - map additional fields to standard WooCommerce fields
	 *
	 * @param string $key The field key
	 * @param string $value The field value
	 * @param string $group The field group (billing/shipping)
	 * @param object $wc_object The WooCommerce object (customer/order)
	 * @return void
	 */
	public function handle_field_save( $key, $value, $group, $wc_object ) {
		// Map district field to city field for Turkey
		if ( 'hezarfen/district' === $key ) {
			$city_meta_key = '_' . $group . '_city';
			$wc_object->update_meta_data( $city_meta_key, $value, true );
		}

		// Map neighborhood field to address_1 field for Turkey
		if ( 'hezarfen/neighborhood' === $key ) {
			$address_1_meta_key = '_' . $group . '_address_1';
			$wc_object->update_meta_data( $address_1_meta_key, $value, true );
		}
	}

	/**
	 * Get default value for district field
	 *
	 * @param mixed $value Current value
	 * @param string $group Field group (billing/shipping)
	 * @param object $wc_object WooCommerce object
	 * @return mixed
	 */
	public function get_district_default_value( $value, $group, $wc_object ) {
		$city_meta_key = '_' . $group . '_city';
		return $wc_object->get_meta( $city_meta_key );
	}

	/**
	 * Get default value for neighborhood field
	 *
	 * @param mixed $value Current value
	 * @param string $group Field group (billing/shipping)
	 * @param object $wc_object WooCommerce object
	 * @return mixed
	 */
	public function get_neighborhood_default_value( $value, $group, $wc_object ) {
		$address_1_meta_key = '_' . $group . '_address_1';
		return $wc_object->get_meta( $address_1_meta_key );
	}

	/**
	 * Sanitize district field
	 *
	 * @param string $field_value The field value
	 * @return string
	 */
	public function sanitize_district_field( $field_value ) {
		return sanitize_text_field( $field_value );
	}

	/**
	 * Sanitize neighborhood field
	 *
	 * @param string $field_value The field value
	 * @return string
	 */
	public function sanitize_neighborhood_field( $field_value ) {
		return sanitize_text_field( $field_value );
	}

	/**
	 * Validate district field
	 *
	 * @param string $field_value The field value
	 * @return \WP_Error|void
	 */
	public function validate_district_field( $field_value ) {
		// Get current customer's country
		$customer = WC()->customer;
		if ( ! $customer ) {
			return;
		}

		$country = $customer->get_billing_country();
		if ( empty( $country ) ) {
			$country = $customer->get_shipping_country();
		}

		// Only validate for Turkey
		if ( 'TR' !== $country ) {
			return;
		}

		if ( empty( $field_value ) ) {
			return new \WP_Error( 'required_district', __( 'District is required for Turkish addresses.', 'hezarfen-for-woocommerce' ) );
		}

		// Validate that the district exists for the selected city
		$current_city_plate_number = $customer->get_billing_state();
		if ( empty( $current_city_plate_number ) ) {
			$current_city_plate_number = $customer->get_shipping_state();
		}

		if ( ! empty( $current_city_plate_number ) ) {
			$districts = Mahalle_Local::get_districts( $current_city_plate_number );
			if ( is_array( $districts ) && ! in_array( $field_value, $districts, true ) ) {
				return new \WP_Error( 'invalid_district', __( 'Please select a valid district.', 'hezarfen-for-woocommerce' ) );
			}
		}
	}

	/**
	 * Validate neighborhood field
	 *
	 * @param string $field_value The field value
	 * @return \WP_Error|void
	 */
	public function validate_neighborhood_field( $field_value ) {
		// Get current customer's country
		$customer = WC()->customer;
		if ( ! $customer ) {
			return;
		}

		$country = $customer->get_billing_country();
		if ( empty( $country ) ) {
			$country = $customer->get_shipping_country();
		}

		// Only validate for Turkey
		if ( 'TR' !== $country ) {
			return;
		}

		if ( empty( $field_value ) ) {
			return new \WP_Error( 'required_neighborhood', __( 'Neighborhood is required for Turkish addresses.', 'hezarfen-for-woocommerce' ) );
		}

		// Validate that the neighborhood exists for the selected city and district
		$current_city_plate_number = $customer->get_billing_state();
		$current_district = $customer->get_billing_city();
		
		if ( empty( $current_city_plate_number ) ) {
			$current_city_plate_number = $customer->get_shipping_state();
			$current_district = $customer->get_shipping_city();
		}

		if ( ! empty( $current_city_plate_number ) && ! empty( $current_district ) ) {
			$neighborhoods = Mahalle_Local::get_neighborhoods( $current_city_plate_number, $current_district, false );
			if ( is_array( $neighborhoods ) && ! in_array( $field_value, $neighborhoods, true ) ) {
				return new \WP_Error( 'invalid_neighborhood', __( 'Please select a valid neighborhood.', 'hezarfen-for-woocommerce' ) );
			}
		}
	}

	/**
	 * Enqueue scripts for checkout block integration
	 *
	 * @return void
	 */
	public function enqueue_checkout_block_scripts() {
		// Only enqueue on checkout page with blocks
		if ( ! is_checkout() || ! has_block( 'woocommerce/checkout' ) ) {
			return;
		}

		wp_enqueue_style(
			'hezarfen-checkout-block-css',
			plugins_url( 'assets/css/checkout-block.css', WC_HEZARFEN_FILE ),
			array(),
			WC_HEZARFEN_VERSION
		);

		wp_enqueue_script(
			'hezarfen-checkout-block',
			plugins_url( 'assets/js/checkout-block.js', WC_HEZARFEN_FILE ),
			array( 'jquery' ),
			WC_HEZARFEN_VERSION,
			true
		);

		wp_localize_script(
			'hezarfen-checkout-block',
			'hezarfen_checkout_block',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'hezarfen_checkout_block_nonce' ),
			)
		);
	}

	/**
	 * AJAX handler to get districts for a given city
	 *
	 * @return void
	 */
	public function ajax_get_districts() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'hezarfen_checkout_block_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$city_plate_number = sanitize_text_field( $_POST['city_plate_number'] ?? '' );
		
		if ( empty( $city_plate_number ) ) {
			wp_send_json_error( 'City plate number is required' );
		}

		$districts = Mahalle_Local::get_districts( $city_plate_number );
		$options = array();

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
	 * AJAX handler to get neighborhoods for a given city and district
	 *
	 * @return void
	 */
	public function ajax_get_neighborhoods() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'hezarfen_checkout_block_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$city_plate_number = sanitize_text_field( $_POST['city_plate_number'] ?? '' );
		$district = sanitize_text_field( $_POST['district'] ?? '' );
		
		if ( empty( $city_plate_number ) || empty( $district ) ) {
			wp_send_json_error( 'City plate number and district are required' );
		}

		$neighborhoods = Mahalle_Local::get_neighborhoods( $city_plate_number, $district, false );
		$options = array();

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
	 * Show admin notice about checkout block support
	 *
	 * @return void
	 */
	public function checkout_block_support_notice() {
		// Only show on WooCommerce admin pages
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'woocommerce' ) === false ) {
			return;
		}

		// Check if user has dismissed this notice
		if ( get_user_meta( get_current_user_id(), 'hezarfen_checkout_block_notice_dismissed', true ) ) {
			return;
		}

		// Check if WooCommerce Blocks is available
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			?>
			<div class="notice notice-info is-dismissible" data-notice="hezarfen-checkout-block">
				<p>
					<strong><?php esc_html_e( 'Hezarfen for WooCommerce', 'hezarfen-for-woocommerce' ); ?></strong>
					<?php esc_html_e( 'now supports WooCommerce Checkout Block! Install or update WooCommerce Blocks to enable Turkish address fields in the new checkout experience.', 'hezarfen-for-woocommerce' ); ?>
				</p>
			</div>
			<?php
		} else {
			?>
			<div class="notice notice-success is-dismissible" data-notice="hezarfen-checkout-block">
				<p>
					<strong><?php esc_html_e( 'Hezarfen for WooCommerce', 'hezarfen-for-woocommerce' ); ?></strong>
					<?php esc_html_e( 'Checkout Block integration is active! Turkish customers will now see district and neighborhood fields in both classic and block checkout.', 'hezarfen-for-woocommerce' ); ?>
				</p>
			</div>
			<?php
		}

		// Add script to handle notice dismissal
		?>
		<script>
		jQuery(document).ready(function($) {
			$(document).on('click', '[data-notice="hezarfen-checkout-block"] .notice-dismiss', function() {
				$.post(ajaxurl, {
					action: 'hezarfen_dismiss_checkout_block_notice',
					nonce: '<?php echo wp_create_nonce( 'hezarfen_dismiss_notice' ); ?>'
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Handle dismissal of checkout block notice
	 *
	 * @return void
	 */
	public function dismiss_checkout_block_notice() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'hezarfen_dismiss_notice' ) ) {
			wp_die( 'Security check failed' );
		}

		// Mark notice as dismissed for current user
		update_user_meta( get_current_user_id(), 'hezarfen_checkout_block_notice_dismissed', true );
		
		wp_send_json_success();
	}
}

new Checkout_Block_Integration();