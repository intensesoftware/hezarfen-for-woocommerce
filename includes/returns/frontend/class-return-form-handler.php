<?php
/**
 * Contains the Return_Form_Handler controller.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Frontend;

use Hezarfen\Inc\Returns\Core\Return_Settings;
use Hezarfen\Inc\Returns\Returns_Module;

defined( 'ABSPATH' ) || exit();

/**
 * Turns customer form submissions into service calls.
 *
 * Sanitising input, checking nonces and proving ownership happen here;
 * the rules of what may change live in Return_Service. Nothing in this
 * class writes to the database directly.
 */
class Return_Form_Handler {

	const ACTION_FIELD = 'hezarfen_returns_action';
	const NONCE_FIELD  = 'hezarfen_returns_nonce';

	const ACTION_CREATE   = 'create';
	const ACTION_CANCEL   = 'cancel';
	const ACTION_TRACKING = 'tracking';
	const ACTION_INFO     = 'info';
	const ACTION_LOOKUP   = 'lookup';

	/**
	 * How many guest lookups one IP may attempt before being throttled.
	 */
	const LOOKUP_ATTEMPT_LIMIT = 8;

	/**
	 * Window the lookup attempts are counted in, in seconds.
	 */
	const LOOKUP_ATTEMPT_WINDOW = 900;

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

