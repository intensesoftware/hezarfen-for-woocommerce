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
	const COURIER_HTML_NAME      = 'courier_company';
	const TRACKING_NUM_HTML_NAME = 'tracking_number';

	/**
	 * Constructor
	 */
	public function __construct() {
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );

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
		$order_id        = $post->ID;
		$courier_company = Helper::get_courier_class( $order_id );
		$tracking_num    = Helper::get_tracking_num( $order_id );
		$tracking_url    = Helper::get_tracking_url( $order_id );
		?>
		<div class="shipment-info">
			<?php
			woocommerce_wp_select(
				array(
					'id'            => self::COURIER_HTML_NAME,
					'label'         => __( 'Courier Company', 'hezarfen-for-woocommerce' ),
					'value'         => $courier_company::$id ? $courier_company::$id : Helper::get_default_courier_id(),
					'options'       => Helper::courier_company_options(),
					'wrapper_class' => 'form-field-wide',
				)
			);
			?>
			<p class="form-field">
				<label for="<?php echo esc_attr( self::TRACKING_NUM_HTML_NAME ); ?>">
					<?php esc_html_e( 'Tracking Number', 'hezarfen-for-woocommerce' ); ?>
				</label>
				<?php if ( $tracking_url ) : ?>
					<a href="<?php echo esc_url( $tracking_url ); ?>" target="_blank"><?php esc_html_e( '(Track Cargo)', 'hezarfen-for-woocommerce' ); ?></a>
				<?php endif; ?>
				<input type="text" name="<?php echo esc_attr( self::TRACKING_NUM_HTML_NAME ); ?>" id="<?php echo esc_attr( self::TRACKING_NUM_HTML_NAME ); ?>" value="<?php echo esc_attr( $tracking_num ); ?>" placeholder="<?php esc_attr_e( 'Enter tracking number', 'hezarfen-for-woocommerce' ); ?>">
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
		$order            = new \WC_Order( $order_id );
		$old_courier      = Helper::get_courier_class( $order_id );
		$old_tracking_num = Helper::get_tracking_num( $order_id );
		$new_courier_id   = ! empty( $_POST[ self::COURIER_HTML_NAME ] ) ? sanitize_text_field( $_POST[ self::COURIER_HTML_NAME ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$new_tracking_num = ! empty( $_POST[ self::TRACKING_NUM_HTML_NAME ] ) ? sanitize_text_field( $_POST[ self::TRACKING_NUM_HTML_NAME ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ( $new_courier_id !== $old_courier::$id ) || ( $new_tracking_num !== $old_tracking_num ) ) {
			$new_courier = Helper::get_courier_class( $new_courier_id );

			update_post_meta( $order_id, Manual_Shipment_Tracking::COURIER_COMPANY_ID_KEY, $new_courier_id );
			update_post_meta( $order_id, Manual_Shipment_Tracking::COURIER_COMPANY_TITLE_KEY, $new_courier::get_title() );
			update_post_meta( $order_id, Manual_Shipment_Tracking::TRACKING_NUM_KEY, $new_tracking_num );

			if ( $new_tracking_num ) {
				update_post_meta( $order_id, Manual_Shipment_Tracking::TRACKING_URL_KEY, $new_courier::create_tracking_url( $new_tracking_num ) );
			} else {
				update_post_meta( $order_id, Manual_Shipment_Tracking::TRACKING_URL_KEY, '' );
			}

			do_action( 'hezarfen_mst_tracking_data_saved', $order, $new_courier_id, $new_tracking_num );

			if ( ( $new_courier_id && $new_tracking_num ) || Courier_Kurye::$id === $new_courier_id ) {
				$order->update_status( apply_filters( 'hezarfen_mst_new_order_status', Manual_Shipment_Tracking::SHIPPED_ORDER_STATUS, $order, $new_courier_id, $new_tracking_num ) );

				if ( 'yes' === get_option( Settings::OPT_ENABLE_SMS ) ) {
					Helper::send_notification( $order );
				}

				do_action( 'hezarfen_mst_order_shipped', $order );
			}
		}
	}

	/**
	 * Enqueues CSS files.
	 * 
	 * @param string $hook_suffix Hook suffix.
	 * 
	 * @return void
	 */
	public function enqueue_styles( $hook_suffix ) {
		global $typenow;

		if ( 'edit.php' === $hook_suffix && 'shop_order' === $typenow ) {
			wp_enqueue_style( 'hezarfen_mst_admin_orders_page_css', HEZARFEN_MST_ASSETS_URL . 'css/admin/orders-page.css', array(), WC_HEZARFEN_VERSION );
		}
	}
}
