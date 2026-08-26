<?php
/**
 * Contains the Customer_Ships_Method.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Shipping;

use Hezarfen\Inc\Returns\Core\Return_Settings;

defined( 'ABSPATH' ) || exit();

/**
 * The customer arranges the return shipment themselves.
 *
 * Always available: it needs nothing but a return address, and it is the
 * safe fallback whenever an automated method fails.
 */
class Customer_Ships_Method implements Return_Shipping_Method_Interface {

	const KEY = 'customer-ships';

	/**
	 * Stable identifier.
	 *
	 * @return string
	 */
	public function get_key() {
		return self::KEY;
	}

	/**
	 * Merchant facing label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Müşteri kendi gönderir', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Short explanation.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Müşteri ürünü dilediği kargo firmasıyla iade adresinize gönderir ve takip numarasını hesabından girer.', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Always selectable.
	 *
	 * @return bool
	 */
	public function is_available() {
		return true;
	}

	/**
	 * The customer types the tracking number in themselves.
	 *
	 * @return bool
	 */
	public function requires_customer_tracking() {
		return true;
	}

	/**
	 * There is nothing to book: the customer walks into the carrier branch
	 * of their choice whenever it suits them.
	 *
	 * @return bool
	 */
	public function requires_customer_booking() {
		return false;
	}

	/**
	 * The customer takes the parcel to the carrier, so nobody collects it
	 * from an address.
	 *
	 * @return bool
	 */
	public function requires_pickup_address() {
		return false;
	}

	/**
	 * No booking, no options.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request Approved request.
	 *
	 * @return array<string, string>
	 */
	public function get_booking_options( $request ) {
		unset( $request );

		return array();
	}

	/**
	 * Nothing to book.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request Approved request.
	 * @param string                                    $choice  Picked option.
	 *
	 * @return \WP_Error
	 */
	public function book( $request, $choice ) {
		unset( $request, $choice );

		return new \WP_Error(
			'hezarfen_returns_booking_not_supported',
			__( 'Bu iade yöntemi için kargo randevusu alınmaz.', 'hezarfen-for-woocommerce' )
		);
	}

	/**
	 * Nothing was ever booked, so nothing can be cancelled.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request The request.
	 *
	 * @return \WP_Error
	 */
	public function cancel_booking( $request ) {
		unset( $request );

		return new \WP_Error(
			'hezarfen_returns_no_booking',
			__( 'Bu iade yönteminde iptal edilecek bir kargo randevusu yok.', 'hezarfen-for-woocommerce' )
		);
	}

	/**
	 * Nothing to do on approval.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request Approved request.
	 *
	 * @return true
	 */
	public function handle_approved( $request ) {
		unset( $request );

		return true;
	}

	/**
	 * Tells the customer where to ship and what to do afterwards.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request The request.
	 *
	 * @return string
	 */
	public function get_customer_instructions( $request ) {
		unset( $request );

		$instructions = __( 'Ürünleri aşağıdaki iade adresine gönderin, ardından kargo takip numarasını bu sayfadaki forma girin.', 'hezarfen-for-woocommerce' );

		$custom = Return_Settings::get_instructions();

		if ( '' !== trim( $custom ) ) {
			$instructions .= "\n" . $custom;
		}

		return wpautop( esc_html( $instructions ) );
	}
}
