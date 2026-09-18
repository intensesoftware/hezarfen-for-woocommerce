<?php
/**
 * Contains the Shipment_Column_State class.
 *
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Value object describing what the orders list "Shipment" column should show for a single order.
 *
 * This is the single source of truth for the column's state. It is resolved once per order by
 * Shipment_Column_State::resolve() and extensions (e.g. Hezarfen Pro) can contribute to it through
 * the `hezarfen_mst_order_shipment_state` filter instead of patching the column output directly.
 */
class Shipment_Column_State {
	const STATUS_SHIPPED       = 'shipped';
	const STATUS_BARCODE_READY = 'barcode_ready';
	const STATUS_NONE          = 'none';

	/**
	 * Status of the shipment for this order.
	 *
	 * One of self::STATUS_SHIPPED, self::STATUS_BARCODE_READY or self::STATUS_NONE.
	 *
	 * @var string
	 */
	public $status = self::STATUS_NONE;

	/**
	 * Number of shipments/barcodes this status is based on.
	 *
	 * @var int
	 */
	public $count = 0;

	/**
	 * The HTML that should be rendered inside the column for this state.
	 *
	 * @var string
	 */
	public $html = '';

	/**
	 * Resolves the shipment state of an order for the orders list "Shipment" column.
	 *
	 * Precedence:
	 * 1. self::STATUS_SHIPPED   — manual shipment data exists (Helper::get_all_shipment_data()).
	 * 2. self::STATUS_BARCODE_READY — no manual shipment data, but at least one active outgoing
	 *    hepsiJET barcode meta exists on the order (return/iade barcodes are not counted).
	 * 3. self::STATUS_NONE      — nothing to show.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return self
	 */
	public static function resolve( $order_id ) {
		$state = new self();

		$shipment_data = Helper::get_all_shipment_data( $order_id );

		if ( $shipment_data ) {
			$state->status = self::STATUS_SHIPPED;
			$state->count  = count( $shipment_data );
		} else {
			$barcode_count = Hepsijet_Bulk_Barcode::count_active_hepsijet_shipments( $order_id );

			if ( $barcode_count > 0 ) {
				$state->status = self::STATUS_BARCODE_READY;
				$state->count  = $barcode_count;
			}
		}

		/**
		 * Filters the resolved shipment state of an order for the orders list "Shipment" column.
		 *
		 * Extensions (e.g. Hezarfen Pro, which tracks its own shipments in a separate DB table)
		 * may use this filter to upgrade a self::STATUS_NONE state to self::STATUS_BARCODE_READY
		 * (or add to an existing count), by returning a modified Shipment_Column_State instance.
		 * Extensions should not downgrade a self::STATUS_SHIPPED state, as that is the highest
		 * precedence status and must not regress.
		 *
		 * Set `status` and `count` only: the column renders the markup itself afterwards, so a
		 * count added here shows up in the "multiple barcodes" badge without the extension having
		 * to reproduce the markup. This holds for self::STATUS_BARCODE_READY and self::STATUS_NONE.
		 *
		 * self::STATUS_SHIPPED is the exception: core can only draw its markup (the courier logo)
		 * from its own manual shipment data. An extension that reports self::STATUS_SHIPPED for an
		 * order without manual shipment data must also set `html` itself, or the cell is left
		 * blank. To append extra controls to the column, use the
		 * `hezarfen_mst_after_shipment_column` action instead.
		 *
		 * @since x.x
		 *
		 * @param Shipment_Column_State $state    The resolved shipment state.
		 * @param int                   $order_id Order ID.
		 *
		 * @return Shipment_Column_State
		 */
		$pre_filter_state = $state;
		$state            = apply_filters( 'hezarfen_mst_order_shipment_state', $state, $order_id );

		// A misbehaving extension can return something other than a Shipment_Column_State instance
		// (null, an array, etc.). One bad extension must not take down the whole orders list, so
		// fall back to the pre-filter state rather than using the filtered value unchecked.
		if ( ! $state instanceof self ) {
			$state = $pre_filter_state;
		}

		// The markup is always built here, from the state as it stands after the filter, so that a
		// count an extension added (a Pro barcode on top of a hepsiJET one, say) is reflected in the
		// "how many" badge. Extensions describe the state; the column decides how it looks.
		$state->html = self::render( $order_id, $state, $shipment_data );

		return $state;
	}

