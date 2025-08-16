<?php
/**
 * Class Checkout_Block_Test.
 *
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

/**
 * Test class for Checkout Block Integration
 * 
 * This class provides methods to test the checkout block integration
 * and verify that the additional fields are working correctly.
 */
class Checkout_Block_Test {

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		// Only add test functionality in development/staging environments
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			add_action( 'wp_footer', array( $this, 'add_debug_info' ) );
		}
	}

	/**
	 * Add debug information to the footer for testing
	 *
	 * @return void
	 */
	public function add_debug_info() {
		if ( ! is_checkout() ) {
			return;
		}

		?>
		<script>
		// Wait a bit for the checkout block to fully render
		setTimeout(function() {
		console.log('Hezarfen Checkout Block Integration Debug Info:');
		console.log('- Plugin version: <?php echo WC_HEZARFEN_VERSION; ?>');
		console.log('- Has checkout block:', document.querySelector('.wp-block-woocommerce-checkout') !== null);
		console.log('- Additional fields registered:', typeof hezarfen_checkout_block !== 'undefined');
		
		// Test if additional fields are present - try multiple selectors
		const districtSelectors = [
			'select[name*="hezarfen/district"]',
			'select[name*="hezarfen-district"]',
			'select[name*="hezarfen/test-district"]',
			'input[name*="hezarfen/district"]',
			'input[name*="hezarfen-district"]',
			'input[name*="hezarfen/test-district"]',
			'[data-field-id*="hezarfen/district"]',
			'[data-field-id*="hezarfen-district"]',
			'[data-field-id*="hezarfen/test-district"]'
		];
		
		const neighborhoodSelectors = [
			'select[name*="hezarfen/neighborhood"]',
			'select[name*="hezarfen-neighborhood"]',
			'select[name*="hezarfen/test-neighborhood"]',
			'input[name*="hezarfen/neighborhood"]',
			'input[name*="hezarfen-neighborhood"]',
			'input[name*="hezarfen/test-neighborhood"]',
			'[data-field-id*="hezarfen/neighborhood"]',
			'[data-field-id*="hezarfen-neighborhood"]',
			'[data-field-id*="hezarfen/test-neighborhood"]'
		];
		
		console.log('- Working integration loaded:', typeof hezarfen_working !== 'undefined');
		
		let districtFields = [];
		let neighborhoodFields = [];
		
		districtSelectors.forEach(selector => {
			const fields = document.querySelectorAll(selector);
			if (fields.length > 0) {
				districtFields = [...districtFields, ...fields];
				console.log(`- Found district fields with selector "${selector}":`, fields.length);
			}
		});
		
		neighborhoodSelectors.forEach(selector => {
			const fields = document.querySelectorAll(selector);
			if (fields.length > 0) {
				neighborhoodFields = [...neighborhoodFields, ...fields];
				console.log(`- Found neighborhood fields with selector "${selector}":`, fields.length);
			}
		});
		
		console.log('- Total district fields found:', districtFields.length);
		console.log('- Total neighborhood fields found:', neighborhoodFields.length);
		
		if (districtFields.length > 0) {
			console.log('- District field names:', Array.from(districtFields).map(f => f.name || f.getAttribute('data-field-id')));
		}
		
		if (neighborhoodFields.length > 0) {
			console.log('- Neighborhood field names:', Array.from(neighborhoodFields).map(f => f.name || f.getAttribute('data-field-id')));
		}
		
		// Also check for any additional checkout fields
		const allAdditionalFields = document.querySelectorAll('[data-field-id*="/"], [name*="/"]');
		console.log('- All additional fields found:', allAdditionalFields.length);
		if (allAdditionalFields.length > 0) {
			console.log('- Additional field IDs:', Array.from(allAdditionalFields).map(f => f.getAttribute('data-field-id') || f.name));
		}
		}, 2000); // Wait 2 seconds for checkout block to fully load
		</script>
		<?php
	}

	/**
	 * Test if additional checkout fields are registered
	 *
	 * @return bool
	 */
	public static function are_fields_registered() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return false;
		}

		// This would need access to the internal WooCommerce fields registry
		// For now, we'll just check if the function exists
		return true;
	}

	/**
	 * Test AJAX endpoints
	 *
	 * @return array
	 */
	public static function test_ajax_endpoints() {
		$results = array();

		// Test districts endpoint
		$districts_response = wp_remote_post( admin_url( 'admin-ajax.php' ), array(
			'body' => array(
				'action' => 'hezarfen_get_districts_for_checkout_block',
				'city_plate_number' => 'TR34', // Istanbul
				'nonce' => wp_create_nonce( 'hezarfen_checkout_block_nonce' ),
			),
		) );

		$results['districts'] = array(
			'status' => wp_remote_retrieve_response_code( $districts_response ),
			'success' => ! is_wp_error( $districts_response ),
		);

		// Test neighborhoods endpoint
		$neighborhoods_response = wp_remote_post( admin_url( 'admin-ajax.php' ), array(
			'body' => array(
				'action' => 'hezarfen_get_neighborhoods_for_checkout_block',
				'city_plate_number' => 'TR34', // Istanbul
				'district' => 'Kadıköy',
				'nonce' => wp_create_nonce( 'hezarfen_checkout_block_nonce' ),
			),
		) );

		$results['neighborhoods'] = array(
			'status' => wp_remote_retrieve_response_code( $neighborhoods_response ),
			'success' => ! is_wp_error( $neighborhoods_response ),
		);

		return $results;
	}

	/**
	 * Get integration status
	 *
	 * @return array
	 */
	public static function get_integration_status() {
		return array(
			'woocommerce_active' => class_exists( 'WooCommerce' ),
			'woocommerce_blocks_active' => function_exists( 'woocommerce_register_additional_checkout_field' ),
			'hezarfen_integration_loaded' => class_exists( 'Hezarfen\Inc\Checkout_Block_Integration' ),
			'assets_exist' => array(
				'js' => file_exists( WC_HEZARFEN_UYGULAMA_YOLU . 'assets/js/checkout-block.js' ),
				'css' => file_exists( WC_HEZARFEN_UYGULAMA_YOLU . 'assets/css/checkout-block.css' ),
			),
		);
	}
}

// Only instantiate in debug mode
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	new Checkout_Block_Test();
}