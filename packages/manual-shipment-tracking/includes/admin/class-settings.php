<?php
/**
 * Contains the class that performs settings related actions.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Performs settings related actions.
 */
class Settings {
	const HEZARFEN_WC_SETTINGS_ID = 'hezarfen';
	const SECTION                 = 'manual_shipment_tracking';

	const OPT_DEFAULT_COURIER          = 'hezarfen_mst_default_courier_company';
	const OPT_SHOW_TRACKING_COLUMN     = 'hezarfen_mst_show_shipment_tracking_column';
	const OPT_ENABLE_SMS               = 'hezarfen_mst_enable_sms_notification';
	const OPT_NOTIF_PROVIDER           = 'hezarfen_mst_notification_provider';
	const OPT_NETGSM_CONTENT           = 'hezarfen_mst_netgsm_sms_content';
	const OPT_RECOG_DATA               = 'hezarfen_mst_recognize_data';
	const OPT_RECOGNITION_TYPE         = 'hezarfen_mst_recognition_type';
	const OPT_ORDER_STATUS_ID          = 'hezarfen_mst_custom_order_status_id';
	const OPT_COURIER_CUSTOM_META      = 'hezarfen_mst_courier_company_custom_meta';
	const OPT_TRACKING_NUM_CUSTOM_META = 'hezarfen_mst_tracking_num_custom_meta';

	const RECOG_TYPE_SUPPORTED_PLUGINS = 'supported_plugins';
	const RECOG_TYPE_CUSTOM_META       = 'custom_meta';

	/**
	 * Constructor
	 * 
	 * @return void
	 */
	public function __construct() {
		self::add_enable_disable_option();

		add_filter( 'woocommerce_get_sections_' . self::HEZARFEN_WC_SETTINGS_ID, array( __CLASS__, 'add_section' ) );

		if ( Manual_Shipment_Tracking::is_enabled() ) {
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts_and_styles' ) );
			add_filter( 'woocommerce_get_settings_' . self::HEZARFEN_WC_SETTINGS_ID, array( __CLASS__, 'add_settings_to_section' ), 10, 2 );
			add_action( 'woocommerce_settings_save_hezarfen', array( __CLASS__, 'settings_save' ) );
		}

		// Add Hepsijet integration settings
		add_filter( 'woocommerce_get_sections_' . self::HEZARFEN_WC_SETTINGS_ID, array( __CLASS__, 'add_hepsijet_section' ) );
		add_filter( 'woocommerce_get_settings_' . self::HEZARFEN_WC_SETTINGS_ID, array( __CLASS__, 'add_hepsijet_settings' ), 10, 2 );
		add_action( 'woocommerce_settings_save_hezarfen', array( __CLASS__, 'save_hepsijet_settings' ), 5 ); // Run early with priority 5
		
		// Decrypt webhook secret for display
		add_filter( 'pre_option_hez_ordermigo_webhook_secret', array( __CLASS__, 'decrypt_webhook_secret_for_display' ), 10 );
	}

	/**
	 * Adds a checkbox to enable/disable the package.
	 * 
	 * @return void
	 */
	private static function add_enable_disable_option() {
		add_filter(
			'hezarfen_general_settings',
			function ( $hezarfen_settings ) {
				$hezarfen_settings[] = array(
					'title'   => __(
						'Enable Manual Shipment Tracking feature',
						'hezarfen-for-woocommerce'
					),
					'type'    => 'checkbox',
					'desc'    => '',
					'id'      => Manual_Shipment_Tracking::ENABLE_DISABLE_OPTION,
					'default' => 'yes',
				);
	
				return $hezarfen_settings;
			} 
		);
	}

	/**
	 * Adds a new section to Hezarfen's settings tab.
	 * 
	 * @param array<string, string> $hezarfen_sections Hezarfen's sections.
	 * 
	 * @return array<string, string>
	 */
	public static function add_section( $hezarfen_sections ) {
		if ( Manual_Shipment_Tracking::is_enabled() ) {
			$hezarfen_sections[ self::SECTION ] = __( 'Manual Shipment Tracking', 'hezarfen-for-woocommerce' );
		}

		return $hezarfen_sections;
	}

