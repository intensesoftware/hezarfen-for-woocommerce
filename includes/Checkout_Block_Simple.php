<?php
/**
 * Simple Checkout Block Integration for testing
 *
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

/**
 * Simple Checkout Block Integration
 * 
 * This is a simplified version to test if basic field registration works
 */
class Checkout_Block_Simple {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_simple_fields' ) );
		add_action( 'init', array( $this, 'register_simple_fields_fallback' ), 25 );
	}

	/**
	 * Register simple test fields
	 */
	public function register_simple_fields() {
		static $registered = false;
		if ( $registered ) {
			return;
		}

		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			error_log( 'Hezarfen Simple: woocommerce_register_additional_checkout_field not available' );
			return;
		}

		error_log( 'Hezarfen Simple: Registering simple test fields' );
		$registered = true;

		try {
			// Simple district field - always visible for testing
			$result1 = woocommerce_register_additional_checkout_field(
			array(
				'id'       => 'hezarfen/test-district',
				'label'    => 'Test District (İlçe)',
				'location' => 'address',
				'type'     => 'select',
				'required' => false,
				'options'  => array(
					array( 'value' => '', 'label' => 'Select District' ),
					array( 'value' => 'kadikoy', 'label' => 'Kadıköy' ),
					array( 'value' => 'besiktas', 'label' => 'Beşiktaş' ),
					array( 'value' => 'sisli', 'label' => 'Şişli' ),
				),
			)
		);
		error_log( 'Hezarfen Simple: District field registered: ' . ( $result1 ? 'SUCCESS' : 'FAILED' ) );

		// Simple neighborhood field - always visible for testing
		$result2 = woocommerce_register_additional_checkout_field(
			array(
				'id'       => 'hezarfen/test-neighborhood',
				'label'    => 'Test Neighborhood (Mahalle)',
				'location' => 'address',
				'type'     => 'select',
				'required' => false,
				'options'  => array(
					array( 'value' => '', 'label' => 'Select Neighborhood' ),
					array( 'value' => 'fenerbahce', 'label' => 'Fenerbahçe' ),
					array( 'value' => 'moda', 'label' => 'Moda' ),
					array( 'value' => 'caferaga', 'label' => 'Caferağa' ),
				),
			)
		);
		error_log( 'Hezarfen Simple: Neighborhood field registered: ' . ( $result2 ? 'SUCCESS' : 'FAILED' ) );

		error_log( 'Hezarfen Simple: Fields registered successfully' );
		
		} catch ( Exception $e ) {
			error_log( 'Hezarfen Simple: Error registering fields: ' . $e->getMessage() );
		}
	}

	/**
	 * Fallback registration
	 */
	public function register_simple_fields_fallback() {
		if ( function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			$this->register_simple_fields();
		}
	}
}

// Only load if we're testing
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	new Checkout_Block_Simple();
}