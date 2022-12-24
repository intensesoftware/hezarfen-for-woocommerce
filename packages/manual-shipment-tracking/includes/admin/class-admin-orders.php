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
		$order_id      = $post->ID;
		$shipment_data = Helper::get_all_shipment_data( $order_id );

		if ( $shipment_data ) {
			foreach ( $shipment_data as $data ) :
				$shipment_data_id = Helper::extract_shipment_data_id( $data );
				$courier_company  = Helper::get_courier_class( Helper::extract_courier_id( $data ) );
				$tracking_num     = Helper::extract_tracking_num( $data );
				$tracking_url     = Helper::extract_tracking_url( $data );

				self::render_shipment_form_elements( $shipment_data_id, $courier_company, $tracking_num, $tracking_url );
			endforeach;
		} else {
			self::render_shipment_form_elements();
		}
	}

	/**
	 * Renders the shipment form elements.
	 * 
	 * @param int|string $shipment_data_id Shipment data ID.
	 * @param string     $courier_company Courier company class.
	 * @param string     $tracking_num Tracking number.
	 * @param string     $tracking_url Tracking URL.
	 * 
	 * @return void
	 */
	private static function render_shipment_form_elements( $shipment_data_id = 1, $courier_company = Courier_Empty::class, $tracking_num = '', $tracking_url = '' ) {
		?>
		<div class="shipment-info">
			<?php
			woocommerce_wp_select(
				array(
					'id'      => sprintf( '%s[%s][%s]', self::DATA_ARRAY_KEY, $shipment_data_id, self::COURIER_HTML_NAME ),
					'label'   => __( 'Courier Company', 'hezarfen-for-woocommerce' ),
					'value'   => $courier_company::$id ? $courier_company::$id : Helper::get_default_courier_id(),
					'options' => Helper::courier_company_options(),
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
				<input
					type="text"
					name="<?php echo esc_attr( sprintf( '%s[%s][%s]', self::DATA_ARRAY_KEY, $shipment_data_id, self::TRACKING_NUM_HTML_NAME ) ); ?>"
					id="<?php echo esc_attr( self::TRACKING_NUM_HTML_NAME ); ?>"
					value="<?php echo esc_attr( $tracking_num ); ?>"
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

		foreach ( $_POST[ self::DATA_ARRAY_KEY ] as $id => $shipment_data ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$new_courier_id   = ! empty( $shipment_data[ self::COURIER_HTML_NAME ] ) ? sanitize_text_field( $shipment_data[ self::COURIER_HTML_NAME ] ) : '';
			$new_tracking_num = ! empty( $shipment_data[ self::TRACKING_NUM_HTML_NAME ] ) ? sanitize_text_field( $shipment_data[ self::TRACKING_NUM_HTML_NAME ] ) : '';

			if ( ! $new_courier_id ) {
				continue;
			}

			$old_data = Helper::get_shipment_data_by_id( $id, $order_id );

			$new_courier   = Helper::get_courier_class( $new_courier_id );
			$prepared_data = Helper::prepare_shipment_data_for_db(
				array(
					$id,
					$new_courier_id,
					$new_courier::get_title(),
					$new_tracking_num,
					$new_tracking_num ? $new_courier::create_tracking_url( $new_tracking_num ) : '',
				)
			);

			if ( ! $old_data ) {
				add_post_meta( $order_id, Manual_Shipment_Tracking::SHIPMENT_DATA_KEY, $prepared_data );
				do_action( 'hezarfen_mst_tracking_data_saved', $order_id, $new_courier_id, $new_tracking_num );
				continue;
			}

			if ( $prepared_data === $old_data ) {
				continue;
			}

			$result = update_post_meta( $order_id, Manual_Shipment_Tracking::SHIPMENT_DATA_KEY, $prepared_data, $old_data );

			if ( true === $result ) {
				do_action( 'hezarfen_mst_tracking_data_saved', $order_id, $new_courier_id, $new_tracking_num );
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
	public function enqueue_styles( $hook_suffix ) {
		global $typenow;

		if ( 'edit.php' === $hook_suffix && 'shop_order' === $typenow ) {
			wp_enqueue_style( 'hezarfen_mst_admin_orders_page_css', HEZARFEN_MST_ASSETS_URL . 'css/admin/orders-page.css', array(), WC_HEZARFEN_VERSION );
		}
	}
}
