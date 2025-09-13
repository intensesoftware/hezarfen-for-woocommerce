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
		
		// Simple test endpoint for debugging
		add_action( 'wp_ajax_hezarfen_mst_test_combined', array( __CLASS__, 'test_combined_endpoint' ) );
		
		// Test class loading endpoint
		add_action( 'wp_ajax_hezarfen_mst_test_class_loading', array( __CLASS__, 'test_class_loading' ) );
		
		// Debug endpoint to test AJAX
		add_action( 'wp_ajax_hezarfen_mst_debug_test', array( __CLASS__, 'debug_test' ) );
		add_action( 'wp_ajax_hezarfen_mst_test_pdf_endpoint', array( __CLASS__, 'test_pdf_endpoint' ) );
		add_action( 'wp_ajax_hezarfen_mst_get_return_dates', array( __CLASS__, 'get_return_dates' ) );
			
			error_log( 'Admin_Ajax::init() completed successfully' );
		} catch ( Exception $e ) {
			error_log( 'Admin_Ajax::init() error: ' . $e->getMessage() );
		}
	}

	/**
	 * Outputs all shipment data of the given order.
	 * 
	 * @return void
	 */
	public static function get_shipment_data() {
		check_ajax_referer( self::GET_SHIPMENT_DATA_NONCE );

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
		// Debug log
		error_log( 'Hepsijet barcode AJAX called' );
		
		check_ajax_referer( self::GET_HEPSIJET_BARCODE_NONCE );

		// Check user capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions', 403 );
		}

		if ( empty( $_POST['delivery_no'] ) ) {
			wp_send_json_error( 'Missing delivery number', 400 );
		}

		$delivery_no = sanitize_text_field( $_POST['delivery_no'] );
		
		// Debug log
		error_log( 'Hepsijet barcode request for delivery: ' . $delivery_no );

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
	 * Debug test endpoint to verify AJAX is working.
	 * 
	 * @return void
	 */
	public static function debug_test() {
		wp_send_json_success( array(
			'message' => 'AJAX is working!',
			'timestamp' => current_time( 'mysql' ),
			'user_id' => get_current_user_id(),
			'registered_actions' => array(
				'hepsijet_barcode' => has_action( 'wp_ajax_' . self::GET_HEPSIJET_BARCODE_ACTION ),
				'hepsijet_barcode_action' => self::GET_HEPSIJET_BARCODE_ACTION,
				'generate_pdf' => has_action( 'wp_ajax_' . self::GENERATE_HEPSIJET_PDF_ACTION ),
				'generate_pdf_action' => self::GENERATE_HEPSIJET_PDF_ACTION
			)
		) );
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
			error_log( 'Starting PDF generation for order ' . $order_id . ' and delivery ' . $delivery_no );
			$pdf_url = self::create_hepsijet_pdf( $order, $barcode_data, $delivery_no );
			error_log( 'PDF generated successfully: ' . $pdf_url );
			wp_send_json_success( array( 'pdf_url' => $pdf_url ) );
		} catch ( Exception $e ) {
			error_log( 'PDF generation failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			wp_send_json_error( 'PDF generation failed: ' . $e->getMessage(), 500 );
		} catch ( Error $e ) {
			error_log( 'PDF generation error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			wp_send_json_error( 'PDF generation error: ' . $e->getMessage(), 500 );
		}
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
		error_log( 'create_hepsijet_pdf called with order: ' . $order->get_id() . ', delivery: ' . $delivery_no );
		
		// Check if TCPDF is available (with Composer autoloader)
		error_log( 'Checking for TCPDF class...' );
		
		// Try to load TCPDF manually if class doesn't exist
		if ( ! class_exists( 'TCPDF' ) ) {
			error_log( 'TCPDF class not found via Composer autoloader, trying manual load...' );
			
			// Check if vendor directory exists and try to include TCPDF directly
			$tcpdf_path = WC_HEZARFEN_UYGULAMA_YOLU . 'vendor/tecnickcom/tcpdf/tcpdf.php';
			if ( file_exists( $tcpdf_path ) ) {
				error_log( 'TCPDF file found at: ' . $tcpdf_path );
				require_once $tcpdf_path;
				
				if ( class_exists( 'TCPDF' ) ) {
					error_log( 'TCPDF class loaded manually successfully!' );
				} else {
					error_log( 'TCPDF class still not available after manual load' );
					error_log( 'Available classes: ' . print_r( get_declared_classes(), true ) );
					throw new Exception( 'TCPDF not available. Please ensure TCPDF is installed via Composer.' );
				}
			} else {
				error_log( 'TCPDF file not found at: ' . $tcpdf_path );
				throw new Exception( 'TCPDF file not found. Please ensure TCPDF is installed via Composer.' );
			}
		} else {
			error_log( 'TCPDF class found successfully via autoloader!' );
		}

		error_log( 'Creating TCPDF instance...' );
		
		// Create new PDF document with fallback constants
		$orientation = defined( 'PDF_PAGE_ORIENTATION' ) ? PDF_PAGE_ORIENTATION : 'P';
		$unit = defined( 'PDF_UNIT' ) ? PDF_UNIT : 'mm';
		$format = defined( 'PDF_PAGE_FORMAT' ) ? PDF_PAGE_FORMAT : 'A4';
		
		error_log( 'TCPDF constants - orientation: ' . $orientation . ', unit: ' . $unit . ', format: ' . $format );
		
		$pdf = new \TCPDF( $orientation, $unit, $format, true, 'UTF-8', false );
		error_log( 'TCPDF instance created successfully' );

		// Set document information
		$pdf->SetCreator( 'Hezarfen for WooCommerce' );
		$pdf->SetAuthor( 'Hezarfen' );
		$pdf->SetTitle( 'Hepsijet Shipment Label - ' . $delivery_no );
		$pdf->SetSubject( 'Shipment Label' );

		// Set default header data (no header text)
		$pdf->SetHeaderData( '', 0, '', '' );

		// Set header and footer fonts
		$pdf->setHeaderFont( array( 'helvetica', '', 12 ) );
		$pdf->setFooterFont( array( 'helvetica', '', 8 ) );

		// Set default monospaced font
		$pdf->SetDefaultMonospacedFont( 'courier' );

		// Set margins
		$pdf->SetMargins( 15, 15, 15 );
		$pdf->SetHeaderMargin( 5 );
		$pdf->SetFooterMargin( 10 );

		// Set auto page breaks
		$pdf->SetAutoPageBreak( TRUE, 25 );

		// Set image scale factor
		$pdf->setImageScale( defined( 'PDF_IMAGE_SCALE_RATIO' ) ? PDF_IMAGE_SCALE_RATIO : 1.25 );

		// Add a page
		$pdf->AddPage();

		// Set font
		$pdf->SetFont( 'helvetica', '', 10 );



		// Barcode Section - Start immediately after page creation

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
				
				// Add image to PDF (100% width, rotated 90 degrees)
				$page_width = $pdf->GetPageWidth();
				$margins = $pdf->getMargins();
				$available_width = $page_width - $margins['left'] - $margins['right'];
				
				// Position image to center it horizontally and use full available width
				$x_position = $margins['left'];
				$pdf->Image( $temp_file, $x_position, $pdf->GetY(), $available_width, 0, 'JPG', '', '', false, 300, '', false, false, 0, false, false, false );
				
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
				error_log( 'Hepsijet return dates WP_Error: ' . $response->get_error_message() );
				wp_send_json_success( array(
					'dates' => array(),
					'message' => 'Unable to fetch return dates at this time'
				) );
			}

			// Response is already decoded from the integration method
			$data = $response;

			// Log the response for debugging
			error_log( 'Hepsijet return dates API response: ' . print_r( $data, true ) );

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
			// Log the exception for debugging
			error_log( 'Hepsijet return dates Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			wp_send_json_success( array(
				'dates' => array(),
				'message' => 'Unable to fetch return dates at this time'
			) );
		}
	}

	/**
	 * Test class loading specifically.
	 * 
	 * @return void
	 */
	public static function test_class_loading() {
		error_log( '=== test_class_loading START ===' );
		
		try {
			// Check user capabilities
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				error_log( 'User capability check failed' );
				wp_send_json_error( 'Insufficient permissions', 403 );
			}
			error_log( 'User capability check passed' );

			// Check if HEZARFEN_MST_PATH is defined
			if ( ! defined( 'HEZARFEN_MST_PATH' ) ) {
				error_log( 'HEZARFEN_MST_PATH constant not defined' );
				wp_send_json_error( 'HEZARFEN_MST_PATH not defined', 500 );
			}
			error_log( 'HEZARFEN_MST_PATH defined: ' . HEZARFEN_MST_PATH );

			// Check if the class file exists
			$class_file = HEZARFEN_MST_PATH . 'includes/courier-companies/class-hepsijet-integration.php';
			error_log( 'Checking if class file exists: ' . $class_file );
			error_log( 'File exists: ' . (file_exists($class_file) ? 'YES' : 'NO') );
			
			// Check if traits exist
			$helper_trait_file = HEZARFEN_MST_PATH . 'includes/trait-helper.php';
			error_log( 'Helper trait file exists: ' . (file_exists($helper_trait_file) ? 'YES' : 'NO') );

			// Try to manually include the file
			error_log( 'Attempting to manually include class file...' );
			require_once $class_file;
			error_log( 'Class file included successfully' );

			// Check if class exists after include (with namespace)
			if ( ! class_exists( 'Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration' ) ) {
				error_log( 'Class still not found after manual include (with namespace)' );
				
				// Try without namespace as fallback
				if ( ! class_exists( 'Courier_Hepsijet_Integration' ) ) {
					error_log( 'Class not found with or without namespace' );
					wp_send_json_error( 'Class not found after manual include', 500 );
				} else {
					error_log( 'Class found without namespace' );
				}
			} else {
				error_log( 'Class found with namespace' );
			}
			error_log( 'Class found after manual include' );

			// Try to instantiate the class (with namespace)
			error_log( 'Attempting to instantiate class...' );
			try {
				$instance = new \Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration();
				error_log( 'Class instantiated successfully with namespace' );
			} catch ( Exception $e ) {
				error_log( 'Failed to instantiate with namespace: ' . $e->getMessage() );
				// Try without namespace as fallback
				$instance = new Courier_Hepsijet_Integration();
				error_log( 'Class instantiated successfully without namespace' );
			}

			wp_send_json_success( array(
				'message' => 'Class loading test passed successfully',
				'timestamp' => current_time( 'mysql' ),
				'user_id' => get_current_user_id(),
				'hezarfen_mst_path' => HEZARFEN_MST_PATH,
				'class_file' => $class_file,
				'class_exists' => class_exists( 'Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration' )
			) );
			
		} catch ( Exception $e ) {
			error_log( 'Class loading test failed with Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			wp_send_json_error( 'Class loading test failed: ' . $e->getMessage(), 500 );
		} catch ( Error $e ) {
			error_log( 'Class loading test failed with Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			wp_send_json_error( 'Class loading test failed: ' . $e->getMessage(), 500 );
		}
		
		error_log( '=== test_class_loading END ===' );
	}

	/**
	 * Test combined endpoint without complex logic.
	 * 
	 * @return void
	 */
	public static function test_combined_endpoint() {
		error_log( '=== test_combined_endpoint START ===' );
		
		try {
			// Check user capabilities
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				error_log( 'User capability check failed' );
				wp_send_json_error( 'Insufficient permissions', 403 );
			}
			error_log( 'User capability check passed' );

			// Check if Courier_Hepsijet_Integration class exists (with namespace)
			if ( ! class_exists( 'Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration' ) ) {
				error_log( 'Courier_Hepsijet_Integration class not found with namespace' );
				
				// Try to check if the file exists
				$class_file = HEZARFEN_MST_PATH . 'includes/courier-companies/class-hepsijet-integration.php';
				error_log( 'Checking if class file exists: ' . $class_file );
				error_log( 'File exists: ' . (file_exists($class_file) ? 'YES' : 'NO') );
				
				// Try to check if traits exist
				$helper_trait_file = HEZARFEN_MST_PATH . 'includes/trait-helper.php';
				error_log( 'Helper trait file exists: ' . (file_exists($helper_trait_file) ? 'YES' : 'NO') );
				
				wp_send_json_error( 'Hepsijet integration class not available', 500 );
			}
			error_log( 'Courier_Hepsijet_Integration class found with namespace' );

			// Check if wc_get_order function exists
			if ( ! function_exists( 'wc_get_order' ) ) {
				error_log( 'wc_get_order function not found' );
				wp_send_json_error( 'WooCommerce not available', 500 );
			}
			error_log( 'wc_get_order function found' );

			wp_send_json_success( array(
				'message' => 'Basic checks passed successfully',
				'timestamp' => current_time( 'mysql' ),
				'user_id' => get_current_user_id(),
				'post_data' => $_POST
			) );
			
		} catch ( Exception $e ) {
			error_log( 'Test combined endpoint failed with Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			wp_send_json_error( 'Test failed: ' . $e->getMessage(), 500 );
		} catch ( Error $e ) {
			error_log( 'Test combined endpoint failed with Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			wp_send_json_error( 'Test failed: ' . $e->getMessage(), 500 );
		}
		
		error_log( '=== test_combined_endpoint END ===' );
	}

	/**
	 * Test PDF endpoint without actual PDF generation.
	 * 
	 * @return void
	 */
	public static function test_pdf_endpoint() {
		wp_send_json_success( array(
			'message' => 'PDF endpoint is working!',
			'timestamp' => current_time( 'mysql' ),
			'user_id' => get_current_user_id(),
			'post_data' => $_POST
		) );
	}

	/**
	 * Get Hepsijet barcode and generate PDF in one request.
	 * This replaces the need for separate barcode and order info calls.
	 * 
	 * @return void
	 */
	public static function get_hepsijet_barcode_pdf() {
		error_log( '=== get_hepsijet_barcode_pdf START ===' );
		
		try {
			check_ajax_referer( self::GET_HEPSIJET_BARCODE_PDF_NONCE );
			error_log( 'Nonce check passed' );

			// Check user capabilities
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				error_log( 'User capability check failed' );
				wp_send_json_error( 'Insufficient permissions', 403 );
			}
			error_log( 'User capability check passed' );

			if ( empty( $_POST['delivery_no'] ) || empty( $_POST['order_id'] ) ) {
				error_log( 'Missing parameters: delivery_no=' . (isset($_POST['delivery_no']) ? $_POST['delivery_no'] : 'NOT_SET') . ', order_id=' . (isset($_POST['order_id']) ? $_POST['order_id'] : 'NOT_SET') );
				wp_send_json_error( 'Missing required parameters', 400 );
			}

			$delivery_no = sanitize_text_field( $_POST['delivery_no'] );
			$order_id = absint( $_POST['order_id'] );
			error_log( 'Parameters sanitized: delivery_no=' . $delivery_no . ', order_id=' . $order_id );

			// Get order info
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				error_log( 'Order not found for ID: ' . $order_id );
				wp_send_json_error( 'Order not found', 404 );
			}
			error_log( 'Order found: ' . $order->get_order_number() );

			// Check if Courier_Hepsijet_Integration class exists (with namespace)
			if ( ! class_exists( 'Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration' ) ) {
				error_log( 'Courier_Hepsijet_Integration class not found with namespace' );
				wp_send_json_error( 'Hepsijet integration class not available', 500 );
			}
			error_log( 'Courier_Hepsijet_Integration class found with namespace' );

			// Get barcode data
			$hepsijet_integration = new \Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration();
			error_log( 'Hepsijet integration instance created' );
			
			$barcode_data = $hepsijet_integration->get_barcode( $delivery_no );
			error_log( 'Barcode data retrieved: ' . (is_wp_error($barcode_data) ? 'WP_ERROR: ' . $barcode_data->get_error_message() : (is_array($barcode_data) ? 'Array with ' . count($barcode_data) . ' items' : 'Type: ' . gettype($barcode_data))) );
			
			if ( is_wp_error( $barcode_data ) ) {
				error_log( 'Barcode data is WP_Error: ' . $barcode_data->get_error_message() );
				wp_send_json_error( $barcode_data->get_error_message(), 500 );
			}

			if ( $barcode_data === false ) {
				error_log( 'Barcode data is false' );
				wp_send_json_error( 'Barcode not found', 404 );
			}

			// Generate PDF using TCPDF
			error_log( 'Starting combined barcode+PDF generation for order ' . $order_id . ' and delivery ' . $delivery_no );
			$pdf_url = self::create_hepsijet_pdf( $order, $barcode_data, $delivery_no );
			error_log( 'Combined barcode+PDF generated successfully: ' . $pdf_url );
			
			// Return both the PDF URL and the barcode data for immediate display
			wp_send_json_success( array(
				'pdf_url' => $pdf_url,
				'barcode_data' => $barcode_data,
				'order_info' => self::get_order_info_data( $order )
			) );
			
		} catch ( Exception $e ) {
			error_log( 'Combined barcode+PDF generation failed with Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			wp_send_json_error( 'PDF generation failed: ' . $e->getMessage(), 500 );
		} catch ( Error $e ) {
			error_log( 'Combined barcode+PDF generation failed with Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			wp_send_json_error( 'PDF generation error: ' . $e->getMessage(), 500 );
		} catch ( Throwable $e ) {
			error_log( 'Combined barcode+PDF generation failed with Throwable: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			wp_send_json_error( 'PDF generation error: ' . $e->getMessage(), 500 );
		}
		
		error_log( '=== get_hepsijet_barcode_pdf END ===' );
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
