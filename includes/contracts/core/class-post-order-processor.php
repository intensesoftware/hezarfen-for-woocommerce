<?php
/**
 * Post Order Processor
 * 
 * Handles contract processing after order completion
 * 
 * @package Hezarfen\Inc\Contracts\Core
 */

namespace Hezarfen\Inc\Contracts\Core;

defined( 'ABSPATH' ) || exit();

/**
 * Post_Order_Processor class
 */
class Post_Order_Processor {
	
	// Hezarfen invoice conditional patterns
	const REGEX_PATTERN_HEZARFEN_FATURA_BIREYSEL = '/@IF_HEZARFEN_FAT_BIREYSEL\s*([\s\S]*?)\s*@END(?:IF)?_HEZARFEN_FAT_BIREYSEL/';
	const REGEX_PATTERN_HEZARFEN_FATURA_KURUMSAL = '/@IF_HEZARFEN_FAT_KURUMSAL\s*([\s\S]*?)\s*@END(?:IF)?_HEZARFEN_FAT_KURUMSAL/';
	
	/**
	 * Initialize post-order processing hooks
	 */
	public static function init() {
		error_log( 'Hezarfen Post_Order_Processor::init() called' );
		
		$settings = get_option( 'hezarfen_mss_settings', array() );
		$contract_creation_type = isset( $settings['sozlesme_olusturma_tipi'] ) ? $settings['sozlesme_olusturma_tipi'] : 'yeni_siparis';

		error_log( 'Hezarfen Contract creation type: ' . $contract_creation_type );

		// Hook based on when contracts should be created
		if ( 'isleniyor' === $contract_creation_type ) {
			add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'process_order_contracts' ) );
			error_log( 'Hezarfen Hooked to woocommerce_order_status_processing' );
		} else {
			add_action( 'woocommerce_thankyou', array( __CLASS__, 'process_order_contracts' ) );
			error_log( 'Hezarfen Hooked to woocommerce_thankyou' );
		}

		// Hook for including contracts in customer emails
		add_action( 'woocommerce_email_customer_details', array( __CLASS__, 'include_contracts_in_email' ), 100, 4 );
		error_log( 'Hezarfen Hooked to woocommerce_email_customer_details' );
	}
	
	/**
	 * Process and save contracts for an order
	 *
	 * @param int $order_id Order ID.
	 */
	public static function process_order_contracts( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Check if contracts already exist for this order
		if ( self::contracts_exist_for_order( $order_id ) ) {
			return;
		}

		// Get client information
		$ip_address = self::get_client_ip();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';

		// Get and process contracts
		$contracts_to_save = self::get_processed_contracts( $order_id );
		
		if ( empty( $contracts_to_save ) ) {
			return;
		}

		// Save contracts to database
		self::save_contracts_to_database( $order_id, $contracts_to_save, $ip_address, $user_agent );
		
		// Send admin notification email if configured
		self::send_admin_notification( $order, $contracts_to_save, $ip_address );
	}
	
	/**
	 * Include contracts in customer emails
	 *
	 * @param \WC_Order $order Order object.
	 * @param bool      $sent_to_admin Whether sent to admin.
	 * @param bool      $plain_text Whether plain text email.
	 * @param \WC_Email $email Email object.
	 */
	public static function include_contracts_in_email( $order, $sent_to_admin, $plain_text, $email ) {
		// Only include in actual emails, not on frontend pages
		if ( ! is_object( $email ) || ! is_a( $email, 'WC_Email' ) ) {
			return;
		}
		
		// Don't include in admin emails
		if ( $sent_to_admin ) {
			return;
		}
		
		if ( ! apply_filters( 'hezarfen_mss_include_agreements_in_customer_email', true ) ) {
			return;
		}

		// Debug: Log email class for troubleshooting
		error_log( 'Hezarfen Email Debug - Email Class: ' . get_class( $email ) );

		// Only include in specific customer emails based on settings
		$settings = get_option( 'hezarfen_mss_settings', array() );
		$contract_creation_type = isset( $settings['sozlesme_olusturma_tipi'] ) ? $settings['sozlesme_olusturma_tipi'] : 'yeni_siparis';

		$should_include = false;
		$email_class = get_class( $email );
		
		// More flexible email class matching
		if ( 'isleniyor' === $contract_creation_type ) {
			// Include in processing order emails
			if ( strpos( $email_class, 'Processing' ) !== false || strpos( $email_class, 'processing' ) !== false ) {
				$should_include = true;
			}
		} else {
			// Include in new order emails (on-hold, new order, etc.)
			if ( strpos( $email_class, 'On_Hold' ) !== false || 
				 strpos( $email_class, 'New_Order' ) !== false ||
				 strpos( $email_class, 'on_hold' ) !== false ||
				 strpos( $email_class, 'new_order' ) !== false ) {
				$should_include = true;
			}
		}

		error_log( 'Hezarfen Email Debug - Should Include: ' . ( $should_include ? 'YES' : 'NO' ) );

		if ( $should_include ) {
			self::render_contracts_in_email( $order );
		}
	}
	
	/**
	 * Check if contracts already exist for an order
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	private static function contracts_exist_for_order( $order_id ) {
		global $wpdb;
		
		$existing_contracts = $wpdb->get_results( $wpdb->prepare( 
			"SELECT id FROM {$wpdb->prefix}hezarfen_contracts WHERE order_id = %d LIMIT 1", 
			$order_id 
		) );

		return ! empty( $existing_contracts );
	}
	
	/**
	 * Get processed contracts for an order
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	private static function get_processed_contracts( $order_id ) {
		$settings = get_option( 'hezarfen_mss_settings', array() );
		$active_contracts = isset( $settings['contracts'] ) ? $settings['contracts'] : array();
		
		$contracts_to_save = array();
		
		foreach ( $active_contracts as $contract_config ) {
			if ( empty( $contract_config['enabled'] ) || empty( $contract_config['template_id'] ) ) {
				continue;
			}
			
			// Get the contract content from the template using the unified renderer
			$contract_content = Contract_Renderer::get_contract_content_from_template( 
				$contract_config['template_id'], 
				$order_id 
			);
			
			// Only save if there's actual content
			if ( ! empty( $contract_content ) ) {
				$contracts_to_save[] = array(
					'name' => $contract_config['name'],
					'content' => $contract_content,
				);
			}
		}
		
		return $contracts_to_save;
	}
	
	/**
	 * Save contracts to database
	 *
	 * @param int    $order_id Order ID.
	 * @param array  $contracts Contracts to save.
	 * @param string $ip_address Client IP address.
	 * @param string $user_agent Client user agent.
	 */
	private static function save_contracts_to_database( $order_id, $contracts, $ip_address, $user_agent ) {
		global $wpdb;
		
		foreach ( $contracts as $contract ) {
			$result = $wpdb->insert(
				$wpdb->prefix . 'hezarfen_contracts',
				array(
					'order_id'        => $order_id,
					'contract_name'   => $contract['name'],
					'contract_content' => $contract['content'],
					'ip_address'      => $ip_address,
					'user_agent'      => $user_agent,
				),
				array( '%d', '%s', '%s', '%s', '%s' )
			);
			
			// Log any database errors
			if ( false === $result ) {
				error_log( 'Hezarfen MSS: Failed to save contract for order ' . $order_id . '. Error: ' . $wpdb->last_error );
			}
		}
	}
	
	/**
	 * Send admin notification email
	 *
	 * @param \WC_Order $order Order object.
	 * @param array     $contracts Saved contracts.
	 * @param string    $ip_address Client IP address.
	 */
	private static function send_admin_notification( $order, $contracts, $ip_address ) {
		$settings = get_option( 'hezarfen_mss_settings', array() );
		$admin_email = isset( $settings['yonetici_sozlesme_saklama_eposta_adresi'] ) ? $settings['yonetici_sozlesme_saklama_eposta_adresi'] : '';

		if ( empty( $admin_email ) || empty( $contracts ) ) {
			return;
		}

		$order_id = $order->get_id();
		$subject = sprintf( 
			__( 'Order #%s - Customer Agreements Saved', 'hezarfen-for-woocommerce' ), 
			$order->get_order_number() 
		);

		$message = self::build_admin_email_content( $order, $contracts, $ip_address );

		// Send HTML email
		add_filter( 'wp_mail_content_type', array( __CLASS__, 'set_html_content_type' ) );
		$sent = wp_mail( $admin_email, $subject, $message );
		remove_filter( 'wp_mail_content_type', array( __CLASS__, 'set_html_content_type' ) );
		
		// Log email sending result
		if ( ! $sent ) {
			error_log( 'Hezarfen MSS: Failed to send admin notification email for order ' . $order_id );
		}
	}
	
	/**
	 * Build admin email content
	 *
	 * @param \WC_Order $order Order object.
	 * @param array     $contracts Saved contracts.
	 * @param string    $ip_address Client IP address.
	 * @return string
	 */
	private static function build_admin_email_content( $order, $contracts, $ip_address ) {
		$order_id = $order->get_id();
		
		$message = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
		$message .= '<h2 style="color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">' . __( 'Customer Agreements Saved', 'hezarfen-for-woocommerce' ) . '</h2>';
		
		// Order information
		$message .= '<div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-radius: 4px;">';
		$message .= '<h3 style="margin-top: 0;">' . __( 'Order Information', 'hezarfen-for-woocommerce' ) . '</h3>';
		$message .= '<p><strong>' . __( 'Order Number:', 'hezarfen-for-woocommerce' ) . '</strong> #' . esc_html( $order->get_order_number() ) . '</p>';
		$message .= '<p><strong>' . __( 'Customer:', 'hezarfen-for-woocommerce' ) . '</strong> ' . esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) . '</p>';
		$message .= '<p><strong>' . __( 'Email:', 'hezarfen-for-woocommerce' ) . '</strong> ' . esc_html( $order->get_billing_email() ) . '</p>';
		$message .= '<p><strong>' . __( 'Date:', 'hezarfen-for-woocommerce' ) . '</strong> ' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . '</p>';
		$message .= '<p><strong>' . __( 'IP Address:', 'hezarfen-for-woocommerce' ) . '</strong> ' . esc_html( $ip_address ) . '</p>';
		$message .= '</div>';

		// Contracts
		$message .= '<h3>' . __( 'Saved Agreements:', 'hezarfen-for-woocommerce' ) . '</h3>';
		
		foreach ( $contracts as $index => $contract ) {
			$message .= '<div style="margin-bottom: 30px; border: 1px solid #ddd; border-radius: 4px; overflow: hidden;">';
			$message .= '<div style="background: #0073aa; color: white; padding: 10px; font-weight: bold;">';
			$message .= esc_html( $contract['name'] );
			$message .= '</div>';
			$message .= '<div style="padding: 15px; max-height: 300px; overflow-y: auto; background: #fff;">';
			$message .= $contract['content']; // Already processed and safe
			$message .= '</div>';
			$message .= '</div>';
		}
		
		// Footer
		$message .= '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 12px;">';
		$message .= '<p>' . __( 'This email was automatically generated by Hezarfen for WooCommerce.', 'hezarfen-for-woocommerce' ) . '</p>';
		$message .= '<p><a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '">' . __( 'View Order in Admin', 'hezarfen-for-woocommerce' ) . '</a></p>';
		$message .= '</div>';
		
		$message .= '</div>';
		
		return $message;
	}
	
	/**
	 * Render contracts in customer emails
	 *
	 * @param \WC_Order $order Order object.
	 */
	private static function render_contracts_in_email( $order ) {
		$order_id = $order->get_id();
		error_log( 'Hezarfen render_contracts_in_email called for order: ' . $order_id );

		// Check if email was already sent for this order
		$email_sent = $order->get_meta( '_in_mss_eposta_gonderildi_mi', true );
		if ( 1 === $email_sent ) {
			error_log( 'Hezarfen Email already sent for order: ' . $order_id );
			return;
		}

		// Mark email as sent
		$order->update_meta_data( '_in_mss_eposta_gonderildi_mi', 1 );
		$order->save();

		// Get saved contracts from database
		$contracts = self::get_saved_contracts( $order_id );
		error_log( 'Hezarfen Found ' . count( $contracts ) . ' contracts for order: ' . $order_id );
		
		if ( empty( $contracts ) ) {
			error_log( 'Hezarfen No contracts found for order: ' . $order_id );
			return;
		}

		// Render each contract with original design
		foreach ( $contracts as $contract ) {
			?>
			<h3><?php echo esc_html( $contract->contract_name ); ?></h3>
			<div style="height:300px;overflow:scroll;margin-bottom:15px;border:1px solid #dddddd;padding:15px">
				<?php echo wp_kses_post( $contract->contract_content ); ?>
			</div>
			<?php
		}
	}
	
	/**
	 * Get saved contracts for an order
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	private static function get_saved_contracts( $order_id ) {
		global $wpdb;
		
		$contracts = $wpdb->get_results( $wpdb->prepare( 
			"SELECT * FROM {$wpdb->prefix}hezarfen_contracts WHERE order_id = %d ORDER BY created_at ASC", 
			$order_id 
		) );

		return $contracts ? $contracts : array();
	}
	
	/**
	 * Get client IP address
	 *
	 * @return string
	 */
	private static function get_client_ip() {
		$ip_keys = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR'
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( $_SERVER[ $key ] );
				
				// Handle comma-separated IPs (forwarded headers)
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				
				// Validate IP address
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return 'Unknown';
	}
	
	/**
	 * Set email content type to HTML
	 *
	 * @return string
	 */
	public static function set_html_content_type() {
		return 'text/html';
	}
}