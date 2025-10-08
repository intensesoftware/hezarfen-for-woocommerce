<?php
/**
 * Plugin Name: Hezarfen - WooCommerce Kargo Entegrasyonu - WooCommerce Kargo Takip, İlçe/Mahalle, Sözleşmeler For Woocommerce
 * Description: Türkiye'nin WooCommerce kargo eklentisi - 26+ kargo firmalası için takip, sms, e-posta bildirimleri + Mesafeli Satış Sözleşmesi desteği
 * Version: 2.7.10
 * Author: Intense Yazılım Ltd.
 * Author URI: https://intense.com.tr
 * Developer: Intense Yazılım Ltd.
 * Developer URI: https://intense.com.tr
 * License: GPL2
 * Text Domain: hezarfen-for-woocommerce
 * Domain Path: /languages
 * Requires PHP: 7.0
 * Requires at least: 5.7
 * Requires Plugins: woocommerce
 * 
 * WC requires at least: 6.9.0
 * WC tested up to: 10.1
 * 
 * @package Hezarfen
 */

 defined( 'ABSPATH' ) || exit();

// check if WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

define( 'WC_HEZARFEN_VERSION', '2.7.10' );
define( 'WC_HEZARFEN_MIN_MBGB_VERSION', '0.6.1' );
define( 'WC_HEZARFEN_MIN_WC_VERSION', '6.9.0' );
define( 'WC_HEZARFEN_FILE', __FILE__ );
define( 'WC_HEZARFEN_UYGULAMA_YOLU', plugin_dir_path( __FILE__ ) );
define( 'WC_HEZARFEN_UYGULAMA_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_HEZARFEN_NEIGH_API_URL', plugin_dir_url( __FILE__ ) . 'api/get-mahalle-data.php' );

add_action( 'plugins_loaded', 'hezarfen_init_plugin', 8 );

/**
 * Initialize plugin after checking requirements
 * 
 * @return void
 */
function hezarfen_init_plugin() {
	// Check if WooCommerce is available and version meets requirement
	if ( ! function_exists( 'WC' ) ) {
		return;
	}
	
	if ( version_compare( WC()->version, WC_HEZARFEN_MIN_WC_VERSION, '<' ) ) {
		add_action( 'admin_notices', 'hezarfen_wc_version_notice' );
		return; // Stop plugin initialization
	}
	
	// Load plugin textdomain
	hezarfen_load_plugin_textdomain();
	
	// Load privacy policy integration
	require_once WC_HEZARFEN_UYGULAMA_YOLU . 'includes/class-privacy-policy.php';
	
	// Load Composer autoloader for dependencies like TCPDF
	if ( file_exists( WC_HEZARFEN_UYGULAMA_YOLU . 'vendor/autoload.php' ) ) {
		require_once WC_HEZARFEN_UYGULAMA_YOLU . 'vendor/autoload.php';
	}
	
	// Load plugin main files
	require_once WC_HEZARFEN_UYGULAMA_YOLU . 'includes/Autoload.php';
}

/**
 * Display admin notice for WooCommerce version requirement
 * 
 * @return void
 */
function hezarfen_wc_version_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: 1: plugin name, 2: minimum WooCommerce version, 3: current WooCommerce version */
				__( '<strong>%1$s</strong> requires WooCommerce version %2$s or higher. You are running version %3$s. Please update WooCommerce.', 'hezarfen-for-woocommerce' ),
				'Hezarfen',
				WC_HEZARFEN_MIN_WC_VERSION,
				WC()->version
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Load plugin textdomain
 * 
 * @return void
 */
function hezarfen_load_plugin_textdomain() {
	load_plugin_textdomain(
		'hezarfen-for-woocommerce',
		false,
		basename( dirname( WC_HEZARFEN_FILE ) ) . '/languages/'
	);
}

/**
 * Add settings link to plugin actions
 * 
 * @param array $links Plugin action links
 * @return array Modified plugin action links
 */
function hezarfen_add_settings_link( $links ) {
	$settings_link = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=hezarfen' ) . '">' . __( 'Settings', 'hezarfen-for-woocommerce' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}

// Add settings link to plugins page
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'hezarfen_add_settings_link' );

// Declare our plugin compatible with the Woocommerce HPOS feature.
add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	} 
);
