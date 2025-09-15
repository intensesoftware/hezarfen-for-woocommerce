<?php
/**
 * SMS Automation Class
 *
 * Handles SMS automation for WooCommerce order status changes
 *
 * @package Hezarfen
 */

defined( 'ABSPATH' ) || exit;

/**
 * SMS Automation class
 */
class SMS_Automation {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_change' ), 10, 4 );
		add_action( 'hezarfen_mst_shipment_data_saved', array( $this, 'handle_order_shipped' ), 10, 2 );
		add_action( 'wp_ajax_hezarfen_save_sms_rules', array( $this, 'ajax_save_sms_rules' ) );
		add_action( 'wp_ajax_hezarfen_get_sms_rules', array( $this, 'ajax_get_sms_rules' ) );
		add_action( 'wp_ajax_hezarfen_save_netgsm_credentials', array( $this, 'ajax_save_netgsm_credentials' ) );
		add_action( 'wp_ajax_hezarfen_get_netgsm_credentials', array( $this, 'ajax_get_netgsm_credentials' ) );
		add_action( 'wp_ajax_hezarfen_get_netgsm_senders', array( $this, 'ajax_get_netgsm_senders' ) );
	}

	/**
	 * Handle order status change
	 *
	 * @param int    $order_id Order ID
	 * @param string $old_status Old status
	 * @param string $new_status New status
	 * @param \WC_Order $order Order object
	 * @return void
	 */
	public function handle_order_status_change( $order_id, $old_status, $new_status, $order ) {
		$rules = get_option( 'hezarfen_sms_rules', array() );

		if ( empty( $rules ) ) {
			return;
		}

		// Normalize status for comparison (remove wc- prefix if present)
		$normalized_new_status = str_replace( 'wc-', '', $new_status );

		foreach ( $rules as $rule ) {
			if ( isset( $rule['condition_status'] ) ) {
				// Normalize rule condition status for comparison
				$normalized_rule_status = str_replace( 'wc-', '', $rule['condition_status'] );
				
				if ( $normalized_rule_status === $normalized_new_status ) {
					if ( isset( $rule['action_type'] ) && $rule['action_type'] === 'netgsm' ) {
						$this->send_sms_for_rule( $order, $rule );
					} elseif ( isset( $rule['action_type'] ) && $rule['action_type'] === 'netgsm_legacy' ) {
						$this->send_sms_for_legacy_rule( $order, $rule );
					} elseif ( isset( $rule['action_type'] ) && $rule['action_type'] === 'pandasms_legacy' ) {
						$this->send_sms_for_pandasms_legacy_rule( $order, $rule );
					}
				}
			}
		}
	}

	/**
	 * Handle order shipped event
	 *
	 * @param \WC_Order $order Order object
	 * @param object $shipment_data Shipment data object
	 * @return void
	 */
	public function handle_order_shipped( $order, $shipment_data ) {
		$rules = get_option( 'hezarfen_sms_rules', array() );

		if ( empty( $rules ) ) {
			return;
		}

		foreach ( $rules as $rule ) {
			if ( isset( $rule['condition_status'] ) && $rule['condition_status'] === 'hezarfen_order_shipped' ) {
				if ( isset( $rule['action_type'] ) && $rule['action_type'] === 'netgsm' ) {
					$this->send_sms_for_shipment_rule( $order, $rule, $shipment_data );
				} elseif ( isset( $rule['action_type'] ) && $rule['action_type'] === 'netgsm_legacy' ) {
					$this->send_sms_for_legacy_shipment_rule( $order, $rule, $shipment_data );
				} elseif ( isset( $rule['action_type'] ) && $rule['action_type'] === 'pandasms_legacy' ) {
					$this->send_sms_for_pandasms_legacy_shipment_rule( $order, $rule, $shipment_data );
				}
			}
		}

		// Trigger the new action for other plugins to hook into
		do_action( 'hezarfen_order_shipped', $order, $shipment_data );
	}

	/**
	 * Send SMS for a specific rule
	 *
	 * @param \WC_Order $order Order object
	 * @param array $rule SMS rule
	 * @return bool
	 */
	private function send_sms_for_rule( $order, $rule ) {
		// Get global NetGSM credentials
		$credentials = self::get_global_netgsm_credentials();
		if ( ! $credentials ) {
			return false;
		}

		$username = $credentials['username'] ?? '';
		$password = $credentials['password'] ?? '';
		$msgheader = $credentials['msgheader'] ?? '';

		if ( empty( $username ) || empty( $password ) || empty( $msgheader ) ) {
			return false;
		}

		// Get phone number based on rule
		$phone = $this->get_phone_number( $order, $rule['phone_type'] );
		if ( empty( $phone ) ) {
			return false;
		}

		// Process message template (will automatically include shipment data if available)
		$message = $this->process_message_template( $order, $rule['message_template'] );

		// Prepare SMS data
		$data = array(
			'msgheader' => $msgheader,
			'messages' => array(
				array(
					'msg' => $message,
					'no' => $phone
				)
			),
			'encoding' => 'TR',
			'iysfilter' => $rule['iys_status'] ?? '0',
			'partnercode' => ''
		);

		// Send SMS via NetGSM API
		$sms_result = $this->send_netgsm_sms( $data, $username, $password );
		
		// Log SMS attempt and mark as sent if successful
		$this->log_sms_attempt( $order, $rule, $phone, $message, $sms_result );
		
		// Mark SMS as sent and add order note if successful
		if ( $sms_result['success'] ) {
			$this->mark_sms_sent( $order, $rule, $phone, $message, $sms_result['jobid'] ?? null );
		}
		
		return $sms_result['success'];
	}

	/**
	 * Send SMS for a specific shipment rule
	 *
	 * @param \WC_Order $order Order object
	 * @param array $rule SMS rule
	 * @param object $shipment_data Shipment data object
	 * @return bool
	 */
	private function send_sms_for_shipment_rule( $order, $rule, $shipment_data ) {
		// Store shipment data temporarily for message processing
		$this->current_shipment_data = $shipment_data;
		
		// Use the existing send_sms_for_rule method
		$result = $this->send_sms_for_rule( $order, $rule );
		
		// Clean up temporary data
		unset( $this->current_shipment_data );
		
		return $result;
	}

	/**
	 * Send SMS for a legacy rule (using NetGSM official plugin)
	 *
	 * @param \WC_Order $order Order object
	 * @param array $rule SMS rule
	 * @return bool
	 */
	private function send_sms_for_legacy_rule( $order, $rule ) {
		// Check if NetGSM plugin is available
		if ( ! \Hezarfen\ManualShipmentTracking\Netgsm::is_netgsm_active() ) {
			return false;
		}

		// Get phone number based on rule
		$phone = $this->get_phone_number( $order, $rule['phone_type'] );
		if ( empty( $phone ) ) {
			return false;
		}

		// Get the legacy message template from Manual Shipment Tracking settings
		$legacy_content = get_option( \Hezarfen\ManualShipmentTracking\Settings::OPT_NETGSM_CONTENT, '' );
		$message = $legacy_content ? \Hezarfen\ManualShipmentTracking\Netgsm::convert_netgsm_metas_to_hezarfen_variables( $legacy_content ) : '';
		if ( empty( $message ) ) {
			return false;
		}

		// Process message template with NetGSM variables
		$processed_message = $this->process_legacy_message_template( $order, $message );

		// Create a temporary shipment data object for legacy compatibility
		$temp_shipment_data = new \Hezarfen\ManualShipmentTracking\Shipment_Data( array(
			'order_id' => $order->get_id(),
			'courier_title' => '',
			'tracking_num' => '',
			'tracking_url' => '',
			'sms_sent' => false
		) );

		// Use the legacy NetGSM class to send SMS
		$netgsm_provider = new \Hezarfen\ManualShipmentTracking\Netgsm();
		$result = $netgsm_provider->perform_sending( $order, $temp_shipment_data );

		// Mark SMS as sent if successful
		if ( $result ) {
			$this->mark_sms_sent( $order, $rule, $phone, $processed_message );
		}

		return $result;
	}

	/**
	 * Send SMS for a legacy shipment rule (using NetGSM official plugin)
	 *
	 * @param \WC_Order $order Order object
	 * @param array $rule SMS rule
	 * @param object $shipment_data Shipment data object
	 * @return bool
	 */
	private function send_sms_for_legacy_shipment_rule( $order, $rule, $shipment_data ) {
		// Check if NetGSM plugin is available
		if ( ! \Hezarfen\ManualShipmentTracking\Netgsm::is_netgsm_active() ) {
			return false;
		}

		// Store shipment data temporarily for message processing and mark_sms_sent
		$this->current_shipment_data = $shipment_data;

		// Get phone number based on rule
		$phone = $this->get_phone_number( $order, $rule['phone_type'] );
		if ( empty( $phone ) ) {
			unset( $this->current_shipment_data );
			return false;
		}

		// Get the legacy message template from Manual Shipment Tracking settings
		$legacy_content = get_option( \Hezarfen\ManualShipmentTracking\Settings::OPT_NETGSM_CONTENT, '' );
		$message = $legacy_content ? \Hezarfen\ManualShipmentTracking\Netgsm::convert_netgsm_metas_to_hezarfen_variables( $legacy_content ) : '';
		if ( empty( $message ) ) {
			unset( $this->current_shipment_data );
			return false;
		}

		// Process message template with NetGSM variables and shipment data
		$processed_message = $this->process_legacy_message_template( $order, $message, $shipment_data );

		// Use the legacy NetGSM class to send SMS
		$netgsm_provider = new \Hezarfen\ManualShipmentTracking\Netgsm();
		$result = $netgsm_provider->perform_sending( $order, $shipment_data );

		// Mark SMS as sent if successful
		if ( $result ) {
			$this->mark_sms_sent( $order, $rule, $phone, $processed_message );
		}

		// Clean up temporary data
		unset( $this->current_shipment_data );

		return $result;
	}

	/**
	 * Send SMS for a PandaSMS legacy rule (using PandaSMS official plugin)
	 *
	 * @param \WC_Order $order Order object
	 * @param array $rule SMS rule
	 * @return bool
	 */
	private function send_sms_for_pandasms_legacy_rule( $order, $rule ) {
		// Check if PandaSMS plugin is available
		if ( ! \Hezarfen\ManualShipmentTracking\Pandasms::is_plugin_ready() ) {
			return false;
		}

		// Get phone number based on rule
		$phone = $this->get_phone_number( $order, $rule['phone_type'] );
		if ( empty( $phone ) ) {
			return false;
		}

		// Create a temporary shipment data object for legacy compatibility
		$temp_shipment_data = new \Hezarfen\ManualShipmentTracking\Shipment_Data( array(
			'order_id' => $order->get_id(),
			'courier_title' => '',
			'tracking_num' => '',
			'tracking_url' => '',
			'sms_sent' => false
		) );

		// Use the legacy PandaSMS class to send SMS
		$pandasms_provider = new \Hezarfen\ManualShipmentTracking\Pandasms();
		$result = $pandasms_provider->perform_sending( $order, $temp_shipment_data );

		// Mark SMS as sent if successful
		if ( $result ) {
			$this->mark_sms_sent( $order, $rule, $phone, 'PandaSMS Legacy SMS' );
		}

		return $result;
	}

	/**
	 * Send SMS for a PandaSMS legacy shipment rule (using PandaSMS official plugin)
	 *
	 * @param \WC_Order $order Order object
	 * @param array $rule SMS rule
	 * @param object $shipment_data Shipment data object
	 * @return bool
	 */
	private function send_sms_for_pandasms_legacy_shipment_rule( $order, $rule, $shipment_data ) {
		// Check if PandaSMS plugin is available
		if ( ! \Hezarfen\ManualShipmentTracking\Pandasms::is_plugin_ready() ) {
			return false;
		}

		// Store shipment data temporarily for message processing and mark_sms_sent
		$this->current_shipment_data = $shipment_data;

		// Get phone number based on rule
		$phone = $this->get_phone_number( $order, $rule['phone_type'] );
		if ( empty( $phone ) ) {
			unset( $this->current_shipment_data );
			return false;
		}

		// Use the legacy PandaSMS class to send SMS
		$pandasms_provider = new \Hezarfen\ManualShipmentTracking\Pandasms();
		$result = $pandasms_provider->perform_sending( $order, $shipment_data );

		// Mark SMS as sent if successful
		if ( $result ) {
			$this->mark_sms_sent( $order, $rule, $phone, 'PandaSMS Legacy SMS with shipment data' );
		}

		// Clean up temporary data
		unset( $this->current_shipment_data );

		return $result;
	}

	/**
	 * Get phone number from order
	 *
	 * @param \WC_Order $order Order object
	 * @param string $phone_type Phone type (billing or shipping)
	 * @return string
	 */
	private function get_phone_number( $order, $phone_type ) {
		if ( $phone_type === 'billing' ) {
			return $order->get_billing_phone();
		} elseif ( $phone_type === 'shipping' ) {
			return $order->get_shipping_phone();
		}
		return '';
	}

	/**
	 * Get available SMS variables with their descriptions for translation
	 *
	 * @return array Array of variable descriptions for translation
	 */
	public static function get_sms_variable_descriptions() {
		return array(
			// Turkish variables (legacy)
			'{siparis_no}' => __( 'Order Number', 'hezarfen-for-woocommerce' ),
			'{uye_adi}' => __( 'Customer First Name', 'hezarfen-for-woocommerce' ),
			'{uye_soyadi}' => __( 'Customer Last Name', 'hezarfen-for-woocommerce' ),
			'{uye_telefonu}' => __( 'Customer Phone', 'hezarfen-for-woocommerce' ),
			'{uye_epostasi}' => __( 'Customer Email', 'hezarfen-for-woocommerce' ),
			'{kullanici_adi}' => __( 'Customer Full Name', 'hezarfen-for-woocommerce' ),
			'{tarih}' => __( 'Order Date', 'hezarfen-for-woocommerce' ),
			'{saat}' => __( 'Order Time', 'hezarfen-for-woocommerce' ),
			'{kargo_firmasi}' => __( 'Courier Company', 'hezarfen-for-woocommerce' ),
			'{takip_kodu}' => __( 'Tracking Number', 'hezarfen-for-woocommerce' ),
			'{takip_linki}' => __( 'Tracking URL', 'hezarfen-for-woocommerce' ),
			
			// New Turkish variables
			'{siparis_durumu}' => __( 'Order Status', 'hezarfen-for-woocommerce' ),
			'{siparis_toplami}' => __( 'Order Total', 'hezarfen-for-woocommerce' ),
			'{fatura_adi}' => __( 'Billing First Name', 'hezarfen-for-woocommerce' ),
			'{fatura_soyadi}' => __( 'Billing Last Name', 'hezarfen-for-woocommerce' ),
			'{fatura_telefonu}' => __( 'Billing Phone', 'hezarfen-for-woocommerce' ),
			'{fatura_epostasi}' => __( 'Billing Email', 'hezarfen-for-woocommerce' ),
			'{fatura_sirket}' => __( 'Billing Company', 'hezarfen-for-woocommerce' ),
			'{fatura_adresi}' => __( 'Billing Address', 'hezarfen-for-woocommerce' ),
			'{fatura_sehir}' => __( 'Billing City', 'hezarfen-for-woocommerce' ),
			'{fatura_ulke}' => __( 'Billing Country', 'hezarfen-for-woocommerce' ),
			'{kargo_adi}' => __( 'Shipping First Name', 'hezarfen-for-woocommerce' ),
			'{kargo_soyadi}' => __( 'Shipping Last Name', 'hezarfen-for-woocommerce' ),
			'{kargo_telefonu}' => __( 'Shipping Phone', 'hezarfen-for-woocommerce' ),
			'{kargo_sirket}' => __( 'Shipping Company', 'hezarfen-for-woocommerce' ),
			'{kargo_adresi}' => __( 'Shipping Address', 'hezarfen-for-woocommerce' ),
			'{kargo_sehir}' => __( 'Shipping City', 'hezarfen-for-woocommerce' ),
			'{kargo_ulke}' => __( 'Shipping Country', 'hezarfen-for-woocommerce' ),
			
			// English variables
			'{order_number}' => __( 'Order Number', 'hezarfen-for-woocommerce' ),
			'{customer_name}' => __( 'Customer Full Name', 'hezarfen-for-woocommerce' ),
			'{order_status}' => __( 'Order Status', 'hezarfen-for-woocommerce' ),
			'{order_total}' => __( 'Order Total', 'hezarfen-for-woocommerce' ),
			'{order_date}' => __( 'Order Date', 'hezarfen-for-woocommerce' ),
			'{order_time}' => __( 'Order Time', 'hezarfen-for-woocommerce' ),
			
			// Billing variables
			'{billing_first_name}' => __( 'Billing First Name', 'hezarfen-for-woocommerce' ),
			'{billing_last_name}' => __( 'Billing Last Name', 'hezarfen-for-woocommerce' ),
			'{billing_phone}' => __( 'Billing Phone', 'hezarfen-for-woocommerce' ),
			'{billing_email}' => __( 'Billing Email', 'hezarfen-for-woocommerce' ),
			'{billing_company}' => __( 'Billing Company', 'hezarfen-for-woocommerce' ),
			'{billing_address}' => __( 'Billing Address', 'hezarfen-for-woocommerce' ),
			'{billing_city}' => __( 'Billing City', 'hezarfen-for-woocommerce' ),
			'{billing_country}' => __( 'Billing Country', 'hezarfen-for-woocommerce' ),
			
			// Shipping variables
			'{shipping_first_name}' => __( 'Shipping First Name', 'hezarfen-for-woocommerce' ),
			'{shipping_last_name}' => __( 'Shipping Last Name', 'hezarfen-for-woocommerce' ),
			'{shipping_phone}' => __( 'Shipping Phone', 'hezarfen-for-woocommerce' ),
			'{shipping_company}' => __( 'Shipping Company', 'hezarfen-for-woocommerce' ),
			'{shipping_address}' => __( 'Shipping Address', 'hezarfen-for-woocommerce' ),
			'{shipping_city}' => __( 'Shipping City', 'hezarfen-for-woocommerce' ),
			'{shipping_country}' => __( 'Shipping Country', 'hezarfen-for-woocommerce' ),
			
			// Shipment variables
			'{courier_company}' => __( 'Courier Company', 'hezarfen-for-woocommerce' ),
			'{tracking_number}' => __( 'Tracking Number', 'hezarfen-for-woocommerce' ),
			'{tracking_url}' => __( 'Tracking URL', 'hezarfen-for-woocommerce' ),
		);
	}

	/**
	 * Get localized SMS variable names (for different languages)
	 *
	 * @return array Array of localized variable names
	 */
	public static function get_localized_sms_variables() {
		$locale = get_locale();
		
		// For Turkish locale, provide Turkish variable names
		if ( $locale === 'tr_TR' ) {
			return array(
				'{order_number}' => '{siparis_no}',
				'{customer_name}' => '{kullanici_adi}',
				'{customer_first_name}' => '{uye_adi}',
				'{customer_last_name}' => '{uye_soyadi}',
				'{customer_phone}' => '{uye_telefonu}',
				'{customer_email}' => '{uye_epostasi}',
				'{order_date}' => '{tarih}',
				'{order_time}' => '{saat}',
				'{order_status}' => '{siparis_durumu}',
				'{order_total}' => '{siparis_toplami}',
				'{billing_first_name}' => '{fatura_adi}',
				'{billing_last_name}' => '{fatura_soyadi}',
				'{billing_phone}' => '{fatura_telefonu}',
				'{billing_email}' => '{fatura_epostasi}',
				'{billing_company}' => '{fatura_sirket}',
				'{billing_address}' => '{fatura_adresi}',
				'{billing_city}' => '{fatura_sehir}',
				'{billing_country}' => '{fatura_ulke}',
				'{shipping_first_name}' => '{kargo_adi}',
				'{shipping_last_name}' => '{kargo_soyadi}',
				'{shipping_phone}' => '{kargo_telefonu}',
				'{shipping_company}' => '{kargo_sirket}',
				'{shipping_address}' => '{kargo_adresi}',
				'{shipping_city}' => '{kargo_sehir}',
				'{shipping_country}' => '{kargo_ulke}',
				'{courier_company}' => '{kargo_firmasi}',
				'{tracking_number}' => '{takip_kodu}',
				'{tracking_url}' => '{takip_linki}',
			);
		}
		
		// For other locales, return English variables as default
		return array();
	}

	/**
	 * Get formatted variable list for display in admin interface
	 *
	 * @param bool $include_shipment_vars Whether to include shipment-specific variables
	 * @return string HTML formatted variable list
	 */
	public static function get_formatted_variable_list( $include_shipment_vars = true ) {
		$descriptions = self::get_sms_variable_descriptions();
		$localized_vars = self::get_localized_sms_variables();
		
		$output = '<div class="hezarfen-sms-variables">';
		$output .= '<h4>' . __( 'Available Variables', 'hezarfen-for-woocommerce' ) . '</h4>';
		
		// Group variables by category - use Turkish variables for Turkish locale
		$is_turkish = ( get_locale() === 'tr_TR' );
		
		$categories = array(
			'order' => array(
				'title' => __( 'Order Variables', 'hezarfen-for-woocommerce' ),
				'vars' => $is_turkish ? 
					array( '{siparis_no}', '{kullanici_adi}', '{siparis_durumu}', '{siparis_toplami}', '{tarih}', '{saat}' ) :
					array( '{order_number}', '{customer_name}', '{order_status}', '{order_total}', '{order_date}', '{order_time}' )
			),
			'billing' => array(
				'title' => __( 'Billing Variables', 'hezarfen-for-woocommerce' ),
				'vars' => $is_turkish ?
					array( '{fatura_adi}', '{fatura_soyadi}', '{fatura_telefonu}', '{fatura_epostasi}', '{fatura_sirket}', '{fatura_adresi}', '{fatura_sehir}', '{fatura_ulke}' ) :
					array( '{billing_first_name}', '{billing_last_name}', '{billing_phone}', '{billing_email}', '{billing_company}', '{billing_address}', '{billing_city}', '{billing_country}' )
			),
			'shipping' => array(
				'title' => __( 'Shipping Variables', 'hezarfen-for-woocommerce' ),
				'vars' => $is_turkish ?
					array( '{kargo_adi}', '{kargo_soyadi}', '{kargo_telefonu}', '{kargo_sirket}', '{kargo_adresi}', '{kargo_sehir}', '{kargo_ulke}' ) :
					array( '{shipping_first_name}', '{shipping_last_name}', '{shipping_phone}', '{shipping_company}', '{shipping_address}', '{shipping_city}', '{shipping_country}' )
			)
		);
		
		if ( $include_shipment_vars ) {
			$categories['shipment'] = array(
				'title' => __( 'Shipment Variables', 'hezarfen-for-woocommerce' ),
				'vars' => $is_turkish ?
					array( '{kargo_firmasi}', '{takip_kodu}', '{takip_linki}' ) :
					array( '{courier_company}', '{tracking_number}', '{tracking_url}' )
			);
		}
		
		foreach ( $categories as $category ) {
			$output .= '<div class="variable-category">';
			$output .= '<h5>' . $category['title'] . '</h5>';
			$output .= '<ul class="variable-list">';
			
			foreach ( $category['vars'] as $var ) {
				if ( isset( $descriptions[ $var ] ) ) {
					$output .= '<li>';
					$output .= '<code class="variable-code" data-variable="' . esc_attr( $var ) . '">' . esc_html( $var ) . '</code>';
					$output .= ' - ' . esc_html( $descriptions[ $var ] );
					$output .= '</li>';
				}
			}
			
			$output .= '</ul>';
			$output .= '</div>';
		}
		
		// Add legacy Turkish variables section if locale is Turkish
		if ( get_locale() === 'tr_TR' ) {
			$output .= '<div class="variable-category">';
			$output .= '<h5>' . __( 'Legacy Turkish Variables', 'hezarfen-for-woocommerce' ) . '</h5>';
			$output .= '<ul class="variable-list">';
			
			$legacy_vars = array( '{siparis_no}', '{uye_adi}', '{uye_soyadi}', '{uye_telefonu}', '{uye_epostasi}', '{kullanici_adi}', '{tarih}', '{saat}' );
			if ( $include_shipment_vars ) {
				$legacy_vars = array_merge( $legacy_vars, array( '{kargo_firmasi}', '{takip_kodu}', '{takip_linki}' ) );
			}
			
			foreach ( $legacy_vars as $var ) {
				if ( isset( $descriptions[ $var ] ) ) {
					$output .= '<li>';
					$output .= '<code class="variable-code" data-variable="' . esc_attr( $var ) . '">' . esc_html( $var ) . '</code>';
					$output .= ' - ' . esc_html( $descriptions[ $var ] );
					$output .= '</li>';
				}
			}
			
			$output .= '</ul>';
			$output .= '</div>';
		}
		
		$output .= '<p class="description">' . __( 'Click on any variable to copy it to your clipboard.', 'hezarfen-for-woocommerce' ) . '</p>';
		$output .= '</div>';
		
		return $output;
	}

	/**
	 * Get translatable order status names
	 *
	 * @return array Array of translatable status names
	 */
	public static function get_translatable_order_status_names() {
		return array(
			'pending' => __( 'Pending payment', 'hezarfen-for-woocommerce' ),
			'processing' => __( 'Processing', 'hezarfen-for-woocommerce' ),
			'on-hold' => __( 'On hold', 'hezarfen-for-woocommerce' ),
			'completed' => __( 'Completed', 'hezarfen-for-woocommerce' ),
			'cancelled' => __( 'Cancelled', 'hezarfen-for-woocommerce' ),
			'refunded' => __( 'Refunded', 'hezarfen-for-woocommerce' ),
			'failed' => __( 'Failed', 'hezarfen-for-woocommerce' ),
			'checkout-draft' => __( 'Draft', 'hezarfen-for-woocommerce' ),
			'hezarfen_order_shipped' => __( 'Order Shipped', 'hezarfen-for-woocommerce' ),
		);
	}

	/**
	 * Get translatable SMS action type names
	 *
	 * @return array Array of translatable action type names
	 */
	public static function get_translatable_action_type_names() {
		return array(
			'netgsm' => __( 'NetGSM', 'hezarfen-for-woocommerce' ),
			'netgsm_legacy' => __( 'NetGSM Official Plugin (Legacy)', 'hezarfen-for-woocommerce' ),
			'pandasms_legacy' => __( 'PandaSMS Official Plugin (Legacy)', 'hezarfen-for-woocommerce' ),
		);
	}

	/**
	 * Get translatable phone type names
	 *
	 * @return array Array of translatable phone type names
	 */
	public static function get_translatable_phone_type_names() {
		return array(
			'billing' => __( 'Billing Phone', 'hezarfen-for-woocommerce' ),
			'shipping' => __( 'Shipping Phone', 'hezarfen-for-woocommerce' ),
		);
	}

	/**
	 * Output available variables for admin interface
	 * This method can be called from settings pages to display available variables
	 *
	 * @param bool $include_shipment_vars Whether to include shipment variables
	 * @return void
	 */
	public static function output_available_variables( $include_shipment_vars = true ) {
		$descriptions = self::get_sms_variable_descriptions();
		$is_turkish = ( get_locale() === 'tr_TR' );
		
		// Group variables by category - use Turkish variables for Turkish locale
		$categories = array(
			'order' => array(
				'title' => __( 'Order Variables', 'hezarfen-for-woocommerce' ),
				'vars' => $is_turkish ? 
					array( '{siparis_no}', '{kullanici_adi}', '{siparis_durumu}', '{siparis_toplami}', '{tarih}', '{saat}' ) :
					array( '{order_number}', '{customer_name}', '{order_status}', '{order_total}', '{order_date}', '{order_time}' )
			),
			'billing' => array(
				'title' => __( 'Billing Variables', 'hezarfen-for-woocommerce' ),
				'vars' => $is_turkish ?
					array( '{fatura_adi}', '{fatura_soyadi}', '{fatura_telefonu}', '{fatura_epostasi}', '{fatura_sirket}', '{fatura_adresi}', '{fatura_sehir}', '{fatura_ulke}' ) :
					array( '{billing_first_name}', '{billing_last_name}', '{billing_phone}', '{billing_email}', '{billing_company}', '{billing_address}', '{billing_city}', '{billing_country}' )
			),
			'shipping' => array(
				'title' => __( 'Shipping Variables', 'hezarfen-for-woocommerce' ),
				'vars' => $is_turkish ?
					array( '{kargo_adi}', '{kargo_soyadi}', '{kargo_telefonu}', '{kargo_sirket}', '{kargo_adresi}', '{kargo_sehir}', '{kargo_ulke}' ) :
					array( '{shipping_first_name}', '{shipping_last_name}', '{shipping_phone}', '{shipping_company}', '{shipping_address}', '{shipping_city}', '{shipping_country}' )
			)
		);
		
		if ( $include_shipment_vars ) {
			$categories['shipment'] = array(
				'title' => __( 'Shipment Variables', 'hezarfen-for-woocommerce' ),
				'vars' => $is_turkish ?
					array( '{kargo_firmasi}', '{takip_kodu}', '{takip_linki}' ) :
					array( '{courier_company}', '{tracking_number}', '{tracking_url}' )
			);
		}
		
		?>
		<p class="description"><?php esc_html_e( 'Available Variables (click to copy):', 'hezarfen-for-woocommerce' ); ?></p>
		<div class="sms-variables-wrapper" style="margin-top: 10px;">
			<?php foreach ( $categories as $category_key => $category ): ?>
				<div class="sms-variable-group">
					<h4 class="sms-variable-group-title" data-group="<?php echo esc_attr( $category_key ); ?>" style="cursor: pointer; margin: 10px 0 5px 0; padding: 8px; background: #f0f0f1; border-left: 4px solid #0073aa; display: flex; align-items: center;">
						<span class="dashicons <?php echo $category_key === 'order' ? 'dashicons-arrow-down-alt2' : 'dashicons-arrow-right-alt2'; ?>" style="margin-right: 5px;"></span>
						<?php echo esc_html( $category['title'] ); ?>
						<?php if ( $category_key === 'shipment' ): ?>
							<span style="font-size: 12px; color: #666; margin-left: 10px;">(<?php esc_html_e( 'for Order Shipped trigger', 'hezarfen-for-woocommerce' ); ?>)</span>
						<?php endif; ?>
					</h4>
					<div class="sms-variable-group-content" data-group-content="<?php echo esc_attr( $category_key ); ?>" style="<?php echo $category_key === 'order' ? 'padding: 10px; background: #fff; border: 1px solid #ddd; margin-bottom: 10px;' : 'display: none; padding: 10px; background: #fff; border: 1px solid #ddd; margin-bottom: 10px;'; ?>">
						<?php foreach ( $category['vars'] as $var ): ?>
							<span class="sms-variable button button-small" data-variable="<?php echo esc_attr( $var ); ?>" title="<?php esc_attr_e( 'Click to copy', 'hezarfen-for-woocommerce' ); ?>" style="cursor: pointer;"><?php echo esc_html( $var ); ?></span>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Process message template with order variables
	 *
	 * @param \WC_Order $order Order object
	 * @param string $template Message template
	 * @return string
	 */
	private function process_message_template( $order, $template ) {
		$order_date = $order->get_date_created();
		
		// Get shipment data if available
		$courier_name = '';
		$tracking_number = '';
		$tracking_url = '';
		
		if ( isset( $this->current_shipment_data ) ) {
			$shipment_data = $this->current_shipment_data;
			
			if ( isset( $shipment_data->courier_id ) && isset( $shipment_data->tracking_num ) ) {
				$tracking_number = $shipment_data->tracking_num;
				
				// Get courier name from courier_title property
				$courier_name = $shipment_data->courier_title ?? '';
				
				// Get tracking URL from tracking_url property
				$tracking_url = $shipment_data->tracking_url ?? '';
			}
		}
		
		$variables = array(
			// Legacy variables with exact names and curly brackets (primary format)
			'{siparis_no}' => $order->get_order_number(),
			'{uye_adi}' => $order->get_billing_first_name(),
			'{uye_soyadi}' => $order->get_billing_last_name(),
			'{uye_telefonu}' => $order->get_billing_phone(),
			'{uye_epostasi}' => $order->get_billing_email(),
			'{kullanici_adi}' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'{tarih}' => $order_date ? $order_date->date_i18n( get_option( 'date_format' ) ) : '',
			'{saat}' => $order_date ? $order_date->date_i18n( 'H:i' ) : '',
			
			// Shipment specific variables (only available when shipment data is present)
			'{kargo_firmasi}' => $courier_name,
			'{takip_kodu}' => $tracking_number,
			'{takip_linki}' => $tracking_url,
			
			// Additional Turkish variables for new system
			'{siparis_durumu}' => wc_get_order_status_name( $order->get_status() ),
			'{siparis_toplami}' => str_replace( '₺', 'TL', html_entity_decode( strip_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' ) ),
			'{fatura_adi}' => $order->get_billing_first_name(),
			'{fatura_soyadi}' => $order->get_billing_last_name(),
			'{fatura_telefonu}' => $order->get_billing_phone(),
			'{fatura_epostasi}' => $order->get_billing_email(),
			'{fatura_sirket}' => $order->get_billing_company(),
			'{fatura_adresi}' => $order->get_billing_address_1() . ( $order->get_billing_address_2() ? ' ' . $order->get_billing_address_2() : '' ),
			'{fatura_sehir}' => $order->get_billing_city(),
			'{fatura_ulke}' => $order->get_billing_country(),
			'{kargo_adi}' => $order->get_shipping_first_name(),
			'{kargo_soyadi}' => $order->get_shipping_last_name(),
			'{kargo_telefonu}' => $order->get_shipping_phone(),
			'{kargo_sirket}' => $order->get_shipping_company(),
			'{kargo_adresi}' => $order->get_shipping_address_1() . ( $order->get_shipping_address_2() ? ' ' . $order->get_shipping_address_2() : '' ),
			'{kargo_sehir}' => $order->get_shipping_city(),
			'{kargo_ulke}' => $order->get_shipping_country(),
			
			// English equivalents for compatibility
			'{order_number}' => $order->get_order_number(),
			'{customer_name}' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'{order_status}' => wc_get_order_status_name( $order->get_status() ),
			'{order_total}' => str_replace( '₺', 'TL', html_entity_decode( strip_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' ) ),
			'{order_date}' => $order_date ? $order_date->date_i18n( get_option( 'date_format' ) ) : '',
			'{order_time}' => $order_date ? $order_date->date_i18n( 'H:i' ) : '',
			
			// Billing variables
			'{billing_first_name}' => $order->get_billing_first_name(),
			'{billing_last_name}' => $order->get_billing_last_name(),
			'{billing_phone}' => $order->get_billing_phone(),
			'{billing_email}' => $order->get_billing_email(),
			'{billing_company}' => $order->get_billing_company(),
			'{billing_address}' => $order->get_billing_address_1() . ( $order->get_billing_address_2() ? ' ' . $order->get_billing_address_2() : '' ),
			'{billing_city}' => $order->get_billing_city(),
			'{billing_country}' => $order->get_billing_country(),
			
			// Shipping variables
			'{shipping_first_name}' => $order->get_shipping_first_name(),
			'{shipping_last_name}' => $order->get_shipping_last_name(),
			'{shipping_phone}' => $order->get_shipping_phone(),
			'{shipping_company}' => $order->get_shipping_company(),
			'{shipping_address}' => $order->get_shipping_address_1() . ( $order->get_shipping_address_2() ? ' ' . $order->get_shipping_address_2() : '' ),
			'{shipping_city}' => $order->get_shipping_city(),
			'{shipping_country}' => $order->get_shipping_country(),
			
			// Shipment variables
			'{courier_company}' => $courier_name,
			'{tracking_number}' => $tracking_number,
			'{tracking_url}' => $tracking_url,
			
			// Legacy square bracket format compatibility (automatically convert old format)
			'[siparis_no]' => $order->get_order_number(),
			'[uye_adi]' => $order->get_billing_first_name(),
			'[uye_soyadi]' => $order->get_billing_last_name(),
			'[uye_telefonu]' => $order->get_billing_phone(),
			'[uye_epostasi]' => $order->get_billing_email(),
			'[kullanici_adi]' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'[tarih]' => $order_date ? $order_date->date_i18n( get_option( 'date_format' ) ) : '',
			'[saat]' => $order_date ? $order_date->date_i18n( 'H:i' ) : '',
			'[hezarfen_kargo_firmasi]' => $courier_name,
			'[hezarfen_kargo_takip_kodu]' => $tracking_number,
			'[hezarfen_kargo_takip_linki]' => $tracking_url,
		);

		return str_replace( array_keys( $variables ), array_values( $variables ), $template );
	}

	/**
	 * Process legacy message template with NetGSM variables and write to legacy storage
	 *
	 * @param \WC_Order $order Order object
	 * @param string $template Message template
	 * @param object $shipment_data Optional shipment data object
	 * @return string
	 */
	private function process_legacy_message_template( $order, $template, $shipment_data = null ) {
		$order_date = $order->get_date_created();
		
		// Get shipment data if available
		$courier_name = '';
		$tracking_number = '';
		$tracking_url = '';
		
		if ( $shipment_data ) {
			$courier_name = $shipment_data->courier_title ?? '';
			$tracking_number = $shipment_data->tracking_num ?? '';
			$tracking_url = $shipment_data->tracking_url ?? '';
		}
		
		// NetGSM legacy variables (square bracket format)
		$variables = array(
			'[siparis_no]' => $order->get_order_number(),
			'[uye_adi]' => $order->get_billing_first_name(),
			'[uye_soyadi]' => $order->get_billing_last_name(),
			'[uye_telefonu]' => $order->get_billing_phone(),
			'[uye_epostasi]' => $order->get_billing_email(),
			'[kullanici_adi]' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'[tarih]' => $order_date ? $order_date->date_i18n( get_option( 'date_format' ) ) : '',
			'[saat]' => $order_date ? $order_date->date_i18n( 'H:i' ) : '',
			'[hezarfen_kargo_firmasi]' => $courier_name,
			'[hezarfen_kargo_takip_kodu]' => $tracking_number,
			'[hezarfen_kargo_takip_linki]' => $tracking_url,
		);

		$processed_message = str_replace( array_keys( $variables ), array_values( $variables ), $template );
		
		// Write the processed message to legacy storage for NetGSM plugin to use
		$this->write_to_legacy_storage( $order, $processed_message, $shipment_data );
		
		return $processed_message;
	}

	/**
	 * Write message to legacy NetGSM storage
	 *
	 * @param \WC_Order $order Order object
	 * @param string $message Processed message
	 * @param object $shipment_data Optional shipment data object
	 * @return void
	 */
	private function write_to_legacy_storage( $order, $message, $shipment_data = null ) {
		// Don't overwrite the existing NetGSM content - it's managed from Manual Shipment Tracking settings
		// Just ensure the processed message variables are available for NetGSM plugin
		
		// If shipment data is available, store the shipment-specific meta data for NetGSM plugin
		if ( $shipment_data ) {
			$order->update_meta_data( \Hezarfen\ManualShipmentTracking\Netgsm::COURIER_TITLE_META_KEY, $shipment_data->courier_title ?? '' );
			$order->update_meta_data( \Hezarfen\ManualShipmentTracking\Netgsm::TRACKING_NUM_META_KEY, $shipment_data->tracking_num ?? '' );
			$order->update_meta_data( \Hezarfen\ManualShipmentTracking\Netgsm::TRACKING_URL_META_KEY, $shipment_data->tracking_url ?? '' );
			$order->save();
		}
	}

	/**
	 * Send SMS via NetGSM API using wp_remote_post
	 *
	 * @param array $data SMS data
	 * @param string $username NetGSM username
	 * @param string $password NetGSM password
	 * @return array Array with 'success' (bool) and 'jobid' (string|null)
	 */
	private function send_netgsm_sms( $data, $username, $password ) {
		$url = 'https://api.netgsm.com.tr/sms/rest/v2/send';

		$args = array(
			'method' => 'POST',
			'timeout' => 30,
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password )
			),
			'body' => wp_json_encode( $data )
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'jobid' => null );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		// Handle HTTP status codes
		if ( $response_code === 406 ) {
			return array( 'success' => false, 'jobid' => null );
		} elseif ( $response_code !== 200 ) {
			return array( 'success' => false, 'jobid' => null );
		}

		// Parse and handle NetGSM response codes
		return $this->handle_netgsm_response( $response_body );
	}

	/**
	 * Handle NetGSM API response codes
	 *
	 * @param string $response_body Response body from NetGSM API
	 * @return array Array with 'success' (bool) and 'jobid' (string|null)
	 */
	private function handle_netgsm_response( $response_body ) {
		$response_body = trim( $response_body );
		
		// Try to parse as JSON first
		$json_response = json_decode( $response_body, true );
		if ( json_last_error() === JSON_ERROR_NONE && is_array( $json_response ) ) {
			return $this->handle_netgsm_json_response( $json_response );
		}
		
		// Fallback to plain text response handling
		return $this->handle_netgsm_plain_response( $response_body );
	}

	/**
	 * Handle NetGSM JSON response format
	 *
	 * @param array $response JSON decoded response
	 * @return array Array with 'success' (bool) and 'jobid' (string|null)
	 */
	private function handle_netgsm_json_response( $response ) {
		$code = $response['code'] ?? '';
		$jobid = $response['jobid'] ?? null;
		$description = $response['description'] ?? '';
		
		// Handle success codes
		switch ( $code ) {
			case '00':
				return array( 'success' => true, 'jobid' => $jobid );
			case '01':
				return array( 'success' => true, 'jobid' => $jobid );
			case '02':
				return array( 'success' => true, 'jobid' => $jobid );
		}
		
		// Handle error codes - all return false
		return array( 'success' => false, 'jobid' => null );
	}

	/**
	 * Handle NetGSM plain text response format (fallback)
	 *
	 * @param string $response_body Plain text response
	 * @return array Array with 'success' (bool) and 'jobid' (string|null)
	 */
	private function handle_netgsm_plain_response( $response_body ) {
		// Check for success responses
		if ( $response_body === '00' ) {
			return array( 'success' => true, 'jobid' => null );
		} elseif ( $response_body === '01' ) {
			return array( 'success' => true, 'jobid' => null );
		} elseif ( $response_body === '02' ) {
			return array( 'success' => true, 'jobid' => null );
		} elseif ( is_numeric( $response_body ) && strlen( $response_body ) > 10 ) {
			// Job ID response (long numeric string)
			return array( 'success' => true, 'jobid' => $response_body );
		}

		// Handle error codes - all return false
		return array( 'success' => false, 'jobid' => null );
	}

	/**
	 * Log SMS sending attempt
	 *
	 * @param \WC_Order $order Order object
	 * @param array $rule SMS rule
	 * @param string $phone Phone number
	 * @param string $message Message content
	 * @param array $sms_result SMS result array with 'success' and 'jobid'
	 * @return void
	 */
	private function log_sms_attempt( $order, $rule, $phone, $message, $sms_result ) {
		$success = $sms_result['success'] ?? false;
		$jobid = $sms_result['jobid'] ?? null;
		
		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'order_id' => $order->get_id(),
			'order_status' => $order->get_status(),
			'phone' => $phone,
			'message' => $message,
			'rule_condition' => $rule['condition_status'] ?? '',
			'action_type' => $rule['action_type'] ?? '',
			'provider' => 'NetGSM',
			'success' => $success ? 'yes' : 'no',
			'jobid' => $jobid,
		);

		// Store in order meta for easy access
		$order->add_meta_data( '_hezarfen_sms_log_' . time(), $log_entry );
		$order->save_meta_data();
	}

	/**
	 * Mark SMS as sent and add order note
	 *
	 * @param \WC_Order $order Order object
	 * @param array $rule SMS rule
	 * @param string $phone Phone number
	 * @param string $message Message content
	 * @param string|null $jobid NetGSM Job ID
	 * @return void
	 */
	private function mark_sms_sent( $order, $rule, $phone, $message, $jobid = null ) {
		// Normalize status for meta keys (remove wc- prefix if present)
		$normalized_status = str_replace( 'wc-', '', $rule['condition_status'] );
		
		// Special handling for hezarfen_order_shipped condition status
		if ( $rule['condition_status'] === 'hezarfen_order_shipped' && isset( $this->current_shipment_data ) ) {
			$this->current_shipment_data->sms_sent = true;
			$this->current_shipment_data->save();
		}
		
		// Mark SMS as sent for this rule and status
		$sms_sent_key = '_hezarfen_sms_sent_' . $normalized_status;
		$order->update_meta_data( $sms_sent_key, 'yes' );
		
		// Also store when it was sent
		$sms_sent_time_key = '_hezarfen_sms_sent_time_' . $normalized_status;
		$order->update_meta_data( $sms_sent_time_key, current_time( 'mysql' ) );
		
		// Store job ID if available
		if ( $jobid ) {
			$sms_jobid_key = '_hezarfen_sms_jobid_' . $normalized_status;
			$order->update_meta_data( $sms_jobid_key, $jobid );
		}
		
		$order->save_meta_data();

		// Add order note
		$translatable_statuses = self::get_translatable_order_status_names();
		$translatable_phone_types = self::get_translatable_phone_type_names();
		
		$status_name = isset( $translatable_statuses[ $rule['condition_status'] ] ) 
			? $translatable_statuses[ $rule['condition_status'] ] 
			: wc_get_order_status_name( $rule['condition_status'] );
		
		$phone_type = isset( $translatable_phone_types[ $rule['phone_type'] ] ) 
			? $translatable_phone_types[ $rule['phone_type'] ] 
			: $rule['phone_type'];
		
		if ( $jobid ) {
			/* translators: 1: Order status name, 2: Phone type (billing/shipping), 3: Phone number, 4: Job ID */
			$note = sprintf( 
				__( 'SMS notification sent via NetGSM for %1$s status to %2$s phone: %3$s (Job ID: %4$s)', 'hezarfen-for-woocommerce' ), 
				$status_name,
				$phone_type,
				$phone,
				$jobid
			);
		} else {
			/* translators: 1: Order status name, 2: Phone type (billing/shipping), 3: Phone number */
			$note = sprintf( 
				__( 'SMS notification sent via NetGSM for %1$s status to %2$s phone: %3$s', 'hezarfen-for-woocommerce' ), 
				$status_name,
				$phone_type,
				$phone
			);
		}
		
		$order->add_order_note( $note );
	}

	/**
	 * Check if SMS was already sent for this rule and order
	 *
	 * @param \WC_Order $order Order object
	 * @param array $rule SMS rule
	 * @return bool
	 */
	private function is_sms_already_sent( $order, $rule ) {
		// Normalize status for meta key (remove wc- prefix if present)
		$normalized_status = str_replace( 'wc-', '', $rule['condition_status'] );
		$sms_sent_key = '_hezarfen_sms_sent_' . $normalized_status;
		return $order->get_meta( $sms_sent_key ) === 'yes';
	}

	/**
	 * AJAX handler to get SMS rules
	 *
	 * @return void
	 */
	public function ajax_get_sms_rules() {
		check_ajax_referer( 'hezarfen_sms_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Unauthorized' );
		}

		$rules = get_option( 'hezarfen_sms_rules', array() );
		wp_send_json_success( $rules );
	}

	/**
	 * AJAX handler to save SMS rules
	 *
	 * @return void
	 */
	public function ajax_save_sms_rules() {
		check_ajax_referer( 'hezarfen_sms_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Unauthorized' );
		}

		// Don't sanitize JSON string - it corrupts the data
		$rules_json = $_POST['rules'] ?? '';
		
		// Validate that it's a valid JSON string
		if ( empty( $rules_json ) ) {
			wp_send_json_error( 'No rules data provided' );
		}
		
		// Clean up any potential encoding issues
		$rules_json = wp_unslash( $rules_json );
		$rules_json = trim( $rules_json );
		
		$rules = json_decode( $rules_json, true );
		$json_error = json_last_error();

		if ( $json_error !== JSON_ERROR_NONE ) {
			wp_send_json_error( 'Invalid JSON data: ' . json_last_error_msg() . ' - Raw data: ' . substr( $rules_json, 0, 200 ) );
		}

		if ( ! is_array( $rules ) ) {
			wp_send_json_error( 'Invalid rules data - not an array' );
		}

		// Sanitize rules
		$sanitized_rules = array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$sanitized_rule = array(
				'condition_status' => sanitize_text_field( $rule['condition_status'] ?? '' ),
				'action_type' => sanitize_text_field( $rule['action_type'] ?? '' ),
				'phone_type' => sanitize_text_field( $rule['phone_type'] ?? '' ),
				'message_template' => sanitize_textarea_field( $rule['message_template'] ?? '' ),
				'iys_status' => sanitize_text_field( $rule['iys_status'] ?? '0' ),
			);

			// NetGSM credentials are now stored globally, no need to save with individual rules

			$sanitized_rules[] = $sanitized_rule;
		}

		update_option( 'hezarfen_sms_rules', $sanitized_rules );
		wp_send_json_success( 'Rules saved successfully' );
	}

	/**
	 * Get global NetGSM credentials
	 *
	 * @return array|false
	 */
	public static function get_global_netgsm_credentials() {
		return get_option( 'hezarfen_global_netgsm_credentials', false );
	}

	/**
	 * Check if global NetGSM credentials are configured
	 *
	 * @return bool
	 */
	public static function is_netgsm_connected() {
		$credentials = self::get_global_netgsm_credentials();
		return $credentials && !empty( $credentials['username'] ) && !empty( $credentials['password'] ) && !empty( $credentials['msgheader'] );
	}

	/**
	 * Save global NetGSM credentials
	 *
	 * @param array $credentials
	 * @return bool
	 */
	public static function save_global_netgsm_credentials( $credentials ) {
		$sanitized_credentials = array(
			'username' => sanitize_text_field( $credentials['username'] ?? '' ),
			'password' => sanitize_text_field( $credentials['password'] ?? '' ),
			'msgheader' => sanitize_text_field( $credentials['msgheader'] ?? '' ),
		);

		return update_option( 'hezarfen_global_netgsm_credentials', $sanitized_credentials );
	}

	/**
	 * AJAX handler to save NetGSM credentials
	 *
	 * @return void
	 */
	public function ajax_save_netgsm_credentials() {
		check_ajax_referer( 'hezarfen_sms_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Unauthorized' );
		}

		$username = sanitize_text_field( $_POST['username'] ?? '' );
		$password = sanitize_text_field( $_POST['password'] ?? '' );
		$msgheader = sanitize_text_field( $_POST['msgheader'] ?? '' );

		if ( empty( $username ) || empty( $password ) || empty( $msgheader ) ) {
			wp_send_json_error( 'All fields are required' );
		}

		$credentials = array(
			'username' => $username,
			'password' => $password,
			'msgheader' => $msgheader,
		);

		if ( self::save_global_netgsm_credentials( $credentials ) ) {
			wp_send_json_success( 'NetGSM credentials saved successfully' );
		} else {
			wp_send_json_error( 'Failed to save NetGSM credentials' );
		}
	}

	/**
	 * AJAX handler to get NetGSM credentials status
	 *
	 * @return void
	 */
	public function ajax_get_netgsm_credentials() {
		check_ajax_referer( 'hezarfen_sms_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Unauthorized' );
		}

		$credentials = self::get_global_netgsm_credentials();
		$is_connected = self::is_netgsm_connected();

		wp_send_json_success( array(
			'is_connected' => $is_connected,
			'credentials' => $is_connected ? array(
				'username' => $credentials['username'],
				'msgheader' => $credentials['msgheader'],
				// Don't send password back for security
			) : null,
		) );
	}

	/**
	 * Fetch available message headers from NetGSM API
	 *
	 * @param string $username NetGSM username
	 * @param string $password NetGSM password
	 * @return array|WP_Error
	 */
	public static function fetch_netgsm_message_headers( $username, $password ) {
		$url = 'https://api.netgsm.com.tr/sms/rest/v2/msgheader';
		
		// Create Basic Auth header
		$credentials = base64_encode( $username . ':' . $password );
		
		// WordPress HTTP API arguments
		$args = array(
			'method'      => 'GET',
			'headers'     => array(
				'Authorization' => 'Basic ' . $credentials,
			),
			'timeout'     => 30,
			'sslverify'   => false,
		);
		
		// Make API call
		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code !== 200 ) {
			return new WP_Error( 'netgsm_api_error', 'NetGSM API returned error code: ' . $response_code );
		}

		$data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'json_decode_error', 'Failed to decode NetGSM API response' );
		}

		// Handle NetGSM response codes
		if ( isset( $data['code'] ) ) {
			// Code "00" means success
			if ( $data['code'] === '00' ) {
				// Return message headers if available (NetGSM uses "msgheaders" plural)
				if ( isset( $data['msgheaders'] ) && is_array( $data['msgheaders'] ) ) {
					return $data['msgheaders'];
				}
				
				return new WP_Error( 'no_msgheaders', __( 'No message headers found in NetGSM response', 'hezarfen-for-woocommerce' ) );
			}
			
			// Handle error codes
			$error_messages = array(
				'30' => __( 'Invalid username/password or API access denied. Please check your credentials and API permissions.', 'hezarfen-for-woocommerce' ),
				'70' => __( 'Invalid request parameters. Please check your credentials.', 'hezarfen-for-woocommerce' ),
				'100' => __( 'NetGSM system error. Please try again later.', 'hezarfen-for-woocommerce' ),
				'101' => __( 'NetGSM system error. Please try again later.', 'hezarfen-for-woocommerce' ),
			);

			$error_message = isset( $error_messages[ $data['code'] ] ) 
				? $error_messages[ $data['code'] ] 
				: sprintf( __( 'NetGSM API error (Code: %s)', 'hezarfen-for-woocommerce' ), $data['code'] );

			return new WP_Error( 'netgsm_api_error', $error_message );
		}

		// Fallback: try to find message headers in different formats
		if ( isset( $data['msgheaders'] ) && is_array( $data['msgheaders'] ) ) {
			return $data['msgheaders'];
		}
		
		if ( isset( $data['msgheader'] ) && is_array( $data['msgheader'] ) ) {
			return $data['msgheader'];
		}

		return new WP_Error( 'no_msgheaders', __( 'No message headers found in NetGSM response', 'hezarfen-for-woocommerce' ) );
	}

	/**
	 * AJAX handler to get NetGSM message headers
	 *
	 * @return void
	 */
	public function ajax_get_netgsm_senders() {
		check_ajax_referer( 'hezarfen_sms_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Unauthorized' );
		}

		$username = sanitize_text_field( $_POST['username'] ?? '' );
		$password = sanitize_text_field( $_POST['password'] ?? '' );

		if ( empty( $username ) || empty( $password ) ) {
			wp_send_json_error( 'Username and password are required' );
		}

		$message_headers = self::fetch_netgsm_message_headers( $username, $password );

		if ( is_wp_error( $message_headers ) ) {
			wp_send_json_error( $message_headers->get_error_message() );
		}

		wp_send_json_success( array(
			'senders' => $message_headers,
		) );
	}
}

// Initialize SMS Automation
new SMS_Automation();