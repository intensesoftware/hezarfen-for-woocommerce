<?php
/**
 * Contains the wpdb backed Return_Repository.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

defined( 'ABSPATH' ) || exit();

/**
 * Stores return requests in the module's custom tables.
 *
 * Every SQL statement in the module lives here. Callers hand over and
 * receive entities; they never see a row array.
 *
 * Table names are interpolated throughout: they are built from
 * $wpdb->prefix and MySQL does not accept them as bound parameters. Every
 * value still goes through $wpdb->prepare().
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 */
class Return_Repository implements Return_Repository_Interface {

	/**
	 * Statuses that release the quantity they had reserved on the order.
	 *
	 * @var string[]
	 */
	private $releasing_statuses = array( Return_Status::REJECTED, Return_Status::CANCELLED );

	/**
	 * Inserts or updates a request together with its lines.
	 *
	 * @param Return_Request $request Request to persist.
	 *
	 * @return int|\WP_Error Row ID on success.
	 */
	public function save( $request ) {
		global $wpdb;

		$table = Returns_Schema::table( Returns_Schema::TABLE_RETURNS );
		$row   = $request->to_row();
		$now   = current_time( 'mysql' );

		$row['updated_at'] = $now;

		if ( $request->get_id() ) {
			$result = $wpdb->update( $table, $row, array( 'id' => $request->get_id() ) );

			if ( false === $result ) {
				return new \WP_Error( 'hezarfen_returns_db_error', __( 'İade talebi güncellenemedi.', 'hezarfen-for-woocommerce' ) );
			}
		} else {
			$row['created_at'] = $now;

			$result = $wpdb->insert( $table, $row );

			if ( false === $result ) {
				return new \WP_Error( 'hezarfen_returns_db_error', __( 'İade talebi kaydedilemedi.', 'hezarfen-for-woocommerce' ) );
			}

			$request->set_id( (int) $wpdb->insert_id );
		}

		$this->save_items( $request );

		return $request->get_id();
	}

	/**
	 * Replaces the persisted lines of a request with the in-memory ones.
	 *
	 * Lines are immutable once the request exists, so a full replace is
	 * both correct and cheaper than diffing.
	 *
	 * @param Return_Request $request Parent request.
	 *
	 * @return void
	 */
	private function save_items( $request ) {
		global $wpdb;

		$items = $request->get_items();

		if ( ! $items ) {
			return;
		}

		$table = Returns_Schema::table( Returns_Schema::TABLE_ITEMS );

		$wpdb->delete( $table, array( 'return_id' => $request->get_id() ), array( '%d' ) );

		foreach ( $items as $item ) {
			$item->set_return_id( $request->get_id() );
			$wpdb->insert( $table, $item->to_row() );
		}
	}

	/**
	 * Finds a request by row ID.
	 *
	 * @param int $id Row ID.
	 *
	 * @return Return_Request|null
	 */
	public function get( $id ) {
		global $wpdb;

		$id = (int) $id;

		if ( ! $id ) {
			return null;
		}

		$table = Returns_Schema::table( Returns_Schema::TABLE_RETURNS );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * Queries requests.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 *
	 * @return Return_Request[]
	 */
	public function query( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'orderby' => 'created_at',
				'order'   => 'DESC',
				'limit'   => 20,
				'offset'  => 0,
			)
		);

		$table  = Returns_Schema::table( Returns_Schema::TABLE_RETURNS );
		$where  = $this->build_where( $args );
		$order  = $this->build_order_by( $args );
		$limit  = max( 0, (int) $args['limit'] );
		$offset = max( 0, (int) $args['offset'] );

		$sql = "SELECT * FROM {$table} {$where} {$order}";

		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		if ( ! $rows ) {
			return array();
		}

		$requests = array();

		foreach ( $rows as $row ) {
			$requests[] = $this->hydrate( $row );
		}