		add_action( 'template_redirect', array( $this, 'handle' ) );
	}

	/**
	 * Dispatches the submitted action.
	 *
	 * @return void
	 */
	public function handle() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST[ self::ACTION_FIELD ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$action = sanitize_key( wp_unslash( $_POST[ self::ACTION_FIELD ] ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'hezarfen_returns_' . $action ) ) {
			$this->notice( __( 'Oturumunuzun süresi doldu. Lütfen formu tekrar gönderin.', 'hezarfen-for-woocommerce' ), 'error' );

			return;
		}

		switch ( $action ) {
			case self::ACTION_CREATE:
				$this->handle_create();
				break;
			case self::ACTION_CANCEL:
				$this->handle_cancel();
				break;
			case self::ACTION_TRACKING:
				$this->handle_tracking();
				break;
			case self::ACTION_INFO:
				$this->handle_info();
				break;
			case self::ACTION_LOOKUP:
				$this->handle_lookup();
				break;
		}
	}

	/**
	 * Creates a request from the multi-line form.
	 *
	 * @return void
	 */
	private function handle_create() {
		// Nonce verified in handle().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$order    = wc_get_order( $order_id );

		if ( ! $order || ! $this->access->can_request_for_order( $order ) ) {
			$this->notice( __( 'Bu sipariş için iade talebi oluşturamazsınız.', 'hezarfen-for-woocommerce' ), 'error' );

			return;
		}

		$request = $this->module->service()->create(
			$order,
			array(
				'lines'         => $this->read_submitted_lines(),
				'customer_note' => $this->read_textarea( 'customer_note' ),
				'created_via'   => is_user_logged_in() ? 'account' : 'guest',
				'ip_address'    => \WC_Geolocation::get_ip_address(),
			)
		);

		if ( is_wp_error( $request ) ) {
			$this->notice( $request->get_error_message(), 'error' );

			return;
		}

		$this->notice(
			sprintf(
				/* translators: %s: return reference. */
				__( 'İade talebiniz alındı. Talep numaranız: %s', 'hezarfen-for-woocommerce' ),
				$request->get_return_number()
			),
			'success'
		);

		$this->redirect( $this->access->get_request_url( $request, ! is_user_logged_in() ) );
	}

	/**
	 * Cancels a pending request.
	 *
	 * @return void
	 */
	private function handle_cancel() {
		$request = $this->read_authorised_request();

		if ( ! $request ) {
			return;
		}

		$result = $this->module->service()->cancel_by_customer( $request );

		if ( is_wp_error( $result ) ) {
			$this->notice( $result->get_error_message(), 'error' );

			return;
		}

		$this->notice( __( 'İade talebiniz iptal edildi.', 'hezarfen-for-woocommerce' ), 'success' );

		$this->redirect( $this->access->get_request_url( $request, ! is_user_logged_in() ) );
	}

	/**
	 * Stores the tracking details the customer typed in.
	 *
	 * @return void
	 */
	private function handle_tracking() {
		$request = $this->read_authorised_request();

		if ( ! $request ) {
			return;
		}

		// Nonce verified in handle().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$courier = isset( $_POST['courier'] ) ? sanitize_text_field( wp_unslash( $_POST['courier'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$number = isset( $_POST['tracking_number'] ) ? sanitize_text_field( wp_unslash( $_POST['tracking_number'] ) ) : '';

		$result = $this->module->service()->set_tracking( $request, $courier, $number );

		if ( is_wp_error( $result ) ) {
			$this->notice( $result->get_error_message(), 'error' );

			return;
		}

		$this->notice( __( 'Kargo bilginiz kaydedildi. Teşekkürler!', 'hezarfen-for-woocommerce' ), 'success' );

		$this->redirect( $this->access->get_request_url( $request, ! is_user_logged_in() ) );
	}

	/**
	 * Records the customer's answer to an information request.
	 *
	 * @return void
	 */
	private function handle_info() {
		$request = $this->read_authorised_request();

		if ( ! $request ) {
			return;
		}

		$result = $this->module->service()->respond_info( $request, $this->read_textarea( 'info_response' ) );

		if ( is_wp_error( $result ) ) {
			$this->notice( $result->get_error_message(), 'error' );

			return;
		}

		$this->notice( __( 'Yanıtınız iletildi.', 'hezarfen-for-woocommerce' ), 'success' );

		$this->redirect( $this->access->get_request_url( $request, ! is_user_logged_in() ) );
	}

	/**
	 * Looks a guest's order up from an order number and billing e-mail.
	 *
	 * @return void
	 */
	private function handle_lookup() {
		if ( ! Return_Settings::is_guest_enabled() ) {
			$this->notice( __( 'Üyeliksiz iade talebi kapalı. Lütfen hesabınıza giriş yapın.', 'hezarfen-for-woocommerce' ), 'error' );

			return;
		}

		if ( $this->is_lookup_throttled() ) {
			$this->notice(
				__( 'Çok fazla deneme yaptınız. Lütfen bir süre sonra tekrar deneyin.', 'hezarfen-for-woocommerce' ),
				'error'
			);

			return;
		}

		// Nonce verified in handle().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$order_number = isset( $_POST['order_number'] ) ? sanitize_text_field( wp_unslash( $_POST['order_number'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$email = isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : '';

		$order = wc_get_order( absint( $order_number ) );

		// One generic message for every failure mode so the form cannot be
		// used to discover which order numbers or e-mails exist.
		$generic_error = __( 'Sipariş numarası ve e-posta adresi eşleşmiyor.', 'hezarfen-for-woocommerce' );

		if ( ! $order || ! $email || strtolower( $order->get_billing_email() ) !== strtolower( $email ) ) {
			$this->record_lookup_attempt();
			$this->notice( $generic_error, 'error' );

			return;
		}

		$this->clear_lookup_attempts();

		$url = $this->access->get_guest_form_url( $order );

		if ( ! $url ) {
			$this->notice( __( 'İade sayfası yapılandırılmamış. Lütfen mağaza ile iletişime geçin.', 'hezarfen-for-woocommerce' ), 'error' );

			return;
		}

		$this->redirect( $url );
	}

	/**
	 * Loads the posted request and verifies the visitor may act on it.
	 *
	 * @return \Hezarfen\Inc\Returns\Core\Return_Request|null
	 */
	private function read_authorised_request() {
		// Nonce verified in handle().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$return_id = isset( $_POST['return_id'] ) ? absint( wp_unslash( $_POST['return_id'] ) ) : 0;

		$request = $this->module->repository()->get( $return_id );

		if ( ! $request || ! $this->access->can_view( $request ) ) {
			$this->notice( __( 'İade talebi bulunamadı.', 'hezarfen-for-woocommerce' ), 'error' );

			return null;
		}

		return $request;
	}

	/**
	 * Reads and sanitises the submitted line selections.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function read_submitted_lines() {
		// Nonce verified in handle().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST['items'] ) || ! is_array( $_POST['items'] ) ) {
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw   = wp_unslash( $_POST['items'] );
		$lines = array();

		foreach ( (array) $raw as $item_id => $line ) {
			if ( ! is_array( $line ) || empty( $line['selected'] ) ) {
				continue;
			}

			$lines[ absint( $item_id ) ] = array(
				'quantity' => isset( $line['quantity'] ) ? absint( $line['quantity'] ) : 0,
				'reason'   => isset( $line['reason'] ) ? sanitize_key( $line['reason'] ) : '',
				'note'     => isset( $line['note'] ) ? sanitize_textarea_field( $line['note'] ) : '',
			);
		}

		return $lines;
	}

	/**
	 * Reads a sanitised textarea value from the request.
	 *
	 * @param string $field Field name.
	 *
	 * @return string
	 */
	private function read_textarea( $field ) {
		// Nonce verified in handle().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST[ $field ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) );
	}

	/**
	 * Whether the visitor exhausted their guest lookup attempts.
	 *
	 * @return bool
	 */
	private function is_lookup_throttled() {
		return (int) get_transient( $this->get_lookup_transient_key() ) >= self::LOOKUP_ATTEMPT_LIMIT;
	}

	/**
	 * Counts a failed guest lookup.
	 *
	 * @return void
	 */
	private function record_lookup_attempt() {
		$key = $this->get_lookup_transient_key();

		set_transient( $key, (int) get_transient( $key ) + 1, self::LOOKUP_ATTEMPT_WINDOW );
	}

	/**
	 * Resets the counter after a successful lookup.
	 *
	 * @return void
	 */
	private function clear_lookup_attempts() {
		delete_transient( $this->get_lookup_transient_key() );
	}

	/**
	 * Per-IP transient key of the lookup throttle.
	 *
	 * @return string
	 */
	private function get_lookup_transient_key() {
		return 'hez_ret_lookup_' . md5( (string) \WC_Geolocation::get_ip_address() );
	}

	/**
	 * Queues a notice for the next page load.
	 *
	 * A guest who never touched the cart has no WooCommerce session cookie
	 * yet, and notices live in the session — so without forcing the cookie
	 * first, the confirmation of their very first return request would be
	 * silently dropped on the redirect.
	 *
	 * @param string $message Notice body.
	 * @param string $type    Notice type accepted by wc_add_notice().
	 *
	 * @return void
	 */
	private function notice( $message, $type = 'success' ) {
		$session = WC()->session;

		// The cookie setter lives on WC_Session_Handler; a store that swaps
		// in another WC_Session implementation simply skips this step.
		if ( ! is_user_logged_in() && $session instanceof \WC_Session_Handler ) {
			$session->set_customer_session_cookie( true );
		}

		wc_add_notice( $message, $type );
	}

	/**
	 * Redirects and stops, keeping notices for the next page load.
	 *
	 * @param string $url Target URL.
	 *
	 * @return void
	 */
	private function redirect( $url ) {
		wp_safe_redirect( $url );
		exit;
	}
}
