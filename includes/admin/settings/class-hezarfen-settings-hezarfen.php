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

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_and_styles' ) );
		add_action( 'woocommerce_admin_field_sms_provider_select', array( $this, 'output_sms_provider_select' ) );
		add_action( 'woocommerce_admin_field_netgsm_connection_status', array( $this, 'output_netgsm_connection_status' ) );
		add_action( 'woocommerce_admin_field_pandasms_connection_info', array( $this, 'output_pandasms_connection_info' ) );
		add_action( 'woocommerce_admin_field_sms_rules_button', array( $this, 'output_sms_rules_button' ) );
		add_action( 'woocommerce_admin_field_netgsm_test_connection', array( $this, 'output_netgsm_test_connection' ) );
		add_action( 'woocommerce_admin_field_roadmap_voting', array( $this, 'output_roadmap_voting' ) );
		// Note: AJAX action is registered in main Hezarfen class to ensure it's always available

		parent::__construct();
	}

	/**
	 * Get own sections.
	 *
	 * @return array<string, string>
	 */
	protected function get_own_sections() {
		// Build base sections
		if ( version_compare( WC_HEZARFEN_VERSION, '2.7.40', '<=' ) ) {
			// Version <= 2.7.40: Roadmap is default, Training is separate
			$sections = array(
				''              => __( 'Roadmap', 'hezarfen-for-woocommerce' ),
				'training'      => __( 'Training', 'hezarfen-for-woocommerce' ),
				'general'       => __( 'General', 'hezarfen-for-woocommerce' ),
				'encryption'    => __( 'Encryption', 'hezarfen-for-woocommerce' ),
				'checkout_page' => __( 'Checkout Page Settings', 'hezarfen-for-woocommerce' ),
				'sms_settings'  => __( 'SMS Settings', 'hezarfen-for-woocommerce' ),
			);
		} else {
			// Version > 2.7.40: Training is default, no Roadmap
			$sections = array(
				''              => __( 'Training', 'hezarfen-for-woocommerce' ),
				'general'       => __( 'General', 'hezarfen-for-woocommerce' ),
				'encryption'    => __( 'Encryption', 'hezarfen-for-woocommerce' ),
				'checkout_page' => __( 'Checkout Page Settings', 'hezarfen-for-woocommerce' ),
				'sms_settings'  => __( 'SMS Settings', 'hezarfen-for-woocommerce' ),
			);
		}

		// if checkout field is active, show the section.
		if ( Helper::is_show_tax_fields() ) {
			$sections['checkout_tax'] = __( 'Checkout Tax Fields', 'hezarfen-for-woocommerce' );
		}

		$post_meta_encryption = new PostMetaEncryption();

		if ( $post_meta_encryption->is_encryption_key_generated() && ! $post_meta_encryption->health_check() ) {
			$sections['encryption_recovery'] = __( 'Encryption Key Recovery', 'hezarfen-for-woocommerce' );
		}

		$sections['system_report'] = __( 'Sistem Raporu', 'hezarfen-for-woocommerce' );

		return $sections;
	}

	/**
	 * Get settings for the default section (Roadmap or Training).
	 *
	 * @return array<array<string, string>>
	 */
	protected function get_settings_for_default_section() {
		// If version > 2.7.40, default is Training (no fields needed)
		if ( version_compare( WC_HEZARFEN_VERSION, '2.7.40', '>' ) ) {
			return array();
		}
		
		// Otherwise, default is Roadmap
		return array(
			array(
				'type' => 'roadmap_voting',
				'id'   => 'hezarfen_roadmap_voting',
			),
		);
	}

	/**
	 * Get settings for the Training section.
	 *
	 * @return array<array<string, string>>
	 */
	protected function get_settings_for_training_section() {
		// Training section is handled by output_training_section()
		return array();
	}

	/**
	 * Get settings for the General section.
	 *
	 * @return array<array<string, string>>
	 */
	protected function get_settings_for_general_section() {
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
					'Enable district and neighborhood feature',
					'hezarfen-for-woocommerce'
				),
				'type'    => 'checkbox',
				'desc'    => '',
				'id'      => 'hezarfen_enable_district_neighborhood_fields',
				'default' => 'yes',
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
	 * Get settings for the Encryption Key Recovery section.
	 *
	 * @return array
	 */
	protected function get_settings_for_encryption_recovery_section() {
		global $hide_save_button;
		$post_meta_encryption = new PostMetaEncryption();

		if ( $post_meta_encryption->health_check() ) {
			$hide_save_button = true;

			$fields = array(
				array(
					'title' => __(
						'Encryption Key Recovery Screen',
						'hezarfen-for-woocommerce'
					),
					'type'  => 'title',
					'desc'  => __(
						'Everything seems fine.',
						'hezarfen-for-woocommerce'
					),
					'id'    => 'hezarfen_encryption_recovery_settings_title',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'hezarfen_encryption_recovery_settings_section_end',
				),
			);
		} else {
			$encryption_key = $post_meta_encryption->create_random_key();

			$fields = array(
				array(
					'title' => __(
						'Encryption Key Recovery Screen',
						'hezarfen-for-woocommerce'
					),
					'type'  => 'title',
					'desc'  => '<strong>Önemli not: ' . __(
						'This page allows you to generate a new encryption key. Before proceeding, please create a complete website backup. We do not provide support for this feature nor assume any responsibility for potential data loss or damage that may occur. Use of this feature is entirely at your own risk, even if you have created backups. By continuing, you acknowledge and accept these terms.',
						'hezarfen-for-woocommerce'
					) . '</strong>',
					'id'    => 'hezarfen_encryption_recovery_settings_title',
				),
				array(
					'title'   => __(
						'New Encryption Key',
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
					'title'   => '',
					'type'    => 'checkbox',
					'desc'    => __( "I understand and agree that I can't acces the TC ID data of the old orders by using this new encryption key. I backed up the phrase in the box to a safe area, and I placed it in wp-config.php file.", 'hezarfen-for-woocommerce' ),
					'default' => 'no',
					'class'   => 'encryption-recovery-confirmation',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'hezarfen_encryption_recovery_settings_section_end',
				),
			);
		}

		return apply_filters( 'hezarfen_encryption_recovery_settings', $fields );
	}

	/**
	 * Get settings for the sms_settings section.
	 *
	 * @return array<array<string, string>>
	 */
	protected function get_settings_for_sms_settings_section() {
		// All three tab groups merged; save_fields() needs the full set.
		return array_merge(
			$this->get_sms_connection_fields(),
			$this->get_sms_test_fields(),
			$this->get_sms_template_fields()
		);
	}

	/**
	 * Tab 1 fields: NetGSM connection.
	 *
	 * @return array<array<string, mixed>>
	 */
	private function get_sms_connection_fields() {
		return array(
			array(
				'title' => __( 'SMS Provider', 'hezarfen-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Choose the SMS company you want to use and set up its connection.', 'hezarfen-for-woocommerce' ),
				'id'    => 'hezarfen_sms_provider_card',
			),
			array(
				'title' => __( 'SMS Company', 'hezarfen-for-woocommerce' ),
				'type'  => 'sms_provider_select',
				'desc'  => '',
				'id'    => 'hezarfen_sms_provider',
			),
			array(
				'title'   => __( 'NetGSM Connection', 'hezarfen-for-woocommerce' ),
				'type'    => 'netgsm_connection_status',
				'desc'    => __( 'Your NetGSM account connection status', 'hezarfen-for-woocommerce' ),
				'id'      => 'hezarfen_netgsm_connection_status',
			),
			array(
				'title'   => 'PandaSMS',
				'type'    => 'pandasms_connection_info',
				'desc'    => '',
				'id'      => 'hezarfen_pandasms_connection_info',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'hezarfen_sms_provider_card_end',
			),
		);
	}

	/**
	 * Tab 2 fields: Test sending.
	 *
	 * @return array<array<string, mixed>>
	 */
	private function get_sms_test_fields() {
		return array(
			array(
				'title' => __( 'Test Sending', 'hezarfen-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Verify your NetGSM connection by sending a test SMS.', 'hezarfen-for-woocommerce' ),
				'id'    => 'hezarfen_netgsm_test_card',
			),
			array(
				'title'   => __( 'Test SMS', 'hezarfen-for-woocommerce' ),
				'type'    => 'netgsm_test_connection',
				'desc'    => __( 'Send a test SMS to verify your NetGSM connection. The message content is generated automatically.', 'hezarfen-for-woocommerce' ),
				'id'      => 'hezarfen_netgsm_test_connection',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'hezarfen_netgsm_test_card_end',
			),
		);
	}

	/**
	 * Tab 3 fields: SMS template / message settings (automation rules).
	 *
	 * @return array<array<string, mixed>>
	 */
	private function get_sms_template_fields() {
		return array(
			array(
				'title' => __( 'SMS Template / Message Settings', 'hezarfen-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Configure SMS notifications for order status changes.', 'hezarfen-for-woocommerce' ),
				'id'    => 'hezarfen_sms_settings_title',
			),
			array(
				'title'   => __( 'Enable SMS Automation', 'hezarfen-for-woocommerce' ),
				'type'    => 'checkbox',
				'desc'    => __( 'Enable automatic SMS notifications for order status changes', 'hezarfen-for-woocommerce' ),
				'id'      => 'hezarfen_sms_automation_enabled',
				'default' => 'no',
			),
			array(
				'title'   => __( 'SMS Rules', 'hezarfen-for-woocommerce' ),
				'type'    => 'sms_rules_button',
				'desc'    => __( 'Configure SMS rules for different order status changes', 'hezarfen-for-woocommerce' ),
				'id'      => 'hezarfen_sms_rules',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'hezarfen_sms_settings_section_end',
			),
		);
	}


	/**
	 * Output NetGSM connection status field
	 *
	 * @param array $value Field data
	 * @return void
	 */
	public function output_netgsm_connection_status( $value ) {
		?>
		<tr valign="top" class="hez-provider-row" data-provider="netgsm">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
			</th>
			<td class="forminp">
				<div id="netgsm-connection-status-main">
					<!-- Connected status box is populated by JavaScript -->
				</div>

				<!-- Inline credentials form (replaces the old modal; shown when not
				     connected, or when "Bilgileri Değiştir" is clicked). -->
				<div id="netgsm-credentials-inline" style="display:none; max-width: 480px; margin-top: 12px; border: 1px solid #c3c4c7; border-radius: 6px; padding: 16px; background: #fff;">
					<form id="netgsm-credentials-form">
						<p style="margin: 0 0 12px 0; color: #50575e; font-size: 13px;">
							<?php esc_html_e( 'Enter your NetGSM credentials to enable SMS sending.', 'hezarfen-for-woocommerce' ); ?>
						</p>
						<div class="mb-4" style="margin-bottom: 12px;">
							<label for="netgsm-modal-username" style="display:block; font-weight:600; font-size:13px; margin-bottom:4px;">
								<?php esc_html_e( 'NetGSM Username', 'hezarfen-for-woocommerce' ); ?>
							</label>
							<input type="text" id="netgsm-modal-username" name="username" class="regular-text" placeholder="<?php esc_attr_e( '850xxxxxxx, 312XXXXXXX etc.', 'hezarfen-for-woocommerce' ); ?>" style="width:100%;" />
						</div>
						<div class="mb-4" style="margin-bottom: 12px;">
							<label for="netgsm-modal-password" style="display:block; font-weight:600; font-size:13px; margin-bottom:4px;">
								<?php esc_html_e( 'NetGSM Password', 'hezarfen-for-woocommerce' ); ?>
							</label>
							<div style="position: relative;">
								<input type="password" id="netgsm-modal-password" name="password" class="regular-text" placeholder="<?php esc_attr_e( 'Your API password', 'hezarfen-for-woocommerce' ); ?>" style="width:100%; padding-right:36px;" />
								<button type="button" id="netgsm-toggle-password" tabindex="-1" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'hezarfen-for-woocommerce' ); ?>" style="position:absolute; right:6px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#646970; padding:4px;">
									<svg id="netgsm-eye-closed" style="width:16px; height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path></svg>
									<svg id="netgsm-eye-open" style="width:16px; height:16px; display:none;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
								</button>
							</div>
						</div>
						<div class="mb-4" style="margin-bottom: 12px;">
							<label for="netgsm-modal-msgheader" style="display:block; font-weight:600; font-size:13px; margin-bottom:4px;">
								<?php esc_html_e( 'Message Header', 'hezarfen-for-woocommerce' ); ?>
							</label>
							<div style="position: relative;">
								<select id="netgsm-modal-msgheader" name="msgheader" class="regular-text" style="width:100%;" disabled>
									<option value=""><?php esc_html_e( 'First enter username and password above', 'hezarfen-for-woocommerce' ); ?></option>
								</select>
								<button type="button" id="netgsm-load-senders" style="display:none; position:absolute; right:6px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#2271b1;">
									<svg style="width:16px; height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
								</button>
							</div>
							<p style="font-size:12px; color:#646970; margin:4px 0 0;">
								<?php esc_html_e( 'Available sender names from your NetGSM account', 'hezarfen-for-woocommerce' ); ?>
							</p>
						</div>
					</form>
					<p style="margin: 0;">
						<button type="button" id="netgsm-save-credentials" class="button button-primary"><?php esc_html_e( 'Connect', 'hezarfen-for-woocommerce' ); ?></button>
						<button type="button" id="netgsm-cancel-creds" class="button" style="display:none;"><?php esc_html_e( 'Cancel', 'hezarfen-for-woocommerce' ); ?></button>
					</p>
				</div>

				<p class="description"><?php echo esc_html( $value['desc'] ?? '' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Output the SMS provider (company) selector.
	 *
	 * @param array $value Field data.
	 * @return void
	 */
	public function output_sms_provider_select( $value ) {
		$provider = class_exists( 'SMS_Automation' ) ? SMS_Automation::get_sms_provider() : 'netgsm';
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="hezarfen-sms-provider"><?php echo esc_html( $value['title'] ); ?></label>
			</th>
			<td class="forminp">
				<select id="hezarfen-sms-provider" style="min-width: 260px;">
					<option value="netgsm" <?php selected( $provider, 'netgsm' ); ?>>NetGSM</option>
					<option value="pandasms" <?php selected( $provider, 'pandasms' ); ?>>PandaSMS (<?php esc_html_e( 'will be removed soon', 'hezarfen-for-woocommerce' ); ?>)</option>
				</select>
				<p class="description"><?php esc_html_e( 'Select the SMS company you want to use.', 'hezarfen-for-woocommerce' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Output the PandaSMS info row (legacy provider, configured via its own plugin).
	 * Kept intentionally simple, like before, plus a deprecation notice.
	 *
	 * @param array $value Field data.
	 * @return void
	 */
	public function output_pandasms_connection_info( $value ) {
		$is_ready = \Hezarfen\ManualShipmentTracking\Pandasms::is_plugin_ready();
		?>
		<tr valign="top" class="hez-provider-row" data-provider="pandasms">
			<th scope="row" class="titledesc">
				<label><?php echo esc_html( $value['title'] ); ?></label>
			</th>
			<td class="forminp">
				<div style="background: #fff2cd; border: 1px solid #f39c12; border-radius: 4px; padding: 12px; margin-bottom: 15px; max-width: 560px;">
					<p style="margin: 0; color: #856404; font-weight: 600;">
						⚠️ <?php esc_html_e( 'The PandaSMS integration will be removed soon. We recommend using NetGSM.', 'hezarfen-for-woocommerce' ); ?>
					</p>
				</div>
				<div style="max-width: 560px; padding: 15px; background: #fff; border: 1px solid <?php echo $is_ready ? '#0073aa' : '#d63638'; ?>; border-radius: 6px;">
					<h4 style="margin: 0 0 15px 0; color: <?php echo $is_ready ? '#0073aa' : '#d63638'; ?>;"><?php esc_html_e( 'PandaSMS Configuration', 'hezarfen-for-woocommerce' ); ?></h4>
					<?php if ( $is_ready ) : ?>
						<p class="description" style="margin-top: 10px;">
							<?php esc_html_e( 'PandaSMS message content is configured in the PandaSMS plugin settings.', 'hezarfen-for-woocommerce' ); ?>
						</p>
					<?php else : ?>
						<div style="background: #fff2cd; border: 1px solid #f39c12; border-radius: 4px; padding: 12px;">
							<p style="margin: 0; color: #856404; font-weight: 500;">
								⚠️ <?php esc_html_e( 'Warning: PandaSMS plugin is not active!', 'hezarfen-for-woocommerce' ); ?>
							</p>
							<p style="margin: 8px 0 0 0; color: #856404;">
								<?php esc_html_e( 'Please install and activate the PandaSMS plugin to use this SMS integration.', 'hezarfen-for-woocommerce' ); ?>
							</p>
						</div>
					<?php endif; ?>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Output the "Test SMS" panel.
	 *
	 * Lets the admin send an automatically generated test SMS to a phone number
	 * of their choice and renders the NetGSM result in a human readable way.
	 *
	 * @param array $value Field data.
	 * @return void
	 */
	public function output_netgsm_test_connection( $value ) {
		$is_connected = class_exists( 'SMS_Automation' ) && SMS_Automation::is_netgsm_connected();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php echo esc_html( $value['title'] ); ?></label>
			</th>
			<td class="forminp">
				<div id="hezarfen-netgsm-test" style="max-width: 560px; border: 1px solid #dcdcde; border-radius: 6px; padding: 16px; background: #fff;">
					<?php if ( ! $is_connected ) : ?>
						<div style="padding: 10px 12px; background: #fcf0f1; border-left: 4px solid #d63638; border-radius: 4px;">
							<p style="margin: 0; color: #8a1f1f;">
								<?php esc_html_e( 'Connect your NetGSM account above before sending a test SMS.', 'hezarfen-for-woocommerce' ); ?>
							</p>
						</div>
					<?php else : ?>
						<div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end;">
							<div style="flex: 1; min-width: 220px;">
								<label for="hezarfen-test-phone" style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">
									<?php esc_html_e( 'Destination phone number', 'hezarfen-for-woocommerce' ); ?>
								</label>
								<input type="tel" id="hezarfen-test-phone" class="regular-text" placeholder="5XXXXXXXXX" style="width: 100%;" />
							</div>
							<button type="button" id="hezarfen-send-test-sms" class="button button-primary">
								<?php esc_html_e( 'Send Test SMS', 'hezarfen-for-woocommerce' ); ?>
							</button>
						</div>
						<p class="description" style="margin-top: 8px;">
							<?php esc_html_e( 'A standard charge from your NetGSM account applies for the test message.', 'hezarfen-for-woocommerce' ); ?>
						</p>
						<div id="hezarfen-test-result" style="margin-top: 12px; display: none;"></div>
					<?php endif; ?>
				</div>
				<p class="description"><?php echo esc_html( $value['desc'] ?? '' ); ?></p>
			</td>
		</tr>
		<?php
	}


	/**
	 * Output SMS rules button field
	 *
	 * @param array $value Field data
	 * @return void
	 */
	public function output_sms_rules_button( $value ) {
		$saved_rules = get_option( 'hezarfen_sms_rules', array() );
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
			</th>
			<td class="forminp">
				<button type="button" id="hezarfen-add-sms-rule" class="button button-primary">
					<?php esc_html_e( 'Add SMS Rule', 'hezarfen-for-woocommerce' ); ?>
				</button>
				<p class="description"><?php echo esc_html( $value['desc'] ); ?></p>
				
				<!-- SMS Rules List -->
				<div id="hezarfen-sms-rules-list" style="margin-top: 15px;">
					<?php if ( ! empty( $saved_rules ) ) : ?>
						<?php foreach ( $saved_rules as $index => $rule ) : ?>
							<div class="sms-rule-item" data-rule-index="<?php echo esc_attr( $index ); ?>" style="border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; border-radius: 4px;">
								<strong><?php esc_html_e( 'Rule', 'hezarfen-for-woocommerce' ); ?> #<?php echo esc_html( $index + 1 ); ?>:</strong>
								<?php 
									$condition_status = $rule['condition_status'] ?? '';
									if ( $condition_status === 'hezarfen_order_shipped' ) {
										$status_label = __( 'Order Shipped', 'hezarfen-for-woocommerce' );
									} else {
										$status_label = wc_get_order_status_name( str_replace( 'wc-', '', $condition_status ) );
									}

									$action_label = $rule['action_type'];
									if ( $rule['action_type'] === 'netgsm' ) {
										$action_label = __( 'NetGSM', 'hezarfen-for-woocommerce' );
									} elseif ( $rule['action_type'] === 'netgsm_legacy' ) {
										$action_label = __( 'NetGSM Official Plugin (Legacy)', 'hezarfen-for-woocommerce' );
									} elseif ( $rule['action_type'] === 'pandasms_legacy' ) {
										$action_label = __( 'PandaSMS Official Plugin (Legacy)', 'hezarfen-for-woocommerce' );
									}

									if ( $rule['phone_type'] === 'billing' ) {
										$phone_label = __( 'Order Billing Phone', 'hezarfen-for-woocommerce' );
									} elseif ( $rule['phone_type'] === 'shipping' ) {
										$phone_label = __( 'Order Shipping Phone', 'hezarfen-for-woocommerce' );
									} elseif ( $rule['phone_type'] === 'shipping_or_billing' ) {
										$phone_label = __( 'Order Shipping Phone (or Billing if not available)', 'hezarfen-for-woocommerce' );
									} else {
										$phone_label = $rule['phone_type'];
									}

									echo wp_kses( 
										sprintf( 
											__( 'When order status changes to %1$s, send SMS via %2$s to %3$s', 'hezarfen-for-woocommerce' ),
											'<strong>' . esc_html( $status_label ) . '</strong>',
											'<strong>' . esc_html( $action_label ) . '</strong>',
											'<strong>' . esc_html( $phone_label ) . '</strong>'
										),
										array( 'strong' => array() )
									);
								?>
								<div style="margin-top: 5px;">
									<button type="button" class="button button-small edit-sms-rule" data-rule-index="<?php echo esc_attr( $index ); ?>">
										<?php esc_html_e( 'Edit', 'hezarfen-for-woocommerce' ); ?>
									</button>
									<button type="button" class="button button-small delete-sms-rule" data-rule-index="<?php echo esc_attr( $index ); ?>">
										<?php esc_html_e( 'Delete', 'hezarfen-for-woocommerce' ); ?>
									</button>
								</div>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<p><?php esc_html_e( 'No SMS rules configured yet.', 'hezarfen-for-woocommerce' ); ?></p>
					<?php endif; ?>
				</div>

				<!-- Inline SMS Rule Form -->
				<div id="hezarfen-sms-rule-form-container" style="display: none; margin-top: 20px; padding: 20px; border: 1px solid #ddd; border-radius: 6px; background: #f9f9f9;">
					<h3 id="sms-rule-form-title"><?php esc_html_e( 'Add SMS Rule', 'hezarfen-for-woocommerce' ); ?></h3>
					
					<form id="sms-rule-form">
						<input type="hidden" id="rule-index" value="">
						
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="condition-status"><?php esc_html_e( 'Trigger Condition', 'hezarfen-for-woocommerce' ); ?></label>
								</th>
								<td>
									<select id="condition-status" name="condition_status" style="width: 300px;">
										<option value=""><?php esc_html_e( 'Select trigger...', 'hezarfen-for-woocommerce' ); ?></option>
										<optgroup label="<?php esc_attr_e( 'Order Status Changes', 'hezarfen-for-woocommerce' ); ?>">
											<?php foreach ( wc_get_order_statuses() as $status_key => $status_label ) : ?>
												<?php if ( $status_key !== 'wc-hezarfen-shipped' ) : // Exclude our shipped status ?>
													<option value="<?php echo esc_attr( $status_key ); ?>"><?php echo esc_html( $status_label ); ?></option>
												<?php endif; ?>
											<?php endforeach; ?>
										</optgroup>
										<optgroup label="<?php esc_attr_e( 'Shipment Events', 'hezarfen-for-woocommerce' ); ?>">
											<option value="hezarfen_order_shipped"><?php esc_html_e( 'Order Shipped', 'hezarfen-for-woocommerce' ); ?></option>
										</optgroup>
									</select>
									<p class="description"><?php esc_html_e( 'Choose when to send the SMS notification', 'hezarfen-for-woocommerce' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="action-type"><?php esc_html_e( 'Action Type', 'hezarfen-for-woocommerce' ); ?></label>
								</th>
								<td>
									<select id="action-type" name="action_type" style="width: 300px;">
										<option value=""><?php esc_html_e( 'Select action...', 'hezarfen-for-woocommerce' ); ?></option>
										<option value="netgsm"><?php esc_html_e( 'Send SMS via NetGSM', 'hezarfen-for-woocommerce' ); ?></option>
																			<option value="netgsm_legacy"><?php esc_html_e( 'Send SMS via NetGSM Official Plugin (Legacy - Deprecated Soon)', 'hezarfen-for-woocommerce' ); ?></option>
																			<option value="pandasms_legacy"><?php esc_html_e( 'Send SMS via PandaSMS Official Plugin', 'hezarfen-for-woocommerce' ); ?></option>
									</select>
								</td>
							</tr>
						</table>

						<!-- NetGSM Settings - Only shown when NetGSM is selected -->
						<div id="netgsm-settings" style="display: none; margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #007cba; border-radius: 6px;">
							<h4 style="margin: 0 0 15px 0; color: #007cba;"><?php esc_html_e( 'NetGSM Configuration', 'hezarfen-for-woocommerce' ); ?></h4>
							
							<div id="netgsm-connection-status">
								<!-- This will be populated by JavaScript -->
							</div>
						</div>

						<!-- NetGSM Legacy Settings - Only shown when NetGSM Legacy is selected -->
						<div id="netgsm-legacy-settings" style="display: none; margin-top: 20px; padding: 15px; background: #fff; border: 1px solid <?php echo \Hezarfen\ManualShipmentTracking\Netgsm::is_netgsm_active() ? '#d63638' : '#dc3232'; ?>; border-radius: 6px;">
							<h4 style="margin: 0 0 15px 0; color: <?php echo \Hezarfen\ManualShipmentTracking\Netgsm::is_netgsm_active() ? '#d63638' : '#dc3232'; ?>;"><?php esc_html_e( 'NetGSM Legacy Configuration (Deprecated Soon)', 'hezarfen-for-woocommerce' ); ?></h4>
							<?php if ( \Hezarfen\ManualShipmentTracking\Netgsm::is_netgsm_active() ) : ?>
								<p style="color: #d63638; font-style: italic; margin-bottom: 15px;">
									<?php esc_html_e( 'This option uses the NetGSM official WordPress plugin and will be deprecated soon. Please consider migrating to the direct NetGSM integration.', 'hezarfen-for-woocommerce' ); ?>
								</p>
							<?php else : ?>
								<div style="background: #fff2cd; border: 1px solid #f39c12; border-radius: 4px; padding: 12px; margin-bottom: 15px;">
									<p style="margin: 0; color: #856404; font-weight: 500;">
										⚠️ <?php esc_html_e( 'Warning: NetGSM plugin is not active!', 'hezarfen-for-woocommerce' ); ?>
									</p>
									<p style="margin: 8px 0 0 0; color: #856404;">
										<?php esc_html_e( 'Please install and activate the NetGSM plugin to use this SMS integration.', 'hezarfen-for-woocommerce' ); ?>
									</p>
														</div>
						<?php endif; ?>
							
							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="netgsm-legacy-phone-type"><?php esc_html_e( 'Phone Number', 'hezarfen-for-woocommerce' ); ?></label>
									</th>
									<td>
										<select id="netgsm-legacy-phone-type" name="netgsm_legacy_phone_type" style="width: 300px;">
											<option value=""><?php esc_html_e( 'Select phone type...', 'hezarfen-for-woocommerce' ); ?></option>
											<option value="billing"><?php esc_html_e( 'Order Billing Phone', 'hezarfen-for-woocommerce' ); ?></option>
											<option value="shipping"><?php esc_html_e( 'Order Shipping Phone', 'hezarfen-for-woocommerce' ); ?></option>
											<option value="shipping_or_billing"><?php esc_html_e( 'Order Shipping Phone (or Billing if not available)', 'hezarfen-for-woocommerce' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="netgsm-legacy-message"><?php esc_html_e( 'SMS Message Template', 'hezarfen-for-woocommerce' ); ?></label>
									</th>
									<td>
										<?php 
										$legacy_content = get_option( \Hezarfen\ManualShipmentTracking\Settings::OPT_NETGSM_CONTENT, '' );
										$display_content = $legacy_content ? \Hezarfen\ManualShipmentTracking\Netgsm::convert_netgsm_metas_to_hezarfen_variables( $legacy_content ) : '';
										?>
										<textarea id="netgsm-legacy-message" name="netgsm_legacy_message" rows="4" style="width: 100%; max-width: 500px; background-color: #f9f9f9;" readonly><?php echo esc_textarea( $display_content ); ?></textarea>
										<p class="description" style="color: #666;">
											<?php esc_html_e( 'This content is synced with the NetGSM SMS content setting in Manual Shipment Tracking. To edit, go to WooCommerce > Settings > Hezarfen > Manual Shipment Tracking.', 'hezarfen-for-woocommerce' ); ?>
											<br><br>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=hezarfen&section=manual_shipment_tracking' ) ); ?>" target="_blank" class="button button-secondary">
												<?php esc_html_e( 'Edit NetGSM SMS Content', 'hezarfen-for-woocommerce' ); ?>
											</a>
										</p>
									</td>
								</tr>
							</table>
						</div>

						<!-- PandaSMS Legacy Settings - Only shown when PandaSMS Legacy is selected -->
						<div id="pandasms-legacy-settings" style="display: none; margin-top: 20px; padding: 15px; background: #fff; border: 1px solid <?php echo \Hezarfen\ManualShipmentTracking\Pandasms::is_plugin_ready() ? '#0073aa' : '#d63638'; ?>; border-radius: 6px;">
							<h4 style="margin: 0 0 15px 0; color: <?php echo \Hezarfen\ManualShipmentTracking\Pandasms::is_plugin_ready() ? '#0073aa' : '#d63638'; ?>;"><?php esc_html_e( 'PandaSMS Configuration', 'hezarfen-for-woocommerce' ); ?></h4>
							<?php if ( \Hezarfen\ManualShipmentTracking\Pandasms::is_plugin_ready() ) : ?>
								<p class="description" style="margin-top: 10px;">
									<?php esc_html_e( 'PandaSMS message content is configured in the PandaSMS plugin settings. This integration will use the trigger "Sipariş kargoya verildiğinde" with shipment variables.', 'hezarfen-for-woocommerce' ); ?>
								</p>
							<?php else : ?>
								<div style="background: #fff2cd; border: 1px solid #f39c12; border-radius: 4px; padding: 12px; margin-bottom: 15px;">
									<p style="margin: 0; color: #856404; font-weight: 500;">
										⚠️ <?php esc_html_e( 'Warning: PandaSMS plugin is not active!', 'hezarfen-for-woocommerce' ); ?>
									</p>
									<p style="margin: 8px 0 0 0; color: #856404;">
										<?php esc_html_e( 'Please install and activate the PandaSMS plugin to use this SMS integration.', 'hezarfen-for-woocommerce' ); ?>
									</p>
								</div>
							<?php endif; ?>
						</div>

						<!-- SMS Content Settings - Shown when any SMS action is selected -->
						<div id="sms-content-settings" style="display: none; margin-top: 15px;">
							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="phone-type"><?php esc_html_e( 'Phone Number', 'hezarfen-for-woocommerce' ); ?></label>
									</th>
									<td>
										<select id="phone-type" name="phone_type" style="width: 300px;">
											<option value=""><?php esc_html_e( 'Select phone type...', 'hezarfen-for-woocommerce' ); ?></option>
											<option value="billing"><?php esc_html_e( 'Order Billing Phone', 'hezarfen-for-woocommerce' ); ?></option>
											<option value="shipping"><?php esc_html_e( 'Order Shipping Phone', 'hezarfen-for-woocommerce' ); ?></option>
											<option value="shipping_or_billing"><?php esc_html_e( 'Order Shipping Phone (or Billing if not available)', 'hezarfen-for-woocommerce' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="message-template"><?php esc_html_e( 'Message Template', 'hezarfen-for-woocommerce' ); ?></label>
									</th>
									<td>
										<textarea id="message-template" name="message_template" rows="4" placeholder="<?php esc_attr_e( 'Enter your SMS message template...', 'hezarfen-for-woocommerce' ); ?>" style="width: 100%; max-width: 500px;"></textarea>
										<?php SMS_Automation::output_available_variables( true ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label><?php esc_html_e( 'IYS Status', 'hezarfen-for-woocommerce' ); ?></label>
									</th>
									<td>
										<label><input type="radio" name="iys_status" value="0" checked> <?php esc_html_e( 'Information', 'hezarfen-for-woocommerce' ); ?></label><br>
										<label><input type="radio" name="iys_status" value="11"> <?php esc_html_e( 'Commercial - Campaign, promotion, celebration etc. - Individual', 'hezarfen-for-woocommerce' ); ?></label><br>
										<label><input type="radio" name="iys_status" value="12"> <?php esc_html_e( 'Commercial - Campaign, promotion, celebration etc. - Business', 'hezarfen-for-woocommerce' ); ?></label>
										<p class="description"><?php esc_html_e( 'Select "Information" for informational messages, "Commercial - Individual" for promotional messages to individuals, or "Commercial - Business" for promotional messages to businesses.', 'hezarfen-for-woocommerce' ); ?></p>
									</td>
								</tr>
							</table>
						</div>
					</form>

					<p class="submit">
						<button type="button" id="save-sms-rule" class="button button-primary"><?php esc_html_e( 'Save Rule', 'hezarfen-for-woocommerce' ); ?></button>
						<button type="button" id="cancel-sms-rule" class="button"><?php esc_html_e( 'Cancel', 'hezarfen-for-woocommerce' ); ?></button>
					</p>
				</div>
			</td>
		</tr>
		<?php
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
		} elseif ( '' === $current_section ) {
			// Default section - Roadmap (if version <= 2.7.40) or Training (if version > 2.7.40)
			$hide_save_button = true;
			
			if ( version_compare( WC_HEZARFEN_VERSION, '2.7.40', '<=' ) ) {
				// Show Roadmap
				$settings = $this->get_settings_for_section( $current_section );
				WC_Admin_Settings::output_fields( $settings );
			} else {
				// Show Training
				$this->output_training_section();
			}
		} elseif ( 'training' === $current_section ) {
			$hide_save_button = true;
			$this->output_training_section();
		} elseif ( 'system_report' === $current_section ) {
			$hide_save_button = true;
			do_action( 'hezarfen_system_report_output' );
		} elseif ( 'sms_settings' === $current_section ) {
			$this->output_sms_settings_tabs();
		} else {
			$settings = $this->get_settings_for_section( $current_section );
			WC_Admin_Settings::output_fields( $settings );
		}
	}

	/**
	 * Render the SMS settings section as three sub-tabs:
	 * NetGSM connection, Test sending and SMS template/message settings.
	 *
	 * Each tab is a normal WooCommerce settings card; only one panel is visible
	 * at a time (toggled by JS). Saving still works because every field stays in
	 * get_settings_for_sms_settings_section().
	 *
	 * @return void
	 */
	private function output_sms_settings_tabs() {
		$tabs = array(
			'connection' => __( 'SMS Provider', 'hezarfen-for-woocommerce' ),
			'test'       => __( 'Test Sending', 'hezarfen-for-woocommerce' ),
			'template'   => __( 'SMS Template / Message Settings', 'hezarfen-for-woocommerce' ),
		);
		?>
		<h2 class="nav-tab-wrapper hezarfen-sms-tabs" style="margin-bottom: 16px;">
			<?php $first = true; foreach ( $tabs as $key => $label ) : ?>
				<a href="#" class="nav-tab<?php echo $first ? ' nav-tab-active' : ''; ?> hezarfen-sms-tab" data-tab="<?php echo esc_attr( $key ); ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php $first = false; endforeach; ?>
		</h2>

		<div class="hezarfen-sms-tab-panel" data-tab="connection">
			<?php WC_Admin_Settings::output_fields( $this->get_sms_connection_fields() ); ?>
		</div>
		<div class="hezarfen-sms-tab-panel" data-tab="test" style="display:none;">
			<?php WC_Admin_Settings::output_fields( $this->get_sms_test_fields() ); ?>
		</div>
		<div class="hezarfen-sms-tab-panel" data-tab="template" style="display:none;">
			<?php WC_Admin_Settings::output_fields( $this->get_sms_template_fields() ); ?>
		</div>
		<?php
	}

	/**
	 * Render the two-step review banner if conditions are met.
	 *
	 * Rules:
	 * - Features must be actively in use (Feature_Status check).
	 * - Not permanently dismissed (clicked "Write a Review" or "No").
	 * - Not snoozed (clicked "Not Now" or "X" → 30-day snooze).
	 * - Maximum 3 impressions lifetime.
	 *
	 * @return void
	 */
	public function maybe_output_review_banner() {
		// Permanently dismissed
		if ( get_option( 'hezarfen_review_dismissed', false ) ) {
			return;
		}

		// Max 3 impressions
		$shown_count = (int) get_option( 'hezarfen_review_shown_count', 0 );
		if ( $shown_count >= 3 ) {
			return;
		}

		// Snoozed (30-day cooldown after "Not Now" or "X")
		$snoozed_until = (int) get_option( 'hezarfen_review_snoozed_until', 0 );
		if ( $snoozed_until > time() ) {
			return;
		}

		// Features must be actively in use
		if ( ! \Hezarfen\Inc\Feature_Status::are_features_active() ) {
			return;
		}

		$nonce = wp_create_nonce( 'hezarfen_dismiss_review' );
		$review_url = 'https://wordpress.org/support/plugin/hezarfen-for-woocommerce/reviews/#new-post';
		?>
		<div id="hezarfen-review-banner" class="hezarfen-review-banner">
			<button type="button" class="hezarfen-review-close" id="hezarfen-review-close" title="<?php esc_attr_e( 'Dismiss', 'hezarfen-for-woocommerce' ); ?>">&times;</button>

			<!-- Step 1: Micro question -->
			<div id="hezarfen-review-step1" class="hezarfen-review-step">
				<span class="hezarfen-review-icon">&#11088;</span>
				<p class="hezarfen-review-question"><?php esc_html_e( 'Everything running smoothly with Hezarfen?', 'hezarfen-for-woocommerce' ); ?></p>
				<div class="hezarfen-review-actions">
					<button type="button" class="hezarfen-review-btn hezarfen-review-btn--yes" id="hezarfen-review-yes">&#128077; <?php esc_html_e( 'Yes', 'hezarfen-for-woocommerce' ); ?></button>
					<button type="button" class="hezarfen-review-btn hezarfen-review-btn--no" id="hezarfen-review-no">&#128078; <?php esc_html_e( 'No', 'hezarfen-for-woocommerce' ); ?></button>
				</div>
			</div>

			<!-- Step 2a: Review prompt (shown after Yes) -->
			<div id="hezarfen-review-step2-yes" class="hezarfen-review-step" style="display:none;">
				<span class="hezarfen-review-icon">&#127881;</span>
				<div class="hezarfen-review-text">
					<p class="hezarfen-review-question"><?php esc_html_e( 'Great! Would you like to share your experience with a short review?', 'hezarfen-for-woocommerce' ); ?></p>
					<p class="hezarfen-review-subtitle"><?php esc_html_e( 'Your review helps us keep developing the plugin.', 'hezarfen-for-woocommerce' ); ?></p>
					<p class="hezarfen-review-hint"><?php esc_html_e( 'You can use your free WordPress.org account to write a review.', 'hezarfen-for-woocommerce' ); ?></p>
				</div>
				<div class="hezarfen-review-actions">
					<a href="<?php echo esc_url( $review_url ); ?>" target="_blank" rel="noopener noreferrer" class="hezarfen-review-btn hezarfen-review-btn--primary" id="hezarfen-review-go">&#11088; <?php esc_html_e( 'Write a Review', 'hezarfen-for-woocommerce' ); ?></a>
					<button type="button" class="hezarfen-review-btn hezarfen-review-btn--later" id="hezarfen-review-later"><?php esc_html_e( 'Not Now', 'hezarfen-for-woocommerce' ); ?></button>
				</div>
			</div>

			<!-- Step 2b: Support prompt (shown after No) -->
			<div id="hezarfen-review-step2-no" class="hezarfen-review-step" style="display:none;">
				<span class="hezarfen-review-icon">&#128591;</span>
				<div class="hezarfen-review-text">
					<p class="hezarfen-review-question"><?php esc_html_e( 'We\'d love to help! Let us know what we can improve.', 'hezarfen-for-woocommerce' ); ?></p>
					<a href="https://intense.com.tr/whatsapp-destek" target="_blank" rel="noopener noreferrer" class="hezarfen-review-support-link" id="hezarfen-review-support">&#128172; <?php esc_html_e( 'WhatsApp Support', 'hezarfen-for-woocommerce' ); ?></a>
				</div>
			</div>
		</div>

		<script>
		(function(){
			var banner = document.getElementById('hezarfen-review-banner');
			var step1 = document.getElementById('hezarfen-review-step1');
			var step2Yes = document.getElementById('hezarfen-review-step2-yes');
			var step2No = document.getElementById('hezarfen-review-step2-no');
			var nonce = '<?php echo esc_js( $nonce ); ?>';

			function hide() {
				banner.style.transition = 'opacity .3s, max-height .3s';
				banner.style.opacity = '0';
				banner.style.maxHeight = '0';
				banner.style.overflow = 'hidden';
				setTimeout(function(){ banner.remove(); }, 300);
			}

			function send(type) {
				var xhr = new XMLHttpRequest();
				xhr.open('POST', ajaxurl);
				xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
				xhr.send('action=hezarfen_dismiss_review&nonce=' + nonce + '&type=' + type);
			}

			function showStep(el) {
				step1.style.display = 'none';
				el.style.display = '';
			}

			document.getElementById('hezarfen-review-yes').addEventListener('click', function(){ showStep(step2Yes); send('snooze_short'); });

			// "No" → show support link, snooze 30 days
			document.getElementById('hezarfen-review-no').addEventListener('click', function(){
				showStep(step2No);
				send('snooze');
			});

			// "Contact Support" → close banner
			document.getElementById('hezarfen-review-support').addEventListener('click', function(){ hide(); });

			// "Write a Review" → snooze 14 days, stays visible until page reload
			document.getElementById('hezarfen-review-go').addEventListener('click', function(){
				send('snooze_short');
			});

			// "Not Now" → snooze 30 days
			document.getElementById('hezarfen-review-later').addEventListener('click', function(){
				send('snooze');
				hide();
			});

			// "X" close → snooze 30 days
			document.getElementById('hezarfen-review-close').addEventListener('click', function(){
				send('snooze');
				hide();
			});
		})();
		</script>
		<?php
	}

	/**
	 * Output roadmap voting interface
	 *
	 * @param array $value Field data
	 * @return void
	 */
	public function output_roadmap_voting( $value ) {
		// Check if user has already voted
		$has_voted = get_option( 'hezarfen_v3_roadmap_last_vote', false );
		
		if ( $has_voted ) {
			// Show minimal thank you message
			$vote_data = get_option( 'hezarfen_roadmap_votes', array() );
			$vote_date = isset( $vote_data['timestamp'] ) ? $vote_data['timestamp'] : '';
			?>
			<div class="hezarfen-roadmap-container" style="max-width: 600px; margin: 80px auto; text-align: center;">
				<div style="font-size: 48px; margin-bottom: 20px; opacity: 0.9;">✓</div>
				<h2 style="color: #2c3e50; font-size: 24px; margin: 0 0 12px 0; font-weight: 500;">
					<?php esc_html_e( 'Teşekkür Ederiz', 'hezarfen-for-woocommerce' ); ?>
				</h2>
				<p style="color: #7f8c8d; font-size: 15px; margin: 0;">
					<?php esc_html_e( 'Oylarınız e-posta ile info@intense.com.tr adresine gönderildi.', 'hezarfen-for-woocommerce' ); ?>
				</p>
				<?php if ( $vote_date ) : ?>
					<p style="color: #bdc3c7; font-size: 13px; margin: 15px 0 0 0;">
						<?php echo esc_html( date( 'd.m.Y H:i:s', strtotime( $vote_date ) ) ); ?>
					</p>
				<?php endif; ?>
			</div>
			<?php
			return;
		}
		
		// Load helper class
		require_once WC_HEZARFEN_UYGULAMA_YOLU . 'includes/admin/settings/class-hezarfen-roadmap-helper.php';
		
		$free_features = Hezarfen_Roadmap_Helper::get_free_features();
		$pro_features = Hezarfen_Roadmap_Helper::get_pro_features();
		?>
		<div class="hezarfen-roadmap-container" style="max-width: 1200px; margin: 20px 0;">
			<div class="hezarfen-roadmap-header" style="margin-bottom: 30px;">
				<h2><?php esc_html_e( 'Hezarfen Geliştirme Yol Haritası (v3.0 - gelecek büyük sürüm)', 'hezarfen-for-woocommerce' ); ?></h2>

				<p style="font-size: 14px; color: #666; margin-top: 10px;">
					<?php esc_html_e( 'Hezarfen, bugün 2bin+ site tarafından kullanılıyor ve her geçen gün büyüyor. Hezarfen’in gelecek büyük versiyonu olan 3.0 için özellik geliştirme planlarımızı yaparken sizin de geri bildiriminizi almak istedik. Hezarfen’e birden fazla dikeyde geliştirerek, bazı özellikler için farklı eklenti kullanma ihtiyaçlarını ortadan kaldırmayı ve bu sayede WooCommerce altyapılarının stabilitesini arttırmayı amaçlıyoruz.', 'hezarfen-for-woocommerce' ); ?>
				</p>
				<p style="font-size: 14px; color: #666; margin-top: 10px;">
					<?php esc_html_e( 'Hangi özelliklerin geliştirilmesini istersiniz? Her kategoriden en fazla 5 özellik seçebilirsiniz. Toplanan veriler geliştirme planlamamıza ışık tutacaktır, nihai karar ekibimiz tarafından değerlendirilecektir. Şimdiden teşekkür ederiz!', 'hezarfen-for-woocommerce' ); ?>
				</p>
			</div>

			<div class="hezarfen-roadmap-sections" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 20px;">
				<!-- Free Features -->
				<div class="hezarfen-roadmap-section">
					<h3 style="margin-bottom: 15px; color: #0073aa;">
						<?php esc_html_e( 'Ücretsiz Sürüm Özellikleri', 'hezarfen-for-woocommerce' ); ?>
						<span id="free-counter" style="font-size: 13px; color: #666; font-weight: normal;">(0/5 <?php esc_html_e( 'seçildi', 'hezarfen-for-woocommerce' ); ?>)</span>
					</h3>
					<div class="hezarfen-features-list" data-type="free" data-max="5">
						<?php foreach ( $free_features as $index => $feature ) : ?>
							<label class="hezarfen-feature-item" data-feature-type="free" style="display: flex; align-items: flex-start; padding: 12px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 8px; cursor: pointer; transition: all 0.2s ease; background: #fff;">
								<input type="checkbox" name="free_features[]" value="<?php echo esc_attr( $index ); ?>" style="margin: 3px 10px 0 0; cursor: pointer;">
								<span style="flex: 1; font-size: 13px; line-height: 1.5;"><?php echo esc_html( $feature ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- Pro Features -->
				<div class="hezarfen-roadmap-section">
					<h3 style="margin-bottom: 15px; color: #16a34a;">
						<?php esc_html_e( 'Pro Paket (Ücretli Sürüm) Özellikleri', 'hezarfen-for-woocommerce' ); ?>
						<span id="pro-counter" style="font-size: 13px; color: #666; font-weight: normal;">(0/5 <?php esc_html_e( 'seçildi', 'hezarfen-for-woocommerce' ); ?>)</span>
					</h3>
					<?php if( ! defined( 'HEZARFEN_PRO_VERSION' ) ): ?>
						<p style="color: #3194ed">
						Pro sürümünü kullanmasanız bile geri bildiriminiz bizim için çok kıymetli. Paylaştığınız görüşler yalnızca Hezarfen’i geliştirmemize yardımcı olur; Pro sürümünü satın alma gibi bir zorunluluk bulunmaz. Pro sürümün fiyat/özellik dengesini iyileştirmek için görüş topluyoruz.
						</p>
					<?php endif; ?>
					<div class="hezarfen-features-list" data-type="pro" data-max="5">
						<?php foreach ( $pro_features as $index => $feature ) : ?>
							<label class="hezarfen-feature-item" data-feature-type="pro" style="display: flex; align-items: flex-start; padding: 12px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 8px; cursor: pointer; transition: all 0.2s ease; background: #fff;">
								<input type="checkbox" name="pro_features[]" value="<?php echo esc_attr( $index ); ?>" style="margin: 3px 10px 0 0; cursor: pointer;">
								<span style="flex: 1; font-size: 13px; line-height: 1.5;"><?php echo esc_html( $feature ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
					
					<!-- Current Features Info -->
					<div style="margin-top: 25px; padding: 15px; background: #f9f9f9; border-radius: 4px; border: 1px solid #e0e0e0;">
						<h4 style="margin: 0 0 10px 0; font-size: 13px; color: #16a34a; font-weight: 600;">
							<?php esc_html_e( 'Mevcut Pro Paket Özellikleri:', 'hezarfen-for-woocommerce' ); ?>
						</h4>
						<p style="margin: 0; font-size: 12px; color: #555; line-height: 1.6;">
							<?php esc_html_e( 'Yurtiçi, DHL, Hepsijet, KolayGelsin, Aras, Sürat kargo entegrasyonları tek pakette', 'hezarfen-for-woocommerce' ); ?>
							<?php
							// Show pricing only if before November 1, 2025
							$cutoff_date = strtotime( '2025-11-01' );
							$current_date = current_time( 'timestamp' );
							
							if ( $current_date < $cutoff_date ) :
							?>
								<a target="_blank" rel="noopener noreferrer" href="https://intense.com.tr/woocommerce-kargo-entegrasyonu/"><span style="display: inline-block; margin-top: 6px; padding: 4px 10px; background: #16a34a; color: white; border-radius: 3px; font-size: 12px; font-weight: 600;">
									5943TL+KDV (1 site için 1 yıllık destek ve güncelleştirme)
								</span></a>
							<?php endif; ?>
						</p>
					</div>
				</div>
			</div>

			<!-- Additional Details -->
			<div class="hezarfen-roadmap-details" style="margin-top: 30px;">
				<label for="roadmap-details" style="display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #333;">
					<?php esc_html_e( 'Eklemek istediğiniz ek bilgi veya önerileriniz:', 'hezarfen-for-woocommerce' ); ?>
					<span style="font-weight: normal; color: #666; font-size: 12px;"><?php esc_html_e( '(opsiyonel)', 'hezarfen-for-woocommerce' ); ?></span>
				</label>
				<textarea 
					id="roadmap-details" 
					name="roadmap_details" 
					rows="4" 
					placeholder="<?php esc_attr_e( 'Seçtiğiniz özellikler hakkında ek açıklamalar, özel istekleriniz veya diğer önerilerinizi buraya yazabilirsiniz...', 'hezarfen-for-woocommerce' ); ?>"
					style="width: 100%; max-width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif; resize: vertical; min-height: 100px; transition: border-color 0.2s ease;"
					onfocus="this.style.borderColor='#2271b1'"
					onblur="this.style.borderColor='#ddd'"
				></textarea>
			</div>

			<div class="hezarfen-roadmap-actions" style="margin-top: 25px; text-align: center;">
				<p style="font-size: 12px; color: #666; margin: 0 0 15px 0; opacity: 0.85;">
					<?php esc_html_e( 'Seçimleriniz info@intense.com.tr adresine e-posta ile gönderilecektir. Paylaşılacak veriler: oylamanız, alan adınız, SMTP gönderimi yapan e-posta adresiniz (gönderici olarak). Verileriniz üçüncü taraflarla paylaşılmaz veya SPAM gönderim yapılmaz.', 'hezarfen-for-woocommerce' ); ?>
				</p>
				<div style="display: flex; justify-content: center; align-items: center; flex-direction: column;">
					<button type="button" id="hezarfen-submit-votes" class="button button-primary button-large" style="padding: 8px 40px; font-size: 14px;">
						<?php esc_html_e( 'Oylarımı Gönder', 'hezarfen-for-woocommerce' ); ?>
					</button>
					<span class="hezarfen-vote-message" style="margin-left: 15px; display: none;"></span>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Output training section with YouTube videos grid
	 *
	 * @return void
	 */
	private function output_training_section() {
		$training_videos = $this->get_training_videos();
		?>
		<div class="hezarfen-training-section">
			<div class="hezarfen-training-header">
				<h2><?php esc_html_e( 'Training Videos', 'hezarfen-for-woocommerce' ); ?></h2>
				<p><?php esc_html_e( 'Learn how to use Hezarfen with these helpful video tutorials.', 'hezarfen-for-woocommerce' ); ?></p>
			</div>
			
			<div class="hezarfen-videos-grid">
				<?php foreach ( $training_videos as $video ) : ?>
					<div class="hezarfen-video-card">
						<div class="hezarfen-video-thumbnail">
							<a href="<?php echo esc_url( $video['url'] ); ?>" target="_blank" rel="noopener noreferrer">
								<img src="<?php echo esc_url( $video['thumbnail'] ); ?>" alt="<?php echo esc_attr( $video['title'] ); ?>" loading="lazy">
								<div class="hezarfen-play-button">
									<svg width="68" height="48" viewBox="0 0 68 48" xmlns="http://www.w3.org/2000/svg">
										<path d="M66.52,7.74c-0.78-2.93-2.49-5.41-5.42-6.19C55.79,.13,34,0,34,0S12.21,.13,6.9,1.55 C3.97,2.33,2.27,4.81,1.48,7.74C0.06,13.05,0,24,0,24s0.06,10.95,1.48,16.26c0.78,2.93,2.49,5.41,5.42,6.19 C12.21,47.87,34,48,34,48s21.79-0.13,27.1-1.55c2.93-0.78,4.64-3.26,5.42-6.19C67.94,34.95,68,24,68,24S67.94,13.05,66.52,7.74z" fill="#f00"></path>
										<path d="M45,24L27,14v20" fill="#fff"></path>
									</svg>
								</div>
							</a>
						</div>
						<div class="hezarfen-video-info">
							<h3 class="hezarfen-video-title">
								<a href="<?php echo esc_url( $video['url'] ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( $video['title'] ); ?>
								</a>
							</h3>
							<p class="hezarfen-video-description"><?php echo esc_html( $video['description'] ); ?></p>
							<div class="hezarfen-video-meta">
								<div class="hezarfen-meta-left">
									<span class="hezarfen-video-duration"><?php echo esc_html( $video['duration'] ); ?></span>
									<span class="hezarfen-video-date"><?php echo esc_html( $this->format_video_date( $video['published'] ?? '' ) ); ?></span>
								</div>
								<span class="hezarfen-comment-cta"><?php esc_html_e( 'Comment to reach out us!', 'hezarfen-for-woocommerce' ); ?></span>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			
		</div>
		<?php
	}

	/**
	 * Get training videos data from YouTube RSS feed
	 *
	 * @return array
	 */
	private function get_training_videos() {
		// Check for cached videos first
		$cached_videos = get_transient( 'hezarfen_youtube_videos' );
		if ( false !== $cached_videos ) {
			return $cached_videos;
		}

		$videos = array();
		$feed_url = 'https://www.youtube.com/feeds/videos.xml?channel_id=UCMciljrlqN1u0uBdL8mbyUQ';

		// Fetch the RSS feed
		$response = wp_remote_get( $feed_url, array(
			'timeout' => 15
		) );

		if ( is_wp_error( $response ) ) {
			// Return fallback video if feed fails
			return $this->get_fallback_videos();
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return $this->get_fallback_videos();
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return $this->get_fallback_videos();
		}

		// Parse XML
		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $body );
		libxml_clear_errors();
		if ( false === $xml ) {
			return $this->get_fallback_videos();
		}

		// Register namespaces
		$xml->registerXPathNamespace( 'atom', 'http://www.w3.org/2005/Atom' );
		$xml->registerXPathNamespace( 'yt', 'http://www.youtube.com/xml/schemas/2015' );
		$xml->registerXPathNamespace( 'media', 'http://search.yahoo.com/mrss/' );

		// Get video entries
		$entries = $xml->xpath( '//atom:entry' );

		if ( empty( $entries ) ) {
			return $this->get_fallback_videos();
		}

		foreach ( $entries as $entry ) {
			$entry->registerXPathNamespace( 'atom', 'http://www.w3.org/2005/Atom' );
			$entry->registerXPathNamespace( 'yt', 'http://www.youtube.com/xml/schemas/2015' );
			$entry->registerXPathNamespace( 'media', 'http://search.yahoo.com/mrss/' );

			$video_id = (string) $entry->xpath( 'yt:videoId' )[0];
			$title = (string) $entry->xpath( 'atom:title' )[0];
			$description = (string) $entry->xpath( 'media:group/media:description' )[0];
			$published = (string) $entry->xpath( 'atom:published' )[0];

			if ( empty( $video_id ) || empty( $title ) ) {
				continue;
			}

			// Get video duration from YouTube API (optional, fallback to default)
			$duration = $this->get_video_duration( $video_id );

			$videos[] = array(
				'id'          => $video_id,
				'title'       => $title,
				'description' => $this->truncate_description( $description ),
				'url'         => 'https://www.youtube.com/watch?v=' . $video_id,
				'thumbnail'   => 'https://img.youtube.com/vi/' . $video_id . '/maxresdefault.jpg',
				'duration'    => $duration,
				'published'   => $published
			);
		}

		// Cache for 1 hour
		set_transient( 'hezarfen_youtube_videos', $videos, HOUR_IN_SECONDS );

		return empty( $videos ) ? $this->get_fallback_videos() : $videos;
	}

	/**
	 * Get fallback videos when RSS feed fails
	 *
	 * @return array
	 */
	private function get_fallback_videos() {
		return array(
			array(
				'id'          => 'jatKJipEdpU',
				'title'       => __( 'Hezarfen Ücretsiz Hepsijet Entegrasyonu ve Avantajlı Kargo Fiyatları', 'hezarfen-for-woocommerce' ),
				'description' => __( 'Learn how to integrate Hepsijet with Hezarfen and get advantageous shipping rates.', 'hezarfen-for-woocommerce' ),
				'url'         => 'https://www.youtube.com/watch?v=jatKJipEdpU',
				'thumbnail'   => 'https://img.youtube.com/vi/jatKJipEdpU/maxresdefault.jpg',
				'duration'    => '5:27',
				'published'   => '2025-09-16T15:28:39+00:00'
			),
		);
	}

	/**
	 * Get video duration (fallback method)
	 *
	 * @param string $video_id YouTube video ID
	 * @return string Duration in MM:SS format
	 */
	private function get_video_duration( $video_id ) {
		// For now, return a default duration
		// In the future, this could use YouTube Data API v3 for accurate durations
		return '5:27';
	}

	/**
	 * Truncate video description
	 *
	 * @param string $description Video description
	 * @return string Truncated description
	 */
	private function truncate_description( $description ) {
		if ( empty( $description ) ) {
			return __( 'Learn how to use Hezarfen with this helpful tutorial.', 'hezarfen-for-woocommerce' );
		}

		// Truncate to 150 characters
		if ( strlen( $description ) > 150 ) {
			$description = substr( $description, 0, 150 ) . '...';
		}

		return $description;
	}

	/**
	 * Format video publication date for display
	 *
	 * @param string $published_date ISO 8601 date string
	 * @return string Formatted date in Turkish format
	 */
	private function format_video_date( $published_date ) {
		if ( empty( $published_date ) ) {
			return '';
		}

		try {
			$date = new DateTime( $published_date );
			// Turkish date format: DD.MM.YYYY
			return $date->format( 'd.m.Y' );
		} catch ( Exception $e ) {
			// Fallback if date parsing fails
			return '';
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
		if ( ! defined( 'HEZARFEN_ENCRYPTION_KEY' ) && ( 'encryption' === $current_section || 'encryption_recovery' === $current_section ) ) {
			WC_Admin_Settings::add_error( __( 'Please place the encryption key in the wp-config.php file.', 'hezarfen-for-woocommerce' ) );
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
					( new PostMetaEncryption() )->update_encryption_tester_text();
				}
			}
		}

		if ( 'encryption_recovery' === $current_section ) {
			$recovery_log   = get_option( 'hezarfen_encryption_key_recovery_log', array() );
			$recovery_log[] = current_datetime();

			update_option( 'hezarfen_encryption_key_recovery_log', $recovery_log );

			// update the tester text.
			if ( ( new PostMetaEncryption() )->health_check() ) {
				// create an encryption tester text.
				( new PostMetaEncryption() )->update_encryption_tester_text( true );
			}
		}
	}

	/**
	 * Enqueues scripts and styles.
	 * 
	 * @param string $hook_suffix The current admin page.
	 * 
	 * @return void
	 */
	public function enqueue_scripts_and_styles( $hook_suffix ) {
		// Only proceed if we're on WooCommerce settings page
		if ( 'woocommerce_page_wc-settings' !== $hook_suffix ) {
			return;
		}

		// Check if we're on Hezarfen tab
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : '';
		if ( 'hezarfen' !== $tab ) {
			return;
		}

		// Get current section
		$current_section = isset( $_GET['section'] ) ? sanitize_text_field( $_GET['section'] ) : '';

		if ( 'encryption_recovery' === $current_section ) {
			wp_enqueue_script( 'wc_hezarfen_settings_js', plugins_url( 'assets/admin/js/settings.js', WC_HEZARFEN_FILE ), array( 'jquery' ), WC_HEZARFEN_VERSION, true );
		}

		if ( '' === $current_section && version_compare( WC_HEZARFEN_VERSION, '2.7.40', '<=' ) ) {
			// Roadmap section (only for version <= 2.7.40)
			wp_enqueue_script( 'wc_hezarfen_roadmap_js', plugins_url( 'assets/admin/js/roadmap.js', WC_HEZARFEN_FILE ), array( 'jquery' ), WC_HEZARFEN_VERSION, true );
			wp_localize_script( 'wc_hezarfen_roadmap_js', 'hezarfenRoadmap', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'hezarfen_roadmap_vote' ),
			) );
		}

		// Load CSS for all Hezarfen settings sections
		wp_enqueue_style( 'wc_hezarfen_settings_css', plugins_url( 'assets/admin/css/settings.css', WC_HEZARFEN_FILE ), array(), WC_HEZARFEN_VERSION );

		// Load training script on all Hezarfen settings pages (for the swimming subscribe card)
		wp_enqueue_script( 'wc_hezarfen_training_js', plugins_url( 'assets/admin/js/training.js', WC_HEZARFEN_FILE ), array( 'jquery' ), WC_HEZARFEN_VERSION, true );

		if ( 'woocommerce_page_wc-settings' === $hook_suffix && 'sms_settings' === $current_section ) {
			wp_enqueue_script( 'wc_hezarfen_sms_settings_js', plugins_url( 'assets/admin/js/sms-settings.js', WC_HEZARFEN_FILE ), array( 'jquery' ), WC_HEZARFEN_VERSION . '-' . filemtime( plugin_dir_path( WC_HEZARFEN_FILE ) . 'assets/admin/js/sms-settings.js' ), true );
			wp_enqueue_style( 'wc_hezarfen_sms_settings_css', plugins_url( 'assets/admin/css/sms-settings.css', WC_HEZARFEN_FILE ), array(), WC_HEZARFEN_VERSION );
			
			// Force load Turkish translations if available
			$current_locale = get_locale();
			if ( $current_locale === 'tr_TR' || strpos( $current_locale, 'tr' ) === 0 ) {
				// Ensure Turkish translations are loaded
				load_plugin_textdomain( 'hezarfen-for-woocommerce', false, dirname( plugin_basename( WC_HEZARFEN_FILE ) ) . '/languages/' );
			}
			
			// Localize script with order statuses and other data
			$order_statuses = wc_get_order_statuses();
			wp_localize_script( 'wc_hezarfen_sms_settings_js', 'hezarfen_sms_settings', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'hezarfen_sms_settings_nonce' ),
				'order_statuses' => $order_statuses,
				'strings' => array(
					'add_rule' => __( 'Add SMS Rule', 'hezarfen-for-woocommerce' ),
					'edit_rule' => __( 'Edit SMS Rule', 'hezarfen-for-woocommerce' ),
					'delete_rule' => __( 'Delete Rule', 'hezarfen-for-woocommerce' ),
					'save_rule' => __( 'Save Rule', 'hezarfen-for-woocommerce' ),
					'saving_rule' => __( 'Saving rule...', 'hezarfen-for-woocommerce' ),
					'cancel' => __( 'Cancel', 'hezarfen-for-woocommerce' ),
					// Inline alert messages
					'please_enter_credentials' => __( 'Please enter username and password first', 'hezarfen-for-woocommerce' ),
					'network_error_loading_senders' => __( 'Network error occurred while loading senders', 'hezarfen-for-woocommerce' ),
					'failed_to_load_senders' => __( 'Failed to load senders', 'hezarfen-for-woocommerce' ),
					'found_sender_single' => __( 'Found 1 sender: %s', 'hezarfen-for-woocommerce' ),
					'found_senders_multiple' => __( 'Found %d senders available', 'hezarfen-for-woocommerce' ),
					'credentials_required' => __( 'Username and password are required', 'hezarfen-for-woocommerce' ),
					'select_message_header' => __( 'Please select a message header', 'hezarfen-for-woocommerce' ),
					'credentials_saved_successfully' => __( 'NetGSM connection verified and established!', 'hezarfen-for-woocommerce' ),
					'failed_to_save_credentials' => __( 'Failed to save credentials', 'hezarfen-for-woocommerce' ),
					'network_error_saving_credentials' => __( 'Network error occurred while saving credentials', 'hezarfen-for-woocommerce' ),
					'password_min_length' => __( 'Password must be at least 6 characters', 'hezarfen-for-woocommerce' ),
					'enter_password_min' => __( 'Enter password (min 6 characters)', 'hezarfen-for-woocommerce' ),
					'enter_username' => __( 'Enter username', 'hezarfen-for-woocommerce' ),
					'will_load_senders' => __( 'Will load senders in 1.5 seconds...', 'hezarfen-for-woocommerce' ),
					'loading_senders_auto' => __( 'Loading senders automatically...', 'hezarfen-for-woocommerce' ),
					'loading_senders_countdown' => __( 'Loading senders in %ss...', 'hezarfen-for-woocommerce' ),
					'loading_senders' => __( 'Loading senders...', 'hezarfen-for-woocommerce' ),
					'error_loading_senders' => __( 'Error loading senders', 'hezarfen-for-woocommerce' ),
					'select_sender' => __( 'Select a sender', 'hezarfen-for-woocommerce' ),
					'connecting' => __( 'Connecting...', 'hezarfen-for-woocommerce' ),
					'rule_number' => __( 'Rule #%d:', 'hezarfen-for-woocommerce' ),
					'rule_description' => __( 'When order status changes to %1$s, send SMS via %2$s to %3$s', 'hezarfen-for-woocommerce' ),
					'edit_button' => __( 'Edit', 'hezarfen-for-woocommerce' ),
					'delete_button' => __( 'Delete', 'hezarfen-for-woocommerce' ),
					'netgsm_label' => __( 'NetGSM', 'hezarfen-for-woocommerce' ),
					'netgsm_legacy_label' => __( 'NetGSM Official Plugin (Legacy)', 'hezarfen-for-woocommerce' ),
					'pandasms_legacy_label' => __( 'PandaSMS Official Plugin (Legacy)', 'hezarfen-for-woocommerce' ),
					'order_shipped_label' => __( 'Order Shipped', 'hezarfen-for-woocommerce' ),
					'trigger' => __( 'Trigger', 'hezarfen-for-woocommerce' ),
					'condition' => __( 'Condition', 'hezarfen-for-woocommerce' ),
					'action' => __( 'Action', 'hezarfen-for-woocommerce' ),
					'order_status_changed' => __( 'Order Status Changed', 'hezarfen-for-woocommerce' ),
					'new_status' => __( 'New Status', 'hezarfen-for-woocommerce' ),
					'send_sms' => __( 'Send SMS via NetGSM', 'hezarfen-for-woocommerce' ),
					'phone_type' => __( 'Phone Number', 'hezarfen-for-woocommerce' ),
					'billing_phone' => __( 'Order Billing Phone', 'hezarfen-for-woocommerce' ),
					'shipping_phone' => __( 'Order Shipping Phone', 'hezarfen-for-woocommerce' ),
					'shipping_or_billing_phone' => __( 'Order Shipping Phone (or Billing if not available)', 'hezarfen-for-woocommerce' ),
					'message_template' => __( 'Message Template', 'hezarfen-for-woocommerce' ),
					'iys_status' => __( 'IYS Status', 'hezarfen-for-woocommerce' ),
					'iys_info' => __( 'Information (0)', 'hezarfen-for-woocommerce' ),
					'iys_commercial' => __( 'Commercial (11)', 'hezarfen-for-woocommerce' ),
					'available_variables' => __( 'Available Variables: {order_number}, {customer_name}, {order_status}, {order_total}', 'hezarfen-for-woocommerce' ),
					// NetGSM Connection Status strings
					'connected_to_netgsm' => __( 'Connected to NetGSM', 'hezarfen-for-woocommerce' ),
					'change_credentials' => __( 'Change Credentials', 'hezarfen-for-woocommerce' ),
					'username_label' => __( 'Username', 'hezarfen-for-woocommerce' ),
					'sender_label' => __( 'Sender', 'hezarfen-for-woocommerce' ),
					// Test SMS strings
					'enter_test_phone'      => __( 'Enter a phone number for the test SMS.', 'hezarfen-for-woocommerce' ),
					'sending_test'          => __( 'Sending test SMS…', 'hezarfen-for-woocommerce' ),
					'test_success_title'    => __( 'Test SMS sent successfully', 'hezarfen-for-woocommerce' ),
					'test_failed_title'     => __( 'Test SMS could not be sent', 'hezarfen-for-woocommerce' ),
					'test_network_error'    => __( 'A network error occurred while sending the test SMS.', 'hezarfen-for-woocommerce' ),
					'label_result'          => __( 'Result', 'hezarfen-for-woocommerce' ),
					'label_netgsm_code'     => __( 'NetGSM code', 'hezarfen-for-woocommerce' ),
					'label_job_id'          => __( 'Job ID', 'hezarfen-for-woocommerce' ),
					'label_sent_to'         => __( 'Sent to', 'hezarfen-for-woocommerce' ),
					'message_sent_recipient'=> __( 'Message sent to the recipient.', 'hezarfen-for-woocommerce' ),
					'registered_senders'    => __( 'Your registered sender names:', 'hezarfen-for-woocommerce' ),
					'pick_sender_hint'      => __( 'Click a sender name and connect it with the Confirm button.', 'hezarfen-for-woocommerce' ),
					'confirm_and_connect'   => __( 'Confirm & Connect', 'hezarfen-for-woocommerce' ),
					'no_registered_senders' => __( 'You have no registered sender names. Please contact NetGSM to register a sender name (header).', 'hezarfen-for-woocommerce' ),
					'sender_connected'      => __( 'Sender name connected.', 'hezarfen-for-woocommerce' ),
					'test_again_hint'       => __( 'You can run the test again now.', 'hezarfen-for-woocommerce' ),
					'sender_connect_failed' => __( 'Could not connect the sender name.', 'hezarfen-for-woocommerce' ),
					'network_error'         => __( 'Network error.', 'hezarfen-for-woocommerce' ),
					'connecting_short'      => __( 'Connecting…', 'hezarfen-for-woocommerce' ),
					// Connection status box
					'not_connected_title'   => __( 'NetGSM Account Not Connected', 'hezarfen-for-woocommerce' ),
					'not_connected_desc'    => __( 'Fill in the form below to connect your NetGSM account and enable SMS sending.', 'hezarfen-for-woocommerce' ),
					'not_member_title'      => __( 'Not a NetGSM member?', 'hezarfen-for-woocommerce' ),
					'not_member_link'       => __( 'Click here to sign up for NetGSM →', 'hezarfen-for-woocommerce' ),
				)
			) );
		}
	}
}

return new Hezarfen_Settings_Hezarfen();
