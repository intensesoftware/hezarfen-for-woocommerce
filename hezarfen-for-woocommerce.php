<?php

/*
Plugin Name: Hezarfen For Woocommerce
Plugin URI: http://intense.com.tr
Description: Hezarfen, WooCommerce eklentisini Türkiye için daha kullanılabilir kılmayı amaçlar.
Version: 1.1.0
Author: Intense Yazılım Ltd.
Author URI: http://intense.com.tr
Developer: Intense Yazılım Ltd.
Developer URI: http://intense.com.tr
License: GPL2
Text Domain: hezarfen-for-woocommerce
Domain Path: /languages
Requires PHP: 7.0
Requires at least: 5.3
*/

defined('ABSPATH') || exit();

define('WC_HEZARFEN_VERSION', '1.1.0');
define('WC_HEZARFEN_FILE', __FILE__);
define('WC_HEZARFEN_UYGULAMA_YOLU', plugin_dir_path(__FILE__));

include_once 'includes/Autoload.php';

add_action('plugins_loaded', 'hezarfen_load_plugin_textdomain');

function hezarfen_load_plugin_textdomain()
{
	load_plugin_textdomain(
		'hezarfen-for-woocommerce',
		false,
		basename(dirname(__FILE__)) . '/languages/'
	);
}