	/**
	 * Adds settings to the new section.
	 * 
	 * @param array<array<string, string>> $settings Other sections' settings.
	 * @param string                       $current_section Current section.
	 * 
	 * @return array<array<string, string>>
	 */
	public static function add_settings_to_section( $settings, $current_section ) {
		if ( self::SECTION === $current_section ) {
			add_action( 'woocommerce_admin_field_hezarfen_mst_netgsm_sms_content_textarea', array( __CLASS__, 'render_netgsm_sms_content_setting' ) );

			foreach ( Manual_Shipment_Tracking::notification_providers() as $id => $class ) {
				$notice = '';

				if ( ! $class::is_plugin_ready() ) {
					/* translators: %s SMS notification provider */
					$notice = sprintf( __( 'In order to %1$s integration work, the %2$s plugin must be activated.', 'hezarfen-for-woocommerce' ), $class::$title, $class::$title );
				}

				if ( Netgsm::$id === $id && ! Netgsm::is_netgsm_order_status_change_notif_active() ) {
					$notice = __( 'In order to NetGSM integration work, the "send SMS to the customer when order status changed" option must be activated from the NetGSM plugin settings.', 'hezarfen-for-woocommerce' );
				}

				$label                         = $notice ? sprintf( '%s (%s)', $class::$title, $notice ) : $class::$title;
				$notification_providers[ $id ] = $label;
			}

			return array(
				array(
					'type'  => 'title',
					'title' => __( 'Manual Shipment Tracking General Settings', 'hezarfen-for-woocommerce' ),
				),
				array(
					'type'  => 'checkbox',
					'title' => __( 'Show Shipment Tracking column on My Account > Orders page', 'hezarfen-for-woocommerce' ),
					'id'    => self::OPT_SHOW_TRACKING_COLUMN,
				),
				array(
					'type' => 'sectionend',
					'id'   => 'hezarfen_mst_general',
				),
				array(
					'type'  => 'title',
					'title' => __( 'Advanced Settings', 'hezarfen-for-woocommerce' ),
				),
				array(
					'type'  => 'checkbox',
					'title' => __( "Recognize third party plugins' data", 'hezarfen-for-woocommerce' ),
					'desc'  => __( "If you used a shipment tracking plugin before, check this to recognize your old shipment tracking plugin's data.", 'hezarfen-for-woocommerce' ),
					'id'    => self::OPT_RECOG_DATA,
					'class' => 'recogize-data',
				),
				array(
					'type'    => 'radio',
					'title'   => __( 'Recognition type', 'hezarfen-for-woocommerce' ),
					'id'      => self::OPT_RECOGNITION_TYPE,
					'class'   => 'recognition recognition-type',
					'options' => array(
						/* translators: %s Supported plugins. */
						self::RECOG_TYPE_SUPPORTED_PLUGINS => sprintf( __( 'Recognize supported plugins: (%s)', 'hezarfen-for-woocommerce' ), implode( ', ', Third_Party_Data_Support::SUPPORTED_PLUGINS ) ),
						self::RECOG_TYPE_CUSTOM_META       => __( 'Recognize custom post meta data', 'hezarfen-for-woocommerce' ),
					),
				),
				array(
					'type'        => 'text',
					'title'       => __( 'Order status ID (optional)', 'hezarfen-for-woocommerce' ),
					'id'          => self::OPT_ORDER_STATUS_ID,
					'class'       => 'recognition custom-meta',
					'placeholder' => __( 'Enter order status id. E.g: wc-shipped', 'hezarfen-for-woocommerce' ),
				),
				array(
					'type'        => 'text',
					'title'       => __( 'Courier company post meta key', 'hezarfen-for-woocommerce' ),
					'id'          => self::OPT_COURIER_CUSTOM_META,
					'class'       => 'recognition custom-meta',
					'placeholder' => __( 'Enter post meta key for courier company', 'hezarfen-for-woocommerce' ),
				),
				array(
					'type'        => 'text',
					'title'       => __( 'Tracking number post meta key', 'hezarfen-for-woocommerce' ),
					'id'          => self::OPT_TRACKING_NUM_CUSTOM_META,
					'class'       => 'recognition custom-meta',
					'placeholder' => __( 'Enter post meta key for tracking number', 'hezarfen-for-woocommerce' ),
				),
				array(
					'type' => 'sectionend',
					'id'   => 'hezarfen_mst_advanced',
				),
				array(
					'type'  => 'title',
					'title' => __( 'SMS Notification Settings (Legacy)', 'hezarfen-for-woocommerce' ),
					'desc'  => '<div style="background: #fff3cd; border: 2px solid #f39c12; border-radius: 6px; padding: 15px; margin: 15px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"><div style="display: flex; align-items: center; margin-bottom: 10px;"><span style="font-size: 20px; margin-right: 8px;">⚠️</span><strong style="color: #d68910; font-size: 16px;">' . __( 'Important Notice:', 'hezarfen-for-woocommerce' ) . '</strong></div><p style="margin: 0 0 12px 0; line-height: 1.5;">' . sprintf( __( 'SMS automation settings have been moved to a new, more advanced system. You can now configure multiple SMS rules with different triggers and conditions. %sManage SMS Rules%s', 'hezarfen-for-woocommerce' ), '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=hezarfen&section=sms_settings' ) ) . '" style="color: #0073aa; text-decoration: none; font-weight: bold; border-bottom: 1px solid #0073aa;">', '</a>' ) . '</p><p style="margin: 0; color: #856404; font-style: italic;">' . __( 'SMS settings have been moved to the new SMS automation system. This legacy section has been removed.', 'hezarfen-for-woocommerce' ) . '</p></div>',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'hezarfen_mst_sms_notification',
				)
			);
		}

		return $settings;
	}

	/**
	 * Outputs the "NetGSM SMS content" setting.
	 * 
	 * @param array<string, mixed> $setting "NetGSM SMS content" setting's data.
	 * 
	 * @return void
	 */
	public static function render_netgsm_sms_content_setting( $setting ) {
		$sms_content = $setting['value'] ? Netgsm::convert_netgsm_metas_to_hezarfen_variables( $setting['value'] ) : '';
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $setting['id'] ); ?>"><?php echo esc_html( $setting['title'] ); ?></label>
			</th>
			<td class="forminp forminp-textarea">
				<textarea
					name="<?php echo esc_attr( $setting['field_name'] ); ?>"
					id="<?php echo esc_attr( $setting['id'] ); ?>"
					class="<?php echo esc_attr( $setting['class'] ); ?>"
					placeholder="<?php echo esc_attr( $setting['placeholder'] ); ?>"
					rows="5"
					cols="30"
					<?php disabled( ! Netgsm::is_plugin_ready() ); ?>
					><?php echo esc_textarea( $sms_content ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Available Variables', 'hezarfen-for-woocommerce' ); ?>:</p>
				<div class="sms-variables-wrapper">
					<?php foreach ( Netgsm::AVAILABLE_VARIABLES as $variable ) : ?>
						<span class="sms-variable button"><?php echo esc_html( $variable ); ?></span>
					<?php endforeach; ?>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Performs some checks and operations before saving.
	 * 
	 * @return void
	 */
	public static function settings_save() {
		global $current_section;

		if ( self::SECTION !== $current_section ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing

		if ( empty( $_POST[ self::OPT_NOTIF_PROVIDER ] ) ) {
			$_POST[ self::OPT_ENABLE_SMS ] = '';
		}

		if ( ! empty( $_POST[ self::OPT_NETGSM_CONTENT ] ) ) {
			$_POST[ self::OPT_NETGSM_CONTENT ] = Netgsm::convert_hezarfen_variables_to_netgsm_metas( sanitize_text_field( $_POST[ self::OPT_NETGSM_CONTENT ] ) );
		}

		if ( empty( $_POST[ self::OPT_RECOGNITION_TYPE ] ) || ( self::RECOG_TYPE_CUSTOM_META === $_POST[ self::OPT_RECOGNITION_TYPE ] && ! self::check_posted_custom_meta_keys() ) ) {
			$_POST[ self::OPT_RECOG_DATA ]       = '';
			$_POST[ self::OPT_RECOGNITION_TYPE ] = '';
		}

		if ( ! empty( $_POST[ self::OPT_ORDER_STATUS_ID ] ) ) {
			if ( 'wc-' !== substr( $_POST[ self::OPT_ORDER_STATUS_ID ], 0, 3 ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$_POST[ self::OPT_ORDER_STATUS_ID ] = sanitize_key( 'wc-' . $_POST[ self::OPT_ORDER_STATUS_ID ] );
			}
		}

		// phpcs:enable
	}

	/**
	 * Checks the posted custom meta keys. Returns false if all of them are empty.
	 * 
	 * @return bool
	 */
	private static function check_posted_custom_meta_keys() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return ! empty( $_POST[ self::OPT_COURIER_CUSTOM_META ] ) || ! empty( $_POST[ self::OPT_TRACKING_NUM_CUSTOM_META ] );
	}

	/**
	 * Adds Hepsijet integration section.
	 * 
	 * @param array<string, string> $hezarfen_sections Hezarfen's sections.
	 * 
	 * @return array<string, string>
	 */
	public static function add_hepsijet_section( $hezarfen_sections ) {
		$hezarfen_sections['hepsijet_integration'] = __( 'Hepsijet Integration', 'hezarfen-for-woocommerce' );
		return $hezarfen_sections;
	}

	/**
	 * Adds Hepsijet integration settings.
	 * 
	 * @param array<array<string, string>> $settings Other sections' settings.
	 * @param string                       $current_section Current section.
	 * 
	 * @return array<array<string, string>>
	 */
	public static function add_hepsijet_settings( $settings, $current_section ) {
		if ( 'hepsijet_integration' !== $current_section ) {
			return $settings;
		}

		$cities = ( new \WC_Countries() )->get_states('TR');
		$cities = array_combine( $cities, array_values($cities) );

		return array(
			array(
				'type'  => 'title',
				'title' => __( 'Intense Hepsijet API Relay Settings', 'hezarfen-for-woocommerce' ),
				'desc'  => __( 'Hepsijet Integration is completely free. Simply log in at intense.com.tr, go to the Hepsijet menu under My Account, complete your registration, upload the required documents, and add balance via credit card or bank transfer. After that, you can create your first label and start shipping right away with discounted rates—no shipping contract needed.', 'hezarfen-for-woocommerce' ),
			),
			array(
				'title' => __( 'Consumer Key', 'hezarfen-for-woocommerce' ),
				'type' => 'text',
				'id' => 'hezarfen_hepsijet_consumer_key',
				'default' => '',
				'desc' => __( 'Consumer Key from Hepsijet API Relay plugin', 'hezarfen-for-woocommerce' )
			),
			array(
				'title' => __( 'Consumer Secret', 'hezarfen-for-woocommerce' ),
				'type' => 'password',
				'id' => 'hezarfen_hepsijet_consumer_secret',
				'default' => '',
				'desc' => __( 'Consumer Secret from Hepsijet API Relay plugin', 'hezarfen-for-woocommerce' )
			),
			array(
				'type' => 'sectionend',
				'id' => 'hezarfen_hepsijet_relay_settings'
			),
			array(
				'type'  => 'title',
				'title' => __( 'Label Settings', 'hezarfen-for-woocommerce' ),
			),
			array(
				'title' => __( 'Show order details on label', 'hezarfen-for-woocommerce' ),
				'type' => 'checkbox',
				'id' => 'hezarfen_hepsijet_show_order_details_on_label',
				'default' => 'yes',
				'desc' => __( 'Display order details on the PDF label. If unchecked, only Hepsijet Label will be shown.', 'hezarfen-for-woocommerce' )
			),
			array(
				'type' => 'sectionend',
				'id' => 'hezarfen_hepsijet_label_settings'
			),
			array(
				'type'  => 'title',
				'title' => __( 'Advanced Settings', 'hezarfen-for-woocommerce' ),
				'desc'  => '<div style="background: #fff3cd; border-left: 4px solid #f39c12; padding: 12px; margin: 10px 0;"><strong>⚠️ ' . __( 'Warning:', 'hezarfen-for-woocommerce' ) . '</strong> ' . __( 'Do not modify these settings unless you know what you are doing. These settings are automatically configured.', 'hezarfen-for-woocommerce' ) . '</div>',
			),
			array(
				'title' => __( 'Webhook Secret', 'hezarfen-for-woocommerce' ),
				'type' => 'text',
				'id' => 'hez_ordermigo_webhook_secret',
				'default' => '',
				'desc' => __( 'This secret is used to verify webhook notifications from OrderMigo. It is automatically generated when you create your first shipment. Do not edit this unless instructed by support.', 'hezarfen-for-woocommerce' ),
				'autoload' => false
			),
			array(
				'type' => 'sectionend',
				'id' => 'hezarfen_hepsijet_advanced_settings'
			),
		);
	}

	/**
	 * Check if OpenSSL extension is available
	 * 
	 * @return bool True if OpenSSL is available
	 */
	private static function is_openssl_available() {
		return extension_loaded( 'openssl' ) && function_exists( 'openssl_encrypt' );
	}

	/**
	 * Encrypt webhook secret
	 * 
	 * @param string $value Value to encrypt
	 * @return string Encrypted value
	 */
	private static function encrypt_webhook_secret( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		// Check if OpenSSL is available
		if ( ! self::is_openssl_available() ) {
			return base64_encode( $value );
		}

		// Use WordPress auth keys for encryption
		$key = AUTH_KEY . SECURE_AUTH_KEY;
		$salt = AUTH_SALT . SECURE_AUTH_SALT;
		
		// Generate encryption key
		$encryption_key = hash( 'sha256', $key );
		$iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
		$iv = substr( hash( 'sha256', $salt ), 0, $iv_length );
		
		// Encrypt the value
		$encrypted = openssl_encrypt( $value, 'aes-256-cbc', $encryption_key, 0, $iv );
		
		if ( $encrypted === false ) {
			return base64_encode( $value );
		}
		
		return base64_encode( $encrypted );
	}

	/**
	 * Decrypt webhook secret
	 * 
	 * @param string $encrypted_value Encrypted value
	 * @return string Decrypted value
	 */
	private static function decrypt_webhook_secret( $encrypted_value ) {
		if ( empty( $encrypted_value ) ) {
			return '';
		}

		$decoded = base64_decode( $encrypted_value );
		
		if ( $decoded === false ) {
			return '';
		}

		// Check if OpenSSL is available
		if ( ! self::is_openssl_available() ) {
			// Fallback: Value was stored with base64 only
			return $decoded;
		}

		// Use WordPress auth keys for decryption
		$key = AUTH_KEY . SECURE_AUTH_KEY;
		$salt = AUTH_SALT . SECURE_AUTH_SALT;
		
		// Generate decryption key
		$encryption_key = hash( 'sha256', $key );
		$iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
		$iv = substr( hash( 'sha256', $salt ), 0, $iv_length );
		
		// Decrypt the value
		$decrypted = openssl_decrypt( $decoded, 'aes-256-cbc', $encryption_key, 0, $iv );
		
		// If decryption fails, try to return the base64 decoded value (fallback scenario)
		if ( $decrypted === false ) {
			return $decoded;
		}
		
		return $decrypted;
	}

	/**
	 * Decrypt webhook secret for display in settings
	 * 
	 * @param mixed $value The option value
	 * @return string Decrypted value
	 */
	public static function decrypt_webhook_secret_for_display( $value ) {
		// Don't decrypt during save operations
		if ( isset( $_POST['save'] ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return $value;
		}

		// Only decrypt when viewing settings page (not saving)
		global $current_section;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( is_admin() && isset( $_GET['page'] ) && 'wc-settings' === sanitize_text_field( wp_unslash( $_GET['page'] ) ) && 'hepsijet_integration' === $current_section ) {
			// Get the raw encrypted value from database
			global $wpdb;
			$encrypted = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'hez_ordermigo_webhook_secret' ) );
			if ( $encrypted ) {
				return self::decrypt_webhook_secret( $encrypted );
			}
		}
		return $value;
	}

	/**
	 * Save Hepsijet settings with encryption for webhook secret
	 * 
	 * @return void
	 */
	public static function save_hepsijet_settings() {
		global $current_section;

		if ( 'hepsijet_integration' !== $current_section ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['save'] ) ) {
			return;
		}

		if ( isset( $_POST['hez_ordermigo_webhook_secret'] ) ) {
			$webhook_secret = sanitize_text_field( wp_unslash( $_POST['hez_ordermigo_webhook_secret'] ) );
			
			// Get current value to see if it changed
			$current_encrypted = get_option( 'hez_ordermigo_webhook_secret', '' );
			$current_decrypted = self::decrypt_webhook_secret( $current_encrypted );
			
			// Only save if the value actually changed
			if ( $webhook_secret !== $current_decrypted ) {
				if ( ! empty( $webhook_secret ) ) {
					// Encrypt and save with autoload disabled
					$encrypted = self::encrypt_webhook_secret( $webhook_secret );
					$result = update_option( 'hez_ordermigo_webhook_secret', $encrypted, false );
				} else {
					// Delete if empty
					delete_option( 'hez_ordermigo_webhook_secret' );
				}
			}
			
			// Prevent WooCommerce from processing this field normally
			unset( $_POST['hez_ordermigo_webhook_secret'] );
		}
		// phpcs:enable
	}

	/**
	 * Enqueues JS and CSS files.
	 * 
	 * @param string $hook_suffix Hook suffix.
	 * 
	 * @return void
	 */
	public static function enqueue_scripts_and_styles( $hook_suffix ) {
		global $current_section;

		if ( 'woocommerce_page_wc-settings' === $hook_suffix && self::SECTION === $current_section ) {
			wp_enqueue_script( 'hezarfen_mst_settings_js', HEZARFEN_MST_ASSETS_URL . 'js/admin/settings.js', array(), WC_HEZARFEN_VERSION, false );
			wp_enqueue_style( 'hezarfen_mst_settings_css', HEZARFEN_MST_ASSETS_URL . 'css/admin/settings.css', array(), WC_HEZARFEN_VERSION );

			$object_props = array(
				'netgsm_key'                => Netgsm::$id,
				'pandasms_key'              => Pandasms::$id,
				'recognize_custom_meta_key' => self::RECOG_TYPE_CUSTOM_META,
			);

			if ( ! Pandasms::is_plugin_ready() ) {
				$activate_pandasms_url = add_query_arg(
					array(
						'_wpnonce' => wp_create_nonce( 'activate-plugin_' . Pandasms::$plugin_basename ),
						'action'   => 'activate',
						'plugin'   => Pandasms::$plugin_basename,
					),
					admin_url( 'plugins.php' )
				);

				$object_props = array_merge(
					$object_props,
					array(
						'installing_text'               => __( 'Installing..', 'hezarfen-for-woocommerce' ),
						'pandasms_title'                => Pandasms::$title,
						'install_pandasms_link_text'    => __( 'Install & activate PandaSMS for Woocommerce plugin', 'hezarfen-for-woocommerce' ),
						'install_pandasms_success_text' => __( 'PandaSMS plugin is successfully installed and activated.', 'hezarfen-for-woocommerce' ),
						'install_pandasms_fail_text'    => __( 'An error occured when installing and activating the PandaSMS plugin.', 'hezarfen-for-woocommerce' ),
						'plugin_install_nonce'          => wp_create_nonce( 'updates' ),
						'activate_pandasms_url'         => $activate_pandasms_url,
						'is_pandasms_installed'         => isset( get_plugins()[ Pandasms::$plugin_basename ] ),
					)
				);
			}

			wp_localize_script(
				'hezarfen_mst_settings_js',
				'hezarfen_mst_backend',
				$object_props,
			);
		}
	}
}
