<?php
/**
 * Contains the Return_Policy_Provider_Interface contract.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

defined( 'ABSPATH' ) || exit();

/**
 * Decides which return policy applies to one order line.
 *
 * Providers are consulted in priority order and the first one with an
 * opinion wins, so a product-level add-on can override the store-wide
 * rule simply by registering with a lower priority number.
 */
interface Return_Policy_Provider_Interface {

	/**
	 * The policy this provider wants to apply to the given line.
	 *
	 * @param \WC_Order      $order Parent order.
	 * @param \WC_Order_Item $item  Order line.
	 *
	 * @return Return_Policy|null Null when the provider has no opinion.
	 */
	public function get_policy( $order, $item );

	/**
	 * Sort weight; lower is consulted first.
	 *
	 * @return int
	 */
	public function get_priority();
}
