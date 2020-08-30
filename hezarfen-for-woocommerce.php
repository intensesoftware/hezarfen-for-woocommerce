<?php

/*
Plugin Name: Hezarfen For Woocommerce
Plugin URI: http://intense.com.tr
Description: Hezarfen, WooCommerce eklentisini Türkiye için daha kullanılabilir kılmayı amaçlar.
Version: 0.4.3
Author: Intense Yazılım Ltd.
Author URI: http://intense.com.tr
Developer: Intense Yazılım Ltd.
Developer URI: http://intense.com.tr
License: GPL2
Text Domain: hezarfen-for-woocommerce
*/

defined( 'ABSPATH' ) || exit;

define('WC_HEZARFEN_VERSION', '0.4.3');
define('WC_HEZARFEN_FILE', __FILE__);
define('WC_HEZARFEN_UYGULAMA_YOLU', plugin_dir_path(__FILE__));

include_once 'includes/Autoload.php';