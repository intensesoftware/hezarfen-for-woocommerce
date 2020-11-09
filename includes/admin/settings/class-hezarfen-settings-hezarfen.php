<?php
/**
 * Hezarfen Settings Tab
 *
 */

defined('ABSPATH') || exit();

use Hezarfen\Inc\Data\PostMetaEncryption;
use Hezarfen\Inc\Data\ServiceCredentialEncryption;

if (class_exists('Hezarfen_Settings_Hezarfen', false)) {
	return new Hezarfen_Settings_Hezarfen();
}

class Hezarfen_Settings_Hezarfen extends WC_Settings_Page
{

	/**
	 * Hezarfen_Settings_Hezarfen constructor.
	 */
	public function __construct()
	{
		$this->id = 'hezarfen';
		$this->label = 'Hezarfen';

		parent::__construct();

		add_filter('woocommerce_admin_settings_sanitize_option_hezarfen_mahalle_io_api_key', array( $this, 'override_the_mahalle_io_apikey' ), 10, 1);
	}

	function override_the_mahalle_io_apikey($value)
	{
		return ( new ServiceCredentialEncryption() )->encrypt( $value );
	}

	private function show_hezarfen_tax_fields()
	{
		return ( get_option( 'hezarfen_show_hezarfen_checkout_tax_fields' ) == 'yes' ) ? true : false;
	}

	/**
	 * Get sections
	 *
	 * @return array
	 */
	public function get_sections()
	{
		$sections = [
			'general' => __('General', 'hezarfen-for-woocommerce'),
			'mahalle_io' => 'mahalle.io',
			'encryption' => __('Encryption', 'hezarfen-for-woocommerce'),
		];

		// if checkout field is active, show the section.
		if( $this->show_hezarfen_tax_fields() )
		{
			$sections['checkout_tax'] = __('Checkout Tax Fields', 'hezarfen-for-woocommerce');
		}

		return apply_filters(
			'woocommerce_get_sections_' . $this->id,
			$sections
		);
	}

