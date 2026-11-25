<?php
/**
 * Contains the Return_Access authorisation helper.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Frontend;

use Hezarfen\Inc\Returns\Core\Return_Request;

defined( 'ABSPATH' ) || exit();

/**
 * Decides who may see a return request or open a form for an order.
 *
 * Returns are account-only: a request belongs to the customer whose order
 * it was opened against, and nothing but an authenticated session (or a
 * shop manager) opens it. There is no link-based access to guard, which is
 * why this class has no tokens in it.
 */
class Return_Access {

	/**
	 * Whether the current visitor may view a request.
	 *
	 * @param Return_Request $request The request.
	 *
	 * @return bool
	 */
	public function can_view( $request ) {
		if ( ! $request instanceof Return_Request ) {
			return false;
		}

		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		$customer_id = $request->get_customer_id();

		return $customer_id && get_current_user_id() === $customer_id;
	}

	/**
	 * Whether the current visitor may open a return form for an order.
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return bool
	 */
	public function can_request_for_order( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return false;
		}

		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		$customer_id = (int) $order->get_customer_id();

		return $customer_id && get_current_user_id() === $customer_id;
	}

	/**
	 * Permalink of a request's detail view in the account area.
	 *
	 * @param Return_Request $request The request.
	 *
	 * @return string
	 */
	public function get_request_url( $request ) {
		return wc_get_endpoint_url(
			My_Account_Returns::get_list_endpoint(),
			(string) $request->get_id(),
			wc_get_page_permalink( 'myaccount' )
		);
	}
}
