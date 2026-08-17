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
