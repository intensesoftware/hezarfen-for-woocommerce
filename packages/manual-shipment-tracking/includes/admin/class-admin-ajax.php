<?php
/**
 * Contains the Admin_Ajax class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

use Exception;

/**
 * The Admin_Ajax class.
 */
class Admin_Ajax {
	const GET_SHIPMENT_DATA_ACTION    = 'hezarfen_mst_get_shipment_data';
	const GET_SHIPMENT_DATA_NONCE     = 'hezarfen-mst-get-shipment-data';
	const NEW_SHIPMENT_DATA_ACTION 	  = 'hezarfen_mst_new_shipment_data';
	const NEW_SHIPMENT_DATA_NONCE     = 'hezarfen_mst_new_shipment_data';
	const REMOVE_SHIPMENT_DATA_ACTION = 'hezarfen_mst_remove_shipment_data';
	const REMOVE_SHIPMENT_DATA_NONCE  = 'hezarfen-mst-remove-shipment-data';
	const CREATE_HEPSIJET_SHIPMENT_ACTION = 'hezarfen_mst_create_hepsijet_shipment';
	const CREATE_HEPSIJET_SHIPMENT_NONCE  = 'hezarfen-mst-create-hepsijet-shipment';
	const TRACK_HEPSIJET_SHIPMENT_ACTION = 'hezarfen_mst_track_hepsijet_shipment';
	const TRACK_HEPSIJET_SHIPMENT_NONCE  = 'hezarfen-mst-track-hepsijet-shipment';
	const CANCEL_HEPSIJET_SHIPMENT_ACTION = 'hezarfen_mst_cancel_hepsijet_shipment';
	const CANCEL_HEPSIJET_SHIPMENT_NONCE  = 'hezarfen-mst-cancel-hepsijet-shipment';
	const GET_HEPSIJET_BARCODE_ACTION = 'hezarfen_mst_get_hepsijet_barcode';
	const GET_HEPSIJET_BARCODE_NONCE  = 'hezarfen-mst-get-hepsijet-barcode';
	const GET_ORDER_INFO_ACTION = 'hezarfen_mst_get_order_info';
	const GET_ORDER_INFO_NONCE  = 'hezarfen-mst-get-order-info';
	const GET_COMBINED_BARCODE_ACTION = 'hezarfen_mst_get_combined_barcode';
	const GET_COMBINED_BARCODE_NONCE  = 'hezarfen-mst-get-combined-barcode';
	const GENERATE_HEPSIJET_PDF_ACTION = 'hezarfen_mst_generate_hepsijet_pdf';
	const GENERATE_HEPSIJET_PDF_NONCE  = 'hezarfen-mst-generate-hepsijet-pdf';
	const GET_HEPSIJET_BARCODE_PDF_ACTION = 'hezarfen_mst_get_hepsijet_barcode_pdf';
	const GET_HEPSIJET_BARCODE_PDF_NONCE  = 'hezarfen-mst-get-hepsijet-barcode-pdf';
	const GET_HEPSIJET_PRICING_ACTION = 'hezarfen_mst_get_hepsijet_pricing';
	const GET_HEPSIJET_PRICING_NONCE  = 'hezarfen_mst_get_hepsijet_pricing';
	const GET_KARGOGATE_BALANCE_ACTION = 'hezarfen_mst_get_kargogate_balance';
	const GET_KARGOGATE_BALANCE_NONCE  = 'hezarfen_mst_get_kargogate_balance';

	const DATA_ARRAY_KEY         = 'hezarfen_mst_shipment_data';
	const COURIER_HTML_NAME      = 'courier_company';
	const TRACKING_NUM_HTML_NAME = 'tracking_number';

	/**
	 * Initialization method.
	 * 
	 * @return void
	 */
	public static function init() {
		try {
			add_action( 'wp_ajax_' . self::GET_SHIPMENT_DATA_ACTION, array( __CLASS__, 'get_shipment_data' ) );
			add_action( 'wp_ajax_' . self::NEW_SHIPMENT_DATA_ACTION, array( __CLASS__, 'new_shipment_data' ) );
			add_action( 'wp_ajax_' . self::REMOVE_SHIPMENT_DATA_ACTION, array( __CLASS__, 'remove_shipment_data' ) );
			add_action( 'wp_ajax_' . self::CREATE_HEPSIJET_SHIPMENT_ACTION, array( __CLASS__, 'create_hepsijet_shipment' ) );
			add_action( 'wp_ajax_' . self::TRACK_HEPSIJET_SHIPMENT_ACTION, array( __CLASS__, 'track_hepsijet_shipment' ) );
			add_action( 'wp_ajax_' . self::CANCEL_HEPSIJET_SHIPMENT_ACTION, array( __CLASS__, 'cancel_hepsijet_shipment' ) );
					add_action( 'wp_ajax_' . self::GET_HEPSIJET_BARCODE_ACTION, array( __CLASS__, 'get_hepsijet_barcode' ) );
		add_action( 'wp_ajax_' . self::GET_ORDER_INFO_ACTION, array( __CLASS__, 'get_order_info' ) );
		add_action( 'wp_ajax_' . self::GET_COMBINED_BARCODE_ACTION, array( __CLASS__, 'get_combined_barcode' ) );
		add_action( 'wp_ajax_' . self::GENERATE_HEPSIJET_PDF_ACTION, array( __CLASS__, 'generate_hepsijet_pdf' ) );
		add_action( 'wp_ajax_' . self::GET_HEPSIJET_BARCODE_PDF_ACTION, array( __CLASS__, 'get_hepsijet_barcode_pdf' ) );
		add_action( 'wp_ajax_' . self::GET_KARGOGATE_BALANCE_ACTION, array( __CLASS__, 'get_kargogate_balance' ) );
		
		add_action( 'wp_ajax_hezarfen_mst_get_return_dates', array( __CLASS__, 'get_return_dates' ) );
		add_action( 'wp_ajax_hepsijet_get_warehouses', array( __CLASS__, 'get_hepsijet_warehouses' ) );
			
		} catch ( Exception $e ) {
		}
	}

	/**
	 * Outputs all shipment data of the given order.
	 * 
	 * @return void
	 */
	public static function get_shipment_data() {
		check_ajax_referer( self::GET_SHIPMENT_DATA_NONCE );

		// Check user capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions', 403 );
		}

		if ( empty( $_GET['order_id'] ) ) {
			wp_send_json_error( null, 400 );
		}

