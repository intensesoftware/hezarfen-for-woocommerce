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
		add_action( 'admin_notices', array( $this, 'show_migration_notice' ) );
		add_action( 'admin_notices', array( $this, 'show_address2_field_notice' ) );
		
		// Trigger SMS migration on admin init to catch plugin updates
		add_action( 'admin_init', array( 'Hezarfen_Install', 'migrate_legacy_sms_settings' ) );
		add_action( 'plugins_loaded', array( $this, 'force_enable_address2_field' ) );
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

	/**
	 * Modify TC number and ensure required billing keys in WooCommerce REST API response
	 *
	 * @param WP_REST_Response $response The response object
	 * @param WC_Order $order The order object
	 * @return WP_REST_Response Modified response
	 */
	public function add_virtual_order_metas_to_metadata($response, $order) {
		// Required billing keys that should always be present
		$required_billing_keys = [
			'_billing_hez_tax_number',
			'_billing_hez_tax_office',
			'_billing_hez_TC_number'
		];
		
		// Get invoice type
		$invoice_type = $order->get_meta('_billing_hez_invoice_type', true);
		
		// Get response data
		$response_data = $response->get_data();
		
		// Ensure meta_data is an array
		if (!isset($response_data['meta_data'])) {
			$response_data['meta_data'] = [];
		}
		
		// Create a map of existing meta keys for easier lookup
		$existing_meta_keys = [];
		foreach ($response_data['meta_data'] as $index => $meta) {
			$meta_data = $meta->get_data();
			$existing_meta_keys[$meta_data['key']] = $index;
		}
		
		// Process TC number if invoice type is person
		if ('person' === $invoice_type) {
			$encrypted_tc_number = $order->get_meta('_billing_hez_TC_number', true);
			if ($encrypted_tc_number) {
				$decrypted_tc_number = (new \Hezarfen\Inc\Data\PostMetaEncryption())->decrypt($encrypted_tc_number);
				
				// Update TC number in response if it exists
				if (isset($existing_meta_keys['_billing_hez_TC_number'])) {
					$index = $existing_meta_keys['_billing_hez_TC_number'];
					$meta_data = $response_data['meta_data'][$index]->get_data();
					$response_data['meta_data'][$index] = [
						'id' => $meta_data['id'],
						'key' => '_billing_hez_TC_number',
						'value' => $decrypted_tc_number
					];
				}
			}
		}
		
		// Ensure all required billing keys exist
		foreach ($required_billing_keys as $key) {
			if (!isset($existing_meta_keys[$key])) {
				// Add empty meta data for missing keys
				$response_data['meta_data'][] = [
					'id' => 0, // You might want to generate a proper ID if needed
					'key' => $key,
					'value' => ''
				];
			}
		}
		
		// Set modified data back to response
		$response->set_data($response_data);
		return $response;
	}
	
	/**
	 * Show migration notice when SMS settings are migrated
	 *
	 * @return void
	 */
	public function show_migration_notice() {
		if ( get_transient( 'hezarfen_sms_migration_notice' ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<strong><?php esc_html_e( 'Hezarfen SMS Migration Complete!', 'hezarfen-for-woocommerce' ); ?></strong>
					<?php 
					printf( 
						esc_html__( 'Your legacy NetGSM SMS settings have been automatically migrated to the new SMS automation system. %sView SMS Rules%s', 'hezarfen-for-woocommerce' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=hezarfen&section=sms_settings' ) ) . '">',
						'</a>'
					);
					?>
				</p>
			</div>
			<?php
			// Delete the transient so it only shows once
			delete_transient( 'hezarfen_sms_migration_notice' );
		}
	}

	/**
	 * Show admin notice when address_2 field is hidden but required for Hezarfen
	 * COMPLETELY DISABLED - No notice will be shown
	 *
	 * @return void
	 */
	public function show_address2_field_notice() {
		// Completely disable the notice functionality
		return;

	/**
	 * Silently force enable address_2 field if it's hidden
	 * No admin notices will be shown
	 *
	 * @return void
	 */
	public function force_enable_address2_field() {
		// Only run if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Check if address_2 field is hidden
		$address_2_visibility = get_option( 'woocommerce_checkout_address_2_field', 'optional' );
		
		// If address_2 field is hidden, silently enable it
		if ( 'hidden' === $address_2_visibility ) {
			update_option( 'woocommerce_checkout_address_2_field', 'optional' );
		}
	}
}

new Hezarfen();
