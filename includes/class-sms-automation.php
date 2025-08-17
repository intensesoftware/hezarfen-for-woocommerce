<?php
/**
 * SMS Automation Class
 * 
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

/**
 * SMS Automation class for handling NetGSM SMS notifications
 */
class SMS_Automation {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_change' ), 10, 4 );
		add_action( 'wp_ajax_hezarfen_get_sms_rules', array( $this, 'ajax_get_sms_rules' ) );
		add_action( 'wp_ajax_hezarfen_save_sms_rules', array( $this, 'ajax_save_sms_rules' ) );
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
		// Check if SMS automation is enabled
		if ( 'yes' !== get_option( 'hezarfen_sms_automation_enabled', 'no' ) ) {
			return;
		}

		// Get SMS rules
		$rules = get_option( 'hezarfen_sms_rules', array() );
		if ( empty( $rules ) ) {
			return;
		}

		// Check if any rule matches the new status
		foreach ( $rules as $rule ) {
			if ( isset( $rule['condition_status'] ) && $rule['condition_status'] === 'wc-' . $new_status ) {
				if ( isset( $rule['action_type'] ) && $rule['action_type'] === 'netgsm' ) {
					$this->send_sms_for_rule( $order, $rule );
				}
			}
		}
	}

	/**
	 * Send SMS for a specific rule
	 *
	 * @param \WC_Order $order Order object
	 * @param array $rule SMS rule
	 * @return bool
	 */
	private function send_sms_for_rule( $order, $rule ) {
		// Get NetGSM credentials from the rule
		$username = $rule['netgsm_username'] ?? '';
		$password = $rule['netgsm_password'] ?? '';
		$msgheader = $rule['netgsm_msgheader'] ?? '';

		if ( empty( $username ) || empty( $password ) || empty( $msgheader ) ) {
			error_log( 'Hezarfen SMS: NetGSM credentials not configured in rule' );
			return false;
		}

		// Get phone number based on rule
		$phone = $this->get_phone_number( $order, $rule['phone_type'] );
		if ( empty( $phone ) ) {
			error_log( 'Hezarfen SMS: No phone number found for order ' . $order->get_id() );
			return false;
		}

		// Process message template
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
		return $this->send_netgsm_sms( $data, $username, $password );
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
		$variables = array(
			'{order_number}' => $order->get_order_number(),
			'{customer_name}' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'{order_status}' => wc_get_order_status_name( $order->get_status() ),
			'{order_total}' => $order->get_formatted_order_total(),
		);

		return str_replace( array_keys( $variables ), array_values( $variables ), $template );
	}

	/**
	 * Send SMS via NetGSM API using wp_remote_post
	 *
	 * @param array $data SMS data
	 * @param string $username NetGSM username
	 * @param string $password NetGSM password
	 * @return bool
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
			error_log( 'Hezarfen SMS: NetGSM API error - ' . $response->get_error_message() );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code !== 200 ) {
			error_log( 'Hezarfen SMS: NetGSM API returned status ' . $response_code . ' - ' . $response_body );
			return false;
		}

		// Log successful SMS
		error_log( 'Hezarfen SMS: SMS sent successfully via NetGSM - Response: ' . $response_body );
		return true;
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
		error_log( 'Hezarfen SMS: Received rules JSON: ' . $rules_json );
		error_log( 'Hezarfen SMS: JSON length: ' . strlen( $rules_json ) );
		error_log( 'Hezarfen SMS: JSON first 100 chars: ' . substr( $rules_json, 0, 100 ) );
		
		// Validate that it's a valid JSON string
		if ( empty( $rules_json ) ) {
			wp_send_json_error( 'No rules data provided' );
		}
		
		// Clean up any potential encoding issues
		$rules_json = wp_unslash( $rules_json );
		$rules_json = trim( $rules_json );
		
		error_log( 'Hezarfen SMS: Cleaned JSON: ' . $rules_json );
		
		$rules = json_decode( $rules_json, true );
		$json_error = json_last_error();
		
		error_log( 'Hezarfen SMS: JSON decode error: ' . $json_error );
		error_log( 'Hezarfen SMS: JSON error message: ' . json_last_error_msg() );
		error_log( 'Hezarfen SMS: Decoded rules: ' . print_r( $rules, true ) );
		error_log( 'Hezarfen SMS: Is array: ' . ( is_array( $rules ) ? 'Yes' : 'No' ) );

		if ( $json_error !== JSON_ERROR_NONE ) {
			wp_send_json_error( 'Invalid JSON data: ' . json_last_error_msg() . ' - Raw data: ' . substr( $rules_json, 0, 200 ) );
		}

		if ( ! is_array( $rules ) ) {
			error_log( 'Hezarfen SMS: Invalid rules data - not an array' );
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