<?php
/**
 * Contract Validator
 *
 * @package Hezarfen\MSS
 */

namespace Hezarfen\Inc\Contracts\Core;

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
		$settings = get_option( 'hezarfen_mss_settings', array() );
		$contracts = isset( $settings['contracts'] ) ? $settings['contracts'] : array();
		
		if ( empty( $contracts ) ) {
			return;
		}
		
		$hidden_contracts = isset( $settings['gosterilmeyecek_sozlesmeler'] ) 
			? $settings['gosterilmeyecek_sozlesmeler'] 
			: array();

		foreach ( $contracts as $contract ) {
			// Skip disabled contracts
			if ( empty( $contract['enabled'] ) ) {
				continue;
			}
			
			// Skip contracts without templates
			if ( empty( $contract['template_id'] ) ) {
				continue;
			}
			
			// Skip validation for hidden contracts (by contract ID)
			if ( in_array( $contract['id'], $hidden_contracts, true ) ) {
				continue;
			}

			$checkbox_name = "contract_{$contract['id']}_checkbox";
			
			if ( empty( $_POST[ $checkbox_name ] ) ) {
				$error_message = sprintf(
					__( '%s sözleşmesini kabul etmelisiniz.', 'hezarfen-for-woocommerce' ),
					esc_html( $contract['name'] )
				);
				
				wc_add_notice( $error_message, 'error' );
			}
		}
	}

}