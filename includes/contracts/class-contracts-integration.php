<?php
/**
 * Contracts Integration for Hezarfen
 * 
 * @package Hezarfen\Inc\Contracts
 */

namespace Hezarfen\Inc\Contracts;

defined( 'ABSPATH' ) || exit();

/**
 * Contracts Integration main class
 */
class Contracts_Integration {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->define_constants();
		$this->load_dependencies();
		$this->init_mss();
	}
	
	/**
	 * Define MSS constants
	 */
	private function define_constants() {
		if ( ! defined( 'HEZARFEN_CONTRACTS_PATH' ) ) {
			define( 'HEZARFEN_CONTRACTS_PATH', WC_HEZARFEN_UYGULAMA_YOLU . 'includes/contracts/' );
		}
		
		if ( ! defined( 'HEZARFEN_CONTRACTS_URL' ) ) {
			define( 'HEZARFEN_CONTRACTS_URL', WC_HEZARFEN_UYGULAMA_URL . 'assets/contracts/' );
		}
	}
	
	/**
	 * Load text domain
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 
			'intense-mss-for-woocommerce', 
			false, 
			basename( dirname( WC_HEZARFEN_FILE ) ) . '/languages/' 
		);
	}
	
	/**
	 * Load dependencies
	 */
	private function load_dependencies() {
		// Load core classes
		require_once HEZARFEN_CONTRACTS_PATH . 'core/class-template-processor.php';
		require_once HEZARFEN_CONTRACTS_PATH . 'core/class-contract-renderer.php';
		require_once HEZARFEN_CONTRACTS_PATH . 'core/class-contract-validator.php';
		require_once HEZARFEN_CONTRACTS_PATH . 'core/class-post-order-processor.php';
		require_once HEZARFEN_CONTRACTS_PATH . 'frontend/class-customer-agreements.php';
		
		// Load settings integration
		require_once HEZARFEN_CONTRACTS_PATH . 'admin/class-contracts-settings.php';
		require_once HEZARFEN_CONTRACTS_PATH . 'admin/class-order-agreements.php';
	}
	
	/**
	 * Initialize MSS functionality
	 */
	public function init_mss() {
		// Initialize Contracts settings integration with Hezarfen
		if ( is_admin() ) {
			new \Hezarfen\Inc\Contracts\Contracts_Settings();
			new \Hezarfen\Inc\Contracts\Admin\Order_Agreements();
		}
		
		// Check if MSS is properly configured and load frontend if needed
		add_action( 'init', array( $this, 'maybe_load_frontend' ), 20 );
	}
	
	/**
	 * Maybe load frontend functionality if MSS is configured
	 */
	public function maybe_load_frontend() {
		// Check if MSS is enabled and has active contracts
		$mss_enabled = get_option( 'hezarfen_contracts_enabled', 'no' );
		
		if ( 'yes' === $mss_enabled ) {
			// Check if there are any active contracts in settings
			$settings = get_option( 'hezarfen_mss_settings', array() );
			$contracts = isset( $settings['contracts'] ) ? $settings['contracts'] : array();
			
			$has_active_contracts = false;
			foreach ( $contracts as $contract ) {
				if ( ! empty( $contract['enabled'] ) && ! empty( $contract['template_id'] ) ) {
					$has_active_contracts = true;
					break;
				}
			}
			
			if ( $has_active_contracts ) {
				$this->load_frontend_functionality();
			}
		}
	}
	
	/**
	 * Load frontend functionality
	 */
	private function load_frontend_functionality() {
		// Initialize core Contracts functionality
		\Hezarfen\Inc\Contracts\Core\Contract_Renderer::init_checkout_hooks();
		\Hezarfen\Inc\Contracts\Core\Post_Order_Processor::init();
		\Hezarfen\Inc\Contracts\Frontend\Customer_Agreements::init();
		
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}
	
	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		if ( is_checkout() || is_view_order_page() ) {
			wp_enqueue_script(
				'hezarfen-contracts-modal',
				HEZARFEN_CONTRACTS_URL . 'js/modal.js',
				array( 'jquery' ),
				WC_HEZARFEN_VERSION,
				true
			);
			
			wp_enqueue_style(
				'hezarfen-contracts-modal',
				HEZARFEN_CONTRACTS_URL . 'css/modal.css',
				array(),
				WC_HEZARFEN_VERSION
			);
		}
		
		if ( is_checkout() ) {
			wp_enqueue_script(
				'hezarfen-contracts-general',
				HEZARFEN_CONTRACTS_URL . 'js/general.js',
				array( 'jquery' ),
				WC_HEZARFEN_VERSION,
				true
			);
			
			wp_localize_script(
				'hezarfen-contracts-general',
				'ajax_object',
				array( 'ajax_url' => admin_url( 'admin-ajax.php' ) )
			);
			
			wp_enqueue_style(
				'hezarfen-contracts-style',
				HEZARFEN_CONTRACTS_URL . 'css/style.css',
				array(),
				WC_HEZARFEN_VERSION
			);
		}
	}
}