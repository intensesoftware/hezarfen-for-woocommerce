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
		$formatted_price = wc_price( $amount );
		$clean_price = strip_tags( $formatted_price );
		
		// Fix Turkish Lira symbol - ensure proper UTF-8 encoding
		$clean_price = str_replace( array( '₺', 'TL', 'TRY' ), '₺', $clean_price );
		
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

		// Set margins (no header/footer margins needed)
		$pdf->SetMargins( 15, 15, 15 );

		// Set auto page breaks
		$pdf->SetAutoPageBreak( TRUE, 25 );

		// Set image scale factor
		$pdf->setImageScale( defined( 'PDF_IMAGE_SCALE_RATIO' ) ? PDF_IMAGE_SCALE_RATIO : 1.25 );

		// Add a page
		$pdf->AddPage();

		// Set font - use DejaVu Sans for Turkish character support
		$pdf->SetFont( 'dejavusans', '', 10 );
		
		// Order Information Section - More compact
		$pdf->SetFont( 'dejavusans', 'B', 10 );
		$pdf->Cell( 0, 5, self::ensure_utf8( __( 'Order Information', 'hezarfen-for-woocommerce' ) ), 0, 1, 'L' );
		$pdf->Line( 15, $pdf->GetY(), 195, $pdf->GetY() );
		$pdf->Ln( 2 );
		
		$pdf->SetFont( 'dejavusans', '', 9 );
		
		// Order details in two columns - more compact
		$col1_x = 15;
		$col2_x = 105;
		$line_height = 4;
		$current_y = $pdf->GetY();
		
		// Left column - Order Info
		$pdf->SetXY( $col1_x, $current_y );
		$pdf->Cell( 35, $line_height, self::ensure_utf8( __( 'Order #:', 'hezarfen-for-woocommerce' ) ), 0, 0, 'L' );
		$pdf->SetFont( 'dejavusans', 'B', 9 );
		$pdf->Cell( 0, $line_height, self::ensure_utf8( $order->get_order_number() ), 0, 1, 'L' );
		
		$pdf->SetFont( 'dejavusans', '', 9 );
		$pdf->SetX( $col1_x );
		$pdf->Cell( 35, $line_height, self::ensure_utf8( __( 'Date:', 'hezarfen-for-woocommerce' ) ), 0, 0, 'L' );
		$pdf->SetFont( 'dejavusans', 'B', 9 );
		$pdf->Cell( 0, $line_height, self::ensure_utf8( $order->get_date_created()->date( 'd/m/Y' ) ), 0, 1, 'L' );
		
		$pdf->SetFont( 'dejavusans', '', 9 );
		$pdf->SetX( $col1_x );
		$pdf->Cell( 35, $line_height, self::ensure_utf8( __( 'Delivery:', 'hezarfen-for-woocommerce' ) ), 0, 0, 'L' );
		$pdf->SetFont( 'dejavusans', 'B', 9 );
		$pdf->Cell( 0, $line_height, self::ensure_utf8( $delivery_no ), 0, 1, 'L' );
		
		// Right column - Customer Info
		$pdf->SetXY( $col2_x, $current_y );
		$pdf->SetFont( 'dejavusans', '', 9 );
		$pdf->Cell( 35, $line_height, self::ensure_utf8( __( 'Customer:', 'hezarfen-for-woocommerce' ) ), 0, 0, 'L' );
		$pdf->SetFont( 'dejavusans', 'B', 9 );
		$customer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$pdf->Cell( 0, $line_height, self::ensure_utf8( $customer_name ), 0, 1, 'L' );
		
		$pdf->SetXY( $col2_x, $pdf->GetY() );
		$pdf->SetFont( 'dejavusans', '', 9 );
		$pdf->Cell( 35, $line_height, self::ensure_utf8( __( 'Phone:', 'hezarfen-for-woocommerce' ) ), 0, 0, 'L' );
		$pdf->SetFont( 'dejavusans', 'B', 9 );
		$phone = $order->get_billing_phone() ? $order->get_billing_phone() : __( 'N/A', 'hezarfen-for-woocommerce' );
		$pdf->Cell( 0, $line_height, self::ensure_utf8( $phone ), 0, 1, 'L' );
		
		$pdf->SetXY( $col2_x, $pdf->GetY() );
		$pdf->SetFont( 'dejavusans', '', 9 );
		$pdf->Cell( 35, $line_height, self::ensure_utf8( __( 'Payment:', 'hezarfen-for-woocommerce' ) ), 0, 0, 'L' );
		$pdf->SetFont( 'dejavusans', 'B', 9 );
		$payment_method = $order->get_payment_method_title() ? $order->get_payment_method_title() : __( 'N/A', 'hezarfen-for-woocommerce' );
		$pdf->Cell( 0, $line_height, self::ensure_utf8( $payment_method ), 0, 1, 'L' );
		
		$pdf->Ln( 3 );
		
		// Shipping Address Section - Compact
		$pdf->SetFont( 'dejavusans', 'B', 10 );
		$pdf->Cell( 0, 5, self::ensure_utf8( __( 'Shipping Address', 'hezarfen-for-woocommerce' ) ), 0, 1, 'L' );
		$pdf->Line( 15, $pdf->GetY(), 195, $pdf->GetY() );
		$pdf->Ln( 2 );
		
		$pdf->SetFont( 'dejavusans', '', 9 );
		$shipping_address = $order->get_formatted_shipping_address();
		if ( empty( $shipping_address ) ) {
			$shipping_address = $order->get_formatted_billing_address();
		}
		
		// Clean up the address formatting for PDF
		$shipping_address = str_replace( '<br/>', "\n", $shipping_address );
		$shipping_address = strip_tags( $shipping_address );
		
		$pdf->MultiCell( 0, 4, self::ensure_utf8( $shipping_address ), 0, 'L' );
		$pdf->Ln( 2 );
		
		// Order Summary - Very compact
		$pdf->SetFont( 'dejavusans', 'B', 10 );
		$pdf->Cell( 0, 5, self::ensure_utf8( __( 'Order Summary', 'hezarfen-for-woocommerce' ) ), 0, 1, 'L' );
		$pdf->Line( 15, $pdf->GetY(), 195, $pdf->GetY() );
		$pdf->Ln( 2 );
		
		// Compact order items - just show count and total
		$item_count = count( $order->get_items() );
		$pdf->SetFont( 'dejavusans', '', 9 );
		$pdf->Cell( 50, 4, self::ensure_utf8( __( 'Items:', 'hezarfen-for-woocommerce' ) ), 0, 0, 'L' );
		$pdf->SetFont( 'dejavusans', 'B', 9 );
		$pdf->Cell( 50, 4, self::ensure_utf8( $item_count . ' ' . __( 'item(s)', 'hezarfen-for-woocommerce' ) ), 0, 0, 'L' );
		
		$pdf->SetFont( 'dejavusans', '', 9 );
		$pdf->Cell( 30, 4, self::ensure_utf8( __( 'Total:', 'hezarfen-for-woocommerce' ) ), 0, 0, 'R' );
		$pdf->SetFont( 'dejavusans', 'B', 10 );
		$pdf->Cell( 30, 4, self::format_price_for_pdf( $order->get_total() ), 1, 1, 'R' );
		
		$pdf->Ln( 3 );

		// === BARCODE SECTION ===
		
		// Barcode Section Header - Compact
		$pdf->SetFont( 'dejavusans', 'B', 10 );
		$pdf->Cell( 0, 5, self::ensure_utf8( __( 'Shipping Barcode', 'hezarfen-for-woocommerce' ) ), 0, 1, 'L' );
		$pdf->Line( 15, $pdf->GetY(), 195, $pdf->GetY() );
		$pdf->Ln( 3 );

		// Add barcode image
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
				
				// Add image to PDF - maintain aspect ratio while fitting on page
				$page_width = $pdf->GetPageWidth();
				$page_height = $pdf->GetPageHeight();
				$margins = $pdf->getMargins();
				$available_width = $page_width - $margins['left'] - $margins['right'];
				$current_y = $pdf->GetY();
				$remaining_height = $page_height - $current_y - $margins['bottom'] - 10; // Leave some margin at bottom
				
				// Get image dimensions to calculate aspect ratio
				$image_info = getimagesizefromstring( $image_data );
				if ( $image_info ) {
					$img_width = $image_info[0];
					$img_height = $image_info[1];
					$aspect_ratio = $img_width / $img_height;
					
					// Calculate dimensions maintaining aspect ratio
					$display_width = $available_width;
					$display_height = $display_width / $aspect_ratio;
					
					// If calculated height exceeds remaining space, scale down
					if ( $display_height > $remaining_height ) {
						$display_height = $remaining_height;
						$display_width = $display_height * $aspect_ratio;
					}
					
					// Center the image horizontally if it's smaller than available width
					$x_position = $margins['left'] + ( $available_width - $display_width ) / 2;
					
					$pdf->Image( $temp_file, $x_position, $current_y, $display_width, $display_height, 'JPG', '', '', false, 300, '', false, false, 0, false, false, false );
				} else {
					// Fallback to original method if we can't get image dimensions
					$x_position = $margins['left'];
					$pdf->Image( $temp_file, $x_position, $current_y, $available_width, 0, 'JPG', '', '', false, 300, '', false, false, 0, false, false, false );
				}
				
				// Clean up temporary file
				unlink( $temp_file );
			}
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