		return $requests;
	}

	/**
	 * Counts requests matching the given arguments.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 *
	 * @return int
	 */
	public function count( $args = array() ) {
		global $wpdb;

		$table = Returns_Schema::table( Returns_Schema::TABLE_RETURNS );
		$where = $this->build_where( $args );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The WHERE clause is assembled from prepared fragments in build_where().
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );
	}

	/**
	 * Counts requests grouped by status.
	 *
	 * @param array<string, mixed> $args Query arguments, minus `status`.
	 *
	 * @return array<string, int>
	 */
	public function count_by_status( $args = array() ) {
		global $wpdb;

		unset( $args['status'] );

		$table = Returns_Schema::table( Returns_Schema::TABLE_RETURNS );
		$where = $this->build_where( $args );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The WHERE clause is assembled from prepared fragments in build_where().
		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} {$where} GROUP BY status", ARRAY_A );

		$counts = array();

		foreach ( (array) $rows as $row ) {
			$counts[ $row['status'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Total quantity already requested per order line for an order.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array<int, int>
	 */
	public function get_requested_quantities( $order_id ) {
		global $wpdb;

		$order_id = (int) $order_id;

		if ( ! $order_id ) {
			return array();
		}

		$returns = Returns_Schema::table( Returns_Schema::TABLE_RETURNS );
		$items   = Returns_Schema::table( Returns_Schema::TABLE_ITEMS );

		$excluded     = $this->releasing_statuses;
		$placeholders = implode( ', ', array_fill( 0, count( $excluded ), '%s' ) );

		$sql = $wpdb->prepare(
			"SELECT i.order_item_id, SUM(i.quantity) AS total
			FROM {$items} AS i
			INNER JOIN {$returns} AS r ON r.id = i.return_id
			WHERE r.order_id = %d AND r.status NOT IN ( {$placeholders} )
			GROUP BY i.order_item_id",
			array_merge( array( $order_id ), $excluded )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		$quantities = array();

		foreach ( (array) $rows as $row ) {
			$quantities[ (int) $row['order_item_id'] ] = (int) $row['total'];
		}

		return $quantities;
	}

	/**
	 * Builds a request entity, with its lines, from a DB row.
	 *
	 * @param array<string, mixed> $row Row from the returns table.
	 *
	 * @return Return_Request
	 */
	private function hydrate( $row ) {
		$request = new Return_Request( $row );
		$request->set_items( $this->get_items( $request->get_id() ) );

		return $request;
	}

	/**
	 * Loads the lines of a request.
	 *
	 * @param int $return_id Parent request ID.
	 *
	 * @return Return_Item[]
	 */
	private function get_items( $return_id ) {
		global $wpdb;

		$table = Returns_Schema::table( Returns_Schema::TABLE_ITEMS );

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE return_id = %d ORDER BY id ASC", (int) $return_id ), ARRAY_A );

		$items = array();

		foreach ( (array) $rows as $row ) {
			$items[] = new Return_Item( $row );
		}

		return $items;
	}

	/**
	 * Builds the WHERE clause shared by query(), count() and
	 * count_by_status().
	 *
	 * @param array<string, mixed> $args Query arguments.
	 *
	 * @return string
	 */
	private function build_where( $args ) {
		global $wpdb;

		$clauses = array( '1=1' );

		if ( ! empty( $args['order_id'] ) ) {
			$clauses[] = $wpdb->prepare( 'order_id = %d', (int) $args['order_id'] );
		}

		if ( ! empty( $args['customer_id'] ) ) {
			$clauses[] = $wpdb->prepare( 'customer_id = %d', (int) $args['customer_id'] );
		}

		if ( ! empty( $args['customer_email'] ) ) {
			$clauses[] = $wpdb->prepare( 'customer_email = %s', (string) $args['customer_email'] );
		}

		if ( ! empty( $args['status'] ) ) {
			$statuses = array_values( array_filter( array_map( 'strval', (array) $args['status'] ) ) );

			if ( $statuses ) {
				// The number of placeholders depends on how many statuses
				// were asked for, so the list is built first and the values
				// are still bound by prepare().
				$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholder count is dynamic; the values are bound below.
				$clauses[] = $wpdb->prepare( "status IN ( {$placeholders} )", $statuses );
			}
		}

		if ( ! empty( $args['search'] ) ) {
			$like      = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$clauses[] = $wpdb->prepare(
				'( return_number LIKE %s OR customer_email LIKE %s OR tracking_number LIKE %s OR order_id = %d )',
				$like,
				$like,
				$like,
				(int) $args['search']
			);
		}

		return 'WHERE ' . implode( ' AND ', $clauses );
	}

	/**
	 * Builds a safe ORDER BY clause from an allow-list.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 *
	 * @return string
	 */
	private function build_order_by( $args ) {
		$allowed = array( 'id', 'created_at', 'updated_at', 'order_id', 'status', 'refund_amount' );
		$orderby = isset( $args['orderby'] ) ? (string) $args['orderby'] : 'created_at';

		if ( ! in_array( $orderby, $allowed, true ) ) {
			$orderby = 'created_at';
		}

		$order = isset( $args['order'] ) && 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';

		return "ORDER BY {$orderby} {$order}";
	}
}
