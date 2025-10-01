<?php
/**
 * Plugin Name: Hezarfen - WooCommerce Kargo Entegrasyonu - WooCommerce Kargo Takip, İlçe/Mahalle, Sözleşmeler For Woocommerce
 * Description: Türkiye'nin WooCommerce kargo eklentisi - 26+ kargo firmalası için takip, sms, e-posta bildirimleri + Mesafeli Satış Sözleşmesi desteği
 * Version: 2.7.1
 * Author: Intense Yazılım Ltd.
 * Author URI: https://intense.com.tr
 * Developer: Intense Yazılım Ltd.
 * Developer URI: https://intense.com.tr
 * License: GPL2
 * Text Domain: hezarfen-for-woocommerce
 * Domain Path: /languages
 * Requires PHP: 7.0
 * Requires at least: 5.7
 * 
 * WC tested up to: 10.1
 * 
 * @package Hezarfen
 */

 defined( 'ABSPATH' ) || exit();

// check if WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

define( 'WC_HEZARFEN_VERSION', '2.7.1' );
define( 'WC_HEZARFEN_MIN_MBGB_VERSION', '0.6.1' );
define( 'WC_HEZARFEN_FILE', __FILE__ );
define( 'WC_HEZARFEN_UYGULAMA_YOLU', plugin_dir_path( __FILE__ ) );
define( 'WC_HEZARFEN_UYGULAMA_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_HEZARFEN_NEIGH_API_URL', plugin_dir_url( __FILE__ ) . 'api/get-mahalle-data.php' );

add_action( 'plugins_loaded', 'hezarfen_load_plugin_textdomain' );

// Load privacy policy integration
require_once WC_HEZARFEN_UYGULAMA_YOLU . 'includes/class-privacy-policy.php';

/**
 * Load plugin textdomain
 * 
 * @return void
 */
function hezarfen_load_plugin_textdomain() {
	load_plugin_textdomain(
		'hezarfen-for-woocommerce',
		false,
		basename( dirname( __FILE__ ) ) . '/languages/'
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

// Load Composer autoloader for dependencies like TCPDF
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

require_once 'includes/Autoload.php';
