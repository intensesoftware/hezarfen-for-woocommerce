<?php
/**
 * Contains the Hepsijet_Bulk_Barcode class.
 *
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Handles bulk barcode creation and printing for HepsiJet shipments.
 */
class Hepsijet_Bulk_Barcode {

	const BULK_ACTION_KEY          = 'hezarfen_hepsijet_bulk_barcode';
	const TRANSIENT_PREFIX         = 'hezarfen_hepsijet_bulk_';
	const TRANSIENT_EXPIRATION     = 3600; // 1 hour
	const AJAX_ACTION              = 'hezarfen_hepsijet_create_single_barcode';
	const AJAX_NONCE               = 'hezarfen-hepsijet-create-single-barcode';
	const AJAX_GET_BARCODE_ACTION  = 'hezarfen_hepsijet_bulk_get_barcode';
	const AJAX_GET_BARCODE_NONCE   = 'hezarfen-hepsijet-bulk-get-barcode';
	const ADMIN_PAGE_SLUG          = 'hezarfen-hepsijet-bulk-barcode';

	/**
	 * Initialization method.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! is_admin() ) {
			return;
		}

		// Register bulk actions for both HPOS and Non-HPOS.
		add_filter( 'bulk_actions-edit-shop_order', array( __CLASS__, 'register_bulk_action' ) );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( __CLASS__, 'register_bulk_action' ) );

		// Handle bulk actions for both HPOS and Non-HPOS.
		add_filter( 'handle_bulk_actions-edit-shop_order', array( __CLASS__, 'handle_bulk_action' ), 10, 3 );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( __CLASS__, 'handle_bulk_action' ), 10, 3 );

		// Register hidden admin page.
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ) );

		// Register AJAX handlers.
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_create_single_barcode' ) );
		add_action( 'wp_ajax_' . self::AJAX_GET_BARCODE_ACTION, array( __CLASS__, 'ajax_get_barcode_for_print' ) );
	}

	/**
	 * Registers the bulk action in the orders dropdown.
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array Modified bulk actions.
	 */
	public static function register_bulk_action( $actions ) {
		$actions[ self::BULK_ACTION_KEY ] = __( 'HepsiJet Barkod Oluştur/Yazdır', 'hezarfen-for-woocommerce' );
		return $actions;
	}

	/**
	 * Handles the bulk action submission.
	 *
	 * @param string $redirect_url Redirect URL.
	 * @param string $action       The action being taken.
	 * @param array  $order_ids    Selected order IDs.
	 * @return string Modified redirect URL.
	 */
	public static function handle_bulk_action( $redirect_url, $action, $order_ids ) {
		if ( self::BULK_ACTION_KEY !== $action || empty( $order_ids ) ) {
			return $redirect_url;
		}

		// Sanitize order IDs.
		$order_ids = array_map( 'absint', $order_ids );
		$order_ids = array_filter( $order_ids );

		if ( empty( $order_ids ) ) {
			return $redirect_url;
		}

		// Store order IDs in a transient.
		$transient_key = self::TRANSIENT_PREFIX . get_current_user_id() . '_' . wp_generate_password( 8, false );
		set_transient( $transient_key, $order_ids, self::TRANSIENT_EXPIRATION );

		// Redirect to the bulk barcode page.
		return admin_url( 'admin.php?page=' . self::ADMIN_PAGE_SLUG . '&bulk_key=' . urlencode( $transient_key ) );
	}

	/**
	 * Registers the hidden admin page.
	 *
	 * @return void
	 */
	public static function register_admin_page() {
		add_submenu_page(
			'', // No parent — hidden page.
			__( 'HepsiJet Toplu Barkod', 'hezarfen-for-woocommerce' ),
			'',
			'manage_woocommerce',
			self::ADMIN_PAGE_SLUG,
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Renders the bulk barcode admin page.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok.', 'hezarfen-for-woocommerce' ) );
		}

		$bulk_key = isset( $_GET['bulk_key'] ) ? sanitize_text_field( wp_unslash( $_GET['bulk_key'] ) ) : '';

		if ( empty( $bulk_key ) ) {
			wp_die( esc_html__( 'Geçersiz istek.', 'hezarfen-for-woocommerce' ) );
		}

		$order_ids = get_transient( $bulk_key );

		if ( false === $order_ids || ! is_array( $order_ids ) ) {
			wp_die( esc_html__( 'Sipariş verileri bulunamadı veya süresi dolmuş. Lütfen tekrar deneyin.', 'hezarfen-for-woocommerce' ) );
		}

		// Gather order data.
		$orders_data = self::get_orders_data( $order_ids );

		// Enqueue assets.
		self::enqueue_assets( $orders_data );

		// Load the template.
		require_once HEZARFEN_MST_PATH . 'includes/views/hepsijet-bulk-barcode-page.php';
	}

