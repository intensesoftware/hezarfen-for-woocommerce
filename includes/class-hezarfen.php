<?php
/**
 * Contains the Hezarfen main class.
 * 
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

use Automattic\WooCommerce\Utilities\OrderUtil, Hezarfen_Roadmap_Helper;

/**
 * Hezarfen main class.
 */
class Hezarfen {
	/**
	 * Addons info
	 * 
	 * @var array<array<string, mixed>>
	 */
	private $addons;

	/**
	 * Notices related to addons.
	 * 
	 * @var array<array<string, string>>
	 */
	private $addon_notices;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->addons = array(
			array(
				'name'        => 'Mahalle Bazlı Gönderim Bedeli for Hezarfen',
				'short_name'  => 'MBGB',
				'version'     => function () {
					return defined( 'WC_HEZARFEN_MBGB_VERSION' ) ? WC_HEZARFEN_MBGB_VERSION : null;
				},
				'min_version' => WC_HEZARFEN_MIN_MBGB_VERSION,
				'activated'   => function () {
					return defined( 'WC_HEZARFEN_MBGB_VERSION' );
				},
			),
		);

		register_activation_hook( WC_HEZARFEN_FILE, array( 'Hezarfen_Install', 'install' ) );

		add_action( 'init', array( 'Hezarfen_Install', 'install' ) );
		add_action( 'plugins_loaded', array( $this, 'check_addons_and_show_notices' ) );
		add_action( 'plugins_loaded', array( $this, 'define_constants' ) );
		add_action( 'admin_notices', array( $this, 'show_migration_notice' ) );
		add_action( 'admin_notices', array( $this, 'show_roadmap_contribution_notice' ) );
		add_action( 'wp_ajax_hezarfen_dismiss_roadmap_notice', array( $this, 'handle_dismiss_roadmap_notice' ) );
		add_action( 'plugins_loaded', array( $this, 'force_enable_address2_field' ) );
		add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_hezarfen_setting_page' ) );
		add_filter( 'woocommerce_get_country_locale', array( $this, 'modify_tr_locale' ), PHP_INT_MAX - 2 );
		add_filter('woocommerce_rest_prepare_shop_order_object', array( $this, 'add_virtual_order_metas_to_metadata' ), 10, 2);
		
