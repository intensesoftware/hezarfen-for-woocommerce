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
			add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'order_save' ), PHP_INT_MAX - 1 );
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
				printf( '<p class="no-shipment-found">%s</p>', esc_html__( 'No shipment data found', 'hezarfen-for-woocommerce' ) );
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
			__( 'Hezarfen', 'hezarfen-for-woocommerce' ),
			array( __CLASS__, 'render_order_edit_metabox' ),
			'shop_order',
			'side',
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
		$order_id      = $post->ID;
		$shipment_data = Helper::get_all_shipment_data( $order_id );

		if ( ! $shipment_data ) {
			$shipment_data[] = new Shipment_Data();
		}

		?>
		<div id="modal-body" title="<?php esc_attr_e( 'Remove shipment data?', 'hezarfen-for-woocommerce' ); ?>" class="hidden">
			<span class="ui-icon ui-icon-alert"></span>
			<p><?php esc_html_e( 'Are you sure you want to remove this shipment data?', 'hezarfen-for-woocommerce' ); ?></p>
		</div>
		<div class="shipment-forms-wrapper">
			<?php 
			foreach ( $shipment_data as $data ) {
				self::render_shipment_form_elements( $data );
			}
			?>
		</div>
		<a class="button duplicate-form">+</a>
		<?php
	}

	/**
	 * Renders the shipment form elements.
	 * 
	 * @param Shipment_Data $shipment_data Shipment data.
	 * 
	 * @return void
	 */
	private static function render_shipment_form_elements( $shipment_data ) {
		$courier_select_name  = sprintf( '%s[%s][%s]', self::DATA_ARRAY_KEY, $shipment_data->id, self::COURIER_HTML_NAME );
		$courier_select_value = $shipment_data->courier_id ? $shipment_data->courier_id : Helper::get_default_courier_id();
		$courier_select_label = __( 'Courier Company', 'hezarfen-for-woocommerce' );
		if ( Helper::is_custom_courier( $shipment_data->courier_id ) ) {
			$courier_select_label = sprintf( '%s <span class="custom-courier-title">(%s)</span>', $courier_select_label, $shipment_data->courier_title );
		}
		?>
		<div class="shipment-form" data-id="<?php echo esc_attr( strval( $shipment_data->id ) ); ?>">
			<a class="remove-form">
				<span class="remove-form-icon"></span>
			</a>
			<p class="form-field courier-company-select-wrapper">
				<label>
					<?php echo wp_kses_post( $courier_select_label ); ?>
					<select class="courier-company-select" name="<?php echo esc_attr( $courier_select_name ); ?>">
						<?php foreach ( Helper::courier_company_options( true ) as $courier_id => $courier_label ) : ?>
							<option value="<?php echo esc_attr( $courier_id ); ?>" data-logo="<?php echo esc_attr( Helper::get_courier_class( $courier_id )::$logo ); ?>" <?php selected( $courier_select_value, $courier_id ); ?>><?php echo esc_html( $courier_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</p>
			<p class="form-field tracking-num-input-wrapper">
				<label>
					<?php esc_html_e( 'Tracking Number', 'hezarfen-for-woocommerce' ); ?>
					<?php if ( $shipment_data->tracking_url ) : ?>
						<a href="<?php echo esc_url( $shipment_data->tracking_url ); ?>" target="_blank"><?php esc_html_e( '(Track Cargo)', 'hezarfen-for-woocommerce' ); ?></a>
					<?php endif; ?>
					<input
						type="text"
						name="<?php echo esc_attr( sprintf( '%s[%s][%s]', self::DATA_ARRAY_KEY, $shipment_data->id, self::TRACKING_NUM_HTML_NAME ) ); ?>"
						value="<?php echo esc_attr( $shipment_data->tracking_num ); ?>"
						class="tracking-num-input"
						placeholder="<?php esc_attr_e( 'Enter tracking number', 'hezarfen-for-woocommerce' ); ?>">
				</label>
			</p>
		</div>
		<?php
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
	 * Saves the data.
	 * 
	 * @param int|string $order_id Order ID.
	 * 
	 * @return void
	 */
	public static function order_save( $order_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST[ self::DATA_ARRAY_KEY ] ) ) {
			return;
		}

		foreach ( $_POST[ self::DATA_ARRAY_KEY ] as $id => $post_data ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$new_courier_id   = ! empty( $post_data[ self::COURIER_HTML_NAME ] ) ? sanitize_text_field( $post_data[ self::COURIER_HTML_NAME ] ) : '';
			$new_tracking_num = ! empty( $post_data[ self::TRACKING_NUM_HTML_NAME ] ) ? sanitize_text_field( $post_data[ self::TRACKING_NUM_HTML_NAME ] ) : '';

			if ( ! $new_courier_id || ! $new_tracking_num ) {
				continue;
			}

			$new_courier  = Helper::get_courier_class( $new_courier_id );
			$current_data = Helper::get_shipment_data_by_id( $id, $order_id, true );

			if ( ! $current_data ) {
				$new_data = new Shipment_Data(
					array(
						'id'            => $id,
						'order_id'      => $order_id,
						'courier_id'    => $new_courier_id,
						'courier_title' => $new_courier::get_title(),
						'tracking_num'  => $new_tracking_num,
						'tracking_url'  => $new_courier::create_tracking_url( $new_tracking_num ),
					)
				);

				$new_data->save( true );
				do_action( 'hezarfen_mst_shipment_data_saved', $order_id, $new_data );

				continue;
			}

			if ( $current_data->courier_id === $new_courier_id && $current_data->tracking_num === $new_tracking_num ) {
				continue;
			}

			$current_data->courier_id    = $new_courier_id;
			$current_data->courier_title = $new_courier::get_title();
			$current_data->tracking_num  = $new_tracking_num;
			$current_data->tracking_url  = $new_courier::create_tracking_url( $new_tracking_num );

			$result = $current_data->save();

			if ( true === $result ) {
				do_action( 'hezarfen_mst_shipment_data_saved', $order_id, $current_data );
			}
		}

		if ( did_action( 'hezarfen_mst_shipment_data_saved' ) ) {
			$order = new \WC_Order( $order_id );
			$order->update_status( apply_filters( 'hezarfen_mst_new_order_status', Manual_Shipment_Tracking::SHIPPED_ORDER_STATUS, $order, $new_courier_id, $new_tracking_num ) ); // @phpstan-ignore-line

			if ( 'yes' === get_option( Settings::OPT_ENABLE_SMS ) ) {
				Helper::send_notification( $order );
			}

			do_action( 'hezarfen_mst_order_shipped', $order );
		}

		// phpcs:enable
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

		if ( 'post.php' === $hook_suffix ) {
			wp_enqueue_style( 'hezarfen_mst_admin_order_edit_css', HEZARFEN_MST_ASSETS_URL . 'css/admin/order-edit.css', array(), WC_HEZARFEN_VERSION );
			wp_enqueue_script( 'hezarfen_mst_admin_order_edit_js', HEZARFEN_MST_ASSETS_URL . 'js/admin/order-edit.js', array( 'jquery', 'jquery-ui-dialog' ), WC_HEZARFEN_VERSION, true );

			wp_localize_script(
				'hezarfen_mst_admin_order_edit_js',
				'hezarfen_mst_backend',
				array(
					'courier_select_placeholder'  => __( 'Choose a courier company', 'hezarfen-for-woocommerce' ),
					'courier_logo_base_url'       => HEZARFEN_MST_COURIER_LOGO_URL,
					'remove_shipment_data_action' => Admin_Ajax::REMOVE_SHIPMENT_DATA_ACTION,
					'remove_shipment_data_nonce'  => wp_create_nonce( Admin_Ajax::REMOVE_SHIPMENT_DATA_NONCE ),
				)
			);
		}
	}
}