	/**
	 * Get setting fields.
	 *
	 * @param string $current_section
	 * @return mixed
	 */
	public function get_settings($current_section = '')
	{
		if ('mahalle_io' == $current_section) {
			$api_key_value = get_option( 'hezarfen_mahalle_io_api_key', null );

			$settings = apply_filters('hezarfen_mahalle_io_settings', [
				[
					'title' => __(
						'mahalle.io Settings',
						'hezarfen-for-woocommerce'
					),
					'type' => 'title',
					'desc' => __(
						'mahalle.io is a optional and paid service for show Turkey neighbors in checkout page.',
						'hezarfen-for-woocommerce'
					),
					'id' => 'hezarfen_mahalleio_options',
				],
				[
					'title' => __('API Key', 'hezarfen-for-woocommerce'),
					'type' => 'text',
					'desc' => __(
						'API key may be created on mahalle.io my account page',
						'hezarfen-for-woocommerce'
					),
					'id' => 'hezarfen_mahalle_io_api_key',
					'value' => ! is_null( $api_key_value ) ? ( new ServiceCredentialEncryption() )->decrypt( $api_key_value ) : ''
				],

				[
					'type' => 'sectionend',
					'id' => 'hezarfen_mahalleio_options',
				],
			]);
		} elseif ('checkout_tax' == $current_section) {
			$settings = apply_filters('hezarfen_checkout_tax_settings', [
				[
					'title' => __(
						'Checkout Tax Fields Settings',
						'hezarfen-for-woocommerce'
					),
					'type' => 'title',
					'desc' => __(
						'You can update the checkout TAX fields. Note: T.C. number field requires encryption feature. If you do not activate the encryption feature, T.C. number field does not appear on the checkout.',
						'hezarfen-for-woocommerce'
					),
					'id' => 'hezarfen_checkout_tax_fields_options',
				],

				[
					'title' => __(
						'Show T.C. Identity Field on Checkout Page ',
						'hezarfen-for-woocommerce'
					),
					'type' => 'checkbox',
					'desc' => __(
						'T.C. Identity Field optionally shows on checkout field when invoice type selected as person.',
						'hezarfen-for-woocommerce'
					),
					'id' => 'hezarfen_checkout_show_TC_identity_field',
					'default' => 'no',
					'std' => 'yes',
				],

				[
					'title' => __(
						'Checkout T.C. Identity Number Fields Required Statuses',
						'hezarfen-for-woocommerce'
					),
					'desc' => __(
						'Is T.C. Identity Number field required?',
						'hezarfen-for-woocommerce'
					),
					'id' =>
						'hezarfen_checkout_is_TC_identity_number_field_required',
					'default' => 'no',
					'type' => 'checkbox',
				],

				[
					'type' => 'sectionend',
					'id' => 'hezarfen_checkout_tax_fields_options',
				],
			]);

			if( !$this->show_hezarfen_tax_fields() )
			{
				global $hide_save_button;

				$hide_save_button = true;

				$settings = apply_filters('hezarfen_checkout_tax_settings', []);
			}

		} elseif ('encryption' == $current_section) {
			// if encryption key not generated before, generate a new key.
			if (!( new PostMetaEncryption() )->is_encryption_key_generated()) {
				// create a new random key.
				$encryption_key = ( new PostMetaEncryption() )->create_random_key();

				$fields = [
					[
						'title' => __(
							'Encryption Settings',
							'hezarfen-for-woocommerce'
						),
						'type' => 'title',
						'desc' => __(
							'If the T.C. Identity Field is active, an encryption key must be generated. The following encryption key generated will be lost upon saving the form. Please back up the generated encryption key to a secure area, then paste it anywhere in the wp-config.php file. In case of deletion of the hezarfen-encryption-key line from wp-config.php, retrospectively, the orders will be sent to T.C. no values will become unreadable.',
							'hezarfen-for-woocommerce'
						),
						'id' => 'hezarfen_checkout_tax_fields_options',
					],
					[
						'title' => __(
							'Encryption Key',
							'hezarfen-for-woocommerce'
						),
						'type' => 'textarea',
						'css' => 'width:100%;height:60px',
						'default' => sprintf(
							"define( 'HEZARFEN_ENCRYPTION_KEY', '%s' );",
							$encryption_key
						),
						'desc' => __(
							'Back up the phrase in the box to a safe area, then place it in wp-config.php file.',
							'hezarfen-for-woocommerce'
						),
					],
					[
						'title' => __(
							'Encryption Key Confirmation',
							'hezarfen-for-woocommerce'
						),
						'type' => 'checkbox',
						'desc' => __(
							'I backed up the key to a secure area and placed it in the wp-config file. In case the encryption key value is deleted from the wp-config.php file, all past orders will be transferred to T.C. I know I cannot access ID data.',
							'hezarfen-for-woocommerce'
						),
						'id' => 'hezarfen_checkout_show_TC_identity_field',
						'default' => 'no',
						'std' => 'yes',
					],
					[
						'type' => 'sectionend',
						'id' => 'hezarfen_checkout_tax_fields_options',
					],
				];
			}

			$settings = apply_filters('hezarfen_checkout_tax_settings', $fields);
		} else {
			$fields = [
				[
					'title' => __(
						'General Settings',
						'hezarfen-for-woocommerce'
					),
					'type' => 'title',
					'desc' => __(
						'You can edit the general settings from this page.',
						'hezarfen-for-woocommerce'
					),
					'id' => 'hezarfen_general_settings_title',
				],
				[
					'title' => __(
						'Show Hezarfen Checkout TAX Fields?',
						'hezarfen-for-woocommerce'
					),
					'type' => 'checkbox',
					'desc' => __(
						'',
						'hezarfen-for-woocommerce'
					),
					'id' => 'hezarfen_show_hezarfen_checkout_tax_fields',
					'default' => 'no',
					'std' => 'yes',
				],
				[
					'type' => 'sectionend',
					'id' => 'hezarfen_general_settings_section_end',
				]
			];

			$settings = apply_filters('hezarfen_general_settings', $fields);
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
	public function output()
	{
		global $current_section;
		global $hide_save_button;

		if ($current_section == 'encryption') {
			if (( new PostMetaEncryption() )->is_encryption_key_generated()) {
				$hide_save_button = true;

				// is key generated and placed to the wp-config.php?
				$health_check_status = ( new PostMetaEncryption() )->health_check();

				// is key correct and is it equal to the key that generated first time?
				$test_the_key = ( new PostMetaEncryption() )->test_the_encryption_key();

				require 'views/encryption.php';
			} else {
				// load the key geneate view
				$settings = $this->get_settings($current_section);
				WC_Admin_Settings::output_fields($settings);
			}
		} else {
			$settings = $this->get_settings($current_section);
			WC_Admin_Settings::output_fields($settings);
		}
	}

	public function save()
	{
		global $current_section;

		// if encryption key generated before, do not continue.
		if (
			$current_section == 'encryption' &&
			(( new PostMetaEncryption() )->health_check() ||
				( new PostMetaEncryption() )->test_the_encryption_key())
		) {
			return false;
		}

		// if encryption key not placed the wp-config, do not continue.
		if( !defined('HEZARFEN_ENCRYPTION_KEY') && $current_section == 'encryption' )
			return false;

		$settings = $this->get_settings($current_section);
		WC_Admin_Settings::save_fields($settings);

		if ($current_section == 'encryption') {
			if (
				get_option('hezarfen_checkout_show_TC_identity_field', false) ==
				'yes'
			) {
				update_option('hezarfen_encryption_key_generated', 'yes');

				if (( new PostMetaEncryption() )->health_check()) {
					// create an encryption tester text.
					( new PostMetaEncryption() )->create_encryption_tester_text();
				}
			}
		}
	}
}

return new Hezarfen_Settings_Hezarfen();
