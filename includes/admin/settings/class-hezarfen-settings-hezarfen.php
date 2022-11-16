<?php
/**
 * Hezarfen Settings Tab
 * 
 * @package Hezarfen\Inc\Admin\Settings
 */

defined( 'ABSPATH' ) || exit();

use Hezarfen\Inc\Data\PostMetaEncryption;
use Hezarfen\Inc\Data\ServiceCredentialEncryption;

if ( class_exists( 'Hezarfen_Settings_Hezarfen', false ) ) {
	return new Hezarfen_Settings_Hezarfen();
}

/**
 * Hezarfen_Settings_Hezarfen the class adds a new setting page on WC settings page.
 */
class Hezarfen_Settings_Hezarfen extends WC_Settings_Page {


	/**
	 * Hezarfen_Settings_Hezarfen constructor.
	 */
	public function __construct() {
		$this->id    = 'hezarfen';
		$this->label = 'Hezarfen';

		parent::__construct();
	}
	
	/**
	 * The current value of the Should Show Hezarfen Tax Settings?
	 *
	 * @return bool
	 */
	private function show_hezarfen_tax_fields() {
		return ( get_option( 'hezarfen_show_hezarfen_checkout_tax_fields' ) == 'yes' ) ? true : false;
	}

	/**
	 * Get sections
	 *
	 * @return array
	 */
	public function get_sections() {
		$sections = array(
			'general'       => __( 'General', 'hezarfen-for-woocommerce' ),
			'encryption'    => __( 'Encryption', 'hezarfen-for-woocommerce' ),
			'checkout-page' => __( 'Checkout Page Settings', 'hezarfen-for-woocommerce' ),
		);

		// if checkout field is active, show the section.
		if ( $this->show_hezarfen_tax_fields() ) {
			$sections['checkout_tax'] = __( 'Checkout Tax Fields', 'hezarfen-for-woocommerce' );
		}

		return apply_filters(
			'woocommerce_get_sections_' . $this->id,
			$sections
		);
	}

	/**
	 * Get setting fields.
	 *
	 * @param string $current_section the current setting section.
	 * @return array
	 */
	public function get_settings( $current_section = '' ) {
		if ( 'checkout_tax' == $current_section ) {
			$settings = apply_filters(
				'hezarfen_checkout_tax_settings',
				array(
					array(
						'title' => __(
							'Checkout Tax Fields Settings',
							'hezarfen-for-woocommerce'
						),
						'type'  => 'title',
						'desc'  => __(
							'You can update the checkout TAX fields. Note: T.C. number field requires encryption feature. If you do not activate the encryption feature, T.C. number field does not appear on the checkout.',
							'hezarfen-for-woocommerce'
						),
						'id'    => 'hezarfen_checkout_tax_fields_title',
					),

					array(
						'title'   => __(
							'Show T.C. Identity Field on checkout page ',
							'hezarfen-for-woocommerce'
						),
						'type'    => 'checkbox',
						'desc'    => __(
							'T.C. Identity Field optionally shows on checkout field when invoice type selected as person. (T.C. field requires encryption. If encryption is not enabled, this field is not displayed.)',
							'hezarfen-for-woocommerce'
						),
						'id'      => 'hezarfen_checkout_show_TC_identity_field',
						'default' => 'no',
					),

					array(
						'title'   => __(
							'Checkout T.C. Identity Number Fields Required Statuses',
							'hezarfen-for-woocommerce'
						),
						'desc'    => __(
							'Is T.C. Identity Number field required?',
							'hezarfen-for-woocommerce'
						),
						'id'      =>
							'hezarfen_checkout_is_TC_identity_number_field_required',
						'default' => 'no',
						'type'    => 'checkbox',
					),

					array(
						'type' => 'sectionend',
						'id'   => 'hezarfen_checkout_tax_fields_section_end',
					),
				)
			);

			if ( ! $this->show_hezarfen_tax_fields() ) {
				global $hide_save_button;

				$hide_save_button = true;

				$settings = apply_filters( 'hezarfen_checkout_tax_settings', array() );
			}       
		} elseif ( 'encryption' == $current_section ) {
			// if encryption key not generated before, generate a new key.
			if ( ! ( new PostMetaEncryption() )->is_encryption_key_generated() ) {
				// create a new random key.
				$encryption_key = ( new PostMetaEncryption() )->create_random_key();

				$fields = array(
					array(
						'title' => __(
							'Encryption Settings',
							'hezarfen-for-woocommerce'
						),
						'type'  => 'title',
						'desc'  => __(
							'If the T.C. Identity Field is active, an encryption key must be generated. The following encryption key generated will be lost upon saving the form. Please back up the generated encryption key to a secure area, then paste it anywhere in the wp-config.php file. In case of deletion of the hezarfen-encryption-key line from wp-config.php, retrospectively, the orders will be sent to T.C. no values will become unreadable.',
							'hezarfen-for-woocommerce'
						),
						'id'    => 'hezarfen_checkout_encryption_fields_title',
					),
					array(
						'title'   => __(
							'Encryption Key',
							'hezarfen-for-woocommerce'
						),
						'type'    => 'textarea',
						'css'     => 'width:100%;height:60px',
						'default' => sprintf(
							"define( 'HEZARFEN_ENCRYPTION_KEY', '%s' );",
							$encryption_key
						),
						'desc'    => __(
							'Back up the phrase in the box to a safe area, then place it in wp-config.php file.',
							'hezarfen-for-woocommerce'
						),
					),
					array(
						'title'   => __(
							'Encryption Key Confirmation',
							'hezarfen-for-woocommerce'
						),
						'type'    => 'checkbox',
						'desc'    => __(
							'I backed up the key to a secure area and placed it in the wp-config file. In case the encryption key value is deleted from the wp-config.php file, all past orders will be transferred to T.C. I know I cannot access ID data.',
							'hezarfen-for-woocommerce'
						),
						'id'      => 'hezarfen_checkout_encryption_key_confirmation',
						'default' => 'no',
					),
					array(
						'type' => 'sectionend',
						'id'   => 'hezarfen_checkout_encryption_fields_section_end',
					),
				);
			}

			$settings = apply_filters( 'hezarfen_checkout_tax_settings', $fields );
		} elseif ( 'checkout-page' == $current_section ) {
			$fields = array(
				array(
					'title' => esc_html__(
						'Checkout Settings',
						'hezarfen-for-woocommerce'
					),
					'type'  => 'title',
					'desc'  => esc_html__(
						'You can set general checkout settings.',
						'hezarfen-for-woocommerce'
					),
					'id'    => 'hezarfen_checkout_settings_title',
				),
				array(
					'title'   => esc_html__(
						'Hide postcode fields?',
						'hezarfen-for-woocommerce'
					),
					'type'    => 'checkbox',
					'desc'    => '',
					'id'      => 'hezarfen_hide_checkout_postcode_fields',
					'default' => 'no',
				),
				array(
					'title'   => esc_html__(
						'Auto sort fields in checkout form?',
						'hezarfen-for-woocommerce'
					),
					'type'    => 'checkbox',
					'desc'    => '',
					'id'      => 'hezarfen_checkout_fields_auto_sort',
					'default' => 'no',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'hezarfen_checkout_settings_section_end',
				),
			);

			$settings = apply_filters( 'hezarfen_checkout_settings', $fields );
		} else {
			$fields = array(
				array(
					'title' => __(
						'General Settings',
						'hezarfen-for-woocommerce'
					),
					'type'  => 'title',
					'desc'  => __(
						'You can edit the general settings from this page.',
						'hezarfen-for-woocommerce'
					),
					'id'    => 'hezarfen_general_settings_title',
				),
				array(
					'title'   => __(
						'Show hezarfen checkout tax fields?',
						'hezarfen-for-woocommerce'
					),
					'type'    => 'checkbox',
					'desc'    => '',
					'id'      => 'hezarfen_show_hezarfen_checkout_tax_fields',
					'default' => 'no',
				),
				array(
					'title'   => __(
						'Sort address fields in My Account > Address pages?',
						'hezarfen-for-woocommerce'
					),
					'type'    => 'checkbox',
					'desc'    => '',
					'id'      => 'hezarfen_sort_my_account_fields',
					'default' => 'no',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'hezarfen_general_settings_section_end',
				),
			);

			$settings = apply_filters( 'hezarfen_general_settings', $fields );
		}

		return apply_filters(
			'woocommerce_get_settings_' . $this->id,
			$settings,
			$current_section
		);
	}