	/**
	 * Gathers order data for the bulk barcode page.
	 *
	 * @param array $order_ids Order IDs.
	 * @return array Array of order data.
	 */
	public static function get_orders_data( $order_ids ) {
		$orders_data = array();

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				continue;
			}

			$hepsijet_shipment = self::get_active_hepsijet_shipment( $order );

			// Gather order items summary.
			$items_summary = array();
			foreach ( $order->get_items() as $item ) {
				$qty  = $item->get_quantity();
				$name = $item->get_name();
				$items_summary[] = $qty > 1 ? $name . ' x' . $qty : $name;
			}

			$orders_data[] = array(
				'order_id'      => $order->get_id(),
				'order_number'  => $order->get_order_number(),
				'customer_name' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
				'items'         => $items_summary,
				'has_barcode'   => ! empty( $hepsijet_shipment ),
				'delivery_no'   => $hepsijet_shipment ? $hepsijet_shipment['delivery_no'] : '',
				'desi'          => $hepsijet_shipment ? floatval( $hepsijet_shipment['desi'] ) : '',
			);
		}

		return $orders_data;
	}

	/**
	 * Finds the active HepsiJet shipment for an order.
	 *
	 * @param \WC_Order $order Order object.
	 * @return array|null Shipment details or null.
	 */
	public static function get_active_hepsijet_shipment( $order ) {
		$all_meta = $order->get_meta_data();

		foreach ( $all_meta as $meta ) {
			if ( strpos( $meta->key, '_hezarfen_hepsijet_shipment_' ) === 0 ) {
				$meta_value = $meta->value;

				if (
					is_array( $meta_value )
					&& isset( $meta_value['delivery_no'] )
					&& ( ! isset( $meta_value['status'] ) || 'active' === $meta_value['status'] )
				) {
					return $meta_value;
				}
			}
		}

		return null;
	}

	/**
	 * Enqueues scripts and styles for the bulk barcode page.
	 *
	 * @param array $orders_data Orders data to pass to JavaScript.
	 * @return void
	 */
	private static function enqueue_assets( $orders_data ) {
		wp_enqueue_style(
			'hezarfen-hepsijet-bulk-barcode-css',
			HEZARFEN_MST_ASSETS_URL . 'css/admin/hepsijet-bulk-barcode.css',
			array(),
			WC_HEZARFEN_VERSION
		);

		wp_enqueue_script(
			'hezarfen-hepsijet-bulk-barcode-js',
			HEZARFEN_MST_ASSETS_URL . 'js/admin/hepsijet-bulk-barcode.js',
			array( 'jquery' ),
			WC_HEZARFEN_VERSION,
			true
		);

		wp_localize_script(
			'hezarfen-hepsijet-bulk-barcode-js',
			'hezarfen_bulk_barcode',
			array(
				'ajax_url'              => admin_url( 'admin-ajax.php' ),
				'create_action'         => self::AJAX_ACTION,
				'create_nonce'          => wp_create_nonce( self::AJAX_NONCE ),
				'get_barcode_action'    => self::AJAX_GET_BARCODE_ACTION,
				'get_barcode_nonce'     => wp_create_nonce( self::AJAX_GET_BARCODE_NONCE ),
				'combined_action'       => Admin_Ajax::GET_COMBINED_BARCODE_ACTION,
				'combined_nonce'        => wp_create_nonce( Admin_Ajax::GET_COMBINED_BARCODE_NONCE ),
				'orders_data'           => $orders_data,
				'i18n'                  => array(
					'creating'              => __( 'Barkod Oluşturuluyor...', 'hezarfen-for-woocommerce' ),
					'processing'            => __( 'İşleniyor...', 'hezarfen-for-woocommerce' ),
					'waiting'               => __( 'Bekliyor...', 'hezarfen-for-woocommerce' ),
					'skipped'               => __( 'Atlandı (mevcut barkod)', 'hezarfen-for-woocommerce' ),
					'success'               => __( 'Başarılı', 'hezarfen-for-woocommerce' ),
					'error'                 => __( 'Hata', 'hezarfen-for-woocommerce' ),
					'cancelled'             => __( 'İptal edildi', 'hezarfen-for-woocommerce' ),
					'completed'             => __( 'Tamamlandı', 'hezarfen-for-woocommerce' ),
					'estimated_remaining'   => __( 'Tahmini kalan süre', 'hezarfen-for-woocommerce' ),
					'seconds_abbr'          => __( 'sn', 'hezarfen-for-woocommerce' ),
					'cancel_btn'            => __( 'İptal Et', 'hezarfen-for-woocommerce' ),
					'print_btn'             => __( 'Başarılıları Yazdır', 'hezarfen-for-woocommerce' ),
					'retry_btn'             => __( 'Hataları Tekrar Dene', 'hezarfen-for-woocommerce' ),
					'desi_required'         => __( 'Barkodu olmayan tüm siparişler için desi değeri girilmelidir.', 'hezarfen-for-woocommerce' ),
					'no_orders_to_process'  => __( 'İşlenecek sipariş bulunamadı.', 'hezarfen-for-woocommerce' ),
					'confirm_cancel'        => __( 'İşlemi iptal etmek istediğinize emin misiniz?', 'hezarfen-for-woocommerce' ),
					'preparing_print'       => __( 'Yazdırma hazırlanıyor...', 'hezarfen-for-woocommerce' ),
					'print_error'           => __( 'Yazdırma verisi alınırken hata oluştu.', 'hezarfen-for-woocommerce' ),
					'barcode_created'       => __( 'Barkod oluşturuldu', 'hezarfen-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * AJAX handler: Creates a single barcode for one order.
	 *
	 * @return void
	 */
	public static function ajax_create_single_barcode() {
		check_ajax_referer( self::AJAX_NONCE );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Yetkisiz işlem.', 'hezarfen-for-woocommerce' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$desi     = isset( $_POST['desi'] ) ? floatval( $_POST['desi'] ) : 0;

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Geçersiz sipariş ID.', 'hezarfen-for-woocommerce' ) ) );
		}

		if ( $desi < 0.01 || $desi > 9999 ) {
			wp_send_json_error( array( 'message' => __( 'Geçersiz desi değeri.', 'hezarfen-for-woocommerce' ) ) );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Sipariş bulunamadı.', 'hezarfen-for-woocommerce' ) ) );
		}

		// Check if order already has an active HepsiJet barcode.
		$existing = self::get_active_hepsijet_shipment( $order );
		if ( $existing ) {
			wp_send_json_success( array(
				'barcode'      => $existing['delivery_no'],
				'already_exists' => true,
			) );
		}

		// Prepare packages array with the given desi.
		$packages = array(
			array( 'desi' => $desi ),
		);

		// Use the existing integration class to create the barcode.
		$hepsijet_integration = new Courier_Hepsijet_Integration();
		$result = $hepsijet_integration->api_create_barcode( $order_id, $packages );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		if ( ! empty( $result['tracking_number'] ) ) {
			wp_send_json_success( array(
				'barcode'        => $result['tracking_number'],
				'already_exists' => false,
			) );
		}

		wp_send_json_error( array( 'message' => __( 'Barkod oluşturulamadı.', 'hezarfen-for-woocommerce' ) ) );
	}

	/**
	 * AJAX handler: Gets barcode label data for printing.
	 *
	 * @return void
	 */
	public static function ajax_get_barcode_for_print() {
		check_ajax_referer( self::AJAX_GET_BARCODE_NONCE );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Yetkisiz işlem.', 'hezarfen-for-woocommerce' ) ) );
		}

		$delivery_no = isset( $_POST['delivery_no'] ) ? sanitize_text_field( wp_unslash( $_POST['delivery_no'] ) ) : '';
		$order_id    = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( empty( $delivery_no ) || ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Eksik parametreler.', 'hezarfen-for-woocommerce' ) ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Sipariş bulunamadı.', 'hezarfen-for-woocommerce' ) ) );
		}

		$hepsijet_integration = new Courier_Hepsijet_Integration();
		$barcode_data = $hepsijet_integration->get_barcode( $delivery_no );

		if ( is_wp_error( $barcode_data ) ) {
			wp_send_json_error( array( 'message' => $barcode_data->get_error_message() ) );
		}

		if ( false === $barcode_data ) {
			wp_send_json_error( array( 'message' => __( 'Barkod verisi bulunamadı.', 'hezarfen-for-woocommerce' ) ) );
		}

		wp_send_json_success( array(
			'barcode_data'  => $barcode_data,
			'delivery_no'   => $delivery_no,
			'order_number'  => $order->get_order_number(),
			'customer_name' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
		) );
	}
}
