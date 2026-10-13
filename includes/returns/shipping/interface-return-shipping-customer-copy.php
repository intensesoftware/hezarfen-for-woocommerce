<?php
/**
 * Contains the Return_Shipping_Customer_Copy_Interface contract.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Shipping;

defined( 'ABSPATH' ) || exit();

/**
 * Customer facing wording of a return shipping method.
 *
 * get_label() and get_description() speak to the merchant in the settings
 * screen — they name the carrier integration and the credentials it needs,
 * none of which belongs on the customer's request form. A method implements
 * this to tell the customer what will happen instead.
 *
 * It is a separate, optional contract on purpose: adding the methods to
 * Return_Shipping_Method_Interface would fatal every method written against
 * the older interface, Pro's included. A method that does not implement it
 * gets a generic wording derived from its capabilities; see
 * hezarfen_returns_shipping_customer_copy().
 */
interface Return_Shipping_Customer_Copy_Interface {

	/**
	 * Title shown to the customer on the request form.
	 *
	 * @return string
	 */
	public function get_customer_label();

	/**
	 * What the customer should expect once the request is approved.
	 *
	 * @return string
	 */
	public function get_customer_description();
}
