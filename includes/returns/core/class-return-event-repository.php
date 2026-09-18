<?php
/**
 * Contains the Return_Event_Repository.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

defined( 'ABSPATH' ) || exit();

/**
 * Append-only store for return timeline entries.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 */
class Return_Event_Repository {

	/**
	 * Appends an entry to a request's timeline.
	 *
	 * @param Return_Event $event Entry to store.
	 *
	 * @return int Row ID, zero on failure.
	 */
	public function add( $event ) {
		global $wpdb;

		$inserted = $wpdb->insert( Returns_Schema::table( Returns_Schema::TABLE_EVENTS ), $event->to_row() );

		if ( false === $inserted ) {
			return 0;
		}

		$id = (int) $wpdb->insert_id;

		/**
		 * Fires after a timeline entry has been stored.
		 *
		 * @param int          $id    Row ID of the new entry.
		 * @param Return_Event $event The entry.
		 */
		do_action( 'hezarfen_returns_event_added', $id, $event );

		return $id;
	}

	/**
	 * Loads the timeline of a request, oldest first.
	 *
	 * @param int  $return_id       Parent request ID.
	 * @param bool $customer_only   Whether to hide internal entries.
	 *
	 * @return Return_Event[]
	 */
	public function get_for_return( $return_id, $customer_only = false ) {
		global $wpdb;

		$return_id = (int) $return_id;

		if ( ! $return_id ) {
			return array();
		}

		$table = Returns_Schema::table( Returns_Schema::TABLE_EVENTS );

		if ( $customer_only ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE return_id = %d AND is_customer_visible = 1 ORDER BY id ASC", $return_id );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE return_id = %d ORDER BY id ASC", $return_id );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		$events = array();

		foreach ( (array) $rows as $row ) {
			$events[] = new Return_Event( $row );
		}

		return $events;
	}
}
