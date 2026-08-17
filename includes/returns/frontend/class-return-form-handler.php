<?php
/**
 * Contains the Return_Form_Handler controller.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Frontend;

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
			wc_add_notice( __( 'Oturumunuzun süresi doldu. Lütfen formu tekrar gönderin.', 'hezarfen-for-woocommerce' ), 'error' );

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
			wc_add_notice( __( 'Bu sipariş için iade talebi oluşturamazsınız.', 'hezarfen-for-woocommerce' ), 'error' );

			return;
		}

		$request = $this->module->service()->create(
			$order,
			array(
				'lines'         => $this->read_submitted_lines(),
				'customer_note' => $this->read_textarea( 'customer_note' ),
			)
		);

		if ( is_wp_error( $request ) ) {
			wc_add_notice( $request->get_error_message(), 'error' );

			return;
		}

		wc_add_notice(
			sprintf(
				/* translators: %s: return reference. */
				__( 'İade talebiniz alındı. Talep numaranız: %s', 'hezarfen-for-woocommerce' ),
				$request->get_return_number()
			),
			'success'
		);

		$this->redirect( $this->access->get_request_url( $request ) );
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
			wc_add_notice( $result->get_error_message(), 'error' );

			return;
		}

		wc_add_notice( __( 'İade talebiniz iptal edildi.', 'hezarfen-for-woocommerce' ), 'success' );

		$this->redirect( $this->access->get_request_url( $request ) );
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
			wc_add_notice( $result->get_error_message(), 'error' );

			return;
		}

		wc_add_notice( __( 'Kargo bilginiz kaydedildi. Teşekkürler!', 'hezarfen-for-woocommerce' ), 'success' );

		$this->redirect( $this->access->get_request_url( $request ) );
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
			wc_add_notice( $result->get_error_message(), 'error' );

			return;
		}

		wc_add_notice( __( 'Yanıtınız iletildi.', 'hezarfen-for-woocommerce' ), 'success' );

		$this->redirect( $this->access->get_request_url( $request ) );
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
			wc_add_notice( __( 'İade talebi bulunamadı.', 'hezarfen-for-woocommerce' ), 'error' );

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
