<?php
/**
 * Plugin Name: Hezarfen For Woocommerce
 * Description: Hezarfen aims to make the Woocommerce plugin more usable in Turkey.
 * Version: 1.6.0
 * Author: Intense Yazılım Ltd.
 * Author URI: http://intense.com.tr
 * Developer: Intense Yazılım Ltd.
 * Developer URI: http://intense.com.tr
 * License: GPL2
 * Text Domain: hezarfen-for-woocommerce
 * Domain Path: /languages
 * Requires PHP: 7.0
 * Requires at least: 5.7
 * WC tested up to: 7.2
 * 
 * @package Hezarfen
 */

defined( 'ABSPATH' ) || exit();

define( 'WC_HEZARFEN_VERSION', '1.4.6' );
define( 'WC_HEZARFEN_MIN_MBGB_VERSION', '0.6.1' );
define( 'WC_HEZARFEN_FILE', __FILE__ );
define( 'WC_HEZARFEN_UYGULAMA_YOLU', plugin_dir_path( __FILE__ ) );
define( 'WC_HEZARFEN_NEIGH_API_URL', plugin_dir_url( __FILE__ ) . 'api/get-mahalle-data.php' );

add_action( 'plugins_loaded', 'hezarfen_load_plugin_textdomain' );

/**
 * Loads the plugin text domain.
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

require_once 'includes/Autoload.php';
