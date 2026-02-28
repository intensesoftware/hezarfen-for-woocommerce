<?php
/**
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

class Feature_Status {

	const CACHE_KEY = 'hezarfen_benefit';
	const CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * Check if Hezarfen features are actively in use.
	 *
	 * Checks existing order meta & contracts table.
	 * Result cached for 24 hours. Zero external calls.
	 *
	 * @return bool
	 */
	public static function are_features_active() {
		$cached = get_transient( self::CACHE_KEY );

		if ( false !== $cached ) {
			return '1' === $cached;
		}

		$result = self::evaluate();

		set_transient( self::CACHE_KEY, $result ? '1' : '0', self::CACHE_TTL );

		return $result;
	}

	/**
	 * Clear cache and re-evaluate.
	 *
	 * @return bool
	 */
	public static function refresh() {
		delete_transient( self::CACHE_KEY );
		return self::are_features_active();
	}

	/**
	 * Sequential EXISTS checks with early return.
	 * Each EXISTS is O(1) on indexed columns, stops at first hit.
	 *
	 * @return bool
	 */
	private static function evaluate() {
		global $wpdb;

		$meta_table = self::get_meta_table();

		// 1. Check if checkout invoice type has been used
		if ( $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM {$meta_table} WHERE meta_key = %s LIMIT 1",
			'_billing_hez_invoice_type'
		) ) ) {
			return true;
		}

		// 2. Check if any contract has been generated
		$contracts_table = $wpdb->prefix . 'hezarfen_contracts';
		$wpdb->suppress_errors( true );
		$has_contracts = $wpdb->get_var( "SELECT 1 FROM {$contracts_table} LIMIT 1" );
		$wpdb->suppress_errors( false );

		if ( $has_contracts ) {
			return true;
		}

		// 3. Check if any SMS notification has been sent
		if ( $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM {$meta_table} WHERE meta_key LIKE %s LIMIT 1",
			$wpdb->esc_like( '_hezarfen_sms_sent_' ) . '%'
		) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the correct order meta table (HPOS compatible).
	 *
	 * @return string
	 */
	private static function get_meta_table() {
		global $wpdb;

		if (
			class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) &&
			\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
		) {
			return $wpdb->prefix . 'wc_orders_meta';
		}

		return $wpdb->postmeta;
	}
}
