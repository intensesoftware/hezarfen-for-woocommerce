<?php
/**
 * Contains the Return_Eligibility service.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

defined( 'ABSPATH' ) || exit();

/**
 * Answers "can this be returned, and how much of it?".
 *
 * The front-end form, the guest flow and the server side validator all go
 * through this one class, so what a customer is offered and what the
 * server accepts can never drift apart.
 */
class Return_Eligibility {

	/**
	 * Request store, used to subtract already requested quantities.
	 *
	 * @var Return_Repository_Interface
	 */
	private $repository;

	/**
	 * Policy resolver.
	 *
	 * @var Return_Policy_Resolver
	 */
	private $policy_resolver;

	/**
	 * Constructor.
	 *
	 * @param Return_Repository_Interface $repository      Request store.
	 * @param Return_Policy_Resolver      $policy_resolver Policy resolver.
	 */
	public function __construct( $repository, $policy_resolver ) {
		$this->repository      = $repository;
		$this->policy_resolver = $policy_resolver;
	}

	/**
	 * Whether the order as a whole is open for return requests.
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return true|\WP_Error True when eligible, otherwise the reason.
	 */
	public function check_order( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return new \WP_Error( 'hezarfen_returns_invalid_order', __( 'Sipariş bulunamadı.', 'hezarfen-for-woocommerce' ) );
		}

		if ( ! in_array( $order->get_status(), Return_Settings::get_eligible_order_statuses(), true ) ) {
			return new \WP_Error(
				'hezarfen_returns_order_status',
				__( 'Bu siparişin durumu iade talebine uygun değil.', 'hezarfen-for-woocommerce' )
			);
		}

		$deadline = $this->get_order_deadline( $order );

		// Order dates expose UTC timestamps, so the comparison stays in UTC.
		if ( $deadline && time() > $deadline ) {
			return new \WP_Error(
				'hezarfen_returns_window_closed',
				sprintf(
					/* translators: %s: return window deadline, formatted date. */
					__( 'Bu sipariş için iade süresi %s tarihinde doldu.', 'hezarfen-for-woocommerce' ),
					wp_date( get_option( 'date_format' ), $deadline )
				)
			);
		}

		if ( ! $this->get_returnable_lines( $order ) ) {
			return new \WP_Error(
				'hezarfen_returns_no_items',
				__( 'Bu siparişte iade edilebilecek ürün kalmadı.', 'hezarfen-for-woocommerce' )
			);
		}

		/**
		 * Filters the order level eligibility verdict.
		 *
		 * @param true|\WP_Error $result Verdict so far.
		 * @param \WC_Order      $order  Order.
		 */
		return apply_filters( 'hezarfen_returns_order_eligibility', true, $order );
	}

	/**
	 * Shorthand for check_order() as a boolean.
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return bool
	 */
	public function is_order_returnable( $order ) {
		return true === $this->check_order( $order );
	}

	/**
	 * The moment the widest applicable window of an order closes.
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return int Unix timestamp, zero when no window applies.
	 */
	public function get_order_deadline( $order ) {
		$reference = $this->policy_resolver->get_window_reference_timestamp( $order );

		if ( ! $reference ) {
			return 0;
		}

		$deadline = 0;

		foreach ( $order->get_items() as $item ) {
			$policy = $this->policy_resolver->resolve( $order, $item );

			if ( ! $policy->is_returnable() ) {
				continue;
			}

			$line_deadline = $policy->get_deadline( $reference );

			// A single unlimited line keeps the whole order open.
			if ( ! $line_deadline ) {
				return 0;
			}

			$deadline = max( $deadline, $line_deadline );
		}

		return $deadline;
	}

	/**
	 * The lines of an order that still have returnable quantity left.
	 *
	 * Each entry carries:
	 *  - item      : \WC_Order_Item
	 *  - item_id   : int
	 *  - max_qty   : int remaining returnable quantity
	 *  - unit_price: float value of one unit, taxes included
	 *  - policy    : Return_Policy
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return array<int, array<string, mixed>> Keyed by order item ID.
	 */
	public function get_returnable_lines( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return array();
		}

		$requested = $this->repository->get_requested_quantities( $order->get_id() );
		$reference = $this->policy_resolver->get_window_reference_timestamp( $order );
		$now       = time();
		$lines     = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			$policy = $this->policy_resolver->resolve( $order, $item );

			if ( ! $policy->is_returnable() || ! $policy->is_within_window( $reference, $now ) ) {
				continue;
			}

			$ordered  = (int) $item->get_quantity();
			$already  = isset( $requested[ $item_id ] ) ? (int) $requested[ $item_id ] : 0;
			$refunded = (int) abs( $order->get_qty_refunded_for_item( $item_id ) );
			$max_qty  = $ordered - $already - $refunded;

			if ( $max_qty < 1 ) {
				continue;
			}

			$lines[ $item_id ] = array(
				'item'       => $item,
				'item_id'    => (int) $item_id,
				'max_qty'    => $max_qty,
				'unit_price' => $this->get_unit_price( $order, $item ),
				'policy'     => $policy,
				'deadline'   => $policy->get_deadline( $reference ),
			);
		}

		/**
		 * Filters the returnable lines of an order.
		 *
		 * @param array<int, array<string, mixed>> $lines Returnable lines keyed by order item ID.
		 * @param \WC_Order                        $order Order.
		 */
		return apply_filters( 'hezarfen_returns_returnable_lines', $lines, $order );
	}

	/**
	 * The remaining returnable quantity of a single line.
	 *
	 * @param \WC_Order $order   Order.
	 * @param int       $item_id Order item ID.
	 *
	 * @return int
	 */
	public function get_max_quantity( $order, $item_id ) {
		$lines = $this->get_returnable_lines( $order );

		return isset( $lines[ (int) $item_id ] ) ? (int) $lines[ (int) $item_id ]['max_qty'] : 0;
	}

	/**
	 * Value of one unit of a line, taxes included.
	 *
	 * @param \WC_Order      $order Order.
	 * @param \WC_Order_Item $item  Order line.
	 *
	 * @return float
	 */
	private function get_unit_price( $order, $item ) {
		$quantity = (int) $item->get_quantity();

		if ( $quantity < 1 ) {
			return 0.0;
		}

		$total = (float) $order->get_line_total( $item, true, false );

		return $total / $quantity;
	}
}
