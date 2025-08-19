<?php
/**
 * Contract Validator
 *
 * @package Hezarfen\MSS
 */

namespace Hezarfen\Inc\MSS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract_Validator class
 */
class Contract_Validator {

	/**
	 * Validate contract checkboxes on checkout
	 *
	 * @return void
	 */
	public static function validate_checkout_contracts() {
		$required_contracts = Contract_Manager::get_required_contracts();
		$settings          = get_option( 'hezarfen_mss_settings', array() );
		
		$hidden_contracts = isset( $settings['gosterilmeyecek_sozlesmeler'] ) 
			? $settings['gosterilmeyecek_sozlesmeler'] 
			: array();

		foreach ( $required_contracts as $contract ) {
			// Skip validation for hidden contracts
			if ( in_array( $contract['type'], $hidden_contracts, true ) ) {
				continue;
			}

			$checkbox_name = "contract_{$contract['id']}_checkbox";
			
			if ( empty( $_POST[ $checkbox_name ] ) ) {
				$error_message = sprintf(
					__( 'You must agree to the %s to proceed.', 'hezarfen-for-woocommerce' ),
					$contract['name']
				);
				
				wc_add_notice( $error_message, 'error' );
			}
		}
	}

	/**
	 * Validate contract data before saving
	 *
	 * @param array $contract_data Contract data to validate.
	 * @return array|WP_Error Validated data or error.
	 */
	public static function validate_contract_data( $contract_data ) {
		$errors = array();

		// Required fields
		$required_fields = array(
			'name' => __( 'Contract name is required.', 'hezarfen-for-woocommerce' ),
			'type' => __( 'Contract type is required.', 'hezarfen-for-woocommerce' ),
		);

		foreach ( $required_fields as $field => $error_message ) {
			if ( empty( $contract_data[ $field ] ) ) {
				$errors[] = $error_message;
			}
		}

		// Validate contract type
		if ( ! empty( $contract_data['type'] ) && ! Contract_Types::type_exists( $contract_data['type'] ) ) {
			$errors[] = __( 'Invalid contract type selected.', 'hezarfen-for-woocommerce' );
		}

		// Validate content
		if ( isset( $contract_data['content'] ) ) {
			$contract_data['content'] = wp_kses_post( $contract_data['content'] );
		}

		// Validate display order
		if ( isset( $contract_data['display_order'] ) ) {
			$display_order = intval( $contract_data['display_order'] );
			if ( $display_order < 0 ) {
				$errors[] = __( 'Display order must be a positive number.', 'hezarfen-for-woocommerce' );
			}
			$contract_data['display_order'] = $display_order;
		}

		// Sanitize and validate boolean fields
		$boolean_fields = array( 'enabled', 'required' );
		foreach ( $boolean_fields as $field ) {
			if ( isset( $contract_data[ $field ] ) ) {
				$contract_data[ $field ] = (bool) $contract_data[ $field ];
			}
		}

		// Sanitize text fields
		$text_fields = array( 'name', 'custom_label' );
		foreach ( $text_fields as $field ) {
			if ( isset( $contract_data[ $field ] ) ) {
				$contract_data[ $field ] = sanitize_text_field( $contract_data[ $field ] );
			}
		}

		// Return errors if any
		if ( ! empty( $errors ) ) {
			return new \WP_Error( 'validation_failed', implode( ' ', $errors ), $errors );
		}

		return $contract_data;
	}

	/**
	 * Check if contract name is unique
	 *
	 * @param string $name Contract name.
	 * @param string $exclude_id Contract ID to exclude from check.
	 * @return bool
	 */
	public static function is_contract_name_unique( $name, $exclude_id = '' ) {
		$contracts = Contract_Manager::get_contracts();
		
		foreach ( $contracts as $contract ) {
			if ( $contract['id'] !== $exclude_id && 
			     strcasecmp( $contract['name'], $name ) === 0 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate contract before deletion
	 *
	 * @param string $contract_id Contract ID.
	 * @return bool|WP_Error
	 */
	public static function can_delete_contract( $contract_id ) {
		$contract = Contract_Manager::get_contract( $contract_id );
		
		if ( ! $contract ) {
			return new \WP_Error( 'contract_not_found', __( 'Contract not found.', 'hezarfen-for-woocommerce' ) );
		}

		// Add any additional validation rules here
		// For example, check if contract is referenced in orders, etc.

		return true;
	}
}