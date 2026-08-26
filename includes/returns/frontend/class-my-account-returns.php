<?php
/**
 * Contains the My_Account_Returns front-end controller.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Frontend;

use Hezarfen\Inc\Returns\Core\Return_Pickup_Address;
use Hezarfen\Inc\Returns\Core\Return_Status;
use Hezarfen\Inc\Returns\Returns_Module;

defined( 'ABSPATH' ) || exit();

/**
 * Wires the return flow into the WooCommerce account pages.
 *
 * There is deliberately no "İadelerim" menu entry: a return belongs to an
 * order, so both starting one and following it happen on that order's
 * detail page. The two endpoints exist only as destinations — the request
 * form, and the request's own view that the order panel and the customer
 * e-mails link to.
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
	 * Endpoint signature waiting to be written once the rules are rebuilt.
	 *
	 * @var string
	 */
	private $pending_endpoint_signature = '';

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
		add_filter( 'woocommerce_endpoint_' . self::get_list_endpoint() . '_title', array( $this, 'get_detail_title' ) );
		add_filter( 'woocommerce_endpoint_' . self::get_request_endpoint() . '_title', array( $this, 'get_request_title' ) );

		add_action( 'template_redirect', array( $this, 'redirect_bare_detail_endpoint' ) );
		add_action( 'woocommerce_account_' . self::get_list_endpoint() . '_endpoint', array( $this, 'render_detail' ) );
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

		if ( get_option( self::ENDPOINT_VERSION_OPTION ) === $signature ) {
			return;
		}

		$this->pending_endpoint_signature = $signature;

		// Deferred on purpose: this module boots on `init`, and flushing
		// there would persist a rule set that misses everything other
		// plugins register later in `init` or on `wp_loaded` — their URLs
		// would 404 until permalinks are saved by hand.
		add_action( 'shutdown', array( $this, 'flush_endpoint_rules' ) );
	}

	/**
	 * Rebuilds the rewrite rules once every plugin has registered its own.
	 *
	 * The signature is stored only after the flush, so a request that dies
	 * before shutdown leaves the work to the next one instead of marking it
	 * done. Without this the account pages 404 until the merchant re-saves
	 * permalinks.
	 *
	 * @return void
	 */
	public function flush_endpoint_rules() {
		if ( '' === $this->pending_endpoint_signature ) {
			return;
		}

		$signature = $this->pending_endpoint_signature;

		$this->pending_endpoint_signature = '';

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules
		flush_rewrite_rules( false );

		update_option( self::ENDPOINT_VERSION_OPTION, $signature );
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
	 * Page title of the request detail endpoint.
	 *
	 * @return string
	 */
	public function get_detail_title() {
		return __( 'İade talebi', 'hezarfen-for-woocommerce' );
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
	 * Sends a bare `/iadelerim/` hit back to the orders list.
	 *
	 * The endpoint only ever addresses a single request, so without an ID
	 * there is nothing to render. A request ID that the visitor may not see
	 * is left alone on purpose: WooCommerce then shows its own login form on
	 * that URL, and the customer lands back on the request afterwards.
	 *
	 * @return void
	 */
	public function redirect_bare_detail_endpoint() {
		global $wp;

		$endpoint = self::get_list_endpoint();

		if ( ! isset( $wp->query_vars[ $endpoint ] ) ) {
			return;
		}

		if ( '' !== trim( (string) $wp->query_vars[ $endpoint ] ) ) {
			return;
		}

		wp_safe_redirect( wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) ) );
		exit;
	}

	/**
	 * Renders one request's detail view.
	 *
	 * @param string $value Endpoint value, expected to be a request ID.
	 *
	 * @return void
	 */
	public function render_detail( $value = '' ) {
		$request = $this->get_current_return( $value );

		if ( ! $request ) {
			wc_print_notice( __( 'İade talebi bulunamadı.', 'hezarfen-for-woocommerce' ), 'error' );

			return;
		}

		$order  = $request->get_order();
		$method = $this->module->shipping()->get_for_request( $request );

		hezarfen_returns_get_template(
			'returns/detail.php',
			array(
				'request'         => $request,
				'events'          => $this->module->events()->get_for_return( $request->get_id(), true ),
				'reasons'         => $this->module->reasons(),
				'shipping_method' => $method,
				'booking'         => $this->get_booking_view( $request, $method ),
				'pickup_address'  => $request->has_pickup_address() ? $request->get_pickup_address() : ( $order ? Return_Pickup_Address::from_order( $order ) : Return_Pickup_Address::empty_address() ),
				'progress_steps'  => Return_Status::get_progress_steps(),
				'back_url'        => $order ? $order->get_view_order_url() : '',
			)
		);
	}

	/**
	 * What the detail view needs to render the pickup day picker.
	 *
	 * The carrier is only asked when there is actually a booking to make,
	 * so viewing a finished request never waits on the relay. A lookup
	 * failure comes back as a message rather than an exception: the request
	 * is fine, it is the carrier that is momentarily unreachable, and the
	 * customer should see why the picker is missing.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request                  $request The request.
	 * @param \Hezarfen\Inc\Returns\Shipping\Return_Shipping_Method_Interface $method  Its shipping method.
	 *
	 * @return array{needed: bool, options: array<string, string>, error: string}
	 */
	private function get_booking_view( $request, $method ) {
		$view = array(
			'needed'  => $method->requires_customer_booking() && $request->is_bookable_by_customer(),
			'options' => array(),
			'error'   => '',
		);

		if ( ! $view['needed'] ) {
			return $view;
		}

		$options = $method->get_booking_options( $request );

		if ( is_wp_error( $options ) ) {
			$view['error'] = $options->get_error_message();

			return $view;
		}

		$view['options'] = (array) $options;

		return $view;
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

			return;
		}

		$eligible = $this->module->eligibility()->check_order( $order );

		if ( is_wp_error( $eligible ) ) {
			wc_print_notice( $eligible->get_error_message(), 'error' );

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
				'pickup_address'  => Return_Pickup_Address::from_order( $order ),
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
				'shipping'    => $this->module->shipping(),
				'deadline'    => $returnable ? $this->module->eligibility()->get_order_deadline( $order ) : 0,
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
