<?php
/**
 * Contains the Returns_List_Table screen.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Admin;

use Hezarfen\Inc\Returns\Core\Return_Status;
use Hezarfen\Inc\Returns\Returns_Module;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists return requests using the familiar WordPress table chrome.
 *
 * Reads through the repository like every other consumer, so the query
 * arguments the admin filters build are the same ones the front-end uses.
 */
class Returns_List_Table extends \WP_List_Table {

	/**
	 * Rows per page.
	 */
	const PER_PAGE = 20;

	/**
	 * Module container.
	 *
	 * @var Returns_Module
	 */
	private $module;

	/**
	 * Total number of rows matching the current filters.
	 *
	 * @var int
	 */
	private $total = 0;

	/**
	 * Constructor.
	 *
	 * @param Returns_Module $module Module container.
	 */
	public function __construct( $module ) {
		$this->module = $module;

		parent::__construct(
			array(
				'singular' => 'hezarfen_return',
				'plural'   => 'hezarfen_returns',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Table columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		$columns = array(
			'return_number' => __( 'Talep', 'hezarfen-for-woocommerce' ),
			'order'         => __( 'Sipariş', 'hezarfen-for-woocommerce' ),
			'customer'      => __( 'Müşteri', 'hezarfen-for-woocommerce' ),
			'items'         => __( 'Ürünler', 'hezarfen-for-woocommerce' ),
			'total'         => __( 'Tutar', 'hezarfen-for-woocommerce' ),
			'status'        => __( 'Durum', 'hezarfen-for-woocommerce' ),
			'created_at'    => __( 'Tarih', 'hezarfen-for-woocommerce' ),
		);

		/**
		 * Filters the columns of the admin returns table.
		 *
		 * @param array<string, string> $columns Columns keyed by id.
		 */
		return apply_filters( 'hezarfen_returns_admin_columns', $columns );
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function get_sortable_columns() {
		return array(
			'return_number' => array( 'id', false ),
			'order'         => array( 'order_id', false ),
			'total'         => array( 'refund_amount', false ),
			'status'        => array( 'status', false ),
			'created_at'    => array( 'created_at', true ),
		);
	}

	/**
	 * Status filter links above the table.
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$counts  = $this->module->repository()->count_by_status();
		$current = $this->get_current_status();
		$base    = admin_url( 'admin.php?page=' . Returns_Admin::PAGE_SLUG );

		$views = array(
			'all' => sprintf(
				'<a href="%1$s" class="%2$s">%3$s <span class="count">(%4$d)</span></a>',
				esc_url( $base ),
				'' === $current ? 'current' : '',
				esc_html__( 'Tümü', 'hezarfen-for-woocommerce' ),
				array_sum( $counts )
			),
		);

		foreach ( Return_Status::get_statuses() as $status => $definition ) {
			if ( empty( $counts[ $status ] ) ) {
				continue;
			}

			$views[ $status ] = sprintf(
				'<a href="%1$s" class="%2$s">%3$s <span class="count">(%4$d)</span></a>',
				esc_url( add_query_arg( 'status', $status, $base ) ),
				$current === $status ? 'current' : '',
				esc_html( $definition['label'] ),
				(int) $counts[ $status ]
			);
		}

		return $views;
	}

	/**
	 * Loads the rows for the current screen.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$paged = max( 1, (int) $this->get_pagenum() );
		$args  = $this->build_query_args();
		$repo  = $this->module->repository();

		$this->total = $repo->count( $args );

		$args['limit']  = self::PER_PAGE;
		$args['offset'] = ( $paged - 1 ) * self::PER_PAGE;

		$this->items = $repo->query( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $this->total,
				'per_page'    => self::PER_PAGE,
				'total_pages' => (int) ceil( $this->total / self::PER_PAGE ),
			)
		);
	}

	/**
	 * Message shown when nothing matches.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'Henüz iade talebi yok.', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Renders the request reference with its row actions.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $item Row.
	 *
	 * @return string
	 */
	public function column_return_number( $item ) {
		$url = Returns_Admin::get_detail_url( $item->get_id() );

		return sprintf(
			'<strong><a href="%1$s">%2$s</a></strong>',
			esc_url( $url ),
			esc_html( $item->get_return_number() )
		);
	}

	/**
	 * Renders the order link.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $item Row.
	 *
	 * @return string
	 */
	public function column_order( $item ) {
		$order = $item->get_order();

		if ( ! $order ) {
			return '&mdash;';
		}

		return sprintf(
			'<a href="%1$s">#%2$s</a>',
			esc_url( $order->get_edit_order_url() ),
			esc_html( $order->get_order_number() )
		);
	}

	/**
	 * Renders the customer identity.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $item Row.
	 *
	 * @return string
	 */
	public function column_customer( $item ) {
		$order = $item->get_order();
		$name  = $order ? trim( $order->get_formatted_billing_full_name() ) : '';

		$output = $name ? '<strong>' . esc_html( $name ) . '</strong><br>' : '';

		return $output . esc_html( $item->get_customer_email() );
	}

	/**
	 * Renders a compact product summary.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $item Row.
	 *
	 * @return string
	 */
	public function column_items( $item ) {
		$lines = array();

		foreach ( $item->get_items() as $line ) {
			$lines[] = sprintf( '%s &times; %d', esc_html( $line->get_product_name() ), (int) $line->get_quantity() );
		}

		return implode( '<br>', $lines );
	}

	/**
	 * Renders the request total.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $item Row.
	 *
	 * @return string
	 */
	public function column_total( $item ) {
		return wc_price( $item->get_refund_amount(), array( 'currency' => $item->get_currency() ) );
	}

	/**
	 * Renders the status pill.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $item Row.
	 *
	 * @return string
	 */
	public function column_status( $item ) {
		return sprintf(
			'<span class="hez-admin-badge hez-admin-badge--%1$s">%2$s</span>',
			esc_attr( Return_Status::get_tone( $item->get_status() ) ),
			esc_html( Return_Status::get_label( $item->get_status() ) )
		);
	}

	/**
	 * Renders the creation date.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $item Row.
	 *
	 * @return string
	 */
	public function column_created_at( $item ) {
		return esc_html( hezarfen_returns_format_datetime( $item->get_created_at() ) );
	}

	/**
	 * Fallback renderer so add-on columns do not fatal.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $item        Row.
	 * @param string                                    $column_name Column id.
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		/**
		 * Filters the content of a custom admin returns column.
		 *
		 * @param string                                    $content     Rendered cell.
		 * @param string                                    $column_name Column id.
		 * @param \Hezarfen\Inc\Returns\Core\Return_Request $item        Row.
		 */
		return (string) apply_filters( 'hezarfen_returns_admin_column_content', '', $column_name, $item );
	}

	/**
	 * Builds the repository arguments from the current request.
	 *
	 * @return array<string, mixed>
	 */
	private function build_query_args() {
		$args = array();

		$status = $this->get_current_status();

		if ( $status ) {
			$args['status'] = $status;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filtering.
		if ( ! empty( $_REQUEST['s'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['search'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_REQUEST['orderby'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['orderby'] = sanitize_key( wp_unslash( $_REQUEST['orderby'] ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_REQUEST['order'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['order'] = 'asc' === strtolower( sanitize_key( wp_unslash( $_REQUEST['order'] ) ) ) ? 'ASC' : 'DESC';
		}

		return $args;
	}

	/**
	 * The status filter currently applied.
	 *
	 * @return string
	 */
	private function get_current_status() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filtering.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

		return Return_Status::exists( $status ) ? $status : '';
	}
}
