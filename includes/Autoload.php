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
	const ADDONS = array(
		array(
			'file'        => 'mahalle-bazli-gonderim-bedeli-for-hezarfen/mahalle-bazli-gonderim-bedeli-for-hezarfen.php',
			'min_version' => WC_HEZARFEN_MIN_MBGB_VERSION,
		),
	);
	
	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		$this->load_plugin_files();

		$this->load_assets();

		register_activation_hook(
			WC_HEZARFEN_FILE,
			array(
				'Hezarfen_Install',
				'install',
			)
		);

		add_filter(
			'woocommerce_get_settings_pages',
			array(
				$this,
				'add_hezarfen_setting_page',
			)
		);

		add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
	}

	/**
	 * Displays admin notices.
	 * 
	 * @return void
	 */
	public function show_admin_notices() {
		foreach ( $this->check_addons() as $notice ) {
			$class = 'error' === $notice['type'] ? 'notice-error' : 'notice-warning';
			printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $class ), esc_html( $notice['message'] ) );
		}
	}

	/**
	 * Checks installed Hezarfen addons' versions. Returns notices if there are outdated addons.
	 * 
	 * @return array
	 */
	private function check_addons() {
		$notices        = array();
		$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );

		foreach ( self::ADDONS as $addon ) {
			if ( in_array( $addon['file'], $active_plugins ) ) {
				if ( ! function_exists( 'get_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$addon_info = get_plugins()[ $addon['file'] ];

				if ( $addon_info['Version'] && version_compare( $addon_info['Version'], $addon['min_version'], '<' ) ) {
					$notices[] = array(
						/* translators: %s plugin name */
						'message' => sprintf( __( '%s plugin has a new version available. Please update it.', 'hezarfen-for-woocommerce' ), $addon_info['Name'] ),
						'type'    => 'warning',
					);
				}
			}
		}

		return $notices;
	}

	/**
	 *
	 * Load Hezarfen Settings Page
	 *
	 * @param array $settings the current WC setting page paths.
	 * @return array
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
		add_action( 'wp_enqueue_scripts', array( $this, 'load_js_files' ) );

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
	 * Load js files
	 *
	 * @return void
	 */
	public function load_js_files() {
		if ( is_checkout() ) {
			wp_enqueue_style(
				'wc_hezarfen_checkout_css',
				plugins_url( 'assets/css/checkout.css', WC_HEZARFEN_FILE ),
				array(),
				WC_HEZARFEN_VERSION
			);

			// TODO: load the js file only in checkout page.
			wp_enqueue_script(
				'wc_hezarfen_checkout_js',
				plugins_url( 'assets/js/checkout.js', WC_HEZARFEN_FILE ),
				array( 'jquery' ),
				WC_HEZARFEN_VERSION,
				true
			);

			// TODO: is that really required?
			wp_enqueue_script(
				'wc_hezarfen_checkout_TR_localization',
				plugins_url( 'assets/packages/select2/i18n/tr.js', WC_HEZARFEN_FILE ),
				array( 'jquery', 'select2' ),
				true,
				'4.1.0-beta.1',
				true
			);

			wp_localize_script(
				'wc_hezarfen_checkout_js',
				'wc_hezarfen_ajax_object',
				array(
					'ajax_url'        => admin_url( 'admin-ajax.php' ),
					'api_url'         => WC_HEZARFEN_API_URL,
					'mahalleio_nonce' => wp_create_nonce( 'mahalle-io-get-data' ),
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
		require_once 'Data/ServiceCredentialEncryption.php';
		require_once 'Data/PostMetaEncryption.php';
		require_once 'Checkout.php';
		require_once 'Ajax.php';
		require_once 'class-mahalle-local.php';
		require_once 'Hezarfen_Install.php';
		require_once 'hezarfen-wc-helpers.php';

		if ( is_admin() ) {
			require_once 'admin/order/OrderDetails.php';
		}
	}
}

new Autoload();
