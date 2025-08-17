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
		// Check if SMS was already sent for this rule and order
		if ( $this->is_sms_already_sent( $order, $rule ) ) {
			return true; // Return true since SMS was already sent successfully
		}
		// Get NetGSM credentials from the rule
		$username = $rule['netgsm_username'] ?? '';
		$password = $rule['netgsm_password'] ?? '';
		$msgheader = $rule['netgsm_msgheader'] ?? '';

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
				
				// Get courier name if available
				if ( method_exists( $shipment_data, 'get_courier_name' ) ) {
					$courier_name = $shipment_data->get_courier_name();
				} elseif ( isset( $shipment_data->courier_name ) ) {
					$courier_name = $shipment_data->courier_name;
				}
				
				// Get tracking URL if available
				if ( method_exists( $shipment_data, 'get_tracking_url' ) ) {
					$tracking_url = $shipment_data->get_tracking_url();
				} elseif ( isset( $shipment_data->tracking_url ) ) {
					$tracking_url = $shipment_data->tracking_url;
				}
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
			'{saat}' => $order_date ? $order_date->date_i18n( get_option( 'time_format' ) ) : '',
			
			// Shipment specific variables (only available when shipment data is present)
			'{kargo_firmasi}' => $courier_name,
			'{takip_kodu}' => $tracking_number,
			'{takip_linki}' => $tracking_url,
			
			// English equivalents for compatibility
			'{order_number}' => $order->get_order_number(),
			'{customer_name}' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'{order_status}' => wc_get_order_status_name( $order->get_status() ),
			'{order_total}' => $order->get_formatted_order_total(),
			'{billing_first_name}' => $order->get_billing_first_name(),
			'{billing_last_name}' => $order->get_billing_last_name(),
			'{billing_phone}' => $order->get_billing_phone(),
			'{billing_email}' => $order->get_billing_email(),
			'{order_date}' => $order_date ? $order_date->date_i18n( get_option( 'date_format' ) ) : '',
			'{order_time}' => $order_date ? $order_date->date_i18n( get_option( 'time_format' ) ) : '',
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
			'[saat]' => $order_date ? $order_date->date_i18n( get_option( 'time_format' ) ) : '',
			'[hezarfen_kargo_firmasi]' => $courier_name,
			'[hezarfen_kargo_takip_kodu]' => $tracking_number,
			'[hezarfen_kargo_takip_linki]' => $tracking_url,
		);

		return str_replace( array_keys( $variables ), array_values( $variables ), $template );
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
		$status_name = wc_get_order_status_name( $rule['condition_status'] );
		$phone_type = $rule['phone_type'] === 'billing' ? __( 'billing', 'hezarfen-for-woocommerce' ) : __( 'shipping', 'hezarfen-for-woocommerce' );
		
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

			// Add NetGSM specific fields if action type is netgsm
			if ( isset( $rule['action_type'] ) && $rule['action_type'] === 'netgsm' ) {
				$sanitized_rule['netgsm_username'] = sanitize_text_field( $rule['netgsm_username'] ?? '' );
				$sanitized_rule['netgsm_password'] = sanitize_text_field( $rule['netgsm_password'] ?? '' );
				$sanitized_rule['netgsm_msgheader'] = sanitize_text_field( $rule['netgsm_msgheader'] ?? '' );
			}

			$sanitized_rules[] = $sanitized_rule;
		}

		update_option( 'hezarfen_sms_rules', $sanitized_rules );
		wp_send_json_success( 'Rules saved successfully' );
	}
}

// Initialize SMS Automation
new SMS_Automation();