<?php
/**
 * Contains the Global_Return_Policy_Provider.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

defined( 'ABSPATH' ) || exit();

/**
 * Applies the store-wide return window to every line.
 *
 * Registered with the highest priority number so any add-on provider gets
 * asked first; this one is the fallback that always answers.
 */
class Global_Return_Policy_Provider implements Return_Policy_Provider_Interface {

	/**
	 * The store-wide policy, minus the product specific exclusions
	 * WooCommerce itself already implies.
	 *
	 * @param \WC_Order      $order Parent order.
	 * @param \WC_Order_Item $item  Order line.
	 *
	 * @return Return_Policy|null
	 */
	public function get_policy( $order, $item ) {
		$product = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;

		// Downloadable and virtual goods never travel back, so offering a
		// physical return for them would only confuse the customer.
		if ( $product && ( $product->is_downloadable() || $product->is_virtual() ) ) {
			return Return_Policy::blocked(
				__( 'Dijital ürünler iade edilemez.', 'hezarfen-for-woocommerce' ),
				'global'
			);
		}

		return new Return_Policy( true, Return_Settings::get_window_days(), '', 'global' );
	}

	/**
	 * Consulted last.
	 *
	 * @return int
	 */
	public function get_priority() {
		return 100;
	}
}
