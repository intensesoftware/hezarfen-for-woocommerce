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
		// Load trait first
		require_once HEZARFEN_MSS_PATH . 'trait-in-mss.php';
		
		// Load core classes
		require_once HEZARFEN_MSS_PATH . 'core/class-contract-types.php';
		require_once HEZARFEN_MSS_PATH . 'core/class-contract-manager.php';
		require_once HEZARFEN_MSS_PATH . 'core/class-template-processor.php';
		require_once HEZARFEN_MSS_PATH . 'core/class-contract-renderer.php';
		require_once HEZARFEN_MSS_PATH . 'core/class-contract-validator.php';
		
		// Load activation/deactivation handler without licensing
		require_once HEZARFEN_MSS_PATH . 'admin/class-mss-activation.php';
		
		// Load original MSS admin class for post types (without menu)
		require_once HEZARFEN_MSS_PATH . 'admin/class-in-mss-yonetim-arayuz.php';
		
		// Load contract management page
		require_once HEZARFEN_MSS_PATH . 'admin/class-contract-management-page.php';
		
		// Load settings integration
		require_once HEZARFEN_MSS_PATH . 'admin/class-mss-settings.php';
	}
	
	/**
	 * Initialize MSS functionality
	 */
	public function init_mss() {
		// Initialize activation/deactivation
		\Hezarfen\Inc\MSS\MSS_Activation::init();
		
		// Initialize the original MSS admin class (for post types and meta boxes)
		// This needs to be done early so post types are registered properly
		new \Intense_MSS_Yonetim_Arayuzu();
		
		// Initialize contract management page (for admin functionality)
		if ( is_admin() ) {
			new \Hezarfen\Inc\MSS\Admin\Contract_Management_Page();
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
			// Check if there are any active contracts
			$active_contracts = \Hezarfen\Inc\MSS\Core\Contract_Manager::get_active_contracts();
			if ( ! empty( $active_contracts ) ) {
				$this->load_frontend_functionality();
			}
		}
	}
	
	/**
	 * Load frontend functionality
	 */
	private function load_frontend_functionality() {
		require_once HEZARFEN_MSS_PATH . 'odeme-sayfasi/class-in-mss-odeme-sayfasi-kullanici-degiskenler.php';
		require_once HEZARFEN_MSS_PATH . 'odeme-sayfasi/class-in-mss-odeme-sayfasi-sepet-degiskenler.php';
		require_once HEZARFEN_MSS_PATH . 'odeme-sayfasi/class-in-mss-sozlesmeler.php';
		require_once HEZARFEN_MSS_PATH . 'siparis-sonrasi/class-in-mss-siparis-sonrasi.php';
		require_once HEZARFEN_MSS_PATH . 'siparis-sonrasi/class-in-mss-siparis-degiskenler.php';
		require_once HEZARFEN_MSS_PATH . 'siparis-detayi/class-in-mss-kullanici-arayuz.php';
		
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}
	
	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		if ( is_checkout() || is_view_order_page() ) {
			wp_enqueue_script(
				'magnific-popup',
				HEZARFEN_MSS_URL . 'kutuphane/magnific-popup/jquery.magnific-popup.min.js',
				array( 'jquery' ),
				HEZARFEN_MSS_VERSION,
				true
			);
			
			wp_enqueue_style(
				'magnific-popup',
				HEZARFEN_MSS_URL . 'kutuphane/magnific-popup/magnific-popup.css',
				array(),
				HEZARFEN_MSS_VERSION
			);
			
			wp_enqueue_script(
				'hezarfen-mss-popup',
				HEZARFEN_MSS_URL . 'js/magnific-popup-init.js',
				array( 'jquery', 'magnific-popup' ),
				HEZARFEN_MSS_VERSION,
				true
			);
			
			wp_enqueue_style(
				'hezarfen-mss-popup-style',
				HEZARFEN_MSS_URL . 'css/magnific-popup-init.css',
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