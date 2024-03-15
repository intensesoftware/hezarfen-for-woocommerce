<?php
/**
 * Hezarfen Settings Tab
 * 
 * @package Hezarfen\Inc\Admin\Settings
 */

defined( 'ABSPATH' ) || exit();

use Hezarfen\Inc\Data\PostMetaEncryption;
use Hezarfen\Inc\Helper;

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
	 * Get own sections.
	 *
	 * @return array<string, string>
	 */
	protected function get_own_sections() {
		$sections = array(
			''              => __( 'General', 'hezarfen-for-woocommerce' ),
			'encryption'    => __( 'Encryption', 'hezarfen-for-woocommerce' ),
			'checkout_page' => __( 'Checkout Page Settings', 'hezarfen-for-woocommerce' ),
		);

		// if checkout field is active, show the section.
		if ( Helper::is_show_tax_fields() ) {
			$sections['checkout_tax'] = __( 'Checkout Tax Fields', 'hezarfen-for-woocommerce' );
		}

		return $sections;
	}

	/**
	 * Get settings for the default(General) section.
	 *
	 * @return array<array<string, string>>
	 */
	protected function get_settings_for_default_section() {
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
				'title'   => __(
					'Hide postcode fields in My Account > Address pages?',
					'hezarfen-for-woocommerce'
				),
				'type'    => 'checkbox',
				'desc'    => '',
				'id'      => 'hezarfen_hide_my_account_postcode_fields',
				'default' => 'no',
			),
		);

		$fields = apply_filters( 'hezarfen_general_settings', $fields );

		$fields[] = array(
			'type' => 'sectionend',
			'id'   => 'hezarfen_general_settings_section_end',
		);

		return $fields;
	}

	/**
	 * Get settings for the Encryption section.
	 *
	 * @return array<array<string, string>>
	 */
	protected function get_settings_for_encryption_section() {
		$fields = array();

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

		return apply_filters( 'hezarfen_checkout_encryption_settings', $fields );
	}

	/**
	 * Get settings for the Checkout Page section.
	 *
	 * @return array<array<string, string>>
	 */
	protected function get_settings_for_checkout_page_section() {
		$cfe_plugin_active = Hezarfen\Inc\Helper::is_cfe_plugin_active();
		if ( $cfe_plugin_active ) {
			$warning_msg = __( 'Disable the Checkout Field Editor for WooCommerce plugin to use this setting.', 'hezarfen-for-woocommerce' );
			$custom_attr = array( 'disabled' => 'disabled' );
		}

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
				'title'             => esc_html__(
					'Hide postcode fields?',
					'hezarfen-for-woocommerce'
				),
				'type'              => 'checkbox',
				'desc'              => $warning_msg ?? '',
				'id'                => 'hezarfen_hide_checkout_postcode_fields',
				'default'           => 'no',
				'custom_attributes' => $custom_attr ?? array(),
			),
			array(
				'title'             => esc_html__(
					'Auto sort fields in checkout form?',
					'hezarfen-for-woocommerce'
				),
				'type'              => 'checkbox',
				'desc'              => $warning_msg ?? '',
				'id'                => 'hezarfen_checkout_fields_auto_sort',
				'default'           => 'no',
				'custom_attributes' => $custom_attr ?? array(),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'hezarfen_checkout_settings_section_end',
			),
		);

		return apply_filters( 'hezarfen_checkout_settings', $fields );
	}

	/**
	 * Get settings for the Tax Fields section.
	 *
	 * @return array<array<string, string>>
	 */
	protected function get_settings_for_checkout_tax_section() {
		if ( Helper::is_show_tax_fields() ) {
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
							'You can update the checkout Tax fields. Note: T.C. number field requires encryption feature. If you do not activate the encryption feature, T.C. number field does not appear on the checkout.',
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
		} else {
			global $hide_save_button;

			$hide_save_button = true;

			$settings = apply_filters( 'hezarfen_checkout_tax_settings', array() );
		}

		return $settings;
	}

	/**
	 * Output the settings
	 *
	 * @since 1.0
	 * 
	 * @return void
	 */
	public function output() {
		global $current_section;
		global $hide_save_button;

		$post_meta_encryption = new PostMetaEncryption();

		if ( 'encryption' == $current_section && $post_meta_encryption->is_encryption_key_generated() ) {
			$hide_save_button = true;

			// is key generated and placed to the wp-config.php?
			$health_check_status = $post_meta_encryption->health_check();

			// is key correct and is it equal to the key that generated first time?
			$test_the_key = $post_meta_encryption->test_the_encryption_key();

			require 'views/encryption.php';
		} else {
			$settings = $this->get_settings_for_section( $current_section );
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

		$settings = $this->get_settings_for_section( $current_section );
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
