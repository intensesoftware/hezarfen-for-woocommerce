<?php
/**
 * Contains the Return_Form_Handler controller.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Frontend;

use Hezarfen\Inc\Returns\Core\Return_Pickup_Address;
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
	const ACTION_BOOKING  = 'booking';
	const ACTION_ADDRESS  = 'address';
	const ACTION_UNBOOK   = 'unbook';
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
			case self::ACTION_BOOKING:
				$this->handle_booking();
				break;
			case self::ACTION_ADDRESS:
				$this->handle_address();
				break;
			case self::ACTION_UNBOOK:
				$this->handle_unbook();
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
				'lines'          => $this->read_submitted_lines(),
				'customer_note'  => $this->read_textarea( 'customer_note' ),
				'pickup_address' => $this->read_pickup_address(),
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

		$result = $this->module->service()->set_tracking_by_customer( $request, $courier, $number );

		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );

			return;
		}

		wc_add_notice( __( 'Kargo bilginiz kaydedildi. Teşekkürler!', 'hezarfen-for-woocommerce' ), 'success' );

		$this->redirect( $this->access->get_request_url( $request ) );
	}

	/**
	 * Books the return shipment for the day the customer picked.
	 *
	 * @return void
	 */
	private function handle_booking() {
		$request = $this->read_authorised_request();

		if ( ! $request ) {
			return;
		}

		// Nonce verified in handle().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$choice = isset( $_POST['pickup_date'] ) ? sanitize_text_field( wp_unslash( $_POST['pickup_date'] ) ) : '';

		$result = $this->module->service()->book_shipment_by_customer( $request, $choice );

		if ( is_wp_error( $result ) ) {
			// No redirect: the detail page re-renders with a freshly
			// fetched day list, so a customer whose slot was taken picks
			// another one right away instead of a stale list.
			wc_add_notice( $result->get_error_message(), 'error' );

			return;
		}

		wc_add_notice(
			sprintf(
				/* translators: %s: pickup date. */
				__( 'İade kargo randevunuz alındı. Kargonuz %s tarihinde adresinizden teslim alınacak.', 'hezarfen-for-woocommerce' ),
				hezarfen_returns_format_date( $request->get_pickup_date() )
			),
			'success'
		);

		$this->redirect( $this->access->get_request_url( $request ) );
	}

	/**
	 * Calls off the carrier appointment the customer booked.
	 *
	 * @return void
	 */
	private function handle_unbook() {
		$request = $this->read_authorised_request();

		if ( ! $request ) {
			return;
		}

		$result = $this->module->service()->cancel_booking_by_customer( $request );

		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );

			return;
		}

		wc_add_notice( __( 'Kargo randevunuz iptal edildi. Dilerseniz yeni bir alım günü seçebilirsiniz.', 'hezarfen-for-woocommerce' ), 'success' );

		$this->redirect( $this->access->get_request_url( $request ) );
	}

	/**
	 * Stores a corrected pickup address for an approved request.
	 *
	 * @return void
	 */
	private function handle_address() {
		$request = $this->read_authorised_request();

		if ( ! $request ) {
			return;
		}

		$result = $this->module->service()->update_pickup_address( $request, $this->read_pickup_address() );

		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );

			return;
		}

		wc_add_notice( __( 'Kargo alım adresiniz güncellendi.', 'hezarfen-for-woocommerce' ), 'success' );

		// A redirect, so the day list is rebuilt for the new district
		// instead of the one the page was rendered with.
		$this->redirect( $this->access->get_request_url( $request ) );
	}

	/**
	 * Reads the pickup address fields out of the submission.
	 *
	 * @return array<string, string>
	 */
	private function read_pickup_address() {
		// Nonce verified in handle(); Return_Pickup_Address sanitises every
		// part it reads.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$input = isset( $_POST['pickup_address'] ) && is_array( $_POST['pickup_address'] ) ? $_POST['pickup_address'] : array();

		return Return_Pickup_Address::from_input( $input );
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

		/**
		 * Fires after the customer's answer was stored, while the submission
		 * is still in hand.
		 *
		 * Add-ons that put fields on the response form read them here; after
		 * the redirect below there is nothing left to read.
		 *
		 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request The request.
		 */
		do_action( 'hezarfen_returns_info_response_saved', $request );

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
