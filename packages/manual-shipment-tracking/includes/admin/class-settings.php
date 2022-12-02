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

	/**
	 * Constructor
	 * 
	 * @return void
	 */
	public function __construct() {
		$this->assign_callbacks_to_hooks();
	}

	/**
	 * Assigns callbacks to hooks.
	 * 
	 * @return void
	 */
	public function assign_callbacks_to_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_and_styles' ) );

		add_filter( 'woocommerce_get_sections_' . self::HEZARFEN_WC_SETTINGS_ID, array( $this, 'add_section' ) );
		add_filter( 'woocommerce_get_settings_' . self::HEZARFEN_WC_SETTINGS_ID, array( $this, 'add_settings_to_section' ), 10, 2 );

		add_action( 'woocommerce_settings_save_hezarfen', array( __CLASS__, 'convert_variables' ) );
	}

	/**
	 * Adds a new section to Hezarfen's settings tab.
	 * 
	 * @param array<string, string> $hezarfen_sections Hezarfen's sections.
	 * 
	 * @return array<string, string>
	 */
	public function add_section( $hezarfen_sections ) {
		$hezarfen_sections[ self::SECTION ] = __( 'Manual Shipment Tracking', 'hezarfen-for-woocommerce' );
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
	public function add_settings_to_section( $settings, $current_section ) {
		if ( self::SECTION === $current_section ) {
			add_action( 'woocommerce_admin_field_hezarfen_mst_netgsm_sms_content_textarea', array( $this, 'render_netgsm_sms_content_setting' ) );

			foreach ( Helper::get_notification_providers() as $id => $title ) {
				$label = $title;

				if ( 'netgsm' === $id ) {
					$notice = '';
					if ( ! Netgsm::is_netgsm_active() ) {
						$notice = __( 'In order to NetGSM integration work, the NetGSM plugin must be activated.', 'hezarfen-for-woocommerce' );
					} elseif ( ! Netgsm::is_netgsm_order_status_change_notif_active() ) {
						$notice = __( 'In order to NetGSM integration work, the "send SMS to the customer when order status changed" option must be activated from the NetGSM plugin settings.', 'hezarfen-for-woocommerce' );
					}

					$label = $notice ? sprintf( '%s (%s)', $label, $notice ) : $label;
				}

				$notification_providers[ $id ] = $label;
			}

			return array(
				array(
					'type'  => 'title',
					'title' => __( 'Manual Shipment Tracking General Settings', 'hezarfen-for-woocommerce' ),
				),
				array(
					'type'    => 'select',
					'title'   => __( 'Default courier company', 'hezarfen-for-woocommerce' ),
					'id'      => 'hezarfen_mst_default_courier_company',
					'options' => Helper::courier_company_options(),
				),
				array(
					'type'  => 'checkbox',
					'title' => __( 'Show Shipment Tracking column on My Account > Orders page', 'hezarfen-for-woocommerce' ),
					'id'    => 'hezarfen_mst_show_shipment_tracking_column',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'hezarfen_mst_general',
				),
				array(
					'type'  => 'title',
					'title' => __( 'SMS Notification Settings', 'hezarfen-for-woocommerce' ),
				),
				array(
					'type'  => 'checkbox',
					'title' => __( 'Enable SMS notification when order shipped', 'hezarfen-for-woocommerce' ),
					'id'    => 'hezarfen_mst_enable_sms_notification',
					'class' => 'enable-sms-notif',
				),
				array(
					'type'     => 'radio',
					'title'    => __( 'Notification Provider', 'hezarfen-for-woocommerce' ),
					'id'       => 'hezarfen_mst_notification_provider',
					'class'    => 'notification notif-provider',
					'options'  => isset( $notification_providers ) ? $notification_providers : array(),
					'disabled' => ! Netgsm::is_netgsm_ready() ? array( Netgsm::$id ) : array(),
				),
				array(
					'type'        => 'hezarfen_mst_netgsm_sms_content_textarea',
					'title'       => __( 'NetGSM SMS content', 'hezarfen-for-woocommerce' ),
					'id'          => 'hezarfen_mst_netgsm_sms_content',
					'class'       => 'notification netgsm sms-content',
					'placeholder' => __( 'Enter SMS content.', 'hezarfen-for-woocommerce' ),
				),
				array(
					'type' => 'sectionend',
					'id'   => 'hezarfen_mst_sms_notification',
				),
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
	public function render_netgsm_sms_content_setting( $setting ) {
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
					<?php disabled( ! Netgsm::is_netgsm_ready() ); ?>
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
	 * Converts hezarfen SMS variables to NetGSM metas before saving to the database.
	 * 
	 * @return void
	 */
	public static function convert_variables() {
		global $current_section;

		if ( self::SECTION === $current_section && ! empty( $_POST['hezarfen_mst_netgsm_sms_content'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_POST['hezarfen_mst_netgsm_sms_content'] = Netgsm::convert_hezarfen_variables_to_netgsm_metas( sanitize_text_field( $_POST['hezarfen_mst_netgsm_sms_content'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
	}

	/**
	 * Enqueues JS and CSS files.
	 * 
	 * @param string $hook_suffix Hook suffix.
	 * 
	 * @return void
	 */
	public function enqueue_scripts_and_styles( $hook_suffix ) {
		global $current_section;

		if ( 'woocommerce_page_wc-settings' === $hook_suffix && self::SECTION === $current_section ) {
			wp_enqueue_script( 'hezarfen_mst_settings_js', HEZARFEN_MST_ASSETS_URL . 'js/admin/settings.js', array(), WC_HEZARFEN_VERSION, false );
			wp_enqueue_style( 'hezarfen_mst_settings_css', HEZARFEN_MST_ASSETS_URL . 'css/admin/settings.css', array(), WC_HEZARFEN_VERSION );
		}
	}
}
