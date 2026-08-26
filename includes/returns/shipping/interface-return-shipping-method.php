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
	 * Whether the return shipment is booked by the customer from their
	 * account, after the merchant approved the request.
	 *
	 * A carrier that collects the parcel needs a pickup day the customer
	 * will actually be home on, so the booking cannot be made for them at
	 * approval time. Methods that answer true are asked for their options
	 * and then for a booking; the ones that answer false are done as soon
	 * as handle_approved() ran.
	 *
	 * @return bool
	 */
	public function requires_customer_booking();

	/**
	 * Whether the method needs an address to collect the parcel from.
	 *
	 * A courier that comes to the door has to be told where the door is,
	 * and the order's shipping address is only a guess at it. Methods that
	 * answer true get a confirmed address captured from the customer; the
	 * ones that answer false are never asked for one, so a store that lets
	 * customers post the parcel themselves shows no address form at all.
	 *
	 * @return bool
	 */
	public function requires_pickup_address();

	/**
	 * The choices the customer picks from when booking, keyed by the value
	 * that comes back in the form.
	 *
	 * Carrier calls belong here rather than in the caller, which is why a
	 * lookup failure comes back as a WP_Error the account page renders
	 * instead of the form.
	 *
	 * @param Return_Request $request Approved request.
	 *
	 * @return array<string, string>|\WP_Error Option label keyed by value.
	 */
	public function get_booking_options( $request );

	/**
	 * Books the return shipment for the customer's chosen option.
	 *
	 * Implementations validate the choice against their own current
	 * options — the value arrives from a form and may be stale or forged —
	 * write the resulting tracking details onto the request, and leave
	 * persistence to the caller.
	 *
	 * @param Return_Request $request Approved request.
	 * @param string         $choice  Value of the picked option.
	 *
	 * @return true|\WP_Error
	 */
	public function book( $request, $choice );

	/**
	 * Cancels a booking the customer already made.
	 *
	 * A customer who picked the wrong day needs a way out that also frees
	 * the slot at the carrier — dropping the tracking number locally would
	 * leave a courier driving to their door. Implementations clear the
	 * booking details off the request and leave persistence to the caller.
	 *
	 * @param Return_Request $request Booked request.
	 *
	 * @return true|\WP_Error
	 */
	public function cancel_booking( $request );

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
