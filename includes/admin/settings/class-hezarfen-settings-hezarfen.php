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
		add_action( 'woocommerce_admin_field_sms_rules_button', array( $this, 'output_sms_rules_button' ) );

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
			'sms_settings'  => __( 'SMS Settings', 'hezarfen-for-woocommerce' ),
		);

		// if checkout field is active, show the section.
		if ( Helper::is_show_tax_fields() ) {
			$sections['checkout_tax'] = __( 'Checkout Tax Fields', 'hezarfen-for-woocommerce' );
		}

		$post_meta_encryption = new PostMetaEncryption();

		if ( $post_meta_encryption->is_encryption_key_generated() && ! $post_meta_encryption->health_check() ) {
			$sections['encryption_recovery'] = __( 'Encryption Key Recovery', 'hezarfen-for-woocommerce' );
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
		$fields = array(
			array(
				'title' => __( 'SMS Automation Settings', 'hezarfen-for-woocommerce' ),
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

		return $fields;
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
								<?php esc_html_e( 'When order status changes to', 'hezarfen-for-woocommerce' ); ?>
								<strong><?php echo esc_html( wc_get_order_status_name( str_replace( 'wc-', '', $rule['condition_status'] ?? '' ) ) ); ?></strong>,
								<?php esc_html_e( 'send SMS via', 'hezarfen-for-woocommerce' ); ?>
								<strong><?php echo esc_html( $rule['action_type'] === 'netgsm' ? 'NetGSM' : $rule['action_type'] ); ?></strong>
								<?php esc_html_e( 'to', 'hezarfen-for-woocommerce' ); ?>
								<strong><?php echo esc_html( $rule['phone_type'] === 'billing' ? __( 'Billing Phone', 'hezarfen-for-woocommerce' ) : __( 'Shipping Phone', 'hezarfen-for-woocommerce' ) ); ?></strong>
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
									<p><strong><?php esc_html_e( 'When order status changes to:', 'hezarfen-for-woocommerce' ); ?></strong></p>
									<select id="condition-status" name="condition_status" required style="width: 300px;">
										<option value=""><?php esc_html_e( 'Select status...', 'hezarfen-for-woocommerce' ); ?></option>
										<?php foreach ( wc_get_order_statuses() as $status_key => $status_label ) : ?>
											<option value="<?php echo esc_attr( $status_key ); ?>"><?php echo esc_html( $status_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="action-type"><?php esc_html_e( 'Action Type', 'hezarfen-for-woocommerce' ); ?></label>
								</th>
								<td>
									<select id="action-type" name="action_type" required style="width: 300px;">
										<option value=""><?php esc_html_e( 'Select action...', 'hezarfen-for-woocommerce' ); ?></option>
										<option value="netgsm"><?php esc_html_e( 'Send SMS via NetGSM', 'hezarfen-for-woocommerce' ); ?></option>
									</select>
								</td>
							</tr>
						</table>

						<!-- NetGSM Settings - Only shown when NetGSM is selected -->
						<div id="netgsm-settings" style="display: none; margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #007cba; border-radius: 6px;">
							<h4 style="margin: 0 0 15px 0; color: #007cba;"><?php esc_html_e( 'NetGSM Configuration', 'hezarfen-for-woocommerce' ); ?></h4>
							
							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="netgsm-username"><?php esc_html_e( 'NetGSM Username', 'hezarfen-for-woocommerce' ); ?></label>
									</th>
									<td>
										<input type="text" id="netgsm-username" name="netgsm_username" placeholder="<?php esc_attr_e( '850xxxxxxx, 312XXXXXXX etc.', 'hezarfen-for-woocommerce' ); ?>" style="width: 300px;">
										<p class="description"><?php esc_html_e( 'Your NetGSM username', 'hezarfen-for-woocommerce' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="netgsm-password"><?php esc_html_e( 'NetGSM Password', 'hezarfen-for-woocommerce' ); ?></label>
									</th>
									<td>
										<input type="password" id="netgsm-password" name="netgsm_password" placeholder="<?php esc_attr_e( 'Your API password', 'hezarfen-for-woocommerce' ); ?>" style="width: 300px;">
										<p class="description"><?php esc_html_e( 'Your NetGSM API password', 'hezarfen-for-woocommerce' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="netgsm-msgheader"><?php esc_html_e( 'Message Header', 'hezarfen-for-woocommerce' ); ?></label>
									</th>
									<td>
										<input type="text" id="netgsm-msgheader" name="netgsm_msgheader" placeholder="<?php esc_attr_e( 'Sender name (3-11 chars)', 'hezarfen-for-woocommerce' ); ?>" maxlength="11" style="width: 300px;">
										<p class="description"><?php esc_html_e( 'SMS sender name (3-11 characters)', 'hezarfen-for-woocommerce' ); ?></p>
									</td>
								</tr>
							</table>
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
											<option value="billing"><?php esc_html_e( 'Billing Phone', 'hezarfen-for-woocommerce' ); ?></option>
											<option value="shipping"><?php esc_html_e( 'Shipping Phone', 'hezarfen-for-woocommerce' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="message-template"><?php esc_html_e( 'Message Template', 'hezarfen-for-woocommerce' ); ?></label>
									</th>
									<td>
										<textarea id="message-template" name="message_template" rows="4" placeholder="<?php esc_attr_e( 'Enter your SMS message template...', 'hezarfen-for-woocommerce' ); ?>" style="width: 100%; max-width: 500px;"></textarea>
										<p class="description"><?php esc_html_e( 'Available Variables (click to copy):', 'hezarfen-for-woocommerce' ); ?></p>
										<div class="sms-variables-wrapper" style="margin-top: 10px;">
											<span class="sms-variable button button-small" data-variable="{siparis_no}" title="<?php esc_attr_e( 'Click to copy', 'hezarfen-for-woocommerce' ); ?>">{siparis_no}</span>
											<span class="sms-variable button button-small" data-variable="{uye_adi}" title="<?php esc_attr_e( 'Click to copy', 'hezarfen-for-woocommerce' ); ?>">{uye_adi}</span>
											<span class="sms-variable button button-small" data-variable="{uye_soyadi}" title="<?php esc_attr_e( 'Click to copy', 'hezarfen-for-woocommerce' ); ?>">{uye_soyadi}</span>
											<span class="sms-variable button button-small" data-variable="{uye_telefonu}" title="<?php esc_attr_e( 'Click to copy', 'hezarfen-for-woocommerce' ); ?>">{uye_telefonu}</span>
											<span class="sms-variable button button-small" data-variable="{uye_epostasi}" title="<?php esc_attr_e( 'Click to copy', 'hezarfen-for-woocommerce' ); ?>">{uye_epostasi}</span>
											<span class="sms-variable button button-small" data-variable="{kullanici_adi}" title="<?php esc_attr_e( 'Click to copy', 'hezarfen-for-woocommerce' ); ?>">{kullanici_adi}</span>
											<span class="sms-variable button button-small" data-variable="{tarih}" title="<?php esc_attr_e( 'Click to copy', 'hezarfen-for-woocommerce' ); ?>">{tarih}</span>
											<span class="sms-variable button button-small" data-variable="{saat}" title="<?php esc_attr_e( 'Click to copy', 'hezarfen-for-woocommerce' ); ?>">{saat}</span>
										</div>
										<p class="description" style="margin-top: 10px; font-style: italic; color: #666;">
											<?php esc_html_e( 'Variables use Turkish names from the legacy system. Old square bracket format [variable] is also supported.', 'hezarfen-for-woocommerce' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label><?php esc_html_e( 'IYS Status', 'hezarfen-for-woocommerce' ); ?></label>
									</th>
									<td>
										<label><input type="radio" name="iys_status" value="0" checked> <?php esc_html_e( 'Information (0)', 'hezarfen-for-woocommerce' ); ?></label><br>
										<label><input type="radio" name="iys_status" value="11"> <?php esc_html_e( 'Commercial (11)', 'hezarfen-for-woocommerce' ); ?></label>
										<p class="description"><?php esc_html_e( 'Select "Information" for informational messages or "Commercial" for promotional messages.', 'hezarfen-for-woocommerce' ); ?></p>
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
		global $current_section;

		if ( 'woocommerce_page_wc-settings' === $hook_suffix && 'encryption_recovery' === $current_section ) {
			wp_enqueue_script( 'wc_hezarfen_settings_js', plugins_url( 'assets/admin/js/settings.js', WC_HEZARFEN_FILE ), array( 'jquery' ), WC_HEZARFEN_VERSION, true );
			wp_enqueue_style( 'wc_hezarfen_settings_css', plugins_url( 'assets/admin/css/settings.css', WC_HEZARFEN_FILE ), array(), WC_HEZARFEN_VERSION );
		}

		if ( 'woocommerce_page_wc-settings' === $hook_suffix && 'sms_settings' === $current_section ) {
			wp_enqueue_script( 'wc_hezarfen_sms_settings_js', plugins_url( 'assets/admin/js/sms-settings.js', WC_HEZARFEN_FILE ), array( 'jquery' ), WC_HEZARFEN_VERSION, true );
			wp_enqueue_style( 'wc_hezarfen_sms_settings_css', plugins_url( 'assets/admin/css/sms-settings.css', WC_HEZARFEN_FILE ), array(), WC_HEZARFEN_VERSION );
			
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
					'cancel' => __( 'Cancel', 'hezarfen-for-woocommerce' ),
					'trigger' => __( 'Trigger', 'hezarfen-for-woocommerce' ),
					'condition' => __( 'Condition', 'hezarfen-for-woocommerce' ),
					'action' => __( 'Action', 'hezarfen-for-woocommerce' ),
					'order_status_changed' => __( 'Order Status Changed', 'hezarfen-for-woocommerce' ),
					'new_status' => __( 'New Status', 'hezarfen-for-woocommerce' ),
					'send_sms' => __( 'Send SMS via NetGSM', 'hezarfen-for-woocommerce' ),
					'phone_type' => __( 'Phone Number', 'hezarfen-for-woocommerce' ),
					'billing_phone' => __( 'Billing Phone', 'hezarfen-for-woocommerce' ),
					'shipping_phone' => __( 'Shipping Phone', 'hezarfen-for-woocommerce' ),
					'message_template' => __( 'Message Template', 'hezarfen-for-woocommerce' ),
					'iys_status' => __( 'IYS Status', 'hezarfen-for-woocommerce' ),
					'iys_info' => __( 'Information (0)', 'hezarfen-for-woocommerce' ),
					'iys_commercial' => __( 'Commercial (11)', 'hezarfen-for-woocommerce' ),
					'available_variables' => __( 'Available Variables: {order_number}, {customer_name}, {order_status}, {order_total}', 'hezarfen-for-woocommerce' ),
				)
			) );
		}
	}
}

return new Hezarfen_Settings_Hezarfen();