		wp_send_json_success(
			Helper::get_all_shipment_data( intval( $_GET['order_id'] ) )
		);
	}

	/**
	 * Adds new shipment tracking number/company.
	 * 
	 * @return void
	 */
	public static function new_shipment_data() {
		check_ajax_referer( self::NEW_SHIPMENT_DATA_NONCE );

		if ( empty( $_POST['order_id'] ) ) {
			wp_send_json_error( null, 400 );
		}

		$order_id = absint( $_POST['order_id'] );

		$order = wc_get_order( $order_id );

		$new_courier_id   = ! empty( $_POST[ self::COURIER_HTML_NAME ] ) ? sanitize_text_field( $_POST[ self::COURIER_HTML_NAME ] ) : '';
		$new_tracking_num = ! empty( $_POST[ self::TRACKING_NUM_HTML_NAME ] ) ? sanitize_text_field( $_POST[ self::TRACKING_NUM_HTML_NAME ] ) : '';

		// Don't allow hepsijet-entegrasyon through this endpoint
		if ( 'hepsijet-entegrasyon' === $new_courier_id ) {
			wp_send_json_error( 'Use create shipment endpoint for Hepsijet integration', 400 );
		}

		if ( ! $new_courier_id || ( Courier_Kurye::$id !== $new_courier_id && ! $new_tracking_num ) ) {
			wp_send_json_error( null, 400 );
		}

		Helper::new_order_shipment_data($order, null, $new_courier_id, $new_tracking_num);
	}

	/**
	 * Removes the given shipment data from db.
	 * 
	 * @return void
	 */
	public static function remove_shipment_data() {
		check_ajax_referer( self::REMOVE_SHIPMENT_DATA_NONCE );

		if ( empty( $_POST['meta_id'] ) || empty( $_POST['order_id'] ) ) {
			wp_send_json_error( null, 400 );
		}

		$data = Helper::get_shipment_data_by_id( intval( $_POST['meta_id'] ), intval( $_POST['order_id'] ), true );

		if ( $data ) {
			if ( $data->remove() ) {
				wp_send_json_success();
			} else {
				wp_send_json_error( null, 500 );
			}
		}

		wp_send_json_error( null, 404 );
	}

	/**
	 * Creates Hepsijet shipment via API integration.
	 * 
	 * @return void
	 */
	public static function create_hepsijet_shipment() {
		check_ajax_referer( self::CREATE_HEPSIJET_SHIPMENT_NONCE );

		// Check user capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions', 403 );
		}

		if ( empty( $_POST['order_id'] ) || empty( $_POST['packages'] ) ) {
			wp_send_json_error( 'Missing required parameters', 400 );
		}

		$order_id = absint( $_POST['order_id'] );
		$packages = json_decode( stripslashes( $_POST['packages'] ), true );
		$type = sanitize_text_field( $_POST['type'] ?? 'standard' );
		$delivery_slot = sanitize_text_field( $_POST['delivery_slot'] ?? '' );
		$delivery_date = sanitize_text_field( $_POST['delivery_date'] ?? '' );
		$warehouse_id = sanitize_text_field( $_POST['warehouse_id'] ?? '' );
		$return_address = isset( $_POST['return_address'] ) ? json_decode( stripslashes( $_POST['return_address'] ), true ) : null;

		// Sanitize return address fields if present
		if ( $return_address && is_array( $return_address ) ) {
			$return_address = array(
				'first_name'   => sanitize_text_field( $return_address['first_name'] ?? '' ),
				'last_name'    => sanitize_text_field( $return_address['last_name'] ?? '' ),
				'city'         => sanitize_text_field( $return_address['city'] ?? '' ),
				'district'     => sanitize_text_field( $return_address['district'] ?? '' ),
				'neighborhood' => sanitize_text_field( $return_address['neighborhood'] ?? '' ),
				'address'      => sanitize_textarea_field( $return_address['address'] ?? '' ),
				'phone'        => sanitize_text_field( $return_address['phone'] ?? '' ),
			);
		}

		// Validate packages array
		if ( ! is_array( $packages ) || empty( $packages ) ) {
			wp_send_json_error( 'Invalid packages data', 400 );
		}

		// Return shipments only allow single package
		if ( $type === 'returned' && count( $packages ) > 1 ) {
			wp_send_json_error( __( 'Return shipments can only have one package.', 'hezarfen-for-woocommerce' ), 400 );
		}

		// Validate return address for return shipments
		if ( $type === 'returned' ) {
			if ( ! $return_address || empty( $return_address['first_name'] ) || empty( $return_address['last_name'] ) ||
			     empty( $return_address['city'] ) || empty( $return_address['district'] ) ||
			     empty( $return_address['neighborhood'] ) || empty( $return_address['address'] ) || empty( $return_address['phone'] ) ) {
				wp_send_json_error( __( 'All return address fields are required.', 'hezarfen-for-woocommerce' ), 400 );
			}
		}

		// Validate each package
		foreach ( $packages as $package ) {
			if ( ! isset( $package['desi'] ) || floatval( $package['desi'] ) < 0.01 ) {
				wp_send_json_error( 'Invalid package desi value', 400 );
			}
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( 'Order not found', 404 );
		}

		// Create Hepsijet integration instance
		$hepsijet_integration = new \Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration();

		// Pass warehouse_id and return_address to API
		$result = $hepsijet_integration->api_create_barcode( $order_id, $packages, $type, $delivery_slot, $delivery_date, $warehouse_id, $return_address );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message(), 500 );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Tracks Hepsijet shipment status.
	 * 
	 * @return void
	 */
	public static function track_hepsijet_shipment() {
		check_ajax_referer( self::TRACK_HEPSIJET_SHIPMENT_NONCE );

		// Check user capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions', 403 );
		}

		if ( empty( $_POST['delivery_no'] ) ) {
			wp_send_json_error( 'Missing delivery number', 400 );
		}

		$delivery_no = sanitize_text_field( $_POST['delivery_no'] );

		// Create Hepsijet integration instance
		$hepsijet_integration = new \Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration();
		
		$result = $hepsijet_integration->api_get_shipping_details( $delivery_no );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message(), 500 );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Cancels Hepsijet shipment.
	 * 
	 * @return void
	 */
	public static function cancel_hepsijet_shipment() {
		check_ajax_referer( self::CANCEL_HEPSIJET_SHIPMENT_NONCE );

		// Check user capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions', 403 );
		}

		if ( empty( $_POST['delivery_no'] ) ) {
			wp_send_json_error( 'Missing delivery number', 400 );
		}

		$delivery_no = sanitize_text_field( $_POST['delivery_no'] );

		// Create Hepsijet integration instance
		$hepsijet_integration = new \Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration();
		
		$result = $hepsijet_integration->api_cancel_shipment( $delivery_no );

		if ( is_wp_error( $result ) ) {
			// Get the error message and status from WP_Error
			$error_message = $result->get_error_message();
			$error_status = $result->get_error_data( 'status' ) ?? 400;
			
			// Send error response with the actual error message and appropriate status
			wp_send_json_error( $error_message, $error_status );
		}

		if ( $result === true ) {
			// Mark shipment as cancelled in the encapsulated shipment meta
			if ( ! empty( $_POST['order_id'] ) ) {
				$order_id = absint( $_POST['order_id'] );
				
				$order = wc_get_order( $order_id );
				if ( $order ) {
					// Find shipment by delivery number
					$shipment_meta_key = '_hezarfen_hepsijet_shipment_' . $delivery_no;
					$shipment_details = $order->get_meta( $shipment_meta_key );
					
					if ( $shipment_details && is_array( $shipment_details ) ) {
						$shipment_details['cancelled_at'] = current_time('mysql');
						$shipment_details['cancel_reason'] = 'IPTAL';
						$shipment_details['status'] = 'cancelled';
						
						$order->update_meta_data( $shipment_meta_key, $shipment_details );
						$order->save_meta_data();
					}
				}
			}
			
			wp_send_json_success( array( 'message' => 'Shipment cancelled successfully' ) );
		} else {
			// Handle unexpected response format
			wp_send_json_error( 'Unexpected response format from API', 500 );
		}
	}

	/**
	 * Gets Hepsijet barcode labels.
	 * 
	 * @return void
	 */
	public static function get_hepsijet_barcode() {
		
		check_ajax_referer( self::GET_HEPSIJET_BARCODE_NONCE );

		// Check user capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions', 403 );
		}

		if ( empty( $_POST['delivery_no'] ) ) {
			wp_send_json_error( 'Missing delivery number', 400 );
		}

		$delivery_no = sanitize_text_field( $_POST['delivery_no'] );
		

		// Create Hepsijet integration instance
		$hepsijet_integration = new \Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration();
		
		$result = $hepsijet_integration->get_barcode( $delivery_no );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message(), 500 );
		}

		if ( $result === false ) {
			wp_send_json_error( 'Barcode not found', 404 );
		}

		// Return the barcode labels (base64 image data from relay)
		wp_send_json_success( $result );
	}

	/**
	 * Gets order information for barcode modal.
	 * 
	 * @return void
	 */
	public static function get_order_info() {
		check_ajax_referer( self::GET_ORDER_INFO_NONCE );

		// Check user capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions', 403 );
		}

		if ( empty( $_POST['order_id'] ) ) {
			wp_send_json_error( 'Missing order ID', 400 );
		}

		$order_id = absint( $_POST['order_id'] );
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( 'Order not found', 404 );
		}

		// Prepare order information
		$order_info = array(
			'order_number' => $order->get_order_number(),
			'order_date' => $order->get_date_created()->format('d/m/Y H:i'),
			'customer_name' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
			'customer_company' => $order->get_shipping_company(),
			'shipping_address' => array(
				'address_1' => $order->get_shipping_address_1(),
				'address_2' => $order->get_shipping_address_2(),
				'city' => $order->get_shipping_city(),
				'state' => $order->get_shipping_state(),
				'postcode' => $order->get_shipping_postcode(),
				'country' => $order->get_shipping_country(),
				'phone' => $order->get_billing_phone()
			),
			'order_total' => $order->get_formatted_order_total(),
			'payment_method' => $order->get_payment_method_title(),
			'items' => array()
		);

		// Get order items
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$order_info['items'][] = array(
				'name' => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'total' => wc_price( $item->get_total() ),
				'sku' => $product ? $product->get_sku() : ''
			);
		}

		wp_send_json_success( $order_info );
	}

	/**
	 * Generate Hepsijet PDF with order info and barcode using TCPDF.
	 * 
	 * @return void
	 */
	public static function generate_hepsijet_pdf() {
		check_ajax_referer( self::GENERATE_HEPSIJET_PDF_NONCE );

		// Check user capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions', 403 );
		}

		if ( empty( $_POST['order_id'] ) || empty( $_POST['delivery_no'] ) ) {
			wp_send_json_error( 'Missing required parameters', 400 );
		}

		$order_id = absint( $_POST['order_id'] );
		$delivery_no = sanitize_text_field( $_POST['delivery_no'] );

		// Get order info
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( 'Order not found', 404 );
		}

		// Get barcode data
		$hepsijet_integration = new \Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration();
		$barcode_data = $hepsijet_integration->get_barcode( $delivery_no );
		
		if ( is_wp_error( $barcode_data ) ) {
			wp_send_json_error( $barcode_data->get_error_message(), 500 );
		}

		if ( $barcode_data === false ) {
			wp_send_json_error( 'Barcode not found', 404 );
		}

		// Generate PDF using TCPDF
		try {
			$pdf_url = self::create_hepsijet_pdf( $order, $barcode_data, $delivery_no );

			wp_send_json_success( array( 'pdf_url' => $pdf_url ) );
		} catch ( Exception $e ) {
			wp_send_json_error( 'PDF generation failed: ' . $e->getMessage(), 500 );
		} catch ( Error $e ) {
			wp_send_json_error( 'PDF generation error: ' . $e->getMessage(), 500 );
		}
	}

	/**
	 * Ensure proper UTF-8 encoding for Turkish characters.
	 * 
	 * @param string $text Text to encode.
	 * @return string UTF-8 encoded text.
	 */
	private static function ensure_utf8( $text ) {
		if ( ! is_string( $text ) ) {
			return $text;
		}
		
		// Convert to UTF-8 if not already
		if ( ! mb_check_encoding( $text, 'UTF-8' ) ) {
			$text = mb_convert_encoding( $text, 'UTF-8', 'auto' );
		}
		
		return $text;
	}

	/**
	 * Largest font size in [$min, $base] at which $text fits within $max_width.
	 *
	 * Keeps a column header from overflowing its column (and bleeding into the
	 * neighbouring column) regardless of how long the translated string is.
	 *
	 * @param TCPDF  $pdf       PDF instance.
	 * @param string $text      Text to measure (already UTF-8).
	 * @param float  $max_width Available width in user units.
	 * @param string $style     Font style (e.g. 'B').
	 * @param int    $base      Preferred (largest) font size.
	 * @param int    $min       Smallest acceptable font size.
	 * @return int Font size that fits, or $min if none do.
	 */
	private static function fit_font_size( $pdf, $text, $max_width, $style, $base, $min ) {
		for ( $size = $base; $size > $min; $size-- ) {
			$pdf->SetFont( 'dejavusans', $style, $size );
			if ( $pdf->GetStringWidth( $text ) <= $max_width ) {
				return $size;
			}
		}
		return $min;
	}

	/**
	 * Format price with proper Turkish Lira symbol for PDF.
	 * 
	 * @param float $amount Price amount.
	 * @return string Formatted price with proper TRY symbol.
	 */
	private static function format_price_for_pdf( $amount ) {
		// Get WooCommerce formatted price
		$formatted_price = wc_price( $amount );
		$clean_price = strip_tags( $formatted_price );
		
		// Fix Turkish Lira symbol BEFORE decoding - replace HTML entities
		$clean_price = str_replace( 
			array( '&#8378;', '&lira;', '&#36;', '&#8364;', '&euro;', '&pound;' ), 
			array( '₺', '₺', '$', '€', '€', '£' ), 
			$clean_price 
		);
		
		// Remove any remaining HTML entities and decode
		$clean_price = html_entity_decode( $clean_price, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		
		// Normalize various text formats to proper symbol
		$clean_price = str_replace( array( 'TL', 'TRY', 'tl', 'try' ), '₺', $clean_price );
		
		// If no currency symbol found, add Turkish Lira manually
		if ( ! preg_match( '/₺/', $clean_price ) ) {
			// Get currency settings
			$currency = get_woocommerce_currency();
			$currency_pos = get_option( 'woocommerce_currency_pos' );
			
			// Format number
			$decimals = wc_get_price_decimals();
			$decimal_sep = wc_get_price_decimal_separator();
			$thousand_sep = wc_get_price_thousand_separator();
			$formatted_number = number_format( $amount, $decimals, $decimal_sep, $thousand_sep );
			
			// Add currency symbol based on position
			switch ( $currency_pos ) {
				case 'left':
					$clean_price = '₺' . $formatted_number;
					break;
				case 'right':
					$clean_price = $formatted_number . '₺';
					break;
				case 'left_space':
					$clean_price = '₺ ' . $formatted_number;
					break;
				case 'right_space':
				default:
					$clean_price = $formatted_number . ' ₺';
					break;
			}
		} else {
			// Ensure proper spacing around existing currency symbol
			$clean_price = preg_replace( '/\s*₺\s*/', ' ₺', $clean_price );
			$clean_price = trim( $clean_price );
		}
		
		return self::ensure_utf8( $clean_price );
	}

	/**
	 * Create Hepsijet PDF using TCPDF.
	 * 
	 * @param WC_Order $order Order object.
	 * @param array $barcode_data Barcode data from API.
	 * @param string $delivery_no Delivery number.
	 * @return string Base64 encoded PDF data for inline display.
	 * @throws Exception If PDF generation fails.
	 */
	private static function create_hepsijet_pdf( $order, $barcode_data, $delivery_no, $pdf = null, $return_pdf_object = false ) {
		// Initialize TCPDF if no existing instance was provided.
		if ( null === $pdf ) {
			// Try to load TCPDF manually if class doesn't exist
			if ( ! class_exists( 'TCPDF' ) ) {
				// Check if vendor directory exists and try to include TCPDF directly
				$tcpdf_path = WC_HEZARFEN_UYGULAMA_YOLU . 'vendor/tecnickcom/tcpdf/tcpdf.php';
				if ( file_exists( $tcpdf_path ) ) {
					require_once $tcpdf_path;

					if ( ! class_exists( 'TCPDF' ) ) {
						throw new Exception( 'TCPDF not available. Please ensure TCPDF is installed via Composer.' );
					}
				} else {
					throw new Exception( 'TCPDF file not found. Please ensure TCPDF is installed via Composer.' );
				}
			}

			// Create new PDF document with fallback constants
			$orientation = defined( 'PDF_PAGE_ORIENTATION' ) ? PDF_PAGE_ORIENTATION : 'P';
			$unit = defined( 'PDF_UNIT' ) ? PDF_UNIT : 'mm';

			// Resolve the page format from the admin-selected paper size.
			$paper_size = get_option( 'hezarfen_hepsijet_label_paper_size', 'a4' );
			switch ( $paper_size ) {
				case 'a5':
					$format = 'A5';
					break;
				case 'a6':
					$format = 'A6';
					break;
				case '100x150':
					$format = array( 100, 150 );
					break;
				case '100x100':
					$format = array( 100, 100 );
					break;
				case '80x100':
					$format = array( 80, 100 );
					break;
				case 'custom':
					// Clamp to the same bounds the settings inputs allow so a bad
					// value can't produce an unusable page.
					$custom_w = min( 300, max( 40, (float) get_option( 'hezarfen_hepsijet_label_custom_width', 100 ) ) );
					$custom_h = min( 400, max( 40, (float) get_option( 'hezarfen_hepsijet_label_custom_height', 150 ) ) );
					$format   = array( $custom_w, $custom_h );
					break;
				case 'a4':
				default:
					$format = defined( 'PDF_PAGE_FORMAT' ) ? PDF_PAGE_FORMAT : 'A4';
					break;
			}

			$pdf = new \TCPDF( $orientation, $unit, $format, true, 'UTF-8', false );

			// Set document information
			$pdf->SetCreator( 'WooCommerce' );
			$pdf->SetAuthor( get_bloginfo( 'name' ) );
			$pdf->SetTitle( 'Shipment Label - ' . $delivery_no );
			$pdf->SetSubject( 'Shipment Label' );

			// Disable header and footer completely
			$pdf->setPrintHeader( false );
			$pdf->setPrintFooter( false );

			// Set default monospaced font
			$pdf->SetDefaultMonospacedFont( 'dejavusansmono' );

			// Smaller pages need tighter margins to keep the 100mm content column
			// from being cropped by the printable area.
			$page_margin = is_array( $format ) ? 2 : 5;
			$pdf->SetMargins( $page_margin, $page_margin, $page_margin );

			// Set auto page breaks
			$pdf->SetAutoPageBreak( TRUE, $page_margin );

			// Set image scale factor
			$pdf->setImageScale( defined( 'PDF_IMAGE_SCALE_RATIO' ) ? PDF_IMAGE_SCALE_RATIO : 1.25 );
		}

		// Add a page
		$pdf->AddPage();

		// Set font - use DejaVu Sans for Turkish character support
		$pdf->SetFont( 'dejavusans', '', 13 );

		// Determine label layout up-front: rotated barcode only when order details
		// are rendered next to it. Without order details, the barcode is shown flat.
		$show_order_details = get_option( 'hezarfen_hepsijet_show_order_details_on_label', 'yes' ) === 'yes';
		$show_prices        = get_option( 'hezarfen_hepsijet_show_prices_on_label', 'yes' ) === 'yes';
		$show_order_note    = get_option( 'hezarfen_hepsijet_show_order_note_on_label', 'yes' ) === 'yes';

		// Cap the barcode's visible height (sheet sizes only) so the product
		// list gets the rest of the page. Filterable for advanced use.
		$barcode_max_height = (float) apply_filters( 'hezarfen_hepsijet_label_barcode_max_height', 60, $order );
		if ( $barcode_max_height <= 0 ) {
			$barcode_max_height = 60;
		}

		// On thermal/label stock the barcode spans the full label width — the
		// small page is dominated by the shipping barcode. The height cap (and,
		// with it, the "info beside barcode" layout) applies only to sheet sizes
		// such as A4/A5/A6 where there is room to leave for the order details.
		$paper_size       = get_option( 'hezarfen_hepsijet_label_paper_size', 'a4' );
		$is_thermal_label = in_array( $paper_size, array( '100x150', '100x100', '80x100', 'custom' ), true );

		// All content (barcode + 2-column block + order note) is constrained to a
		// 100mm-wide column anchored to the left margin so the output prints at
		// the same physical size on either an A4 sheet (top-left corner) or a
		// 100mm thermal label. On pages narrower than 100mm + margins, the
		// column collapses to whatever usable width the page allows.
		$margins       = $pdf->getMargins();
		$usable_width  = $pdf->GetPageWidth() - ( $margins['left'] + $margins['right'] );
		$content_width = min( 100, $usable_width );
		$content_x     = $margins['left'];

		// Vertical breathing room placed below the barcode so the order
		// details / products block never butts up against (or visually
		// overlaps) the barcode's bottom edge and its tracking-number text.
		$barcode_bottom_gap = 6;

		// Captured barcode geometry so the order info / details blocks can be
		// laid out around it (filled in while the barcode is drawn below).
		$barcode_top_y     = $pdf->GetY();
		$barcode_bottom_y  = $barcode_top_y;
		$barcode_visible_w = 0;

		// === BARCODE AT TOP ===

		// Add barcode image at the top
		if ( is_array( $barcode_data ) && ! empty( $barcode_data ) ) {
			// Get the first barcode image (base64 data)
			$barcode_image_data = $barcode_data[0];

			// Remove data:image/jpeg;base64, prefix if present
			if ( strpos( $barcode_image_data, 'data:image/jpeg;base64,' ) === 0 ) {
				$barcode_image_data = substr( $barcode_image_data, 23 );
			}

			// Decode base64 and create temporary file
			$image_data = base64_decode( $barcode_image_data );
			if ( $image_data !== false ) {
				// Create temporary file
				$temp_file = wp_tempnam( 'hepsijet_barcode_' . $delivery_no . '.jpg' );
				file_put_contents( $temp_file, $image_data );

				// Add image to PDF at top with proper sizing
				$current_y = $pdf->GetY();

				// Get image dimensions to calculate aspect ratio
				$image_info = getimagesizefromstring( $image_data );
				if ( $image_info ) {
					if ( $show_order_details ) {
						// Rotated layout: the barcode sits at the top of the
						// content column so order details can render below it.
						//
						// The image is rotated 90° clockwise with GD and then
						// drawn as a normal image. Pre-rotating the pixels (rather
						// than rotating inside the PDF) lets us size the result to
						// the content width exactly, so the barcode never gets
						// clipped by the page edge or shifted off the column.
						$rotated_file = $temp_file;
						$rot_w        = $image_info[1]; // dims after a 90° rotation
						$rot_h        = $image_info[0];

						$src_gd = function_exists( 'imagecreatefromstring' ) ? @imagecreatefromstring( $image_data ) : false;
						if ( $src_gd ) {
							$rotated_gd = imagerotate( $src_gd, 270, imagecolorallocate( $src_gd, 255, 255, 255 ) );
							imagedestroy( $src_gd );
							if ( $rotated_gd ) {
								$rot_w        = imagesx( $rotated_gd );
								$rot_h        = imagesy( $rotated_gd );
								$rotated_file = wp_tempnam( 'hepsijet_barcode_rot_' . $delivery_no . '.png' );
								imagepng( $rotated_gd, $rotated_file );
								imagedestroy( $rotated_gd );
							}
						}

						// Full content width, preserving the rotated aspect ratio.
						$draw_width  = $content_width;
						$draw_height = $content_width * $rot_h / max( 1, $rot_w );

						// Sheet sizes (A4/A5/A6) cap the barcode height so the order
						// details get room; thermal labels keep it at full size.
						// Shrinking width and height together keeps the barcode
						// left-aligned and aspect-correct.
						if ( ! $is_thermal_label ) {
							$usable_height = $pdf->GetPageHeight() - $margins['top'] - $margins['bottom'];
							$effective_cap = min( $barcode_max_height, max( 20, $usable_height - 55 ) );

							if ( $effective_cap > 0 && $draw_height > $effective_cap ) {
								$shrink       = $effective_cap / $draw_height;
								$draw_width  *= $shrink;
								$draw_height *= $shrink;
							}
						}

						$pdf->Image( $rotated_file, $content_x, $current_y, $draw_width, $draw_height, '', '', '', false, 300, '', false, false, 0, false, false, false );

						if ( $rotated_file !== $temp_file ) {
							@unlink( $rotated_file );
						}

						$barcode_top_y     = $current_y;
						$barcode_bottom_y  = $current_y + $draw_height;
						$barcode_visible_w = $draw_width;

						$pdf->SetY( $current_y + $draw_height + $barcode_bottom_gap );
					} else {
						// Flat layout: render the barcode in its natural orientation
						// inside the 100mm content column. No rotation is applied.
						$image_aspect_ratio = $image_info[0] / max( 1, $image_info[1] );

						$display_width  = $content_width;
						$display_height = $display_width / $image_aspect_ratio;

						$pdf->Image( $temp_file, $content_x, $current_y, $display_width, $display_height, 'JPG', '', '', false, 300, '', false, false, 0, false, false, false );

						$barcode_top_y     = $current_y;
						$barcode_bottom_y  = $current_y + $display_height;
						$barcode_visible_w = $display_width;

						$pdf->SetY( $current_y + $display_height + $barcode_bottom_gap );
					}
				}

			// Clean up temporary file
			unlink( $temp_file );
		}
	}

	
	// === CUSTOM FILTERABLE IMAGE ===
	// Allow developers to add custom image between barcode and order details
	$custom_image = apply_filters( 'hezarfen_hepsijet_label_custom_image', '', $order, $delivery_no );
		
		if ( ! empty( $custom_image ) ) {
			$current_y = $pdf->GetY();
			
			// Check if it's a URL or file path
			if ( filter_var( $custom_image, FILTER_VALIDATE_URL ) || file_exists( $custom_image ) ) {
				try {
					// If URL, download the image
					if ( filter_var( $custom_image, FILTER_VALIDATE_URL ) ) {
						$image_data = file_get_contents( $custom_image );
						if ( $image_data !== false ) {
							$temp_file = wp_tempnam( 'hezarfen_custom_image_' . $delivery_no );
							file_put_contents( $temp_file, $image_data );
							$custom_image = $temp_file;
						}
					}
					
					// Get image info to determine dimensions
					$image_info = @getimagesize( $custom_image );
					if ( $image_info ) {
						// Fixed height of 30mm, calculate width maintaining aspect ratio.
						// Width is clamped to the 100mm content column so custom
						// images don't break out of the label area.
						$image_width  = $image_info[0];
						$image_height = $image_info[1];
						$aspect_ratio = $image_width / $image_height;

						$display_height = 30;
						$display_width  = $display_height * $aspect_ratio;
						if ( $display_width > $content_width ) {
							$display_width  = $content_width;
							$display_height = $display_width / $aspect_ratio;
						}

						// Center within the 100mm content column
						$x_position = $content_x + ( $content_width - $display_width ) / 2;

						// Add the image to PDF
						$pdf->Image( $custom_image, $x_position, $current_y, $display_width, $display_height );
						
						// Move Y position to after the image
						$pdf->SetY( $current_y + $display_height );
						
						// Add spacing after custom image
						$pdf->Ln( 5 );
						
						// Clean up temp file if it was downloaded
						if ( isset( $temp_file ) && file_exists( $temp_file ) ) {
							unlink( $temp_file );
						}
					}
				} catch ( Exception $e ) {
					// Silently fail if image cannot be processed
				}
			}
		}
		
		
		// === ORDER INFORMATION + ORDER DETAILS LAYOUT ===
		// $show_order_details and $show_prices are resolved above, alongside the
		// barcode rendering decision.
		//
		// When the (aspect-correct) barcode leaves enough empty space to its
		// right, Order Information is placed there and Order Details spans the
		// full width below the barcode — this fills the otherwise-blank top-right
		// area and gives the product list the whole page width. Otherwise the
		// classic side-by-side columns below the barcode are used.

		$column_gap         = 3;
		$line_height        = 4;

		$right_of_barcode_w  = $content_width - $barcode_visible_w - $column_gap;
		$info_beside_barcode = ( $show_order_details && $barcode_visible_w > 0 && $right_of_barcode_w >= 38 );

		// On thermal stock the full-width barcode can leave too little room for
		// the order details. When that happens hide them (the barcode image
		// already carries the address) and print a short note instead.
		$details_fit_on_label = true;
		if ( $is_thermal_label && $show_order_details ) {
			$room_below_barcode   = ( $pdf->GetPageHeight() - $margins['bottom'] ) - ( $barcode_bottom_y + $barcode_bottom_gap );
			$details_fit_on_label = ( $room_below_barcode >= 40 );
		}

		if ( $info_beside_barcode ) {
			$info_col_x        = $content_x + $barcode_visible_w + $column_gap;
			$info_col_width    = $right_of_barcode_w;
			$info_start_y      = $barcode_top_y;

			$details_col_x     = $content_x;
			$details_col_width = $content_width;
			$details_start_y   = $barcode_bottom_y + $barcode_bottom_gap;
		} else {
			$info_col_x        = $content_x;
			$info_col_width    = $content_width * 0.30;
			$info_start_y      = $pdf->GetY();

			$details_col_x     = $info_col_x + $info_col_width + $column_gap;
			$details_col_width = $content_width - $info_col_width - $column_gap;
			$details_start_y   = $info_start_y;
		}

		if ( $show_order_details && $details_fit_on_label ) {
			// Label/value widths inside the 30mm info column. Value cells are
			// bounded to $info_col_width minus the label so long phone numbers
			// or order numbers can't bleed into the details column.
			$order_no_label_w = 16;
			$date_label_w     = 12;
			$phone_label_w    = 12;

			// === LEFT COLUMN: ORDER INFORMATION ===

			// Order Information Header (shrink to fit the narrow info column so
			// it can't overflow into the Order Details column).
			$info_header      = self::ensure_utf8( __( 'Order Information', 'hezarfen-for-woocommerce' ) );
			$info_header_size = self::fit_font_size( $pdf, $info_header, $info_col_width, 'B', 10, 7 );
			$pdf->SetXY( $info_col_x, $info_start_y );
			$pdf->SetFont( 'dejavusans', 'B', $info_header_size );
			$pdf->Cell( $info_col_width, 5, $info_header, 0, 1, 'L' );
			$pdf->SetX( $info_col_x );
			$pdf->Line( $info_col_x, $pdf->GetY(), $info_col_x + $info_col_width, $pdf->GetY() );
			$pdf->Ln( 2 );

			// Order details - Order # on first row. The $stretch=1 argument
			// shrinks an over-long value horizontally so it can never bleed past
			// the info column into the Order Details column next to it.
			$pdf->SetFont( 'dejavusans', '', 8 );
			$pdf->SetX( $info_col_x );
			$pdf->Cell( $order_no_label_w, $line_height, self::ensure_utf8( __( 'Order #:', 'hezarfen-for-woocommerce' ) ), 0, 0, 'L', false, '', 1 );
			$pdf->SetFont( 'dejavusans', 'B', 8 );
			$pdf->Cell( $info_col_width - $order_no_label_w, $line_height, self::ensure_utf8( $order->get_order_number() ), 0, 1, 'L', false, '', 1 );

			// Date on second row
			$pdf->SetFont( 'dejavusans', '', 8 );
			$pdf->SetX( $info_col_x );
			$pdf->Cell( $date_label_w, $line_height, self::ensure_utf8( __( 'Tarih:', 'hezarfen-for-woocommerce' ) ), 0, 0, 'L', false, '', 1 );
			$pdf->SetFont( 'dejavusans', 'B', 8 );
			$pdf->Cell( $info_col_width - $date_label_w, $line_height, self::ensure_utf8( $order->get_date_created()->date( 'd/m/Y' ) ), 0, 1, 'L', false, '', 1 );

			// Phone row
			$pdf->SetFont( 'dejavusans', '', 8 );
			$pdf->SetX( $info_col_x );
			$pdf->Cell( $phone_label_w, $line_height, self::ensure_utf8( __( 'Phone:', 'hezarfen-for-woocommerce' ) ), 0, 0, 'L', false, '', 1 );
			$pdf->SetFont( 'dejavusans', 'B', 8 );
			$phone = $order->get_shipping_phone() ? $order->get_shipping_phone() : $order->get_billing_phone();
			$pdf->Cell( $info_col_width - $phone_label_w, $line_height, self::ensure_utf8( $phone ), 0, 1, 'L', false, '', 1 );

			$pdf->Ln( 2 );

			// Shipping Address (no header)
			$pdf->SetFont( 'dejavusans', '', 8 );
			$shipping_address = $order->get_formatted_shipping_address();
			if ( empty( $shipping_address ) ) {
				$shipping_address = $order->get_formatted_billing_address();
			}

			// Clean up the address formatting for PDF
			$shipping_address = str_replace( '<br/>', "\n", $shipping_address );
			$shipping_address = strip_tags( $shipping_address );

			$pdf->SetX( $info_col_x );
			$pdf->MultiCell( $info_col_width, 3, self::ensure_utf8( $shipping_address ), 0, 'L' );

			// Store left column end position
			$info_col_end_y = $pdf->GetY();

			// When Order Information sits beside the barcode, keep the full-width
			// Order Details block clear of both the barcode and a tall info block.
			if ( $info_beside_barcode ) {
				$details_start_y = max( $details_start_y, $info_col_end_y );
			}

			// === ORDER DETAILS ===

			// Which product fields to print as columns (name / SKU). Quantity is
			// always its own column; the price column follows "Show prices".
			$show_product_name = get_option( 'hezarfen_hepsijet_show_product_name_on_label', 'yes' ) === 'yes';
			$show_product_sku  = get_option( 'hezarfen_hepsijet_show_product_sku_on_label', 'no' ) === 'yes';
			if ( ! $show_product_name && ! $show_product_sku ) {
				$show_product_name = true; // never leave a row without a label
			}

			// Product table columns: [Name] [Code] [Qty] [Total]. Qty/Total/Code
			// take a fixed slice; Name gets whatever is left.
			$qty_col_width   = 9;
			$total_col_width = $show_prices ? 16 : 0;
			$code_col_width  = $show_product_sku ? 18 : 0;
			$name_col_width  = $details_col_width - $qty_col_width - $total_col_width - $code_col_width;
			if ( ! $show_product_name ) {
				// No name column — give its width to the code column instead.
				$code_col_width += $name_col_width;
				$name_col_width  = 0;
			}

			// Compact line height for item and totals rows so longer product
			// lists fit. Filterable for installs that want roomier rows.
			$details_row_h = (float) apply_filters( 'hezarfen_hepsijet_label_row_height', 3.6, $order );

			// Order Details Header (shrink to fit its column for consistency).
			$details_header      = self::ensure_utf8( __( 'Order Details', 'hezarfen-for-woocommerce' ) );
			$details_header_size = self::fit_font_size( $pdf, $details_header, $details_col_width, 'B', 10, 7 );
			$pdf->SetXY( $details_col_x, $details_start_y );
			$pdf->SetFont( 'dejavusans', 'B', $details_header_size );
			$pdf->Cell( $details_col_width, 5, $details_header, 0, 1, 'L' );
			$pdf->SetX( $details_col_x );
			$pdf->Line( $details_col_x, $pdf->GetY(), $details_col_x + $details_col_width, $pdf->GetY() );
			$pdf->Ln( 2 );

			// Auto-fit: the product list is shown only if the whole list (plus
			// the totals/note below) fits on the label; otherwise it is hidden
			// and a short note is shown. A manual "Max product rows" value (> 0)
			// replaces the height test with a fixed item-count cap.
			$order_items      = $order->get_items();
			$item_count       = count( $order_items );
			$max_product_rows = (int) apply_filters( 'hezarfen_hepsijet_label_max_product_rows', 0, $order );

			$pdf->SetFont( 'dejavusans', '', 8 );

			// Space the totals block and the order note will need below the list.
			$reserve = 0;
			if ( $show_prices ) {
				$totals_rows = 2; // items subtotal + order total
				if ( $order->get_total_discount() > 0 ) { $totals_rows++; }
				if ( $order->get_total_fees() > 0 ) { $totals_rows++; }
				if ( $order->get_shipping_methods() ) { $totals_rows++; }
				if ( wc_tax_enabled() ) { $totals_rows += count( $order->get_tax_totals() ); }
				$reserve += $totals_rows * $details_row_h;
			}
			$note_for_reserve = $show_order_note ? trim( (string) $order->get_customer_note() ) : '';
			if ( '' !== $note_for_reserve ) {
				$pdf->SetFont( 'dejavusans', '', 8.5 );
				$reserve += 10 + $pdf->getStringHeight( $content_width, self::ensure_utf8( $note_for_reserve ) );
				$pdf->SetFont( 'dejavusans', '', 8 );
			}

			// Height available for the table (column header + product rows), with
			// a small safety margin for accumulated line-height rounding.
			$first_col_width = $show_product_name ? $name_col_width : $code_col_width;
			$table_budget    = ( $pdf->GetPageHeight() - $margins['bottom'] ) - $pdf->GetY() - $reserve - ( 2 * $details_row_h );

			// First pass: build and measure every row (no drawing yet).
			$rows        = array();
			$rows_height = $details_row_h; // column header row
			foreach ( $order_items as $item ) {
				$product_name = $item->get_name();
				$quantity = $item->get_quantity();
				$line_product = $item->get_product();
				$product_sku  = $line_product ? (string) $line_product->get_sku() : '';
				
				// Get product variants/attributes
				$meta_data = $item->get_meta_data();
				$variants = array();
				
				foreach ( $meta_data as $meta ) {
					$key = $meta->get_data()['key'];
					$value = $meta->get_data()['value'];
					
					// Skip internal WooCommerce meta keys
					if ( strpos( $key, '_' ) === 0 ) {
						continue;
					}
					
					// Format attribute name to be human readable
					$display_key = $key;
				
					// Remove common prefixes
					$display_key = str_replace( array( 'pa_', 'attribute_pa_', 'attribute_' ), '', $display_key );
					
					// Convert underscores and dashes to spaces
					$display_key = str_replace( array( '_', '-' ), ' ', $display_key );
					
					// Convert to title case (capitalize each word)
					$display_key = ucwords( strtolower( $display_key ) );
				
					// Handle common attribute names with better labels
					$label_mappings = array(
						'Size' => 'Size',
						'Color' => 'Color',
						'Colour' => 'Color',
						'Material' => 'Material',
						'Style' => 'Style',
						'Weight' => 'Weight',
						'Length' => 'Length',
						'Width' => 'Width',
						'Height' => 'Height',
						'Brand' => 'Brand',
						'Model' => 'Model',
						'Type' => 'Type',
						'Variant' => 'Variant',
						'Option' => 'Option',
						'Any Text Input' => 'Custom Text',
						'Text Options' => 'Options'
					);
					
					// Apply label mapping if exists
					if ( isset( $label_mappings[ $display_key ] ) ) {
						$display_key = $label_mappings[ $display_key ];
					}
					
					// Clean up the value - decode HTML entities and fix currency symbols
					$clean_value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
					
					// Replace common HTML entities that might not be decoded
					$clean_value = str_replace( 
						array( '&#8378;', '&#8364;', '&#36;', '&euro;', '&pound;' ), 
						array( '₺', '€', '$', '€', '£' ), 
						$clean_value 
					);
					
					$variants[] = $display_key . ': ' . $clean_value;
				}
			
				// Name column text: product name plus any variant lines. When the
				// name column is hidden, the SKU stands in as the row label.
				if ( $show_product_name ) {
					$name_text = $product_name;
				} else {
					$name_text = ( '' !== $product_sku ) ? $product_sku : $product_name;
				}
				if ( ! empty( $variants ) ) {
					foreach ( $variants as $variant ) {
						$name_text .= "\n  " . $variant;
					}
				}

				$predicted_h  = max( $details_row_h, $pdf->getNumLines( self::ensure_utf8( $name_text ), $first_col_width ) * $details_row_h );
				$rows[]       = array(
					'name'  => $name_text,
					'sku'   => $product_sku,
					'qty'   => $quantity,
					'total' => $item->get_total(),
				);
				$rows_height += $predicted_h;
			}

			// Decide whether the whole list fits; if not, hide it entirely.
			$list_fits = ( $max_product_rows > 0 ) ? ( $item_count <= $max_product_rows ) : ( $rows_height <= $table_budget );

			if ( $list_fits ) {
				// Column header row: Name | Code | Qty | Total (enabled columns).
				$pdf->SetFont( 'dejavusans', 'B', 9 );
				$pdf->SetX( $details_col_x );
				if ( $show_product_name ) {
					$pdf->Cell( $name_col_width, $details_row_h, self::ensure_utf8( __( 'Ürün Adı', 'hezarfen-for-woocommerce' ) ), 1, 0, 'L' );
				}
				if ( $show_product_sku ) {
					$pdf->Cell( $code_col_width, $details_row_h, self::ensure_utf8( __( 'Ürün Kodu', 'hezarfen-for-woocommerce' ) ), 1, 0, 'L', false, '', 1 );
				}
				$pdf->Cell( $qty_col_width, $details_row_h, self::ensure_utf8( __( 'Adet', 'hezarfen-for-woocommerce' ) ), 1, ( $show_prices ? 0 : 1 ), 'C' );
				if ( $show_prices ) {
					$pdf->Cell( $total_col_width, $details_row_h, self::ensure_utf8( __( 'Total', 'hezarfen-for-woocommerce' ) ), 1, 1, 'R' );
				}

				// Product rows.
				$pdf->SetFont( 'dejavusans', '', 8 );
				foreach ( $rows as $r ) {
					$start_x = $details_col_x;
					$start_y = $pdf->GetY();

					// The first column is a MultiCell (it may wrap onto several
					// lines); its final height drives the sibling single-line
					// cells so the row borders line up.
					$pdf->SetXY( $start_x, $start_y );
					$pdf->MultiCell( $first_col_width, $details_row_h, self::ensure_utf8( $r['name'] ), 1, 'L' );
					$row_height = $pdf->GetY() - $start_y;

					$x = $start_x + $first_col_width;

					// Code column (only when the name column is also shown).
					if ( $show_product_sku && $show_product_name ) {
						$pdf->SetXY( $x, $start_y );
						$pdf->Cell( $code_col_width, $row_height, self::ensure_utf8( $r['sku'] ), 1, 0, 'L', false, '', 1 );
						$x += $code_col_width;
					}

					// Quantity column.
					$pdf->SetXY( $x, $start_y );
					$pdf->Cell( $qty_col_width, $row_height, self::ensure_utf8( (string) $r['qty'] ), 1, 0, 'C' );
					$x += $qty_col_width;

					// Total (price) column.
					if ( $show_prices ) {
						$pdf->SetXY( $x, $start_y );
						$pdf->Cell( $total_col_width, $row_height, self::format_price_for_pdf( $r['total'] ), 1, 0, 'R' );
					}

					$pdf->SetY( $start_y + $row_height );
				}
			} else {
				// The product list does not fit — hide it and explain why.
				$pdf->SetX( $details_col_x );
				$pdf->SetFont( 'dejavusans', '', 8 );
				$pdf->MultiCell( $details_col_width, $details_row_h, self::ensure_utf8( sprintf(
					/* translators: %d: number of products in the order */
					__( '%d ürün bulunduğu için ürün detayları etikete sığmıyor ve gösterilemiyor.', 'hezarfen-for-woocommerce' ),
					$item_count
				) ), 1, 'L' );
			}

			// === ORDER TOTALS (matching WooCommerce native format exactly) ===
			// The label spans every column except the price column.
			$totals_label_width = $details_col_width - $total_col_width;
			if ( $show_prices ) {
				// Items Subtotal
				$pdf->SetFont( 'dejavusans', '', 8 );
				$pdf->SetX( $details_col_x );
				$pdf->Cell( $totals_label_width, $details_row_h, self::ensure_utf8( __( 'Items Subtotal:', 'woocommerce' ) ), 1, 0, 'R' );
				$pdf->Cell( $total_col_width, $details_row_h, self::format_price_for_pdf( $order->get_subtotal() ), 1, 1, 'R' );

				// Coupon(s) - if discount > 0
				if ( $order->get_total_discount() > 0 ) {
					$pdf->SetFont( 'dejavusans', '', 8 );
					$pdf->SetX( $details_col_x );
					$pdf->Cell( $totals_label_width, $details_row_h, self::ensure_utf8( __( 'Coupon(s):', 'woocommerce' ) ), 1, 0, 'R' );
					$pdf->Cell( $total_col_width, $details_row_h, self::format_price_for_pdf( -$order->get_total_discount() ), 1, 1, 'R' );
				}

				// Fees - if total fees > 0
				if ( $order->get_total_fees() > 0 ) {
					$pdf->SetFont( 'dejavusans', '', 8 );
					$pdf->SetX( $details_col_x );
					$pdf->Cell( $totals_label_width, $details_row_h, self::ensure_utf8( __( 'Fees:', 'woocommerce' ) ), 1, 0, 'R' );
					$pdf->Cell( $total_col_width, $details_row_h, self::format_price_for_pdf( $order->get_total_fees() ), 1, 1, 'R' );
				}

				// Shipping - if shipping methods exist
				if ( $order->get_shipping_methods() ) {
					$pdf->SetFont( 'dejavusans', '', 8 );
					$pdf->SetX( $details_col_x );
					$pdf->Cell( $totals_label_width, $details_row_h, self::ensure_utf8( __( 'Shipping:', 'woocommerce' ) ), 1, 0, 'R' );
					$pdf->Cell( $total_col_width, $details_row_h, self::format_price_for_pdf( $order->get_shipping_total() ), 1, 1, 'R' );
				}

				// Tax - if tax enabled
				if ( wc_tax_enabled() ) {
					foreach ( $order->get_tax_totals() as $code => $tax_total ) {
						$pdf->SetFont( 'dejavusans', '', 8 );
						$pdf->SetX( $details_col_x );
						$pdf->Cell( $totals_label_width, $details_row_h, self::ensure_utf8( $tax_total->label . ':' ), 1, 0, 'R' );
						$pdf->Cell( $total_col_width, $details_row_h, self::format_price_for_pdf( wc_round_tax_total( $tax_total->amount ) ), 1, 1, 'R' );
					}
				}

				// Order Total
				$pdf->SetFont( 'dejavusans', 'B', 8 );
				$pdf->SetX( $details_col_x );
				$pdf->Cell( $totals_label_width, $details_row_h, self::ensure_utf8( __( 'Order Total', 'woocommerce' ) . ':' ), 1, 0, 'R' );
				$pdf->Cell( $total_col_width, $details_row_h, self::format_price_for_pdf( $order->get_total() ), 1, 1, 'R' );
			}

			// Move to the end of the longer column before the Order Note section
			$details_col_end_y = $pdf->GetY();
			$pdf->SetY( max( $info_col_end_y, $details_col_end_y ) + 3 );

			// === ORDER NOTE SECTION ===
			// Only render when there is an actual note. Printing an empty
			// "Order Note: -" block just wastes vertical space and, on small
			// thermal labels, can push content onto a second page.
			$order_note = trim( (string) $order->get_customer_note() );
			if ( $show_order_note && '' !== $order_note ) {
				$pdf->Ln( 3 );

				// Order Note Header (constrained to the 100mm content column)
				$pdf->SetX( $content_x );
				$pdf->SetFont( 'dejavusans', 'B', 10 );
				$pdf->Cell( $content_width, 5, self::ensure_utf8( __( 'Order Note', 'hezarfen-for-woocommerce' ) ), 0, 1, 'L' );
				$pdf->SetX( $content_x );
				$pdf->Line( $content_x, $pdf->GetY(), $content_x + $content_width, $pdf->GetY() );
				$pdf->Ln( 2 );

				// Order Note Content
				$pdf->SetX( $content_x );
				$pdf->SetFont( 'dejavusans', '', 8.5 );
				$pdf->MultiCell( $content_width, 4, self::ensure_utf8( $order_note ), 0, 'L' );
			}
		} elseif ( $show_order_details && ! $details_fit_on_label ) {
			// Order details were requested but the full-width barcode leaves no
			// room for them on this label, so show a short note in their place.
			$pdf->SetY( $barcode_bottom_y + $barcode_bottom_gap );
			$pdf->SetX( $content_x );
			$pdf->SetFont( 'dejavusans', '', 8 );
			$pdf->MultiCell( $content_width, 4, self::ensure_utf8( __( 'Sipariş detayları etikete sığmadığı için gösterilmiyor.', 'hezarfen-for-woocommerce' ) ), 0, 'L' );
		}




		// If caller wants the TCPDF object back (for combined/multi-page PDFs), return it.
		if ( $return_pdf_object ) {
			return $pdf;
		}

		// Generate PDF in memory and return as base64 data
		$pdf_data = $pdf->Output( '', 'S' );

		// Convert to base64 for inline display
		$pdf_base64 = base64_encode( $pdf_data );

		// Return base64 PDF data for inline display
		return 'data:application/pdf;base64,' . $pdf_base64;
	}

	/**
	 * Get available return dates from Hepsijet Relay API.
	 * 
	 * @return void
	 */
	public static function get_return_dates() {
		check_ajax_referer( 'hezarfen_mst_get_return_dates' );

		// Check user capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions', 403 );
		}

		$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
		$end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
		$city       = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
		$district   = isset( $_POST['district'] ) ? sanitize_text_field( wp_unslash( $_POST['district'] ) ) : '';

		if ( ! $start_date || ! $end_date || ! $city || ! $district ) {
			wp_send_json_error( 'Missing required parameters', 400 );
		}

		try {
			// Get Hepsijet integration instance to use its authentication
			$hepsijet_integration = new \Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration();
			
			// Use the integration's make_relay_request method for proper authentication
			$response = $hepsijet_integration->make_relay_request_for_return_dates( array(
				'start_date' => $start_date,
				'end_date' => $end_date,
				'city' => $city,
				'district' => $district
			) );

			if ( is_wp_error( $response ) ) {
				// Log the WP_Error for debugging
				wp_send_json_success( array(
					'dates' => array(),
					'message' => 'Unable to fetch return dates at this time'
				) );
			}

			// Response is already decoded from the integration method
			$data = $response;

			wp_send_json_success( array(
				'dates' => $data
			) );

		} catch ( Exception $e ) {
			wp_send_json_success( array(
				'dates' => array(),
				'message' => 'Unable to fetch return dates at this time'
			) );
		}
	}

	/**
	 * Get Hepsijet barcode and generate PDF in one request.
	 * This replaces the need for separate barcode and order info calls.
	 * 
	 * @return void
	 */
	public static function get_hepsijet_barcode_pdf() {
		
		try {
			check_ajax_referer( self::GET_HEPSIJET_BARCODE_PDF_NONCE );

			// Check user capabilities
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error( 'Insufficient permissions', 403 );
			}

			if ( empty( $_POST['delivery_no'] ) || empty( $_POST['order_id'] ) ) {
				wp_send_json_error( 'Missing required parameters', 400 );
			}

			$delivery_no = sanitize_text_field( $_POST['delivery_no'] );
			$order_id = absint( $_POST['order_id'] );

			// Get order info
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				wp_send_json_error( 'Order not found', 404 );
			}

			// Check if Courier_Hepsijet_Integration class exists (with namespace)
			if ( ! class_exists( 'Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration' ) ) {
				wp_send_json_error( 'Hepsijet integration class not available', 500 );
			}

			// Get barcode data
			$hepsijet_integration = new \Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration();
			
			$barcode_data = $hepsijet_integration->get_barcode( $delivery_no );
			
			if ( is_wp_error( $barcode_data ) ) {
				wp_send_json_error( $barcode_data->get_error_message(), 500 );
			}

			if ( $barcode_data === false ) {
				wp_send_json_error( 'Barcode not found', 404 );
			}

			// Generate PDF using TCPDF
			$pdf_url = self::create_hepsijet_pdf( $order, $barcode_data, $delivery_no );
			
			// Return both the PDF URL and the barcode data for immediate display
			wp_send_json_success( array(
				'pdf_url' => $pdf_url,
				'barcode_data' => $barcode_data,
				'order_info' => self::get_order_info_data( $order )
			) );
			
		} catch ( Exception $e ) {
			wp_send_json_error( 'PDF generation failed: ' . $e->getMessage(), 500 );
		} catch ( Error $e ) {
			wp_send_json_error( 'PDF generation error: ' . $e->getMessage(), 500 );
		} catch ( Throwable $e ) {
			wp_send_json_error( 'PDF generation error: ' . $e->getMessage(), 500 );
		}
	}

	/**
	 * Get order info data as array (helper method).
	 * 
	 * @param WC_Order $order Order object.
	 * @return array Order information.
	 */
	private static function get_order_info_data( $order ) {
		// Prepare order information
		$order_info = array(
			'order_number' => $order->get_order_number(),
			'order_date' => $order->get_date_created()->format('d/m/Y H:i'),
			'customer_name' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
			'customer_company' => $order->get_shipping_company(),
			'shipping_address' => array(
				'address_1' => $order->get_shipping_address_1(),
				'address_2' => $order->get_shipping_address_2(),
				'city' => $order->get_shipping_city(),
				'state' => $order->get_shipping_state(),
				'postcode' => $order->get_shipping_postcode(),
				'country' => $order->get_shipping_country(),
				'phone' => $order->get_billing_phone()
			),
			'order_total' => $order->get_formatted_order_total(),
			'payment_method' => $order->get_payment_method_title(),
			'items' => array()
		);

		// Get order items
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$order_info['items'][] = array(
				'name' => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'total' => wc_price( $item->get_total() ),
				'sku' => $product ? $product->get_sku() : ''
			);
		}

		return $order_info;
	}

	/**
	 * Gets Hepsijet ile Avantajlı Kargo Fiyatları wallet balance.
	 * 
	 * @return void
	 */
	public static function get_kargogate_balance() {
		check_ajax_referer( self::GET_KARGOGATE_BALANCE_NONCE );

		// Check user capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions', 403 );
		}

		// Create Hepsijet integration instance
		$hepsijet_integration = new \Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration();
		
		$result = $hepsijet_integration->get_kargogate_balance();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message(), 500 );
		}

		// Return the balance data
		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler: Generates a combined PDF with barcodes from multiple orders.
	 *
	 * Expects POST data:
	 *   - orders: JSON-encoded array of { order_id, delivery_no }.
	 *
	 * @return void
	 */
	public static function get_combined_barcode() {
		check_ajax_referer( self::GET_COMBINED_BARCODE_NONCE );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Yetkisiz işlem.', 'hezarfen-for-woocommerce' ) ) );
		}

		$orders_json = isset( $_POST['orders'] ) ? sanitize_text_field( wp_unslash( $_POST['orders'] ) ) : '';
		$orders      = json_decode( $orders_json, true );

		if ( empty( $orders ) || ! is_array( $orders ) ) {
			wp_send_json_error( array( 'message' => __( 'Geçersiz sipariş verisi.', 'hezarfen-for-woocommerce' ) ) );
		}

		try {
			$pdf_data_uri = self::generate_combined_pdf( $orders );

			wp_send_json_success( array( 'pdf_url' => $pdf_data_uri ) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Generates a single combined PDF containing barcode labels for multiple orders.
	 *
	 * @param array $orders Array of arrays, each with 'order_id' and 'delivery_no'.
	 * @return string Base64-encoded data URI of the combined PDF.
	 * @throws Exception If PDF generation fails.
	 */
	public static function generate_combined_pdf( $orders ) {
		$hepsijet_integration = new Courier_Hepsijet_Integration();
		$pdf = null;

		foreach ( $orders as $order_item ) {
			$order_id    = absint( $order_item['order_id'] );
			$delivery_no = sanitize_text_field( $order_item['delivery_no'] );

			if ( ! $order_id || empty( $delivery_no ) ) {
				continue;
			}

			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			$barcode_data = $hepsijet_integration->get_barcode( $delivery_no );

			if ( is_wp_error( $barcode_data ) || false === $barcode_data ) {
				continue;
			}

			// Pass existing $pdf (or null for the first iteration) and request the object back.
			$pdf = self::create_hepsijet_pdf( $order, $barcode_data, $delivery_no, $pdf, true );
		}

		if ( null === $pdf ) {
			throw new Exception( __( 'Hiçbir barkod PDF\'e eklenemedi.', 'hezarfen-for-woocommerce' ) );
		}

		// Finalize: render the combined PDF as base64 data URI.
		$pdf_data   = $pdf->Output( '', 'S' );
		$pdf_base64 = base64_encode( $pdf_data );

		return 'data:application/pdf;base64,' . $pdf_base64;
	}

	/**
	 * Gets HepsiJet warehouses (merchant + stores)
	 *
	 * @return void
	 */
	public static function get_hepsijet_warehouses() {
		check_ajax_referer( self::CREATE_HEPSIJET_SHIPMENT_NONCE );

		// Check user capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions', 403 );
		}

		try {
			// Create Hepsijet integration instance
			$hepsijet_integration = new \Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration();
			
			// Get warehouses (with 3-hour caching)
			$result = $hepsijet_integration->get_warehouses();

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array(
					'message' => $result->get_error_message()
				), 500 );
			}

			// Return the warehouses data
			wp_send_json_success( $result );
		} catch ( \Exception $e ) {
			wp_send_json_error( array(
				'message' => $e->getMessage()
			), 500 );
		}
	}
}
