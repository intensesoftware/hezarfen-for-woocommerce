<?php
/**
 * Contains the Returns_Schema class.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

defined( 'ABSPATH' ) || exit();

/**
 * Owns the returns database schema: table names, DDL and upgrades.
 *
 * The schema is versioned on its own option rather than on the plugin
 * version, so the tables land on sites that update the plugin without a
 * version bump reaching Hezarfen_Install's guard clause.
 */
class Returns_Schema {

	const DB_VERSION     = '1.3.0';
	const VERSION_OPTION = 'hezarfen_returns_db_version';

	const TABLE_RETURNS = 'hezarfen_returns';
	const TABLE_ITEMS   = 'hezarfen_return_items';
	const TABLE_EVENTS  = 'hezarfen_return_events';

	/**
	 * Prefixed name of one of the module's tables.
	 *
	 * @param string $table One of the TABLE_* constants.
	 *
	 * @return string
	 */
	public static function table( $table ) {
		global $wpdb;

		return $wpdb->prefix . $table;
	}

	/**
	 * Creates or upgrades the tables when the stored schema version is
	 * behind. Safe to call on every request — it costs a single option
	 * read once the schema is current.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		if ( version_compare( (string) get_option( self::VERSION_OPTION, '0' ), self::DB_VERSION, '>=' ) ) {
			return;
		}

		self::install();
	}

	/**
	 * Runs dbDelta for every table and stores the schema version.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		foreach ( self::get_table_definitions( $charset_collate ) as $sql ) {
			dbDelta( $sql );
		}

		self::upgrade_return_number_index();

		update_option( self::VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Turns the pre-1.1.0 plain index on `return_number` into a unique one.
	 *
	 * `dbDelta` adds missing indexes but never rewrites an existing one, so
	 * a site created on 1.0.0 would keep the non-unique key — and with it
	 * the double-submit window where two requests share a reference. The
	 * swap runs as a single ALTER so the column is never left unindexed,
	 * and is skipped when duplicates already exist rather than failing
	 * loudly on every request.
	 *
	 * @return void
	 */
	private static function upgrade_return_number_index() {
		global $wpdb;

		$table = self::table( self::TABLE_RETURNS );

		// The table name is built from $wpdb->prefix and cannot be bound as a
		// parameter; none of these statements carry user input. Schema work is
		// what this class is for.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$index = $wpdb->get_row( "SHOW INDEX FROM {$table} WHERE Key_name = 'return_number'", ARRAY_A );

		if ( ! $index || ! (int) $index['Non_unique'] ) {
			return;
		}

		$duplicates = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ( SELECT return_number FROM {$table} GROUP BY return_number HAVING COUNT(*) > 1 ) AS d" );

		if ( $duplicates ) {
			return;
		}

		$wpdb->query( "ALTER TABLE {$table} DROP INDEX return_number, ADD UNIQUE KEY return_number (return_number)" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * The DDL statements, one per table.
	 *
	 * `dbDelta` is whitespace sensitive: two spaces after PRIMARY KEY and
	 * one field per line are required for it to diff correctly.
	 *
	 * @param string $charset_collate Charset/collation clause.
	 *
	 * @return string[]
	 */
	private static function get_table_definitions( $charset_collate ) {
		$returns = self::table( self::TABLE_RETURNS );
		$items   = self::table( self::TABLE_ITEMS );
		$events  = self::table( self::TABLE_EVENTS );

		$definitions = array();

		$definitions[] = "CREATE TABLE {$returns} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			return_number varchar(32) NOT NULL DEFAULT '',
			order_id bigint(20) unsigned NOT NULL,
			customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			customer_email varchar(190) NOT NULL DEFAULT '',
			status varchar(32) NOT NULL DEFAULT 'pending',
			shipping_method varchar(32) NOT NULL DEFAULT '',
			courier varchar(64) NOT NULL DEFAULT '',
			tracking_number varchar(100) NOT NULL DEFAULT '',
			pickup_date varchar(10) NOT NULL DEFAULT '',
			return_address_id varchar(64) NOT NULL DEFAULT '',
			pickup_address text NULL,
			customer_note text NULL,
			refund_amount decimal(19,4) NOT NULL DEFAULT 0.0000,
			currency varchar(10) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY customer_id (customer_id),
			KEY status (status),
			KEY created_at (created_at),
			KEY customer_email (customer_email),
			UNIQUE KEY return_number (return_number)
		) {$charset_collate};";

		$definitions[] = "CREATE TABLE {$items} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			return_id bigint(20) unsigned NOT NULL,
			order_item_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_name varchar(255) NOT NULL DEFAULT '',
			sku varchar(100) NOT NULL DEFAULT '',
			quantity int(11) NOT NULL DEFAULT 0,
			line_total decimal(19,4) NOT NULL DEFAULT 0.0000,
			reason_key varchar(64) NOT NULL DEFAULT '',
			reason_note text NULL,
			PRIMARY KEY  (id),
			KEY return_id (return_id),
			KEY order_item_id (order_item_id)
		) {$charset_collate};";

		$definitions[] = "CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			return_id bigint(20) unsigned NOT NULL,
			type varchar(32) NOT NULL DEFAULT 'note',
			actor_type varchar(20) NOT NULL DEFAULT 'system',
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			actor_name varchar(190) NOT NULL DEFAULT '',
			from_status varchar(32) NOT NULL DEFAULT '',
			to_status varchar(32) NOT NULL DEFAULT '',
			message text NULL,
			is_customer_visible tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY return_id (return_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		return $definitions;
	}
}
