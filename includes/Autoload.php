<?php
/**
 * Class Autoload.
 *
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

/**
 * Autoload
 */
class Autoload {
	/**
	 * Package names and their main classes.
	 * 
	 * @var string[]
	 */
	private $packages = array(
		'manual-shipment-tracking',
	);

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		$this->load_plugin_files();
		$this->load_assets();

		add_action( 'plugins_loaded', array( $this, 'load_packages' ) );
	}

	/**
	 * Load assets
	 *
	 * @return void
	 */
	public function load_assets() {
		add_action( 'wp_enqueue_scripts', array( $this, 'load_js_and_css_files' ) );
	}

	/**
	 * Load js and css files
	 *
	 * @return void
	 */
	public function load_js_and_css_files() {
		wp_register_script(
			'wc_hezarfen_mahalle_helper_js',
			plugins_url( 'assets/js/mahalle-helper.js', WC_HEZARFEN_FILE ),
			array( 'jquery', 'select2', 'selectWoo' ),
			WC_HEZARFEN_VERSION,
			true
		);
		wp_localize_script(
			'wc_hezarfen_mahalle_helper_js',
			'hezarfen_mahalle_helper_backend',
			array(
				'api_url'            => WC_HEZARFEN_NEIGH_API_URL,
				'select_option_text' => __( 'Select an option', 'hezarfen-for-woocommerce' ),
			)
		);

		if ( is_checkout() ) {
			wp_enqueue_style(
				'wc_hezarfen_checkout_css',
				plugins_url( 'assets/css/checkout.css', WC_HEZARFEN_FILE ),
				array(),
				WC_HEZARFEN_VERSION
			);

			wp_enqueue_script(
				'wc_hezarfen_checkout_js',
				plugins_url( 'assets/js/checkout.js', WC_HEZARFEN_FILE ),
				array( 'jquery', 'wc-checkout', 'wc_hezarfen_mahalle_helper_js' ),
				WC_HEZARFEN_VERSION,
				true
			);

			wp_localize_script(
				'wc_hezarfen_checkout_js',
				'wc_hezarfen_ajax_object',
				array(
					'ajax_url'                            => admin_url( 'admin-ajax.php' ),
					'mahalleio_nonce'                     => wp_create_nonce( 'mahalle-io-get-data' ),
					'billing_district_field_classes'      => apply_filters( 'hezarfen_checkout_fields_class_wc_hezarfen_billing_district', array() ),
					'shipping_district_field_classes'     => apply_filters( 'hezarfen_checkout_fields_class_wc_hezarfen_shipping_district', array() ),
					'billing_neighborhood_field_classes'  => apply_filters( 'hezarfen_checkout_fields_class_wc_hezarfen_billing_neighborhood', array() ),
					'shipping_neighborhood_field_classes' => apply_filters( 'hezarfen_checkout_fields_class_wc_hezarfen_shipping_neighborhood', array() ),
					'should_notify_neighborhood_changed'  => apply_filters( 'hezarfen_checkout_should_notify_neighborhood_changed', false ),
				)
			);
		}
	}

	/**
	 * Load plugin files.
	 *
	 * @return void
	 */
	public function load_plugin_files() {
		require_once 'class-hezarfen-wc-helper.php';
		require_once 'class-hezarfen.php';
		require_once 'Data/Abstracts/Abstract_Encryption.php';
		require_once 'Data/PostMetaEncryption.php';
		require_once 'Checkout.php';
		require_once 'class-my-account.php';
		require_once 'Ajax.php';
		require_once 'class-mahalle-local.php';
		require_once 'Hezarfen_Install.php';
		require_once 'class-compatibility.php';
		require_once 'class-notification-provider.php';

		if ( is_admin() ) {
			require_once 'admin/order/OrderDetails.php';
		}
	}

	/**
	 * Loads and initializes packages.
	 * 
	 * @return void
	 */
	public function load_packages() {
		foreach ( $this->packages as $package_name ) {
			require_once WC_HEZARFEN_UYGULAMA_YOLU . "packages/$package_name/$package_name.php";
		}
	}
}

new Autoload();
