<?php
/**
 * Contract Manager
 *
 * @package Hezarfen\MSS
 */

namespace Hezarfen\Inc\MSS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract_Manager class
 */
class Contract_Manager {

	/**
	 * Option name for storing contracts
	 */
	const OPTION_NAME = 'hezarfen_mss_contracts';

	/**
	 * Get all contracts
	 *
	 * @return array
	 */
	public static function get_contracts() {
		$contracts = get_option( self::OPTION_NAME, array() );
		return isset( $contracts['contracts'] ) ? $contracts['contracts'] : array();
	}

	/**
	 * Get active contracts (enabled and with template assigned)
	 *
	 * @return array
	 */
	public static function get_active_contracts() {
		$contracts = self::get_contracts();
		$active    = array();

		foreach ( $contracts as $contract ) {
			if ( self::is_contract_active( $contract ) ) {
				$active[] = $contract;
			}
		}

		// Sort by display order
		usort( $active, function( $a, $b ) {
			return intval( $a['display_order'] ) - intval( $b['display_order'] );
		});

		return $active;
	}

	/**
	 * Check if contract is active
	 *
	 * @param array $contract Contract data.
	 * @return bool
	 */
	public static function is_contract_active( $contract ) {
		return isset( $contract['enabled'] ) && 
		       $contract['enabled'] && 
		       isset( $contract['template_id'] ) && 
		       $contract['template_id'] > 0;
	}

	/**
	 * Get contract by ID
	 *
	 * @param string $contract_id Contract ID.
	 * @return array|null
	 */
	public static function get_contract( $contract_id ) {
		$contracts = self::get_contracts();
		
		foreach ( $contracts as $contract ) {
			if ( $contract['id'] === $contract_id ) {
				return $contract;
			}
		}

		return null;
	}

	/**
	 * Save contract
	 *
	 * @param array $contract_data Contract data.
	 * @return bool|WP_Error
	 */
	public static function save_contract( $contract_data ) {
		// Validate required fields
		$required_fields = array( 'id', 'name', 'type' );
		foreach ( $required_fields as $field ) {
			if ( empty( $contract_data[ $field ] ) ) {
				return new \WP_Error( 'missing_field', sprintf( 'Missing required field: %s', $field ) );
			}
		}

		// Validate contract type
		if ( ! Contract_Types::type_exists( $contract_data['type'] ) ) {
			return new \WP_Error( 'invalid_type', 'Invalid contract type' );
		}

		$contracts = self::get_contracts();
		$found     = false;

		// Update existing contract or add new one
		for ( $i = 0; $i < count( $contracts ); $i++ ) {
			if ( $contracts[ $i ]['id'] === $contract_data['id'] ) {
				$contracts[ $i ] = array_merge( $contracts[ $i ], $contract_data );
				$contracts[ $i ]['updated_at'] = current_time( 'mysql' );
				$found = true;
				break;
			}
		}

		if ( ! $found ) {
			$contract_data['created_at'] = current_time( 'mysql' );
			$contract_data['updated_at'] = current_time( 'mysql' );
			$contracts[] = $contract_data;
		}

		return self::save_contracts( $contracts );
	}

	/**
	 * Delete contract
	 *
	 * @param string $contract_id Contract ID.
	 * @return bool
	 */
	public static function delete_contract( $contract_id ) {
		$contracts = self::get_contracts();
		$filtered  = array();

		foreach ( $contracts as $contract ) {
			if ( $contract['id'] !== $contract_id ) {
				$filtered[] = $contract;
			}
		}

		return self::save_contracts( $filtered );
	}

	/**
	 * Save all contracts
	 *
	 * @param array $contracts Contracts array.
	 * @return bool
	 */
	private static function save_contracts( $contracts ) {
		return update_option( self::OPTION_NAME, array( 'contracts' => $contracts ) );
	}

	/**
	 * Get contracts by type
	 *
	 * @param string $type Contract type.
	 * @return array
	 */
	public static function get_contracts_by_type( $type ) {
		$contracts = self::get_contracts();
		$filtered  = array();

		foreach ( $contracts as $contract ) {
			if ( $contract['type'] === $type ) {
				$filtered[] = $contract;
			}
		}

		return $filtered;
	}

	/**
	 * Get required contracts
	 *
	 * @return array
	 */
	public static function get_required_contracts() {
		$contracts = self::get_active_contracts();
		$required  = array();

		foreach ( $contracts as $contract ) {
			if ( isset( $contract['required'] ) && $contract['required'] ) {
				$required[] = $contract;
			}
		}

		return $required;
	}

	/**
	 * Duplicate contract
	 *
	 * @param string $contract_id Contract ID to duplicate.
	 * @return bool|WP_Error
	 */
	public static function duplicate_contract( $contract_id ) {
		$original = self::get_contract( $contract_id );
		if ( ! $original ) {
			return new \WP_Error( 'contract_not_found', 'Contract not found' );
		}

		$duplicate = $original;
		$duplicate['id'] = uniqid( 'contract_' );
		$duplicate['name'] = $duplicate['name'] . ' (Copy)';
		$duplicate['enabled'] = false; // Disable by default

		return self::save_contract( $duplicate );
	}
}