	/**
	 * Builds the column HTML for a resolved state.
	 *
	 * @param int             $order_id      Order ID.
	 * @param self            $state         Resolved state.
	 * @param Shipment_Data[] $shipment_data Manual shipment data of the order.
	 *
	 * @return string
	 */
	private static function render( $order_id, $state, $shipment_data ) {
		// An extension can report STATUS_SHIPPED without there being manual shipment data to draw a
		// courier logo from. It owns its own markup in that case; anything it already put in html
		// is kept rather than replaced with nothing.
		if ( self::STATUS_SHIPPED === $state->status ) {
			return $shipment_data ? self::render_shipped_html( $order_id, $shipment_data ) : $state->html;
		}

		if ( self::STATUS_BARCODE_READY === $state->status ) {
			return self::render_barcode_ready_html( $state->count );
		}

		return self::render_none_html( $order_id );
	}

	/**
	 * Renders the HTML for the self::STATUS_SHIPPED state.
	 *
	 * @param int              $order_id      Order ID.
	 * @param Shipment_Data[]  $shipment_data Manual shipment data.
	 *
	 * @return string
	 */
	private static function render_shipped_html( $order_id, $shipment_data ) {
		ob_start();

		if ( count( $shipment_data ) > 1 ) {
			printf( '<p>%s</p>', esc_html__( 'Shipment in pieces', 'hezarfen-for-woocommerce' ) );
		} else {
			$courier = Helper::get_courier_class( $shipment_data[0]->courier_id );

			if ( $courier::$logo ) {
				printf( '<img src="%s" class="courier-logo" loading="lazy" alt="%s">', esc_url( HEZARFEN_MST_COURIER_LOGO_URL . $courier::$logo ), esc_attr( $courier::get_title( $order_id ) ) );
			} else {
				printf( '<p>%s</p>', esc_html( $courier::get_title( $order_id ) ) );
			}
		}

		printf( '<span data-order-id="%s" class="dashicons dashicons-info-outline shipment-info-icon"></span>', esc_attr( (string) $order_id ) );

		return ob_get_clean();
	}

	/**
	 * Renders the HTML for the self::STATUS_BARCODE_READY state.
	 *
	 * @param int $count Number of active barcodes.
	 *
	 * @return string
	 */
	private static function render_barcode_ready_html( $count ) {
		ob_start();

		printf( '<span class="hezarfen-mst-barcode-ready">%s</span>', esc_html__( 'Barcode ready', 'hezarfen-for-woocommerce' ) );

		if ( $count > 1 ) {
			printf(
				'<span class="hezarfen-mst-barcode-count" title="%s">&times;%s</span>',
				/* translators: %d: number of barcodes created for this order. */
				esc_attr( sprintf( __( '%d barcodes created for this order', 'hezarfen-for-woocommerce' ), $count ) ),
				esc_html( (string) $count )
			);
		}

		return ob_get_clean();
	}

	/**
	 * Renders the HTML for the self::STATUS_NONE state, honoring the legacy filter.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return string
	 */
	private static function render_none_html( $order_id ) {
		/**
		 * Filters the message shown in the "Shipment" column when an order has no shipment data.
		 *
		 * @deprecated x.x Use the `hezarfen_mst_order_shipment_state` filter instead, which lets
		 *             extensions upgrade the whole state (status + count) rather than just the
		 *             fallback message. This filter is kept only for backward compatibility with
		 *             older builds of Hezarfen Pro and is applied solely to the self::STATUS_NONE
		 *             case.
		 *
		 * @param string|null $no_shipment_msg Message HTML, or null to use the default text.
		 * @param int         $order_id        Order ID.
		 *
		 * @return string|null
		 */
		$no_shipment_msg = apply_filters( 'hezarfen_shop_order_no_shipment_found_msg', null, $order_id );

		if ( is_null( $no_shipment_msg ) ) {
			return esc_html__( 'No shipment data found', 'hezarfen-for-woocommerce' );
		}

		return wp_kses_post( $no_shipment_msg );
	}
}
