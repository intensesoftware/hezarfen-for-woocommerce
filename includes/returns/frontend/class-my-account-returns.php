<?php
/**
 * Contains the My_Account_Returns front-end controller.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Frontend;

use Hezarfen\Inc\Returns\Core\Return_Status;
use Hezarfen\Inc\Returns\Returns_Module;

defined( 'ABSPATH' ) || exit();

/**
 * Adds the "İadelerim" area to the WooCommerce account pages.
 *
 * Owns two endpoints — the request list (which doubles as the detail view
 * when it carries an ID) and the request form — plus the panel on the
 * order detail page, which is the only place a return starts from.
 */
class My_Account_Returns {

	const ENDPOINT_LIST    = 'iadelerim';
	const ENDPOINT_REQUEST = 'iade-talebi';

	const ENDPOINT_VERSION_OPTION = 'hezarfen_returns_endpoints_version';
	const ENDPOINT_VERSION        = '1';

	/**
	 * Module container.
	 *
	 * @var Returns_Module
	 */
	private $module;

	/**
	 * Access checker.
	 *
	 * @var Return_Access
	 */
	private $access;

	/**
	 * Constructor.
	 *
	 * @param Returns_Module $module Module container.
	 */
	public function __construct( $module ) {
		$this->module = $module;
		$this->access = new Return_Access();

		$this->register_endpoints();

		add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ), 20 );
		add_filter( 'woocommerce_endpoint_' . self::get_list_endpoint() . '_title', array( $this, 'get_list_title' ) );
		add_filter( 'woocommerce_endpoint_' . self::get_request_endpoint() . '_title', array( $this, 'get_request_title' ) );

		add_action( 'woocommerce_account_' . self::get_list_endpoint() . '_endpoint', array( $this, 'render_list_or_detail' ) );
		add_action( 'woocommerce_account_' . self::get_request_endpoint() . '_endpoint', array( $this, 'render_request_form' ) );

		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_order_panel' ), 20 );
	}

	/**
	 * Slug of the list endpoint.
	 *
	 * @return string
	 */
	public static function get_list_endpoint() {
		/**
		 * Filters the My Account endpoint slug of the returns list.
		 *
		 * @param string $slug Endpoint slug.
		 */
		return (string) apply_filters( 'hezarfen_returns_list_endpoint', self::ENDPOINT_LIST );
	}

	/**
	 * Slug of the request form endpoint.
	 *
	 * @return string
	 */
	public static function get_request_endpoint() {
		/**
		 * Filters the My Account endpoint slug of the return form.
		 *
		 * @param string $slug Endpoint slug.
		 */
		return (string) apply_filters( 'hezarfen_returns_request_endpoint', self::ENDPOINT_REQUEST );
	}

	/**
	 * Registers the rewrite endpoints, flushing rules only when the set
	 * changed. A permanent flush on every load would be a needless write
	 * on each request.
	 *
	 * @return void
	 */
	private function register_endpoints() {
		add_rewrite_endpoint( self::get_list_endpoint(), EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( self::get_request_endpoint(), EP_ROOT | EP_PAGES );

		$signature = self::ENDPOINT_VERSION . '|' . self::get_list_endpoint() . '|' . self::get_request_endpoint();

		if ( get_option( self::ENDPOINT_VERSION_OPTION ) !== $signature ) {
			update_option( self::ENDPOINT_VERSION_OPTION, $signature );
			// One-shot: the signature is stored first, so this runs once per
			// endpoint change rather than on every request. Without it the
			// account pages 404 until the merchant re-saves permalinks.
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Registers the endpoints as WooCommerce query vars.
	 *
	 * @param array<string, string> $vars Query vars.
	 *
	 * @return array<string, string>
	 */
	public function add_query_vars( $vars ) {
		$vars[ self::get_list_endpoint() ]    = self::get_list_endpoint();
		$vars[ self::get_request_endpoint() ] = self::get_request_endpoint();

		return $vars;
	}

	/**
	 * Inserts the menu item right after "Siparişler".
	 *
	 * @param array<string, string> $items Menu items.
	 *
	 * @return array<string, string>
	 */
	public function add_menu_item( $items ) {
		$new      = array();
		$endpoint = self::get_list_endpoint();

		foreach ( $items as $key => $label ) {
			$new[ $key ] = $label;

			if ( 'orders' === $key ) {
				$new[ $endpoint ] = __( 'İadelerim', 'hezarfen-for-woocommerce' );
			}
		}

		if ( ! isset( $new[ $endpoint ] ) ) {
			$new[ $endpoint ] = __( 'İadelerim', 'hezarfen-for-woocommerce' );
		}

		return $new;
	}

	/**
	 * Page title of the list endpoint.
	 *
	 * @return string
	 */
	public function get_list_title() {
		return $this->get_current_return() ? __( 'İade talebi', 'hezarfen-for-woocommerce' ) : __( 'İadelerim', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Page title of the request endpoint.
	 *
	 * @return string
	 */
	public function get_request_title() {
		return __( 'İade talebi oluştur', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Renders either the customer's request list or one request's detail.
	 *
	 * @param string $value Endpoint value.
	 *
	 * @return void
	 */
	public function render_list_or_detail( $value = '' ) {
		$request = $this->get_current_return( $value );

		if ( $request ) {
			$this->render_detail( $request );

			return;
		}

		$customer_id = get_current_user_id();

		$requests = $this->module->repository()->query(
			array(
				'customer_id' => $customer_id,
				'limit'       => 50,
			)
		);

		hezarfen_returns_get_template(
			'returns/my-account-list.php',
			array(
				'requests'   => $requests,
				'access'     => $this->access,
				'orders_url' => wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) ),
			)
		);
	}

	/**
	 * Renders one request's detail view.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request The request.
	 *
	 * @return void
	 */
	private function render_detail( $request ) {
		hezarfen_returns_get_template(
			'returns/detail.php',
			array(
				'request'         => $request,
				'events'          => $this->module->events()->get_for_return( $request->get_id(), true ),
				'reasons'         => $this->module->reasons(),
				'shipping_method' => $this->module->shipping()->get_for_request( $request ),
				'progress_steps'  => Return_Status::get_progress_steps(),
				'back_url'        => wc_get_endpoint_url( self::get_list_endpoint(), '', wc_get_page_permalink( 'myaccount' ) ),
			)
		);
	}

	/**
	 * Renders the request form for the customer's own order.
	 *
	 * @param string $value Endpoint value, expected to be an order ID.
	 *
	 * @return void
	 */
	public function render_request_form( $value = '' ) {
		$order_id = absint( $value );
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order || ! $this->access->can_request_for_order( $order ) ) {
			wc_print_notice( __( 'Bu sipariş için iade talebi oluşturamazsınız.', 'hezarfen-for-woocommerce' ), 'error' );

			$this->render_list_or_detail();

			return;
		}

		$eligible = $this->module->eligibility()->check_order( $order );

		if ( is_wp_error( $eligible ) ) {
			wc_print_notice( $eligible->get_error_message(), 'error' );

			$this->render_list_or_detail();

			return;
		}

		hezarfen_returns_get_template(
			'returns/request-form.php',
			array(
				'order'           => $order,
				'lines'           => $this->module->eligibility()->get_returnable_lines( $order ),
				'reasons'         => $this->module->reasons(),
				'shipping_method' => $this->module->shipping()->get_active_method(),
				'deadline'        => $this->module->eligibility()->get_order_deadline( $order ),
				'cancel_url'      => $order->get_view_order_url(),
			)
		);
	}

	/**
	 * Shows the return entry point (or the existing requests) underneath
	 * the order table on the view-order page.
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return void
	 */
	public function render_order_panel( $order ) {
		if ( ! is_account_page() || ! $order instanceof \WC_Order ) {
			return;
		}

		$requests = $this->module->repository()->query(
			array(
				'order_id' => $order->get_id(),
				'limit'    => 20,
			)
		);

		$returnable = $this->module->eligibility()->is_order_returnable( $order );

		if ( ! $requests && ! $returnable ) {
			return;
		}

		hezarfen_returns_get_template(
			'returns/order-page-panel.php',
			array(
				'order'       => $order,
				'requests'    => $requests,
				'returnable'  => $returnable,
				'access'      => $this->access,
				'request_url' => wc_get_endpoint_url( self::get_request_endpoint(), (string) $order->get_id(), wc_get_page_permalink( 'myaccount' ) ),
			)
		);
	}

	/**
	 * Resolves the request the current URL points at, if the visitor may
	 * see it.
	 *
	 * @param string $value Endpoint value, when already known.
	 *
	 * @return \Hezarfen\Inc\Returns\Core\Return_Request|null
	 */
	private function get_current_return( $value = '' ) {
		global $wp;

		if ( '' === $value && isset( $wp->query_vars[ self::get_list_endpoint() ] ) ) {
			$value = $wp->query_vars[ self::get_list_endpoint() ];
		}

		$return_id = absint( $value );

		if ( ! $return_id ) {
			return null;
		}

		$request = $this->module->repository()->get( $return_id );

		return ( $request && $this->access->can_view( $request ) ) ? $request : null;
	}
}
