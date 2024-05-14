<?php
/**
 * Plugin Name: Hezarfen For Woocommerce
 * Description: Hezarfen, WooCommerce eklentisini Türkiye için daha kullanılabilir kılmayı amaçlar.
 * Version: 2.0.2
 * Author: Intense Yazılım Ltd.
 * Author URI: http://intense.com.tr
 * Developer: Intense Yazılım Ltd.
 * Developer URI: http://intense.com.tr
 * License: GPL2
 * Text Domain: hezarfen-for-woocommerce
 * Domain Path: /languages
 * Requires PHP: 7.0
 * Requires at least: 5.7
 * 
 * WC tested up to: 8.8
 * 
 * @package Hezarfen
 */

 defined( 'ABSPATH' ) || exit();

define( 'WC_HEZARFEN_VERSION', '2.0.2' );
define( 'WC_HEZARFEN_MIN_MBGB_VERSION', '0.6.1' );
define( 'WC_HEZARFEN_FILE', __FILE__ );
define( 'WC_HEZARFEN_UYGULAMA_YOLU', plugin_dir_path( __FILE__ ) );
define( 'WC_HEZARFEN_UYGULAMA_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_HEZARFEN_NEIGH_API_URL', plugin_dir_url( __FILE__ ) . 'api/get-mahalle-data.php' );

add_action( 'plugins_loaded', 'hezarfen_load_plugin_textdomain' );

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

// Declare our plugin compatible with the Woocommerce HPOS feature.
add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	} 
);

require_once 'includes/Autoload.php';
