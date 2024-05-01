<?php
/**
 * Contains the My_Account class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Adds new features to the My Account pages.
 */
class My_Account {
	/**
	 * Initialization method.
	 * 
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );

		if ( 'yes' === get_option( Settings::OPT_SHOW_TRACKING_COLUMN ) ) {
			add_filter( 'woocommerce_account_orders_columns', array( __CLASS__, 'add_new_column' ), PHP_INT_MAX - 1 );
			add_action( 'woocommerce_my_account_my_orders_column_hezarfen-mst-shipment-tracking', array( __CLASS__, 'add_tracking_info_to_column' ) );
		}

		add_action( 'woocommerce_view_order', array( __CLASS__, 'add_tracking_info_to_order_details' ), 0 );
	}

	/**
	 * Adds tracking information column to My Account > Orders page.
	 * 
	 * @param array<string, string> $columns Columns.
	 * 
	 * @return array<string, string>
	 */
	public static function add_new_column( $columns ) {
		$offset      = 4;
		$first_part  = array_slice( $columns, 0, $offset );
		$second_part = array_slice( $columns, $offset );

		return $first_part + array( 'hezarfen-mst-shipment-tracking' => __( 'Tracking Information', 'hezarfen-for-woocommerce' ) ) + $second_part;
	}

	/**
	 * Adds tracking information to the "Tracking Information" column in the My Account > Orders page.
	 * 
	 * @param \WC_Order $order Order instance.
	 * 
	 * @return void
	 */
	public static function add_tracking_info_to_column( $order ) {
		$order_id      = $order->get_id();
		$shipment_data = Helper::get_all_shipment_data( $order_id );

		foreach ( $shipment_data as $data ) {
			self::render_tracking_info_in_column( $data );
		}
	}

	/**
	 * Renders tracking info HTML in the "Tracking Information" column.
	 * 
	 * @param Shipment_Data $shipment_data Shipment data.
	 * 
	 * @return void
	 */
	private static function render_tracking_info_in_column( $shipment_data ) {
		?>
		<div class="tracking-info">
			<span style="display: block"><?php echo esc_html( $shipment_data->courier_title ); ?></span>
			<?php if ( $shipment_data->tracking_url ) : ?>
				<a href="<?php echo esc_url( $shipment_data->tracking_url ); ?>" target="_blank"><?php echo esc_html( $shipment_data->tracking_num ); ?></a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Adds tracking information to the customer order details page.
	 * 
	 * @param string|int $order_id Order ID.
	 * 
	 * @return void
	 */
	public static function add_tracking_info_to_order_details( $order_id ) {
		$shipment_data = Helper::get_all_shipment_data( $order_id );
		?>

		<div class="hezarfen-mst-tracking-info-wrapper">
			<h2 class="woocommerce-order-details__title"><?php esc_html_e( 'Tracking Information', 'hezarfen-for-woocommerce' ); ?></h2>
			<?php
			if ( $shipment_data ) {
				foreach ( $shipment_data as $data ) {
					self::render_tracking_info_in_order_details( $data );
				}
			} else {
				?>
				<p><?php esc_html_e( "Your order hasn't been shipped yet.", 'hezarfen-for-woocommerce' ); ?></p>
				<?php
			}
			?>
		</div>
		<?php
	}

	/**
	 * Renders tracking info HTML in the customer order details page.
	 * 
	 * @param Shipment_Data $shipment_data Shipment data.
	 * 
	 * @return void
	 */
	private static function render_tracking_info_in_order_details( $shipment_data ) {
		?>
		<div class="tracking-info">
			<p><?php echo sprintf( '%s: <strong>%s</strong>', esc_html__( 'Courier Company', 'hezarfen-for-woocommerce' ), esc_html( $shipment_data->courier_title ) ); ?></p>
			<p><?php echo sprintf( '%s: <strong>%s</strong>', esc_html__( 'Tracking Number', 'hezarfen-for-woocommerce' ), esc_html( $shipment_data->tracking_num ) ); ?></p>
			<?php if ( $shipment_data->tracking_url ) : ?>
				<p><a class="button tracking-url" href="<?php echo esc_url( $shipment_data->tracking_url ); ?>" target="_blank"><?php esc_html_e( 'Track Cargo', 'hezarfen-for-woocommerce' ); ?></a></p>
			<?php endif; ?>
		</div>
		<hr>
		<?php
	}

	/**
	 * Enqueues CSS files.
	 * 
	 * @return void
	 */
	public static function enqueue_styles() {
		global $wp;

		if ( is_account_page() && ! empty( $wp->query_vars['view-order'] ) ) {
			wp_enqueue_style( 'hezarfen_mst_customer_order_details_css', HEZARFEN_MST_ASSETS_URL . 'css/customer-order-details.css', array(), WC_HEZARFEN_VERSION );
		}
	}
}
