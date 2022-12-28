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

	/**
	 * Constructor
	 */
	public function __construct() {
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_and_styles' ) );

			add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );

			add_filter( 'woocommerce_reports_order_statuses', array( $this, 'append_order_status_to_reports' ), 20 );
			add_action( 'woocommerce_process_shop_order_meta', array( $this, 'order_save' ), PHP_INT_MAX - 1 );
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

		foreach ( $shipment_data as $data ) {
			self::render_shipment_form_elements( $data );
		}
	}

	/**
	 * Renders the shipment form elements.
	 * 
	 * @param Shipment_Data $shipment_data Shipment data.
	 * 
	 * @return void
	 */
	private static function render_shipment_form_elements( $shipment_data ) {
		$courier_select_label = __( 'Courier Company', 'hezarfen-for-woocommerce' );
		if ( Helper::is_custom_courier( $shipment_data->courier_id ) ) {
			$courier_select_label = sprintf( '%s (%s)', $courier_select_label, $shipment_data->courier_title );
		}
		?>
		<div class="shipment-info">
			<?php
			woocommerce_wp_select(
				array(
					'id'      => sprintf( '%s[%s][%s]', self::DATA_ARRAY_KEY, $shipment_data->id, self::COURIER_HTML_NAME ),
					'label'   => $courier_select_label,
					'value'   => $shipment_data->courier_id ? $shipment_data->courier_id : Helper::get_default_courier_id(),
					'options' => Helper::courier_company_options(),
					'class'   => 'courier-company-select',
				)
			);
			?>
			<p class="form-field">
				<label for="<?php echo esc_attr( self::TRACKING_NUM_HTML_NAME ); ?>">
					<?php esc_html_e( 'Tracking Number', 'hezarfen-for-woocommerce' ); ?>
				</label>
				<?php if ( $shipment_data->tracking_url ) : ?>
					<a href="<?php echo esc_url( $shipment_data->tracking_url ); ?>" target="_blank"><?php esc_html_e( '(Track Cargo)', 'hezarfen-for-woocommerce' ); ?></a>
				<?php endif; ?>
				<input
					type="text"
					name="<?php echo esc_attr( sprintf( '%s[%s][%s]', self::DATA_ARRAY_KEY, $shipment_data->id, self::TRACKING_NUM_HTML_NAME ) ); ?>"
					id="<?php echo esc_attr( self::TRACKING_NUM_HTML_NAME ); ?>"
					value="<?php echo esc_attr( $shipment_data->tracking_num ); ?>"
					placeholder="<?php esc_attr_e( 'Enter tracking number', 'hezarfen-for-woocommerce' ); ?>">
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
	public function append_order_status_to_reports( $statuses ) {
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
	public function order_save( $order_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST[ self::DATA_ARRAY_KEY ] ) ) {
			return;
		}

		foreach ( $_POST[ self::DATA_ARRAY_KEY ] as $id => $post_data ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$new_courier_id   = ! empty( $post_data[ self::COURIER_HTML_NAME ] ) ? sanitize_text_field( $post_data[ self::COURIER_HTML_NAME ] ) : '';
			$new_tracking_num = ! empty( $post_data[ self::TRACKING_NUM_HTML_NAME ] ) ? sanitize_text_field( $post_data[ self::TRACKING_NUM_HTML_NAME ] ) : '';

			if ( ! $new_courier_id ) {
				continue;
			}

			$new_courier  = Helper::get_courier_class( $new_courier_id );
			$current_data = Helper::get_shipment_data_by_id( $id, $order_id, true );

			if ( ! $current_data ) {
				$new_data = new Shipment_Data(
					array(
						$id,
						$order_id,
						$new_courier_id,
						$new_courier::get_title(),
						$new_tracking_num,
						$new_courier::create_tracking_url( $new_tracking_num ),
					)
				);

				$new_data->save( true );
				do_action( 'hezarfen_mst_tracking_data_saved', $order_id, $new_data );

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
				do_action( 'hezarfen_mst_tracking_data_saved', $order_id, $current_data );
			}
		}

		if ( did_action( 'hezarfen_mst_tracking_data_saved' ) ) {
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
	public function enqueue_scripts_and_styles( $hook_suffix ) {
		global $typenow;

		if ( 'shop_order' !== $typenow ) {
			return;
		}

		if ( 'edit.php' === $hook_suffix ) {
			wp_enqueue_style( 'hezarfen_mst_admin_orders_page_css', HEZARFEN_MST_ASSETS_URL . 'css/admin/orders-page.css', array(), WC_HEZARFEN_VERSION );
		}

		if ( 'post.php' === $hook_suffix ) {
			wp_enqueue_script( 'hezarfen_mst_admin_order_edit_page_js', HEZARFEN_MST_ASSETS_URL . 'js/admin/order-edit.js', array( 'jquery' ), WC_HEZARFEN_VERSION, true );
		}
	}
}
