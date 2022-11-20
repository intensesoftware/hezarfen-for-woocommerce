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
	 * Addons info
	 * 
	 * @var array<array<string, mixed>>
	 */
	private $addons;

	/**
	 * Notices related to addons.
	 * 
	 * @var array<array<string, string>>
	 */
	private $addon_notices;

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
		$this->addons = array(
			array(
				'name'        => 'Mahalle Bazlı Gönderim Bedeli for Hezarfen',
				'short_name'  => 'MBGB',
				'version'     => function () {
					return defined( 'WC_HEZARFEN_MBGB_VERSION' ) ? WC_HEZARFEN_MBGB_VERSION : null;
				},
				'min_version' => WC_HEZARFEN_MIN_MBGB_VERSION,
				'activated'   => function () {
					return defined( 'WC_HEZARFEN_MBGB_VERSION' );
				},
			),
		);

		$this->load_plugin_files();
		$this->load_packages();

		$this->load_assets();

		register_activation_hook(
			WC_HEZARFEN_FILE,
			array(
				'Hezarfen_Install',
				'install',
			)
		);

		add_action(
			'plugins_loaded',
			array(
				$this,
				'check_addons_and_show_notices',
			)
		);

		add_filter(
			'woocommerce_get_settings_pages',
			array(
				$this,
				'add_hezarfen_setting_page',
			)
		);
	}

	/**
	 *
	 * Load Hezarfen Settings Page
	 *
	 * @param \WC_Settings_Page[] $settings the current WC setting page paths.
	 *
	 * @return \WC_Settings_Page[]
	 */
	public function add_hezarfen_setting_page( $settings ) {
		$settings[] = include_once WC_HEZARFEN_UYGULAMA_YOLU .
			'includes/admin/settings/class-hezarfen-settings-hezarfen.php';

		return $settings;
	}
	
	/**
	 * Load assets
	 *
	 * @return void
	 */
	public function load_assets() {
		add_action( 'wp_enqueue_scripts', array( $this, 'load_js_and_css_files' ) );

		if ( is_admin() ) {
			add_action(
				'admin_enqueue_scripts',
				array(
					$this,
					'load_admin_assets_files',
				)
			);
		}
	}

	/**
	 * Load assets files for admin
	 * 
	 * @return void
	 */
	public function load_admin_assets_files() {
		wp_enqueue_script(
			'wc_hezarfen_admin_order_details_js',
			plugins_url( 'assets/admin/js/order-details.js', WC_HEZARFEN_FILE ),
			array( 'jquery' ),
			WC_HEZARFEN_VERSION,
			true
		);
		wp_enqueue_style(
			'wc_hezarfen_checkout_css',
			plugins_url( 'assets/admin/css/order-details.css', WC_HEZARFEN_FILE ),
			array(),
			WC_HEZARFEN_VERSION
		);
	}
	
	/**
	 * Load js and css files
	 *
	 * @return void
	 */
	public function load_js_and_css_files() {
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
				array( 'jquery', 'wc-checkout' ),
				WC_HEZARFEN_VERSION,
				true
			);

			wp_localize_script(
				'wc_hezarfen_checkout_js',
				'wc_hezarfen_ajax_object',
				array(
					'ajax_url'                            => admin_url( 'admin-ajax.php' ),
					'api_url'                             => WC_HEZARFEN_NEIGH_API_URL,
					'mahalleio_nonce'                     => wp_create_nonce( 'mahalle-io-get-data' ),
					'select_option_text'                  => __( 'Select an option', 'hezarfen-for-woocommerce' ),
					'billing_district_field_classes'      => apply_filters( 'hezarfen_checkout_fields_class_wc_hezarfen_billing_district', array( 'form-row-wide' ) ),
					'shipping_district_field_classes'     => apply_filters( 'hezarfen_checkout_fields_class_wc_hezarfen_shipping_district', array( 'form-row-wide' ) ),
					'billing_neighborhood_field_classes'  => apply_filters( 'hezarfen_checkout_fields_class_wc_hezarfen_billing_neighborhood', array( 'form-row-wide' ) ),
					'shipping_neighborhood_field_classes' => apply_filters( 'hezarfen_checkout_fields_class_wc_hezarfen_shipping_neighborhood', array( 'form-row-wide' ) ),
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
		require_once 'Data/Abstracts/Abstract_Encryption.php';
		require_once 'Data/PostMetaEncryption.php';
		require_once 'Checkout.php';
		require_once 'Ajax.php';
		require_once 'class-mahalle-local.php';
		require_once 'Hezarfen_Install.php';
		require_once 'class-hezarfen-wc-helper.php';

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

	/**
	 * Checks addons and shows notices if necessary.
	 * Defines constants to disable outdated addons.
	 * 
	 * @return void
	 */
	public function check_addons_and_show_notices() {
		$this->addon_notices = Helper::check_addons( $this->addons );
		if ( $this->addon_notices ) {
			foreach ( $this->addon_notices as $notice ) {
				define( 'WC_HEZARFEN_OUTDATED_ADDON_' . $notice['addon_short_name'], true );
			}

			add_action(
				'admin_notices',
				function () {
					Helper::render_admin_notices( $this->addon_notices );
				}
			);
		}

		// Check Intense Türkiye İl İlçe Eklentisi For WooCommerce plugin.
		if ( defined( 'INTENSE_IL_ILCE_PLUGIN_PATH' ) ) {
			add_action(
				'admin_notices',
				function () {
					$notice = array(
						'message' => __( '<strong>Hezarfen for WooCommerce</strong> eklentisinin sağıklı çalışabilmesi için <strong>Intense Türkiye İl İlçe Eklentisi For WooCommerce</strong> eklentisini siliniz. <strong>Hezarfen</strong> eklentisi zaten bünyesinde İl, ilçe ve mahalle verilerini barındırmaktadır.', 'hezarfen-for-woocommerce' ),
						'type'    => 'error',
					);

					Helper::render_admin_notices( array( $notice ), true );
				}
			);
		}
	}
}

new Autoload();
