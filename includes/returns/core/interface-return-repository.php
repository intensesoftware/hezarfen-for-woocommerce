<?php
/**
 * Contains the Return_Repository_Interface contract.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

defined( 'ABSPATH' ) || exit();

/**
 * Persistence contract for return requests.
 *
 * The service layer depends on this interface rather than on the wpdb
 * implementation, which keeps the business rules storage agnostic and
 * testable with an in-memory double.
 */
interface Return_Repository_Interface {

	/**
	 * Inserts or updates a request together with its lines.
	 *
	 * @param Return_Request $request Request to persist.
	 *
	 * @return int|\WP_Error Row ID on success.
	 */
	public function save( $request );

	/**
	 * Finds a request by row ID.
	 *
	 * @param int $id Row ID.
	 *
	 * @return Return_Request|null
	 */
	public function get( $id );

	/**
	 * Queries requests.
	 *
	 * Supported arguments: order_id, customer_id, customer_email, status
	 * (string or array), search, orderby, order, limit, offset.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 *
	 * @return Return_Request[]
	 */
	public function query( $args = array() );

	/**
	 * Counts requests matching the same arguments accepted by query().
	 *
	 * @param array<string, mixed> $args Query arguments.
	 *
	 * @return int
	 */
	public function count( $args = array() );

	/**
	 * Counts requests grouped by status.
	 *
	 * @param array<string, mixed> $args Query arguments, minus `status`.
	 *
	 * @return array<string, int> Counts keyed by status.
	 */
	public function count_by_status( $args = array() );

	/**
	 * Total quantity already requested per order line for an order.
	 *
	 * Requests in a closed-negative state (rejected, cancelled) do not
	 * consume quantity, so their lines are excluded by default.
	 *
	 * @param int           $order_id Order ID.
	 * @param string[]|null $statuses Count only requests in these statuses;
	 *                                null keeps the default behaviour.
	 *
	 * @return array<int, int> Quantities keyed by order item ID.
	 */
	public function get_requested_quantities( $order_id, $statuses = null );
}
