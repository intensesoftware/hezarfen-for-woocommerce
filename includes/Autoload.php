<?php

namespace Hezarfen\Inc;

defined('ABSPATH') || exit();

class Autoload
{
	function __construct()
	{
		$this->load_plugin_files();

		$this->load_assets();

		register_activation_hook(WC_HEZARFEN_FILE, [
			'Hezarfen_Install',
			'install',
		]);

		add_filter('woocommerce_get_settings_pages', [
			$this,
			'add_hezarfen_setting_page',
		]);
	}

	/**
	 *
	 * Load Hezarfen Settings Page
	 *
	 * @param $settings
	 * @return array
	 */
	public function add_hezarfen_setting_page($settings)
	{
		$settings[] = include_once WC_HEZARFEN_UYGULAMA_YOLU .
			'includes/admin/settings/class-hezarfen-settings-hezarfen.php';

		return $settings;
	}

	function load_assets()
	{
		add_action('wp_enqueue_scripts', [$this, 'load_js_files']);

		if (is_admin()) {
			add_action('admin_enqueue_scripts', [
				$this,
				'load_admin_assets_files',
			]);
		}
	}

	/**
	 * Load assets files for admin
	 */
	function load_admin_assets_files()
	{
		wp_enqueue_script(
			'wc_hezarfen_admin_order_details_js',
			plugins_url('assets/admin/js/order-details.js', WC_HEZARFEN_FILE),
			['jquery'],
			WC_HEZARFEN_VERSION
		);
		wp_enqueue_style(
			'wc_hezarfen_checkout_css',
			plugins_url('assets/admin/css/order-details.css', WC_HEZARFEN_FILE),
			[],
			WC_HEZARFEN_VERSION
		);
	}

	function load_js_files()
	{
		if (is_checkout()) {
			wp_enqueue_style(
				'wc_hezarfen_checkout_css',
				plugins_url('assets/css/checkout.css', WC_HEZARFEN_FILE),
				[],
				WC_HEZARFEN_VERSION
			);

			wp_enqueue_script(
				'wc_hezarfen_checkout_js',
				plugins_url('assets/js/checkout.js', WC_HEZARFEN_FILE),
				['jquery'],
				WC_HEZARFEN_VERSION
			);

			wp_enqueue_script(
				'wc_hezarfen_checkout_TR_localization',
				plugins_url('assets/packages/select2/i18n/tr.js', WC_HEZARFEN_FILE),
				['jquery', 'select2']
			);

			wp_localize_script(
				'wc_hezarfen_checkout_js',
				'wc_hezarfen_ajax_object',
				['ajax_url' => admin_url('admin-ajax.php'), 'mahalleio_nonce' => wp_create_nonce( 'mahalle-io-get-data' ) ]
			);
		}
	}

	function load_plugin_files()
	{
		require_once 'Data/Abstracts/Abstract_Encryption.php';
		require_once 'Data/ServiceCredentialEncryption.php';
		require_once 'Data/PostMetaEncryption.php';
		require_once 'Checkout.php';
		require_once 'Ajax.php';
		require_once 'Hezarfen_Install.php';
		require_once 'hezarfen-wc-helpers.php';
		require_once 'Services/MahalleIO.php';
		require_once 'packages/Manuel-Shipment/manual-shipment.php';

		if (is_admin()) {
			require_once 'admin/order/OrderDetails.php';
		}
	}
}

new Autoload();
