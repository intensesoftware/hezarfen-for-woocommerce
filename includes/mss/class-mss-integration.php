<?php
/**
 * MSS Integration for Hezarfen
 * 
 * @package Hezarfen\Inc\MSS
 */

namespace Hezarfen\Inc\MSS;

defined( 'ABSPATH' ) || exit();

/**
 * MSS Integration main class
 */
class MSS_Integration {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->define_constants();
		$this->init_hooks();
		$this->load_dependencies();
		$this->init_mss();
	}
	
	/**
	 * Define MSS constants
	 */
	private function define_constants() {
		if ( ! defined( 'HEZARFEN_MSS_PATH' ) ) {
			define( 'HEZARFEN_MSS_PATH', WC_HEZARFEN_UYGULAMA_YOLU . 'includes/mss/' );
		}
		
		if ( ! defined( 'HEZARFEN_MSS_URL' ) ) {
			define( 'HEZARFEN_MSS_URL', WC_HEZARFEN_UYGULAMA_URL . 'assets/mss/' );
		}
		
		if ( ! defined( 'HEZARFEN_MSS_VERSION' ) ) {
			define( 'HEZARFEN_MSS_VERSION', '2.0.1' );
		}
	}
	
	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
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
		require_once HEZARFEN_MSS_PATH . 'core/class-template-processor.php';
		require_once HEZARFEN_MSS_PATH . 'core/class-contract-renderer.php';
		require_once HEZARFEN_MSS_PATH . 'core/class-contract-validator.php';
		
		// Load settings integration
		require_once HEZARFEN_MSS_PATH . 'admin/class-mss-settings.php';
		require_once HEZARFEN_MSS_PATH . 'admin/class-order-agreements.php';
	}
	
	/**
	 * Initialize MSS functionality
	 */
	public function init_mss() {
		// Initialize MSS settings integration with Hezarfen
		if ( is_admin() ) {
			new \Hezarfen\Inc\MSS\MSS_Settings();
			new \Hezarfen\Inc\MSS\Admin\Order_Agreements();
		}
		
		// Check if MSS is properly configured and load frontend if needed
		add_action( 'init', array( $this, 'maybe_load_frontend' ), 20 );
	}
	
	/**
	 * Maybe load frontend functionality if MSS is configured
	 */
	public function maybe_load_frontend() {
		// Check if MSS is enabled and has active contracts
		$mss_enabled = get_option( 'hezarfen_mss_enabled', 'no' );
		
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
		require_once HEZARFEN_MSS_PATH . 'odeme-sayfasi/class-in-mss-sozlesmeler.php';
		require_once HEZARFEN_MSS_PATH . 'siparis-sonrasi/class-in-mss-siparis-sonrasi.php';
		
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}
	
	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		if ( is_checkout() || is_view_order_page() ) {
			wp_enqueue_script(
				'hezarfen-mss-modal',
				HEZARFEN_MSS_URL . 'js/modal.js',
				array( 'jquery' ),
				HEZARFEN_MSS_VERSION,
				true
			);
			
			wp_enqueue_style(
				'hezarfen-mss-modal',
				HEZARFEN_MSS_URL . 'css/modal.css',
				array(),
				HEZARFEN_MSS_VERSION
			);
		}
		
		if ( is_checkout() ) {
			wp_enqueue_script(
				'hezarfen-mss-general',
				HEZARFEN_MSS_URL . 'js/general.js',
				array( 'jquery' ),
				HEZARFEN_MSS_VERSION,
				true
			);
			
			wp_localize_script(
				'hezarfen-mss-general',
				'ajax_object',
				array( 'ajax_url' => admin_url( 'admin-ajax.php' ) )
			);
			
			wp_enqueue_style(
				'hezarfen-mss-style',
				HEZARFEN_MSS_URL . 'css/style.css',
				array(),
				HEZARFEN_MSS_VERSION
			);
		}
	}
}