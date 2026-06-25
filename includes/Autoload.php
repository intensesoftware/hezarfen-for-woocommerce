<?php
/**
 * Class Autoload.
 *
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

/**
 * Autoload
 */
class Autoload {
	/**
	 * Package names and their main classes.
	 * 
	 * @var string[]
	 */
	private $packages = array(
		'manual-shipment-tracking',
	);

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		$this->load_plugin_files();
		$this->load_assets();

		add_action( 'plugins_loaded', array( $this, 'load_packages' ) );

		// Initialize Contracts integration immediately
		$this->init_contracts_integration();

		// Initialize block-based (Gutenberg) checkout support.
		$this->init_checkout_blocks();
	}

	/**
	 * Load assets
	 *
	 * @return void
	 */
	public function load_assets() {
		add_action( 'wp_enqueue_scripts', array( $this, 'load_js_and_css_files' ) );
	}

	/**
	 * Load js and css files
	 *
	 * @return void
	 */
	public function load_js_and_css_files() {
		$neighborhood_enabled = 'yes' === apply_filters( 'hezarfen_enable_district_neighborhood_fields', get_option( 'hezarfen_enable_district_neighborhood_fields', 'yes' ) );

		// Only register and enqueue mahalle-helper.js if neighborhood feature is enabled
		if ( $neighborhood_enabled ) {
			wp_register_script(
				'wc_hezarfen_mahalle_helper_js',
				plugins_url( 'assets/js/mahalle-helper.js', WC_HEZARFEN_FILE ),
				array( 'jquery', 'select2', 'selectWoo' ),
				WC_HEZARFEN_VERSION,
				true
			);
			wp_localize_script(
				'wc_hezarfen_mahalle_helper_js',
				'hezarfen_mahalle_helper_backend',
				array(
					'api_url'            => WC_HEZARFEN_NEIGH_API_URL,
					'select_option_text' => __( 'Select an option', 'hezarfen-for-woocommerce' ),
					'no_results_text'    => __( 'No results found', 'hezarfen-for-woocommerce' ),
				)
			);
		}

		// Always load checkout assets if on checkout page (for tax and other features)
		if ( is_checkout() ) {
			wp_enqueue_style(
				'wc_hezarfen_checkout_css',
				plugins_url( 'assets/css/checkout.css', WC_HEZARFEN_FILE ),
				array(),
				WC_HEZARFEN_VERSION
			);

			// Conditionally include mahalle-helper.js in dependencies only if neighborhood is enabled
			$checkout_dependencies = array( 'jquery', 'wc-checkout' );
			if ( $neighborhood_enabled ) {
				$checkout_dependencies[] = 'wc_hezarfen_mahalle_helper_js';
			}

			wp_enqueue_script(
				'wc_hezarfen_checkout_js',
				plugins_url( 'assets/js/checkout.js', WC_HEZARFEN_FILE ),
				$checkout_dependencies,
				WC_HEZARFEN_VERSION,
				true
			);

			wp_localize_script(
				'wc_hezarfen_checkout_js',
				'wc_hezarfen_ajax_object',
				array(
					'ajax_url'                            => admin_url( 'admin-ajax.php' ),
					'mahalleio_nonce'                     => wp_create_nonce( 'mahalle-io-get-data' ),
					'neighborhood_enabled'                => $neighborhood_enabled,
					'billing_district_field_classes'      => apply_filters( 'hezarfen_checkout_fields_class_wc_hezarfen_billing_district', array() ),
					'shipping_district_field_classes'     => apply_filters( 'hezarfen_checkout_fields_class_wc_hezarfen_shipping_district', array() ),
					'billing_neighborhood_field_classes'  => apply_filters( 'hezarfen_checkout_fields_class_wc_hezarfen_billing_neighborhood', array() ),
					'shipping_neighborhood_field_classes' => apply_filters( 'hezarfen_checkout_fields_class_wc_hezarfen_shipping_neighborhood', array() ),
					'should_notify_neighborhood_changed'  => apply_filters( 'hezarfen_checkout_should_notify_neighborhood_changed', false ),
				)
			);
		}
	}

	/**
	 * Load plugin files.
	 *
	 * @return void
	 */
	public function load_plugin_files() {
		require_once 'class-hezarfen-wc-helper.php';
		require_once 'class-hezarfen.php';
		require_once 'Data/Abstracts/Abstract_Encryption.php';
		require_once 'Data/PostMetaEncryption.php';
		require_once 'Checkout.php';
		require_once 'InvoiceInfo.php';
		require_once 'class-my-account.php';
		require_once 'Ajax.php';
		require_once 'class-mahalle-local.php';
		require_once 'Hezarfen_Install.php';
		require_once 'class-compatibility.php';
		require_once 'class-notification-provider.php';
		require_once 'class-sms-automation.php';
		require_once 'class-feature-status.php';

		// Load Contracts Integration
		require_once 'contracts/class-contracts-integration.php';

		if ( is_admin() ) {
			require_once 'admin/order/OrderDetails.php';
			require_once 'admin/order/OrderListColumns.php';
			require_once 'admin/class-admin-menu.php';
			new \Hezarfen\Inc\Admin\Admin_Menu();
		}
	}

	/**
	 * Loads and initializes packages.
	 * 
	 * @return void
	 */
	public function load_packages() {
		foreach ( $this->packages as $package_name ) {
			require_once WC_HEZARFEN_UYGULAMA_YOLU . "packages/$package_name/$package_name.php";
		}
	}

	/**
	 * Initialize Contracts Integration
	 *
	 * @return void
	 */
	public function init_contracts_integration() {
		new \Hezarfen\Inc\Contracts\Contracts_Integration();
	}

	/**
	 * Initialize block-based checkout (WooCommerce Cart & Checkout Blocks) support.
	 *
	 * Registers the React checkout block, its Store API extension (for invoice/tax
	 * fields) and the REST controller that serves district/neighborhood data.
	 *
	 * @return void
	 */
	public function init_checkout_blocks() {
		require_once 'blocks/class-hezarfen-locations-rest.php';
		require_once 'blocks/class-hezarfen-store-api.php';
		require_once 'blocks/class-hezarfen-blocks-integration.php';

		new \Hezarfen\Inc\Blocks\Hezarfen_Locations_REST();
		new \Hezarfen\Inc\Blocks\Hezarfen_Store_API();

		add_action(
			'woocommerce_blocks_checkout_block_registration',
			function( $integration_registry ) {
				$integration_registry->register( new \Hezarfen\Inc\Blocks\Hezarfen_Blocks_Integration() );
			}
		);

		// Force-insert our block placeholders into the checkout so they appear
		// without the merchant having to add them manually. This mirrors how
		// WooCommerce injects its own checkout inner blocks at render time.
		add_filter( 'render_block', array( $this, 'inject_checkout_block_placeholders' ), 10, 2 );
	}

	/**
	 * Injects the Hezarfen checkout block placeholders into the relevant
	 * WooCommerce checkout inner blocks. The WooCommerce blocks frontend mounts
	 * any `data-block-name` placeholder whose component has been registered via
	 * `registerCheckoutBlock`, so this makes our fields render automatically.
	 *
	 * @param string $block_content The rendered block HTML.
	 * @param array  $block         The parsed block.
	 *
	 * @return string
	 */
	public function inject_checkout_block_placeholders( $block_content, $block ) {
		if ( empty( $block['blockName'] ) ) {
			return $block_content;
		}

		$placeholders = array(
			'woocommerce/checkout-billing-address-block'    => 'hezarfen/checkout-billing-fields',
			'woocommerce/checkout-shipping-address-block'   => 'hezarfen/checkout-shipping-fields',
			'woocommerce/checkout-contact-information-block' => 'hezarfen/checkout-invoice-fields',
		);

		if ( ! isset( $placeholders[ $block['blockName'] ] ) ) {
			return $block_content;
		}

		$block_name = $placeholders[ $block['blockName'] ];

		// Avoid double injection if the block is already present in the content.
		if ( false !== strpos( $block_content, $block_name ) ) {
			return $block_content;
		}

		$placeholder = sprintf(
			'<div data-block-name="%1$s" class="wp-block-%2$s"></div>',
			esc_attr( $block_name ),
			esc_attr( str_replace( '/', '-', $block_name ) )
		);

		// Insert just before the parent block's closing </div> so our fields
		// render inside it.
		$closing_pos = strrpos( $block_content, '</div>' );

		if ( false === $closing_pos ) {
			return $block_content . $placeholder;
		}

		return substr( $block_content, 0, $closing_pos ) . $placeholder . substr( $block_content, $closing_pos );
	}
}

new Autoload();
