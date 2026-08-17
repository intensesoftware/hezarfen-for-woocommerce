<?php
/**
 * Contains the Return_Shipping_Method_Interface contract.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Shipping;

use Hezarfen\Inc\Returns\Core\Return_Request;

defined( 'ABSPATH' ) || exit();

/**
 * Describes how the goods travel back to the merchant.
 *
 * Two methods ship with the module: the customer arranges the shipment
 * themselves, or Kargokit produces a return label automatically. A store's
 * own carrier contract plugs in through the registry filter, which is why
 * approval never calls a concrete method directly.
 */
interface Return_Shipping_Method_Interface {

	/**
	 * Stable identifier persisted on the request.
	 *
	 * @return string
	 */
	public function get_key();

	/**
	 * Merchant facing label.
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Short explanation shown next to the label in the settings screen.
	 *
	 * @return string
	 */
	public function get_description();

	/**
	 * Whether the method is configured well enough to be selectable.
	 *
	 * @return bool
	 */
	public function is_available();

	/**
	 * Whether the customer is expected to supply the tracking number.
	 *
	 * @return bool
	 */
	public function requires_customer_tracking();

	/**
	 * Runs when a request reaches the approved status.
	 *
	 * Implementations that produce a label write the resulting tracking
	 * details onto the request and leave persistence to the caller.
	 *
	 * @param Return_Request $request Approved request.
	 *
	 * @return true|\WP_Error True on success, otherwise a non fatal error
	 *                        the caller records on the timeline.
	 */
	public function handle_approved( $request );

	/**
	 * Customer facing instructions for shipping the parcel back.
	 *
	 * @param Return_Request $request The request.
	 *
	 * @return string HTML allowed by wp_kses_post().
	 */
	public function get_customer_instructions( $request );
}