		// Register roadmap voting AJAX action
		add_action( 'wp_ajax_hezarfen_submit_roadmap_votes', array( $this, 'handle_roadmap_vote_submission_proxy' ) );
	}

	/**
	 * Modifies TR country locale data.
	 * 
	 * @param array<array<string, mixed>> $locales Locale data of all countries.
	 * 
	 * @return array<array<string, mixed>>
	 */
	public function modify_tr_locale( $locales ) {
		if ( 'yes' !== apply_filters( 'hezarfen_enable_district_neighborhood_fields', get_option( 'hezarfen_enable_district_neighborhood_fields', 'yes' ) ) ) {
			return $locales;
		}

		$locales['TR']['city'] = array_merge(
			$locales['TR']['city'] ?? array(),
			array(
				'label' => __( 'Town / City', 'hezarfen-for-woocommerce' ),
			)
		);

		$locales['TR']['address_1'] = array_merge(
			$locales['TR']['address_1'] ?? array(),
			array(
				'label'       => __( 'Neighborhood', 'hezarfen-for-woocommerce' ),
				'placeholder' => __( 'Select an option', 'hezarfen-for-woocommerce' ),
			)
		);

		return $locales;
	}

	/**
	 * Define constants after plugins are loaded.
	 */
	public function define_constants() {
		if ( ! defined( 'WC_HEZARFEN_HPOS_ENABLED' ) ) {
			define( 'WC_HEZARFEN_HPOS_ENABLED', OrderUtil::custom_orders_table_usage_is_enabled() );
		}
	}

	/**
	 * Checks addons and shows notices if necessary.
	 * Defines constants to disable outdated addons.
	 * 
	 * @return void
	 */
	public function check_addons_and_show_notices() {
		$this->addon_notices = Helper::check_addons( $this->addons );
		if ( $this->addon_notices ) {
			foreach ( $this->addon_notices as $notice ) {
				define( 'WC_HEZARFEN_OUTDATED_ADDON_' . $notice['addon_short_name'], true );
			}

			add_action(
				'admin_notices',
				function () {
					Helper::render_admin_notices( $this->addon_notices );
				}
			);
		}

		// Check Intense Türkiye İl İlçe Eklentisi For WooCommerce plugin.
		if ( defined( 'INTENSE_IL_ILCE_PLUGIN_PATH' ) ) {
			add_action(
				'admin_notices',
				function () {
					$notice = array(
						'message' => __( 'In order to <strong>Hezarfen for WooCommerce</strong> plugin work, please remove the <strong>Intense Türkiye İl İlçe Eklentisi For WooCommerce</strong> plugin. The <strong>Hezarfen</strong> plugin already has province, district and neighborhood data in it.', 'hezarfen-for-woocommerce' ),
						'type'    => 'error',
					);

					Helper::render_admin_notices( array( $notice ), true );
				}
			);
		}

		// Show notice if the Woocommerce Checkout Blocks feature is active.
		if ( class_exists( '\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils' ) ) {
			if ( \Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils::is_checkout_block_default() ) {
				add_action(
					'admin_notices',
					function () {
						$notice = array(
							'message' => __( "Because the Woocommerce Checkout Blocks feature is active on your website, <strong>Hezarfen for Woocommerce</strong> plugin doesn't work on the checkout page. You can revert to old checkout system easily by following <a target='_blank' href='https://woocommerce.com/document/cart-checkout-blocks-status/#reverting-to-shortcodes-with-woocommerce-8-3'>these instructions</a>.", 'hezarfen-for-woocommerce' ),
							'type'    => 'warning',
						);
	
						Helper::render_admin_notices( array( $notice ), true );
					}
				);
			}
		}
	}

	/**
	 *
	 * Load Hezarfen Settings Page
	 *
	 * @param \WC_Settings_Page[] $settings the current WC setting page objects.
	 * @return \WC_Settings_Page[]
	 */
	public function add_hezarfen_setting_page( $settings ) {
		$settings[] = include_once WC_HEZARFEN_UYGULAMA_YOLU .
			'includes/admin/settings/class-hezarfen-settings-hezarfen.php';

		return $settings;
	}

	/**
	 * Modify TC number and ensure required billing keys in WooCommerce REST API response
	 *
	 * @param WP_REST_Response $response The response object
	 * @param WC_Order $order The order object
	 * @return WP_REST_Response Modified response
	 */
	public function add_virtual_order_metas_to_metadata($response, $order) {
		// Required billing keys that should always be present
		$required_billing_keys = [
			'_billing_hez_tax_number',
			'_billing_hez_tax_office',
			'_billing_hez_TC_number'
		];
		
		// Get invoice type
		$invoice_type = $order->get_meta('_billing_hez_invoice_type', true);
		
		// Get response data
		$response_data = $response->get_data();
		
		// Ensure meta_data is an array
		if (!isset($response_data['meta_data'])) {
			$response_data['meta_data'] = [];
		}
		
		// Create a map of existing meta keys for easier lookup
		$existing_meta_keys = [];
		foreach ($response_data['meta_data'] as $index => $meta) {
			$meta_data = $meta->get_data();
			$existing_meta_keys[$meta_data['key']] = $index;
		}
		
		// Process TC number if invoice type is person
		if ('person' === $invoice_type) {
			$encrypted_tc_number = $order->get_meta('_billing_hez_TC_number', true);
			if ($encrypted_tc_number) {
				$decrypted_tc_number = (new \Hezarfen\Inc\Data\PostMetaEncryption())->decrypt($encrypted_tc_number);
				
				// Update TC number in response if it exists
				if (isset($existing_meta_keys['_billing_hez_TC_number'])) {
					$index = $existing_meta_keys['_billing_hez_TC_number'];
					$meta_data = $response_data['meta_data'][$index]->get_data();
					$response_data['meta_data'][$index] = [
						'id' => $meta_data['id'],
						'key' => '_billing_hez_TC_number',
						'value' => $decrypted_tc_number
					];
				}
			}
		}
		
		// Ensure all required billing keys exist
		foreach ($required_billing_keys as $key) {
			if (!isset($existing_meta_keys[$key])) {
				// Add empty meta data for missing keys
				$response_data['meta_data'][] = [
					'id' => 0, // You might want to generate a proper ID if needed
					'key' => $key,
					'value' => ''
				];
			}
		}
		
		// Set modified data back to response
		$response->set_data($response_data);
		return $response;
	}
	
	/**
	 * Show migration notice when SMS settings are migrated
	 *
	 * @return void
	 */
	public function show_migration_notice() {
		if ( get_transient( 'hezarfen_sms_migration_notice' ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<strong><?php esc_html_e( 'Hezarfen SMS Migration Complete!', 'hezarfen-for-woocommerce' ); ?></strong>
					<?php 
					printf( 
						esc_html__( 'Your legacy SMS settings have been automatically migrated to the new SMS automation system and SMS automation has been enabled. %sView SMS Settings%s', 'hezarfen-for-woocommerce' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=hezarfen&section=sms_settings' ) ) . '">',
						'</a>'
					);
					?>
				</p>
			</div>
			<?php
			// Delete the transient so it only shows once
			delete_transient( 'hezarfen_sms_migration_notice' );
		}
	}

	/**
	 * Silently force enable address_2 field if it's hidden
	 * No admin notices will be shown
	 *
	 * @return void
	 */
	public function force_enable_address2_field() {
		// Only run if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		if ( 'yes' !== apply_filters( 'hezarfen_enable_district_neighborhood_fields', get_option( 'hezarfen_enable_district_neighborhood_fields', 'yes' ) ) ) {
			return;
		}

		// Check if address_2 field is hidden
		$address_2_visibility = get_option( 'woocommerce_checkout_address_2_field', 'optional' );
		
		// If address_2 field is hidden, silently enable it
		if ( 'hidden' === $address_2_visibility ) {
			update_option( 'woocommerce_checkout_address_2_field', 'optional' );
		}
	}

	/**
	 * Handle roadmap vote submission via AJAX
	 *
	 * @return void
	 */
	public function handle_roadmap_vote_submission_proxy() {
		// Check if roadmap voting is available for this version
		if ( version_compare( WC_HEZARFEN_VERSION, '2.7.40', '>' ) ) {
			wp_send_json_error( array( 'message' => __( 'Roadmap oylaması bu sürümde artık mevcut değil.', 'hezarfen-for-woocommerce' ) ) );
			return;
		}
		
		// Test response to verify function is called
		if ( ! isset( $_POST['nonce'] ) ) {
			wp_send_json_error( array( 'message' => 'TEST: No nonce provided' ) );
			return;
		}
		

		// Verify nonce
		$nonce_check = wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'hezarfen_roadmap_vote' );

		if ( ! $nonce_check ) {
			wp_send_json_error( array( 'message' => __( 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyin.', 'hezarfen-for-woocommerce' ) ) );
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Yetkisiz erişim.', 'hezarfen-for-woocommerce' ) ) );
			return;
		}

		$free_features = isset( $_POST['free_features'] ) ? array_map( 'intval', (array) $_POST['free_features'] ) : array();
		$pro_features = isset( $_POST['pro_features'] ) ? array_map( 'intval', (array) $_POST['pro_features'] ) : array();
		$details = isset( $_POST['details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['details'] ) ) : '';
		
		// Validate limits
		if ( count( $free_features ) > 5 ) {
			wp_send_json_error( array( 'message' => __( 'En fazla 5 ücretsiz özellik seçebilirsiniz.', 'hezarfen-for-woocommerce' ) ) );
			return;
		}

		if ( count( $pro_features ) > 5 ) {
			wp_send_json_error( array( 'message' => __( 'En fazla 5 ücretli özellik seçebilirsiniz.', 'hezarfen-for-woocommerce' ) ) );
			return;
		}

		// Include the settings file to get the feature methods
		require_once WC_HEZARFEN_UYGULAMA_YOLU . 'includes/admin/settings/class-hezarfen-roadmap-helper.php';
		
		$all_free_features = Hezarfen_Roadmap_Helper::get_free_features();
		$all_pro_features = Hezarfen_Roadmap_Helper::get_pro_features();

		// Prepare data
		$domain = parse_url( home_url(), PHP_URL_HOST );
		$timestamp = current_time( 'mysql' );

		// Build email content
		$selected_free_features = array();
		foreach ( $free_features as $index ) {
			if ( isset( $all_free_features[ $index ] ) ) {
				$selected_free_features[] = $all_free_features[ $index ];
			}
		}

		$selected_pro_features = array();
		foreach ( $pro_features as $index ) {
			if ( isset( $all_pro_features[ $index ] ) ) {
				$selected_pro_features[] = $all_pro_features[ $index ];
			}
		}

		// Create email body
		$email_subject = sprintf( 'Hezarfen v3.0 Roadmap Oyları - %s', $domain );
		
		$email_body = "Hezarfen v3.0 Geliştirme Yol Haritası Oyları\n\n";
		$email_body .= "Alan Adı: " . $domain . "\n";
		$email_body .= "Tarih: " . $timestamp . "\n\n";
		
		$email_body .= "=== ÜCRETSİZ SÜRÜM ÖZELLİKLERİ (" . count( $selected_free_features ) . "/5) ===\n\n";
		if ( ! empty( $selected_free_features ) ) {
			foreach ( $selected_free_features as $i => $feature ) {
				$email_body .= ( $i + 1 ) . ". " . $feature . "\n";
			}
		} else {
			$email_body .= "Seçim yapılmadı\n";
		}
		
		$email_body .= "\n=== ÜCRETLİ SÜRÜM ÖZELLİKLERİ (" . count( $selected_pro_features ) . "/5) ===\n\n";
		if ( ! empty( $selected_pro_features ) ) {
			foreach ( $selected_pro_features as $i => $feature ) {
				$email_body .= ( $i + 1 ) . ". " . $feature . "\n";
			}
		} else {
			$email_body .= "Seçim yapılmadı\n";
		}
		
		// Add details if provided
		if ( ! empty( $details ) ) {
			$email_body .= "\n=== EK BİLGİ VE ÖNERİLER ===\n\n";
			$email_body .= $details . "\n";
		}

		// Send email
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$email_sent = wp_mail( 'info@intense.com.tr', $email_subject, $email_body, $headers );

		if ( ! $email_sent ) {
			wp_send_json_error( array( 
				'message' => __( 'E-posta gönderimi başarısız oldu. Lütfen daha sonra tekrar deneyin.', 'hezarfen-for-woocommerce' )
			) );
			return;
		}

		// Save locally for reference
		$data = array(
			'domain' => $domain,
			'free_features' => $free_features,
			'pro_features' => $pro_features,
			'details' => $details,
			'timestamp' => $timestamp,
		);
		
		update_option( 'hezarfen_roadmap_votes', $data );
		update_option( 'hezarfen_v3_roadmap_last_vote', current_time( 'timestamp' ) );

		wp_send_json_success( array(
			'message' => __( 'Oylarınız info@intense.com.tr adresine e-posta ile gönderildi. Teşekkür ederiz!', 'hezarfen-for-woocommerce' )
		) );
	}

	/**
	 * Show roadmap contribution notice
	 *
	 * @return void
	 */
	public function show_roadmap_contribution_notice() {
		// Only show if version <= 2.7.40
		if ( version_compare( WC_HEZARFEN_VERSION, '2.7.40', '>' ) ) {
			return;
		}

		// Don't show if user has already voted
		if ( get_option( 'hezarfen_roadmap_votes', false ) ) {
			return;
		}

		// Don't show if user has dismissed it
		if ( get_option( 'hezarfen_roadmap_notice_dismissed', false ) ) {
			return;
		}

		// Only show on admin pages
		if ( ! is_admin() ) {
			return;
		}

		// Get current screen
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// Show on WooCommerce related pages
		if ( strpos( $screen->id, 'woocommerce' ) === false && strpos( $screen->id, 'shop' ) === false ) {
			return;
		}

		?>
		<div class="notice notice-info is-dismissible hezarfen-roadmap-notice" data-notice="roadmap-contribution">
			<p>
				<strong><?php esc_html_e( '🗺️ Hezarfen v3.0 Geliştirme Yol Haritası', 'hezarfen-for-woocommerce' ); ?></strong>
			</p>
			<p>
				<?php esc_html_e( 'Hezarfen\'in geleceğini şekillendirmemize yardımcı olun! Hangi özelliklerin geliştirilmesini istediğinizi belirtin.', 'hezarfen-for-woocommerce' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=hezarfen' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Oylamaya Katıl', 'hezarfen-for-woocommerce' ); ?>
				</a>
			</p>
		</div>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$(document).on('click', '.hezarfen-roadmap-notice .notice-dismiss', function() {
				$.post(ajaxurl, {
					action: 'hezarfen_dismiss_roadmap_notice',
					nonce: '<?php echo esc_js( wp_create_nonce( 'hezarfen_dismiss_roadmap_notice' ) ); ?>'
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Handle dismiss roadmap notice AJAX request
	 *
	 * @return void
	 */
	public function handle_dismiss_roadmap_notice() {
		check_ajax_referer( 'hezarfen_dismiss_roadmap_notice', 'nonce' );
		
		update_option( 'hezarfen_roadmap_notice_dismissed', true );
		
		wp_send_json_success();
	}
}

new Hezarfen();
