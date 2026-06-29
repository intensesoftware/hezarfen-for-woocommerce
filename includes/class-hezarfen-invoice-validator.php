<?php
/**
 * Shared validation rules for the Hezarfen invoice/tax fields.
 *
 * Both the classic checkout (`Checkout::validate_posted_data()`) and the
 * block-based checkout (`Hezarfen\Inc\Blocks\Hezarfen_Store_API`) accept the
 * same data, so the actual rules (T.C. number length, tax number length, masked
 * fallback) live here to avoid the two paths drifting apart over time.
 *
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

/**
 * Hezarfen_Invoice_Validator
 */
class Hezarfen_Invoice_Validator {

	/**
	 * Value stored for the T.C. identity number when encryption is unavailable,
	 * so the real number is never persisted in clear text.
	 */
	const MASKED_VALUE = '******';

	/**
	 * Whether the given value is a valid T.C. identity number (11 digits).
	 *
	 * @param string $tc_number Raw, decrypted T.C. identity number.
	 *
	 * @return bool
	 */
	public static function is_valid_tc_number( $tc_number ) {
		return 11 === strlen( (string) $tc_number ) && is_numeric( $tc_number );
	}

	/**
	 * Whether the given value is a valid Turkish tax number (10 or 11 digits).
	 *
	 * @param string $tax_number Raw tax number.
	 *
	 * @return bool
	 */
	public static function is_valid_tax_number( $tax_number ) {
		return is_numeric( $tax_number ) && in_array( strlen( (string) $tax_number ), array( 10, 11 ), true );
	}
}
