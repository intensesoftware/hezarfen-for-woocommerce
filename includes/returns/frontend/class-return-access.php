<?php
/**
 * Contains the Return_Access authorisation helper.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Frontend;

use Hezarfen\Inc\Returns\Core\Return_Request;
use Hezarfen\Inc\Returns\Core\Return_Settings;

defined( 'ABSPATH' ) || exit();

/**
 * Decides who may see a return request or open a form for an order.
 *
 * Two independent proofs of ownership are accepted: an authenticated
 * customer whose ID matches the record, or a token. Guests get a token
 * only after proving they know both the order number and the billing
 * e-mail, and the token is derived from the order key so it cannot be
 * guessed from the order ID alone.
 */
class Return_Access {

	const QUERY_RETURN = 'hezarfen_return';
	const QUERY_KEY    = 'hezarfen_key';
	const QUERY_ORDER  = 'hezarfen_order';

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

		if ( $customer_id && get_current_user_id() === $customer_id ) {
			return true;
		}

		$token = $this->get_query_token();

		if ( $token && hash_equals( $request->get_access_token(), $token ) ) {
			return true;
		}

		return false;
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

		if ( $customer_id && get_current_user_id() === $customer_id ) {
			return true;
		}

		if ( ! Return_Settings::is_guest_enabled() ) {
			return false;
		}

		$token = $this->get_query_token();

		return $token && $this->verify_order_token( $order, $token );
	}

	/**
	 * Derives the guest token of an order.
	 *
	 * Built on the order key, which WooCommerce already treats as the
	 * secret that grants a guest access to their own order.
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return string
	 */
	public function get_order_token( $order ) {
		return wp_hash( 'hezarfen_returns|' . $order->get_id() . '|' . $order->get_order_key() );
	}

	/**
	 * Constant-time comparison of a supplied order token.
	 *
	 * @param \WC_Order $order Order.
	 * @param string    $token Supplied token.
	 *
	 * @return bool
	 */
	public function verify_order_token( $order, $token ) {
		return hash_equals( $this->get_order_token( $order ), (string) $token );
	}

	/**
	 * The token carried by the current request, if any.
	 *
	 * Guests arrive with the token in the query string and then post the
	 * form back with it in a hidden field, so both are accepted. The token
	 * is an ownership proof compared with hash_equals, never an action
	 * authorisation — the POST handlers still verify a nonce.
	 *
	 * @return string
	 */
	public function get_query_token() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST[ self::QUERY_KEY ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			return sanitize_text_field( wp_unslash( $_POST[ self::QUERY_KEY ] ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET[ self::QUERY_KEY ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return sanitize_text_field( wp_unslash( $_GET[ self::QUERY_KEY ] ) );
		}

		return '';
	}

	/**
	 * Permalink of a request's detail view for the given audience.
	 *
	 * @param Return_Request $request   The request.
	 * @param bool           $with_token Whether to append the guest token.
	 *
	 * @return string
	 */
	public function get_request_url( $request, $with_token = false ) {
		if ( ! $with_token && $request->get_customer_id() ) {
			return wc_get_endpoint_url(
				My_Account_Returns::get_list_endpoint(),
				(string) $request->get_id(),
				wc_get_page_permalink( 'myaccount' )
			);
		}

		$page_url = Return_Settings::get_page_url();

		if ( ! $page_url ) {
			return wc_get_page_permalink( 'myaccount' );
		}

		return add_query_arg(
			array(
				self::QUERY_RETURN => $request->get_id(),
				self::QUERY_KEY    => $request->get_access_token(),
			),
			$page_url
		);
	}

	/**
	 * Permalink of the guest form for an order.
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return string
	 */
	public function get_guest_form_url( $order ) {
		$page_url = Return_Settings::get_page_url();

		if ( ! $page_url ) {
			return '';
		}

		return add_query_arg(
			array(
				self::QUERY_ORDER => $order->get_id(),
				self::QUERY_KEY   => $this->get_order_token( $order ),
			),
			$page_url
		);
	}
}
