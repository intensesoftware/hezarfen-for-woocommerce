<?php
/**
 * Contains the Hezarfen main class.
 * 
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Hezarfen main class.
 */
class Hezarfen {
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
	 * Constructor
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

		register_activation_hook( WC_HEZARFEN_FILE, array( 'Hezarfen_Install', 'install' ) );

		add_action( 'plugins_loaded', array( $this, 'check_addons_and_show_notices' ) );
		add_action( 'plugins_loaded', array( $this, 'define_constants' ) );
		add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_hezarfen_setting_page' ) );
		add_filter( 'woocommerce_get_country_locale', array( $this, 'modify_tr_locale' ), PHP_INT_MAX - 2 );
		add_filter('woocommerce_rest_prepare_shop_order_object', array( $this, 'add_virtual_order_metas_to_metadata' ), 10, 2);
	}

	/**
	 * Modifies TR country locale data.
	 * 
	 * @param array<array<string, mixed>> $locales Locale data of all countries.
	 * 
	 * @return array<array<string, mixed>>
	 */
	public function modify_tr_locale( $locales ) {
		$locales['TR']['city'] = array_merge(
			$locales['TR']['city'] ?? array(),
			array(
				'label' => __( 'Town / City', 'hezarfen-for-woocommerce' ),
			)
		);

		$locales['TR']['address_1'] = array_merge(
			$locales['TR']['address_1'] ?? array(),
			array(
				'label'       => __( 'Neighborhood', 'hezarfen-for-woocommerce' ),
				'placeholder' => __( 'Select an option', 'hezarfen-for-woocommerce' ),
			)
		);

		return $locales;
	}

	/**
	 * Define constants after plugins are loaded.
	 */
	public function define_constants() {
		if ( ! defined( 'WC_HEZARFEN_HPOS_ENABLED' ) ) {
			define( 'WC_HEZARFEN_HPOS_ENABLED', OrderUtil::custom_orders_table_usage_is_enabled() );
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
						'message' => __( 'In order to <strong>Hezarfen for WooCommerce</strong> plugin work, please remove the <strong>Intense Türkiye İl İlçe Eklentisi For WooCommerce</strong> plugin. The <strong>Hezarfen</strong> plugin already has province, district and neighborhood data in it.', 'hezarfen-for-woocommerce' ),
						'type'    => 'error',
					);

					Helper::render_admin_notices( array( $notice ), true );
				}
			);
		}

		// Show notice if the Woocommerce Checkout Blocks feature is active.
		if ( class_exists( '\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils' ) ) {
			if ( \Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils::is_checkout_block_default() ) {
				add_action(
					'admin_notices',
					function () {
						$notice = array(
							'message' => __( "Because the Woocommerce Checkout Blocks feature is active on your website, <strong>Hezarfen for Woocommerce</strong> plugin doesn't work on the checkout page. You can revert to old checkout system easily by following <a target='_blank' href='https://woocommerce.com/document/cart-checkout-blocks-status/#reverting-to-shortcodes-with-woocommerce-8-3'>these instructions</a>.", 'hezarfen-for-woocommerce' ),
							'type'    => 'warning',
						);
	
						Helper::render_admin_notices( array( $notice ), true );
					}
				);
			}
		}
	}

	/**
	 *
	 * Load Hezarfen Settings Page
	 *
	 * @param \WC_Settings_Page[] $settings the current WC setting page objects.
	 * @return \WC_Settings_Page[]
	 */
	public function add_hezarfen_setting_page( $settings ) {
		$settings[] = include_once WC_HEZARFEN_UYGULAMA_YOLU .
			'includes/admin/settings/class-hezarfen-settings-hezarfen.php';

		return $settings;
	}

	public function add_virtual_order_metas_to_metadata($response, $order) {
        $identity_number_field_value = $order->get_meta('_billing_hez_TC_number', true);
        $identity_number_field_decrypted_value = (new \Hezarfen\Inc\Data\PostMetaEncryption())->decrypt($identity_number_field_value);

        if ('person' === $order->get_meta('_billing_hez_invoice_type', true)) {
            $tax_number = $identity_number_field_decrypted_value;
        } else {
            $tax_number = $order->get_meta('_billing_hez_tax_number', true);
        }

        $tax_office = $order->get_meta('_billing_hez_tax_office', true);

        $meta_data = $response->data['meta_data'];

        $meta_data[] = array(
            'key'   => '_hezarfen_billing_v_tax_number',
            'value' => $tax_number,
        );

        $meta_data[] = array(
            'key'   => '_hezarfen_billing_v_tax_office',
            'value' => $tax_office,
        );

        $response->data['meta_data'] = $meta_data;

        return $response;
    }
}

new Hezarfen();
