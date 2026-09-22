<?php
/**
 * Contains the Order_Returns_Panel metabox.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Admin;

use Hezarfen\Inc\Returns\Core\Return_Status;
use Hezarfen\Inc\Returns\Returns_Module;

defined( 'ABSPATH' ) || exit();

/**
 * Shows an order's return requests on the order edit screen.
 *
 * Read-only on purpose: the order screen answers "is anything coming
 * back?", while the actual handling stays on the returns screen so there
 * is only one place where a request can be moved along.
 */
class Order_Returns_Panel {

	/**
	 * Module container.
	 *
	 * @var Returns_Module
	 */
	private $module;

	/**
	 * Constructor.
	 *
	 * @param Returns_Module $module Module container.
	 */
	public function __construct( $module ) {
		$this->module = $module;

		add_action( 'add_meta_boxes', array( $this, 'register' ), 30, 2 );
	}

	/**
	 * Registers the metabox on both the legacy and the HPOS order screen.
	 *
	 * @param string             $screen_id Current screen ID.
	 * @param \WP_Post|\WC_Order $post_or_order Screen subject.
	 *
	 * @return void
	 */
	public function register( $screen_id, $post_or_order = null ) {
		unset( $post_or_order );

		$order_screens = array( 'shop_order', 'woocommerce_page_wc-orders' );

		if ( ! in_array( $screen_id, $order_screens, true ) ) {
			return;
		}

		add_meta_box(
			'hezarfen-order-returns',
			__( 'Hezarfen İade Talepleri', 'hezarfen-for-woocommerce' ),
			array( $this, 'render' ),
			$screen_id,
			'side',
			'default'
		);
	}

	/**
	 * Renders the panel.
	 *
	 * @param \WP_Post|\WC_Order $post_or_order Screen subject.
	 *
	 * @return void
	 */
	public function render( $post_or_order ) {
		$order = $post_or_order instanceof \WC_Order ? $post_or_order : wc_get_order( $post_or_order );

		if ( ! $order ) {
			return;
		}

		$requests = $this->module->repository()->query(
			array(
				'order_id' => $order->get_id(),
				'limit'    => 20,
			)
		);

		if ( ! $requests ) {
			echo '<p class="description">' . esc_html__( 'Bu sipariş için iade talebi yok.', 'hezarfen-for-woocommerce' ) . '</p>';

			return;
		}

		echo '<ul class="hez-order-returns">';

		foreach ( $requests as $request ) {
			printf(
				'<li><a href="%1$s"><strong>%2$s</strong></a> <span class="hez-admin-badge hez-admin-badge--%3$s">%4$s</span><br><span class="description">%5$s</span></li>',
				esc_url( Returns_Admin::get_detail_url( $request->get_id() ) ),
				esc_html( $request->get_return_number() ),
				esc_attr( Return_Status::get_tone( $request->get_status() ) ),
				esc_html( Return_Status::get_label( $request->get_status() ) ),
				esc_html(
					sprintf(
						/* translators: 1: item count, 2: creation date. */
						__( '%1$d ürün · %2$s', 'hezarfen-for-woocommerce' ),
						$request->get_total_quantity(),
						hezarfen_returns_format_datetime( $request->get_created_at() )
					)
				)
			);
		}

		echo '</ul>';
	}
}