	/**
	 * Output the settings
	 *
	 * @since 1.0
	 */
	public function output() {
		global $current_section;
		global $hide_save_button;

		if ( 'encryption' == $current_section ) {
			if ( ( new PostMetaEncryption() )->is_encryption_key_generated() ) {
				$hide_save_button = true;

				// is key generated and placed to the wp-config.php?
				$health_check_status = ( new PostMetaEncryption() )->health_check();

				// is key correct and is it equal to the key that generated first time?
				$test_the_key = ( new PostMetaEncryption() )->test_the_encryption_key();

				require 'views/encryption.php';
			} else {
				// load the key geneate view.
				$settings = $this->get_settings( $current_section );
				WC_Admin_Settings::output_fields( $settings );
			}
		} else {
			$settings = $this->get_settings( $current_section );
			WC_Admin_Settings::output_fields( $settings );
		}
	}
	
	/**
	 * Save
	 *
	 * @return false|void
	 */
	public function save() {
		global $current_section;

		// if encryption key generated before, do not continue.
		if (
			'encryption' == $current_section &&
			( ( new PostMetaEncryption() )->health_check() ||
				( new PostMetaEncryption() )->test_the_encryption_key() )
		) {
			return false;
		}

		// if encryption key not placed the wp-config, do not continue.
		if ( ! defined( 'HEZARFEN_ENCRYPTION_KEY' ) && 'encryption' == $current_section ) {
			return false;
		}

		$settings = $this->get_settings( $current_section );
		WC_Admin_Settings::save_fields( $settings );

		if ( 'encryption' == $current_section ) {
			if (
				get_option( 'hezarfen_checkout_encryption_key_confirmation', false ) ==
				'yes'
			) {
				update_option( 'hezarfen_encryption_key_generated', 'yes' );

				if ( ( new PostMetaEncryption() )->health_check() ) {
					// create an encryption tester text.
					( new PostMetaEncryption() )->create_encryption_tester_text();
				}
			}
		}
	}
}

return new Hezarfen_Settings_Hezarfen();
