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

		if ( empty( $_POST['order_id'] ) || empty( $_POST['package_count'] ) || empty( $_POST['desi'] ) ) {
			wp_send_json_error( 'Missing required parameters', 400 );
		}

		$order_id = absint( $_POST['order_id'] );
		$package_count = absint( $_POST['package_count'] );
		$desi = floatval( $_POST['desi'] );
		$type = sanitize_text_field( $_POST['type'] ?? 'standard' );
		$delivery_slot = sanitize_text_field( $_POST['delivery_slot'] ?? '' );
		$delivery_date = sanitize_text_field( $_POST['delivery_date'] ?? '' );

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( 'Order not found', 404 );
		}

		// Create Hepsijet integration instance
		$hepsijet_integration = new \Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration();
		
		$result = $hepsijet_integration->api_create_barcode( $order_id, $package_count, $desi, $type, $delivery_slot, $delivery_date );

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
	private static function create_hepsijet_pdf( $order, $barcode_data, $delivery_no ) {
		// Try to load TCPDF manually if class doesn't exist
		if ( ! class_exists( 'TCPDF' ) ) {
			// Check if vendor directory exists and try to include TCPDF directly
			$tcpdf_path = WC_HEZARFEN_UYGULAMA_YOLU . 'vendor/tecnickcom/tcpdf/tcpdf.php';
			if ( file_exists( $tcpdf_path ) ) {
				require_once $tcpdf_path;
				
				if ( class_exists( 'TCPDF' ) ) {
				} else {
					throw new Exception( 'TCPDF not available. Please ensure TCPDF is installed via Composer.' );
				}
			} else {
				throw new Exception( 'TCPDF file not found. Please ensure TCPDF is installed via Composer.' );
			}
		} else {
		}
		
		// Create new PDF document with fallback constants
		$orientation = defined( 'PDF_PAGE_ORIENTATION' ) ? PDF_PAGE_ORIENTATION : 'P';
		$unit = defined( 'PDF_UNIT' ) ? PDF_UNIT : 'mm';
		$format = defined( 'PDF_PAGE_FORMAT' ) ? PDF_PAGE_FORMAT : 'A4';
		
		
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

		// Set margins (minimal margins for maximum space)
		$pdf->SetMargins( 5, 5, 5 );

		// Set auto page breaks
		$pdf->SetAutoPageBreak( TRUE, 5 );

		// Set image scale factor
		$pdf->setImageScale( defined( 'PDF_IMAGE_SCALE_RATIO' ) ? PDF_IMAGE_SCALE_RATIO : 1.25 );

		// Add a page
		$pdf->AddPage();

		// Set font - use DejaVu Sans for Turkish character support
		$pdf->SetFont( 'dejavusans', '', 13 );
		
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
				$page_width = $pdf->GetPageWidth();
				$margins = $pdf->getMargins();
				$current_y = $pdf->GetY();
				
				// Get image dimensions to calculate aspect ratio
				$image_info = getimagesizefromstring( $image_data );
				if ( $image_info ) {
					// Fixed dimensions: 200x300 where X-axis after rotation should be 300
					// Before rotation: width=200, height=300
					// After 90° counterclockwise rotation: width becomes 300, height becomes 200
					$display_width = 130;  // This will become height after rotation
					$display_height = 163; // This will become width after rotation
					

					$y_position = $current_y;
					
					// Save the current graphic state
					$pdf->StartTransform();
					
					// Translate to the desired position, rotate, then place the image
					$pdf->Translate($display_width, $current_y); // Move right by width (200) and down to current Y
					$pdf->Rotate(-90); // Rotate 90 degrees counterclockwise
					
					// Add the image at origin (it will be positioned correctly due to transformations)
					$pdf->Image( $temp_file, 0, -30, $display_width, $display_height, 'JPG', '', '', false, 300, '', false, false, 0, false, false, false );
					
					// Restore the graphic state
					$pdf->StopTransform();
					
					// Move Y position to after the barcode (use the actual height after rotation)
					$pdf->SetY( $current_y + $display_width );
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
						$page_width = $pdf->GetPageWidth();
						$margins = $pdf->getMargins();
						
						// Fixed height of 8mm, calculate width maintaining aspect ratio
						$image_width = $image_info[0];
						$image_height = $image_info[1];
						$aspect_ratio = $image_width / $image_height;
						
						// Set fixed height (8mm) and calculate width
						$display_height = 15;
						$display_width = $display_height * $aspect_ratio;
						
						// Position image on the right
						$x_position = $page_width - $display_width - $margins['right'];
						
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
					error_log( 'Hezarfen custom label image error: ' . $e->getMessage() );
				}
			}
		}
		
		
		// === 2-COLUMN LAYOUT ===
		
		// Check if order details should be shown on label
		$show_order_details = get_option( 'hezarfen_hepsijet_show_order_details_on_label', 'yes' ) === 'yes';
		
		// Define column positions and widths
		// Total available width is approximately 190 units (page width minus margins)
		$total_width = 190;
		$left_col_x = 5;
		$left_col_width = $total_width * 0.30; // 30% for Order Details
		$right_col_x = $left_col_x + $left_col_width + 5; // 5 units gap between columns
		$right_col_width = $total_width * 0.70; // 70% for Order Items
		$line_height = 4;
		$section_start_y = $pdf->GetY();
		
		if ( $show_order_details ) {
			// === LEFT COLUMN: ORDER INFO & RECIPIENT ===
			
			// Order Information Header
			$pdf->SetXY( $left_col_x, $section_start_y );
			$pdf->SetFont( 'dejavusans', 'B', 14 );
			$pdf->Cell( $left_col_width, 5, self::ensure_utf8( __( 'Order Information', 'hezarfen-for-woocommerce' ) ), 0, 1, 'L' );
			$pdf->SetX( $left_col_x );
			$pdf->Line( $left_col_x, $pdf->GetY(), $left_col_x + $left_col_width, $pdf->GetY() );
			$pdf->Ln( 2 );
			
			// Order details - Order # on first row
			$pdf->SetFont( 'dejavusans', '', 11 );
			$pdf->SetX( $left_col_x );
			$pdf->Cell( 20, $line_height, self::ensure_utf8( __( 'Order #:', 'hezarfen-for-woocommerce' ) ), 0, 0, 'L' );
			$pdf->SetFont( 'dejavusans', 'B', 11 );
			$pdf->Cell( 0, $line_height, self::ensure_utf8( $order->get_order_number() ), 0, 1, 'L' );
			
			// Date on second row
			$pdf->SetFont( 'dejavusans', '', 11 );
			$pdf->SetX( $left_col_x );
			$pdf->Cell( 18, $line_height, self::ensure_utf8( __( 'Tarih:', 'hezarfen-for-woocommerce' ) ), 0, 0, 'L' );
			$pdf->SetFont( 'dejavusans', 'B', 11 );
			$pdf->Cell( 0, $line_height, self::ensure_utf8( $order->get_date_created()->date( 'd/m/Y' ) ), 0, 1, 'L' );
		
		// Customer and Phone on same row
			$pdf->SetFont( 'dejavusans', '', 11 );
			$pdf->SetX( $left_col_x );
			$pdf->SetFont( 'dejavusans', '', 11 );
			$pdf->Cell( 18, $line_height, self::ensure_utf8( __( 'Phone:', 'hezarfen-for-woocommerce' ) ), 0, 0, 'L' );
			$pdf->SetFont( 'dejavusans', 'B', 11 );
			$phone = $order->get_shipping_phone() ? $order->get_shipping_phone() : $order->get_billing_phone();
			$pdf->Cell( 0, $line_height, self::ensure_utf8( $phone ), 0, 1, 'L' );
			
			$pdf->Ln( 2 );
			
			// Shipping Address in Left Column (no header)
			
			$pdf->SetFont( 'dejavusans', '', 11 );
			$shipping_address = $order->get_formatted_shipping_address();
			if ( empty( $shipping_address ) ) {
				$shipping_address = $order->get_formatted_billing_address();
			}
			
			// Clean up the address formatting for PDF
			$shipping_address = str_replace( '<br/>', "\n", $shipping_address );
			$shipping_address = strip_tags( $shipping_address );
		
			$pdf->SetX( $left_col_x );
			$pdf->MultiCell( $left_col_width, 3.5, self::ensure_utf8( $shipping_address ), 0, 'L' );
			
			// Store left column end position
			$left_col_end_y = $pdf->GetY();
			
			// === RIGHT COLUMN: ORDER DETAILS ===
			
			// Define column widths (used by both items and totals sections)
			$product_col_width = $right_col_width - 40; // Product column takes most space
			$total_col_width = 40; // Fixed width for total column
			
			// Order Details Header
			$pdf->SetXY( $right_col_x, $section_start_y );
			$pdf->SetFont( 'dejavusans', 'B', 14 );
			$pdf->Cell( $right_col_width, 5, self::ensure_utf8( __( 'Order Details', 'hezarfen-for-woocommerce' ) ), 0, 1, 'L' );
			$pdf->SetX( $right_col_x );
			$pdf->Line( $right_col_x, $pdf->GetY(), $right_col_x + $right_col_width, $pdf->GetY() );
			$pdf->Ln( 2 );
			
			// Items table headers (no Qty column)
			$pdf->SetFont( 'dejavusans', 'B', 12 );
			$pdf->SetX( $right_col_x );
			$pdf->Cell( $product_col_width, 4, self::ensure_utf8( __( 'Product', 'hezarfen-for-woocommerce' ) ), 1, 0, 'L' );
			$pdf->Cell( $total_col_width, 4, self::ensure_utf8( __( 'Total', 'hezarfen-for-woocommerce' ) ), 1, 1, 'R' );
			
			// Order items
			$pdf->SetFont( 'dejavusans', '', 11 );
			foreach ( $order->get_items() as $item ) {
				$product_name = $item->get_name();
				$quantity = $item->get_quantity();
				
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
			
				// Build complete product text with variants on new lines
				$product_text = $product_name . ' x ' . $quantity;
				if ( ! empty( $variants ) ) {
					foreach ( $variants as $variant ) {
						$product_text .= "\n  " . $variant;
					}
				}
				
				// Calculate cell height based on number of lines
				$line_count = 1 + count( $variants );
				$cell_height = 4 * $line_count;
				
				// Save current position
				$start_x = $right_col_x;
				$start_y = $pdf->GetY();
				
				// Draw product cell with border
				$pdf->SetXY( $start_x, $start_y );
				$pdf->MultiCell( $product_col_width, 4, self::ensure_utf8( $product_text ), 1, 'L' );
				
				// Draw total cell with border (aligned to the right of product cell)
				$pdf->SetXY( $start_x + $product_col_width, $start_y );
				$pdf->Cell( $total_col_width, $cell_height, self::format_price_for_pdf( $item->get_total() ), 1, 1, 'R' );
				
				// Move to next row (MultiCell already moved Y position)
			}
		
			// === ORDER TOTALS (matching WooCommerce native format exactly) ===
			
			// Items Subtotal
			$pdf->SetFont( 'dejavusans', '', 11 );
			$pdf->SetX( $right_col_x );
			$pdf->Cell( $product_col_width, 4, self::ensure_utf8( __( 'Items Subtotal:', 'woocommerce' ) ), 1, 0, 'R' );
			$pdf->Cell( $total_col_width, 4, self::format_price_for_pdf( $order->get_subtotal() ), 1, 1, 'R' );
		
			// Coupon(s) - if discount > 0
			if ( $order->get_total_discount() > 0 ) {
				$pdf->SetFont( 'dejavusans', '', 11 );
				$pdf->SetX( $right_col_x );
				$pdf->Cell( $product_col_width, 4, self::ensure_utf8( __( 'Coupon(s):', 'woocommerce' ) ), 1, 0, 'R' );
				$pdf->Cell( $total_col_width, 4, self::format_price_for_pdf( -$order->get_total_discount() ), 1, 1, 'R' );
			}
			
			// Fees - if total fees > 0
			if ( $order->get_total_fees() > 0 ) {
				$pdf->SetFont( 'dejavusans', '', 11 );
				$pdf->SetX( $right_col_x );
				$pdf->Cell( $product_col_width, 4, self::ensure_utf8( __( 'Fees:', 'woocommerce' ) ), 1, 0, 'R' );
				$pdf->Cell( $total_col_width, 4, self::format_price_for_pdf( $order->get_total_fees() ), 1, 1, 'R' );
			}
			
			// Shipping - if shipping methods exist
			if ( $order->get_shipping_methods() ) {
				$pdf->SetFont( 'dejavusans', '', 11 );
				$pdf->SetX( $right_col_x );
				$pdf->Cell( $product_col_width, 4, self::ensure_utf8( __( 'Shipping:', 'woocommerce' ) ), 1, 0, 'R' );
				$pdf->Cell( $total_col_width, 4, self::format_price_for_pdf( $order->get_shipping_total() ), 1, 1, 'R' );
			}
			
			// Tax - if tax enabled
			if ( wc_tax_enabled() ) {
				foreach ( $order->get_tax_totals() as $code => $tax_total ) {
					$pdf->SetFont( 'dejavusans', '', 11 );
					$pdf->SetX( $right_col_x );
					$pdf->Cell( $product_col_width, 4, self::ensure_utf8( $tax_total->label . ':' ), 1, 0, 'R' );
					$pdf->Cell( $total_col_width, 4, self::format_price_for_pdf( wc_round_tax_total( $tax_total->amount ) ), 1, 1, 'R' );
				}
			}
			
			// Order Total
			$pdf->SetFont( 'dejavusans', 'B', 14 );
			$pdf->SetX( $right_col_x );
			$pdf->Cell( $product_col_width, 5, self::ensure_utf8( __( 'Order Total', 'woocommerce' ) . ':' ), 1, 0, 'R' );
			$pdf->Cell( $total_col_width, 5, self::format_price_for_pdf( $order->get_total() ), 1, 1, 'R' );
			
			// Store right column end position
			$right_col_end_y = $pdf->GetY();
			
			// Move to the end of the longer column
			$pdf->SetY( max( $left_col_end_y, $right_col_end_y ) + 3 );
			// === ORDER NOTE SECTION ===
			$order_note = $order->get_customer_note();
			$pdf->Ln( 5 );
			
			// Order Note Header
			$pdf->SetFont( 'dejavusans', 'B', 14 );
			$pdf->Cell( 0, 5, self::ensure_utf8( __( 'Order Note', 'hezarfen-for-woocommerce' ) ), 0, 1, 'L' );
			$pdf->Line( $pdf->GetX(), $pdf->GetY(), $pdf->GetX() + 190, $pdf->GetY() );
			$pdf->Ln( 3 );
			
			// Order Note Content (show dash if empty)
			$pdf->SetFont( 'dejavusans', '', 12 );
			$note_content = ! empty( $order_note ) ? $order_note : '-';
			$pdf->MultiCell( 0, 5, self::ensure_utf8( $note_content ), 0, 'L' );
			$pdf->Ln( 3 );
			
			$pdf->Ln( 3 );
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
		// Check user capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions', 403 );
		}

		$start_date = sanitize_text_field( $_POST['start_date'] ?? '' );
		$end_date = sanitize_text_field( $_POST['end_date'] ?? '' );
		$city = sanitize_text_field( $_POST['city'] ?? '' );
		$district = sanitize_text_field( $_POST['district'] ?? '' );

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


			// Check if we have dates array
			if ( isset( $data['dates'] ) && is_array( $data['dates'] ) && ! empty( $data['dates'] ) ) {
				// We have available dates
				wp_send_json_success( array(
					'dates' => $data['dates'],
					'message' => 'Return dates loaded successfully'
				) );
			} else {
				// No dates available - check for API message or use default
				$message = '';
				if ( isset( $data['message'] ) ) {
					$message = $data['message'];
				} elseif ( isset( $data['status'] ) && $data['status'] === 'OK' && isset( $data['message'] ) ) {
					$message = $data['message'];
				} else {
					$message = 'No available return dates found';
				}
				
				wp_send_json_success( array(
					'dates' => array(),
					'message' => $message
				) );
			}

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
}
