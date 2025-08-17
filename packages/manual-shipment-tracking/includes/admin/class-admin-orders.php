<?php
/**
 * Contains the Admin_Orders class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Adds new features related to orders in the admin panel.
 */
class Admin_Orders {
	const DATA_ARRAY_KEY         = 'hezarfen_mst_shipment_data';
	const COURIER_HTML_NAME      = 'courier_company';
	const TRACKING_NUM_HTML_NAME = 'tracking_number';
	const SHIPMENT_COLUMN        = 'hezarfen_mst_shipment_info';

	/**
	 * Initialization method.
	 * 
	 * @return void
	 */
	public static function init() {
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts_and_styles' ) );

			add_filter( 'manage_shop_order_posts_columns', array( __CLASS__, 'add_shipment_column' ), PHP_INT_MAX - 1 );
			add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_shipment_column' ), 10, 2 );
			add_filter( 'woocommerce_shop_order_list_table_columns', array( __CLASS__, 'add_shipment_column' ), PHP_INT_MAX - 1 );
			add_action( 'woocommerce_shop_order_list_table_custom_column', array( __CLASS__, 'render_shipment_column' ), 10, 2 );

			add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );

			add_filter( 'woocommerce_reports_order_statuses', array( __CLASS__, 'append_order_status_to_reports' ), 20 );
		}
	}

	/**
	 * Adds the "Shipment" column to the orders page.
	 * 
	 * @param array<string, string> $columns Columns.
	 * 
	 * @return array<string, string>
	 */
	public static function add_shipment_column( $columns ) {
		$reordered = array();

		foreach ( $columns as $key => $title ) {
			$reordered[ $key ] = $title;

			if ( 'order_status' === $key ) {
				$reordered[ self::SHIPMENT_COLUMN ] = __( 'Shipment', 'hezarfen-for-woocommerce' );
			}
		}

		return $reordered;
	}

	/**
	 * Outputs the "Shipment" column HTML.
	 * 
	 * @param string        $column_key Current column key.
	 * @param int|\WC_Order $order Order ID or object.
	 * 
	 * @return void
	 */
	public static function render_shipment_column( $column_key, $order ) {
		if ( self::SHIPMENT_COLUMN === $column_key ) { // TODO: early return.
			$order_id      = $order instanceof \WC_Order ? $order->get_id() : $order;
			$shipment_data = Helper::get_all_shipment_data( $order_id );
			if ( $shipment_data ) {
				if ( count( $shipment_data ) > 1 ) {
					printf( '<p>%s</p>', esc_html__( 'Shipment in pieces', 'hezarfen-for-woocommerce' ) );
				} else {
					$courier = Helper::get_courier_class( $shipment_data[0]->courier_id );
					if ( $courier::$logo ) {
						printf( '<img src="%s" class="courier-logo" loading="lazy" alt="%s">', esc_url( HEZARFEN_MST_COURIER_LOGO_URL . $courier::$logo ), esc_attr( $courier::get_title( $order_id ) ) );
					} else {
						printf( '<p>%s</p>', esc_html( $courier::get_title( $order_id ) ) );
					}
				}

				printf( '<span data-order-id="%s" class="dashicons dashicons-info-outline shipment-info-icon"></span>', esc_attr( $order_id ) );
			} else {
				$no_shipment_msg = apply_filters( 'hezarfen_shop_order_no_shipment_found_msg', null, $order_id );

				if( is_null( $no_shipment_msg ) ) {
					esc_html_e( 'No shipment data found', 'hezarfen-for-woocommerce' );
				}else{
					printf( $no_shipment_msg );
				}
			}
		}
	}

	/**
	 * Adds a meta box to the admin order edit page.
	 *  
	 * @return void
	 */
	public static function add_meta_box() {
		if ( ! \Hezarfen\Inc\Helper::is_order_edit_page() ) {
			return;
		}

		// Note: For the recent versions of Woocommerce, wc_get_page_screen_id() function can be used alone, without the need to check if HPOS is enabled or not.
		// We are checking because we must support older Woocommerce versions.
		$screen = WC_HEZARFEN_HPOS_ENABLED ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

		add_meta_box(
			'hezarfen-mst-order-edit-metabox',
			__( 'Hezarfen Cargo Tracking & SMS Notifications', 'hezarfen-for-woocommerce' ),
			array( __CLASS__, 'render_order_edit_metabox' ),
			$screen,
			'normal',
			'high'
		);
	}

	/**
	 * Renders the meta box in the admin order edit page.
	 * 
	 * @param \WP_Post|\WC_Order $order The Order or Post object.
	 * 
	 * @return void
	 */
	public static function render_order_edit_metabox( $order ) {
		wp_enqueue_script( 'hezarfen-order-edit', WC_HEZARFEN_UYGULAMA_URL . 'assets/admin/order-edit/build/main.js', array( 'jquery', 'jquery-ui-dialog' ), WC_HEZARFEN_VERSION );
		wp_localize_script(
			'hezarfen-order-edit',
			'hezarfen_mst_backend',
			array(
				'courier_select_placeholder'  => __( 'Choose a courier company', 'hezarfen-for-woocommerce' ),
				'duplicate_btn_tooltip_text'  => __( 'Add new shipment', 'hezarfen-for-woocommerce' ),
				'modal_btn_delete_text'       => __( 'Delete', 'hezarfen-for-woocommerce' ),
				'modal_btn_cancel_text'       => __( 'Cancel', 'hezarfen-for-woocommerce' ),
				'thank_you_message'           => __( 'Thank you for supporting Hezarfen! 🌟', 'hezarfen-for-woocommerce' ),
				'removing_text'               => __( 'Removing...', 'hezarfen-for-woocommerce' ),
				'error_removing_shipment'     => __( 'Error removing shipment data. Please try again.', 'hezarfen-for-woocommerce' ),
				'campaign_ended'              => __( 'Campaign ended', 'hezarfen-for-woocommerce' ),
				'courier_logo_base_url'       => HEZARFEN_MST_COURIER_LOGO_URL,
				'remove_shipment_data_action' => Admin_Ajax::REMOVE_SHIPMENT_DATA_ACTION,
				'remove_shipment_data_nonce'  => wp_create_nonce( Admin_Ajax::REMOVE_SHIPMENT_DATA_NONCE ),
				'new_shipment_data_action' => Admin_Ajax::NEW_SHIPMENT_DATA_ACTION,
				'new_shipment_data_nonce'  => wp_create_nonce( Admin_Ajax::NEW_SHIPMENT_DATA_NONCE ),
				'new_shipment_courier_html_name' => Admin_Ajax::COURIER_HTML_NAME,
				'new_shipment_tracking_num_html_name' => Admin_Ajax::TRACKING_NUM_HTML_NAME
			)
		);
		wp_enqueue_style( 'hezarfen-order-edit', WC_HEZARFEN_UYGULAMA_URL . 'assets/admin/order-edit/build/style-main.css', array(), WC_HEZARFEN_VERSION );

		$order_id      = $order instanceof \WC_Order ? $order->get_id() : $order->ID;
		$shipment_data = Helper::get_all_shipment_data( $order_id );

		if ( ! $shipment_data ) {
			$shipment_data[] = new Shipment_Data();
		}

		if( defined('HEZARFEN_PRO_VERSION') ){
			wp_enqueue_script( 'hez_pro_flowbite', WC_HEZARFEN_UYGULAMA_URL . 'assets/admin/flowbite/build/main.js', array('jquery'), WC_HEZARFEN_VERSION );
		}

		require_once WC_HEZARFEN_UYGULAMA_YOLU . 'packages/manual-shipment-tracking/templates/order-edit/metabox-shipment.php';
	}

	/**
	 * Shows new order status in reports.
	 *
	 * @param string[] $statuses Current order report statuses.
	 * 
	 * @return string[]
	 */
	public static function append_order_status_to_reports( $statuses ) {
		$statuses[] = Manual_Shipment_Tracking::SHIPPED_ORDER_STATUS;
		return $statuses;
	}

	private static function is_wc_order_list_screen() {
		$screen = get_current_screen();

		if (isset($screen->post_type) && 'shop_order' === $screen->post_type) {
			return true;
		}

		return false;
	}

	/**
	 * Enqueues CSS files.
	 * 
	 * @param string $hook_suffix Hook suffix.
	 * 
	 * @return void
	 */
	public static function enqueue_scripts_and_styles( $hook_suffix ) {
		if( ! self::is_wc_order_list_screen() ){
			return;
		}

		wp_enqueue_style( 'hezarfen_mst_admin_orders_css', HEZARFEN_MST_ASSETS_URL . 'css/admin/orders.css', array(), WC_HEZARFEN_VERSION );
		wp_enqueue_script( 'hezarfen_mst_admin_orders_js', HEZARFEN_MST_ASSETS_URL . 'js/admin/orders.js', array( 'jquery', 'jquery-tiptip' ), WC_HEZARFEN_VERSION, true );

		wp_localize_script(
			'hezarfen_mst_admin_orders_js',
			'hezarfen_mst_backend',
			array(
				'get_shipment_data_nonce'  => wp_create_nonce( Admin_Ajax::GET_SHIPMENT_DATA_NONCE ),
				'get_shipment_data_action' => Admin_Ajax::GET_SHIPMENT_DATA_ACTION,
				'tooltip_placeholder'      => esc_html__( 'Fetching data..', 'hezarfen-for-woocommerce' ),
				'courier_company_i18n'     => esc_html__( 'Courier Company', 'hezarfen-for-woocommerce' ),
				'tracking_num_i18n'        => esc_html__( 'Tracking Number', 'hezarfen-for-woocommerce' ),
			)
		);
	}
}
