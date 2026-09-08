<?php
/**
 * Contains the Return_Refunds service.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

defined( 'ABSPATH' ) || exit();

/**
 * Settles a completed return as a WooCommerce refund.
 *
 * What this writes is a *record*, not a payment. `wc_create_refund()` is
 * called with `refund_payment => false`, which is WooCommerce's own manual
 * refund: the money is moved by the store, through whatever channel it
 * actually uses, and WooCommerce is told how much left the order. That is
 * the only shape that fits a store settling returns by hand — the gateway
 * is never contacted and no credentials are needed.
 *
 * The refund is built from the request's own lines, so a partial return
 * refunds exactly the units that came back. WooCommerce then flips the
 * order to `refunded` by itself once the refunded total reaches the order
 * total; the module never sets that status directly, because on a partial
 * return it would claim the whole order was refunded and skew every report
 * that reads it.
 */
class Return_Refunds {

	/**
	 * Records the refund for a request, if there is anything left to refund.
	 *
	 * @param Return_Request $request Completed request.
	 * @param bool           $restock Whether to put the units back in stock.
	 *
	 * @return \WC_Order_Refund|\WP_Error
	 */
	public function create_for_request( $request, $restock = false ) {
		// A recorded refund the merchant has since deleted from the order
		// screen is not a reason to refuse: the record is gone, so writing
		// it again cannot double anything.
		if ( $request->get_refund_id() && wc_get_order( $request->get_refund_id() ) ) {
			return new \WP_Error(
				'hezarfen_returns_already_refunded',
				__( 'Bu talep için zaten bir WooCommerce iadesi oluşturulmuş.', 'hezarfen-for-woocommerce' )
			);
		}

		$order = $request->get_order();

		if ( ! $order ) {
			return new \WP_Error(
				'hezarfen_returns_refund_no_order',
				__( 'İade kaydı için sipariş bulunamadı.', 'hezarfen-for-woocommerce' )
			);
		}

		$line_items = $this->build_line_items( $order, $request );

		if ( ! $line_items ) {
			return new \WP_Error(
				'hezarfen_returns_refund_nothing_left',
				__( 'Bu talepteki ürünler için iade edilecek tutar kalmadı; muhtemelen WooCommerce üzerinden zaten iade edilmişler.', 'hezarfen-for-woocommerce' )
			);
		}

		$amount = 0.0;

		foreach ( $line_items as $line ) {
			$amount += (float) $line['refund_total'];
			$amount += array_sum( array_map( 'floatval', $line['refund_tax'] ) );
		}

		$amount = round( $amount, wc_get_price_decimals() );

		// The order may already carry refunds this module knows nothing
		// about; WooCommerce refuses a refund past the order total, so the
		// cap is applied here where it can be explained.
		$remaining = round( (float) $order->get_remaining_refund_amount(), wc_get_price_decimals() );

		if ( $amount <= 0 || $remaining <= 0 ) {
			return new \WP_Error(
				'hezarfen_returns_refund_nothing_left',
				__( 'Bu siparişte iade edilebilecek tutar kalmadı.', 'hezarfen-for-woocommerce' )
			);
		}

		if ( $amount > $remaining ) {
			$amount = $remaining;
		}

		$refund = wc_create_refund(
			array(
				'order_id'       => $order->get_id(),
				'amount'         => $amount,
				'reason'         => sprintf(
					/* translators: %s: return request reference. */
					__( 'Hezarfen iade talebi %s', 'hezarfen-for-woocommerce' ),
					$request->get_return_number()
				),
				'line_items'     => $line_items,
				'refund_payment' => false,
				'restock_items'  => (bool) $restock,
			)
		);

		if ( is_wp_error( $refund ) ) {
			return $refund;
		}

		if ( ! $refund instanceof \WC_Order_Refund ) {
			return new \WP_Error(
				'hezarfen_returns_refund_failed',
				__( 'WooCommerce iade kaydı oluşturulamadı.', 'hezarfen-for-woocommerce' )
			);
		}

		return $refund;
	}

	/**
	 * The refundable slice of each returned line, in WooCommerce's shape.
	 *
	 * Quantities and amounts are read off the order rather than off the
	 * request: the request stores what the customer asked to send back, and
	 * between then and now part of it may already have been refunded by
	 * hand. Refunding the request's own figures would double up on those.
	 *
	 * @param \WC_Order      $order   Parent order.
	 * @param Return_Request $request Completed request.
	 *
	 * @return array<int, array<string, mixed>> Keyed by order item ID.
	 */
	private function build_line_items( $order, $request ) {
		$line_items = array();

		foreach ( $request->get_items() as $item ) {
			$item_id    = $item->get_order_item_id();
			$order_item = $item_id ? $order->get_item( $item_id ) : null;

			if ( ! $order_item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$ordered = (int) $order_item->get_quantity();

			if ( $ordered < 1 ) {
				continue;
			}

			$already = (int) abs( $order->get_qty_refunded_for_item( $item_id ) );
			$qty     = min( (int) $item->get_quantity(), $ordered - $already );

			if ( $qty < 1 ) {
				continue;
			}

			$total = round( ( (float) $order_item->get_total() / $ordered ) * $qty, wc_get_price_decimals() );

			// Refunded tax is per rate, because that is how WooCommerce
			// stores it and how its own tax reports read it back.
			$taxes      = $order_item->get_taxes();
			$refund_tax = array();

			if ( isset( $taxes['total'] ) && is_array( $taxes['total'] ) ) {
				foreach ( $taxes['total'] as $rate_id => $tax_total ) {
					$already_tax = (float) abs( $order->get_tax_refunded_for_item( $item_id, $rate_id ) );
					$rate_share  = round( ( (float) $tax_total / $ordered ) * $qty, wc_get_price_decimals() );
					$rate_left   = round( (float) $tax_total - $already_tax, wc_get_price_decimals() );

					$refund_tax[ $rate_id ] = max( 0, min( $rate_share, $rate_left ) );
				}
			}

			$refunded_total = (float) abs( $order->get_total_refunded_for_item( $item_id ) );
			$total_left     = round( (float) $order_item->get_total() - $refunded_total, wc_get_price_decimals() );
			$total          = max( 0, min( $total, $total_left ) );

			if ( $total <= 0 && ! array_filter( $refund_tax ) ) {
				continue;
			}

			$line_items[ $item_id ] = array(
				'qty'          => $qty,
				'refund_total' => $total,
				'refund_tax'   => $refund_tax,
			);
		}

		return $line_items;
	}
}
