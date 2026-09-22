<?php
/**
 * Contains the Return_Order_Sync listener.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

defined( 'ABSPATH' ) || exit();

/**
 * Writes the return's milestones onto the order it belongs to.
 *
 * Returns live in their own tables, which is right — they outgrow order
 * meta almost immediately — but it leaves the order screen silent about
 * them. Whoever opens an order to ask "why is this one short two items"
 * reads its notes, not another screen, so the moments that change what the
 * order is worth land there as notes: the request being opened, approved,
 * rejected and settled.
 *
 * Notes only. The order's own status is never touched here — a return is
 * not a reason to move an order, and WooCommerce already flips it to
 * `refunded` on its own once a refund covers the whole total.
 */
class Return_Order_Sync {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'hezarfen_return_created', array( $this, 'on_created' ), 10, 2 );
		add_action( 'hezarfen_return_status_changed', array( $this, 'on_status_changed' ), 10, 3 );
	}

	/**
	 * Notes a new request on its order.
	 *
	 * @param Return_Request $request The stored request.
	 * @param \WC_Order      $order   Parent order.
	 *
	 * @return void
	 */
	public function on_created( $request, $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$order->add_order_note(
			sprintf(
				/* translators: 1: return reference, 2: comma separated list of returned lines. */
				__( 'İade talebi oluşturuldu: %1$s (%2$s)', 'hezarfen-for-woocommerce' ),
				$request->get_return_number(),
				$this->summarize_items( $request )
			)
		);
	}

	/**
	 * Notes the milestones a request passes through.
	 *
	 * Only the statuses that change what the order is worth are written;
	 * the intermediate shipping steps belong on the request's own timeline,
	 * and repeating all of them here would bury the order's notes.
	 *
	 * @param Return_Request $request    The request.
	 * @param string         $old_status Previous status.
	 * @param string         $new_status New status.
	 *
	 * @return void
	 */
	public function on_status_changed( $request, $old_status, $new_status ) {
		$notable = array( Return_Status::APPROVED, Return_Status::REJECTED, Return_Status::COMPLETED, Return_Status::CANCELLED );

		if ( ! in_array( $new_status, $notable, true ) ) {
			return;
		}

		$order = $request->get_order();

		if ( ! $order ) {
			return;
		}

		$order->add_order_note(
			sprintf(
				/* translators: 1: return reference, 2: new status label. */
				__( 'İade talebi %1$s: %2$s', 'hezarfen-for-woocommerce' ),
				$request->get_return_number(),
				Return_Status::get_label( $new_status )
			)
		);
	}

	/**
	 * The returned lines as one short line of text.
	 *
	 * @param Return_Request $request The request.
	 *
	 * @return string
	 */
	private function summarize_items( $request ) {
		$parts = array();

		foreach ( $request->get_items() as $item ) {
			$parts[] = sprintf( '%s × %d', $item->get_product_name(), $item->get_quantity() );
		}

		return implode( ', ', $parts );
	}
}
