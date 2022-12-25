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
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		if ( 'yes' === get_option( Settings::OPT_SHOW_TRACKING_COLUMN ) ) {
			add_filter( 'woocommerce_account_orders_columns', array( $this, 'add_new_column' ), PHP_INT_MAX - 1 );
			add_action( 'woocommerce_my_account_my_orders_column_hezarfen-mst-shipment-tracking', array( $this, 'add_tracking_info_to_column' ) );
		}

		add_action( 'woocommerce_view_order', array( $this, 'add_tracking_info_to_order_details' ), 0 );
	}

	/**
	 * Adds tracking information column to My Account > Orders page.
	 * 
	 * @param array<string, string> $columns Columns.
	 * 
	 * @return array<string, string>
	 */
	public function add_new_column( $columns ) {
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
	public function add_tracking_info_to_column( $order ) {
		$order_id      = $order->get_id();
		$shipment_data = Helper::get_all_shipment_data( $order_id );

		foreach ( $shipment_data as $data ) {
			$this->render_tracking_info_in_column( $data['courier_title'], $data['tracking_num'], $data['tracking_url'] );
		}
	}

	/**
	 * Renders tracking info HTML in the "Tracking Information" column.
	 * 
	 * @param string $courier_title Courier company title.
	 * @param string $tracking_num Tracking number.
	 * @param string $tracking_url Tracking URL.
	 * 
	 * @return void
	 */
	private function render_tracking_info_in_column( $courier_title, $tracking_num, $tracking_url ) {
		?>
		<div class="tracking-info">
			<span style="display: block"><?php echo esc_html( $courier_title ); ?></span>
			<?php if ( $tracking_url ) : ?>
				<a href="<?php echo esc_url( $tracking_url ); ?>" target="_blank"><?php echo esc_html( $tracking_num ); ?></a>
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
	public function add_tracking_info_to_order_details( $order_id ) {
		$shipment_data = Helper::get_all_shipment_data( $order_id );
		?>

		<div class="hezarfen-mst-tracking-info-wrapper">
			<h2 class="woocommerce-order-details__title"><?php esc_html_e( 'Tracking Information', 'hezarfen-for-woocommerce' ); ?></h2>
			<?php
			if ( $shipment_data ) {
				foreach ( $shipment_data as $data ) {
					$this->render_tracking_info_in_order_details( $data['courier_title'], $data['tracking_num'], $data['tracking_url'] );
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
	 * @param string $courier_title Courier company title.
	 * @param string $tracking_num Tracking number.
	 * @param string $tracking_url Tracking URL.
	 * 
	 * @return void
	 */
	private function render_tracking_info_in_order_details( $courier_title, $tracking_num, $tracking_url ) {
		?>
		<div class="tracking-info">
			<h4><?php echo sprintf( '%s: %s', esc_html__( 'Courier Company', 'hezarfen-for-woocommerce' ), esc_html( $courier_title ) ); ?></h4>
			<h4><?php echo sprintf( '%s: %s', esc_html__( 'Tracking Number', 'hezarfen-for-woocommerce' ), esc_html( $tracking_num ) ); ?></h4>
			<?php if ( $tracking_url ) : ?>
				<h4><a class="tracking-url" href="<?php echo esc_url( $tracking_url ); ?>" target="_blank"><?php esc_html_e( 'Click here to find out where your cargo is.', 'hezarfen-for-woocommerce' ); ?></a></h4>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Enqueues CSS files.
	 * 
	 * @return void
	 */
	public function enqueue_styles() {
		global $wp;

		if ( is_account_page() && ! empty( $wp->query_vars['view-order'] ) ) {
			wp_enqueue_style( 'hezarfen_mst_customer_order_details_css', HEZARFEN_MST_ASSETS_URL . 'css/customer-order-details.css', array(), WC_HEZARFEN_VERSION );
		}
	}
}
