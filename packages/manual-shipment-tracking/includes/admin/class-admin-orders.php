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
	 * @param string     $column_key Current column key.
	 * @param int|string $order_id Order ID.
	 * 
	 * @return void
	 */
	public static function render_shipment_column( $column_key, $order_id ) {
		if ( self::SHIPMENT_COLUMN === $column_key ) {
			$shipment_data = Helper::get_all_shipment_data( $order_id );
			if ( $shipment_data ) {
				if ( count( $shipment_data ) > 1 ) {
					printf( '<p>%s</p>', esc_html__( 'Shipment in pieces', 'hezarfen-for-woocommerce' ) );
				} else {
					$courier = Helper::get_courier_class( $shipment_data[0]->courier_id );
					if ( $courier::$logo ) {
						printf( '<img src="%s" class="courier-logo">', esc_url( HEZARFEN_MST_COURIER_LOGO_URL . $courier::$logo ) );
					} else {
						printf( '<p>%s</p>', esc_html( $courier::get_title( $order_id ) ) );
					}
				}

				printf( '<span data-order-id="%s" class="dashicons dashicons-info-outline shipment-info-icon"></span>', esc_attr( $order_id ) );
			} else { 
				printf( '<p class="no-shipment-found">%s</p>', apply_filters( 'hezarfen_shop_order_no_shipment_found_msg', esc_html__( 'No shipment data found', 'hezarfen-for-woocommerce' ), $order_id ) );
			}
		}
	}

	/**
	 * Adds a meta box to the admin order edit page.
	 * 
	 * @param string $post_type Post type.
	 * 
	 * @return void
	 */
	public static function add_meta_box( $post_type ) {
		if ( 'shop_order' !== $post_type ) {
			return;
		}

		add_meta_box(
			'hezarfen-mst-order-edit-metabox',
			__( 'Hezarfen Cargo Tracking', 'hezarfen-for-woocommerce' ),
			array( __CLASS__, 'render_order_edit_metabox' ),
			'shop_order',
			'normal',
			'high'
		);
	}

	/**
	 * Renders the meta box in the admin order edit page.
	 * 
	 * @param \WP_Post $post The Post object.
	 * 
	 * @return void
	 */
	public static function render_order_edit_metabox( $post ) {
		wp_enqueue_script( 'hezarfen-order-edit', WC_HEZARFEN_UYGULAMA_URL . 'assets/admin/order-edit/build/main.js', array( 'jquery', 'jquery-ui-dialog' ), WC_HEZARFEN_VERSION );
		wp_localize_script(
			'hezarfen-order-edit',
			'hezarfen_mst_backend',
			array(
				'courier_select_placeholder'  => __( 'Choose a courier company', 'hezarfen-for-woocommerce' ),
				'duplicate_btn_tooltip_text'  => __( 'Add new shipment', 'hezarfen-for-woocommerce' ),
				'modal_btn_delete_text'       => __( 'Delete', 'hezarfen-for-woocommerce' ),
				'modal_btn_cancel_text'       => __( 'Cancel', 'hezarfen-for-woocommerce' ),
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

		$order_id      = $post->ID;
		$shipment_data = Helper::get_all_shipment_data( $order_id );

		if ( ! $shipment_data ) {
			$shipment_data[] = new Shipment_Data();
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

	/**
	 * Enqueues CSS files.
	 * 
	 * @param string $hook_suffix Hook suffix.
	 * 
	 * @return void
	 */
	public static function enqueue_scripts_and_styles( $hook_suffix ) {
		global $typenow;

		if ( 'shop_order' !== $typenow ) {
			return;
		}

		if ( 'edit.php' === $hook_suffix ) {
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
}
