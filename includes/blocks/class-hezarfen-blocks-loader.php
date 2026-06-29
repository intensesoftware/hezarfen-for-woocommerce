<?php
/**
 * Bootstraps the block-based (Gutenberg) checkout support: the Store API
 * extension, the locations REST controller, the admin compatibility notice, the
 * Blocks integration registration and the auto-injection of the Hezarfen field
 * placeholders into the checkout inner blocks.
 *
 * This logic used to live inside the general `Autoload` class; it is isolated
 * here so `Autoload` stays a plain plugin bootstrap and all block-specific
 * concerns (including render-time HTML manipulation) sit together.
 *
 * @package Hezarfen\Inc\Blocks
 */

namespace Hezarfen\Inc\Blocks;

defined( 'ABSPATH' ) || exit();

/**
 * Hezarfen_Blocks_Loader
 */
class Hezarfen_Blocks_Loader {

	/**
	 * Wires up every block-checkout concern.
	 *
	 * @return void
	 */
	public function __construct() {
		// These classes have no class-declaration-time dependency on WooCommerce
		// Blocks (they only reference Store API / REST classes inside method
		// bodies), so they are safe to load unconditionally. Their hooks only
		// ever fire for block-based (Store API) requests, never on the classic
		// checkout.
		require_once __DIR__ . '/class-hezarfen-locations-rest.php';
		require_once __DIR__ . '/class-hezarfen-store-api.php';

		new Hezarfen_Locations_REST();
		new Hezarfen_Store_API();

		// Admin notice for stores on a WooCommerce version too old for the block
		// checkout fields. Has no WooCommerce Blocks dependency, so it is safe to
		// load even when the Blocks package is absent.
		if ( is_admin() ) {
			require_once __DIR__ . '/class-hezarfen-block-compat-notice.php';
			new Hezarfen_Block_Compat_Notice();
		}

		// The integration class implements a WooCommerce Blocks interface, which
		// PHP resolves at declaration time. Load it only inside the block
		// registration hook, which fires exclusively when WooCommerce Blocks is
		// active and the interface is guaranteed to exist. This avoids any risk
		// of a fatal error on environments where the Blocks package is absent.
		add_action(
			'woocommerce_blocks_checkout_block_registration',
			static function( $integration_registry ) {
				require_once __DIR__ . '/class-hezarfen-blocks-integration.php';
				$integration_registry->register( new Hezarfen_Blocks_Integration() );
			}
		);

		// Force-insert our block placeholders into the checkout so they appear
		// without the merchant having to add them manually. This mirrors how
		// WooCommerce injects its own checkout inner blocks at render time. It is
		// a no-op for every block except the three checkout inner blocks, which
		// only exist on the block-based checkout.
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
			'woocommerce/checkout-billing-address-block'     => 'hezarfen/checkout-billing-fields',
			'woocommerce/checkout-shipping-address-block'    => 'hezarfen/checkout-shipping-fields',
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
