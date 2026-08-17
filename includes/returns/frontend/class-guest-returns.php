<?php
/**
 * Contains the Guest_Returns front-end controller.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Frontend;

use Hezarfen\Inc\Returns\Core\Return_Settings;
use Hezarfen\Inc\Returns\Core\Return_Status;
use Hezarfen\Inc\Returns\Returns_Module;

defined( 'ABSPATH' ) || exit();

/**
 * The public return page customers without an account use.
 *
 * One shortcode serves three states — look your order up, fill the form,
 * follow the request — because a customer who lands here from an e-mail
 * link should never have to work out which page they need.
 */
class Guest_Returns {

	const SHORTCODE = 'hezarfen_iade';

	/**
	 * Slug of the auto-created page.
	 *
	 * Deliberately different from the My Account endpoint slugs: those are
	 * registered with EP_ROOT, so a page sharing a slug with one of them
	 * would be shadowed by the endpoint rewrite rule and 404.
	 */
	const PAGE_SLUG = 'iade';

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

		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	/**
	 * Creates the public return page if it does not exist yet and pins its
	 * ID to the settings.
	 *
	 * Deliberately does not use `wc_create_page()`: that helper lives in
	 * WooCommerce's admin-only function file, and the page has to be able
	 * to appear on a front-end request too.
	 *
	 * @return int Page ID, zero when creation failed.
	 */
	public static function ensure_page() {
		$existing = Return_Settings::get_page_id();

		if ( $existing && 'publish' === get_post_status( $existing ) ) {
			return $existing;
		}

		// Runs at most once per site: the page ID is pinned to an option
		// immediately afterwards, so the uncached lookup never repeats.
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_page_by_path_get_page_by_path
		$page = get_page_by_path( self::PAGE_SLUG );

		if ( $page && 'publish' === $page->post_status ) {
			update_option( Return_Settings::OPTION_PAGE_ID, $page->ID );

			return (int) $page->ID;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'     => __( 'İade Talebi', 'hezarfen-for-woocommerce' ),
				'post_name'      => self::PAGE_SLUG,
				'post_content'   => '<!-- wp:shortcode -->[' . self::SHORTCODE . ']<!-- /wp:shortcode -->',
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			),
			true
		);

		if ( is_wp_error( $page_id ) ) {
			return 0;
		}

		update_option( Return_Settings::OPTION_PAGE_ID, $page_id );

		return (int) $page_id;
	}

	/**
	 * Renders whichever state the current URL asks for.
	 *
	 * @return string
	 */
	public function render() {
		ob_start();

		wc_print_notices();

		$request = $this->get_requested_return();

		if ( $request ) {
			$this->render_detail( $request );

			return (string) ob_get_clean();
		}

		$order = $this->get_requested_order();

		if ( $order ) {
			$this->render_form( $order );

			return (string) ob_get_clean();
		}

		$this->render_lookup();

		return (string) ob_get_clean();
	}

	/**
	 * Renders the request's detail view.
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
				'back_url'        => '',
				'is_guest_view'   => true,
			)
		);
	}

	/**
	 * Renders the return form for a verified guest order.
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return void
	 */
	private function render_form( $order ) {
		$eligible = $this->module->eligibility()->check_order( $order );

		if ( is_wp_error( $eligible ) ) {
			wc_print_notice( $eligible->get_error_message(), 'error' );

			$this->render_lookup();

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
				'cancel_url'      => Return_Settings::get_page_url(),
				'access_token'    => $this->access->get_order_token( $order ),
			)
		);
	}

	/**
	 * Renders the order lookup form.
	 *
	 * @return void
	 */
	private function render_lookup() {
		hezarfen_returns_get_template(
			'returns/guest-lookup.php',
			array(
				'guest_enabled' => Return_Settings::is_guest_enabled(),
				'account_url'   => wc_get_page_permalink( 'myaccount' ),
				'window_days'   => Return_Settings::get_window_days(),
			)
		);
	}

	/**
	 * The request the URL points at, when the token checks out.
	 *
	 * @return \Hezarfen\Inc\Returns\Core\Return_Request|null
	 */
	private function get_requested_return() {
		// Ownership is proven by the token compared inside can_view().
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$return_id = isset( $_GET[ Return_Access::QUERY_RETURN ] ) ? absint( wp_unslash( $_GET[ Return_Access::QUERY_RETURN ] ) ) : 0;

		if ( ! $return_id ) {
			return null;
		}

		$request = $this->module->repository()->get( $return_id );

		return ( $request && $this->access->can_view( $request ) ) ? $request : null;
	}

	/**
	 * The order the URL points at, when the token checks out.
	 *
	 * @return \WC_Order|null
	 */
	private function get_requested_order() {
		// Ownership is proven by the token compared inside
		// can_request_for_order().
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_id = isset( $_GET[ Return_Access::QUERY_ORDER ] ) ? absint( wp_unslash( $_GET[ Return_Access::QUERY_ORDER ] ) ) : 0;

		if ( ! $order_id ) {
			return null;
		}

		$order = wc_get_order( $order_id );

		return ( $order && $this->access->can_request_for_order( $order ) ) ? $order : null;
	}
}
