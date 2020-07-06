<?php

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit;

class Autoload
{

	function __construct()
	{

		$this->load_plugin_files();

		$this->load_assets();

		register_activation_hook( WC_HEZARFEN_FILE, array( 'Hezarfen_Install', 'install' ) );

	}


	function load_assets(){

		add_action('wp_enqueue_scripts', array($this, 'load_js_files'));

	}


	function load_js_files(){

		wp_enqueue_script( 'wc_hezarfen_checkout_js', plugins_url( 'assets/js/checkout.js', WC_HEZARFEN_FILE ), array('jquery'), WC_HEZARFEN_VERSION );

		wp_localize_script( 'wc_hezarfen_checkout_js', 'wc_hezarfen_ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );

	}


	function load_plugin_files(){

		require_once 'Checkout.php';
		require_once 'Ajax.php';
		require_once 'Hezarfen_Install.php';

	}

}


new Autoload();