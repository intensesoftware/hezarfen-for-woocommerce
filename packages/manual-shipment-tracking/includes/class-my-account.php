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

		if ( 'yes' === get_option( 'hezarfen_mst_show_shipment_tracking_column' ) ) {
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
		$order_id              = $order->get_id();
		$courier_company_title = Helper::get_courier_company_class( $order_id )::get_title();
		$tracking_num          = Helper::get_tracking_num( $order_id );
		$tracking_url          = Helper::get_tracking_url( $order_id );

		if ( $courier_company_title ) {
			printf( '<span style="display: block">%s</span>', esc_html( $courier_company_title ) );
		}

		if ( $tracking_url ) {
			printf( '<a href="%s" target="_blank">%s</a>', esc_url( $tracking_url ), esc_html( $tracking_num ) );
		}
	}

	/**
	 * Adds tracking information to the customer order details page.
	 * 
	 * @param string|int $order_id Order ID.
	 * 
	 * @return void
	 */
	public function add_tracking_info_to_order_details( $order_id ) {
		$courier_company_title = Helper::get_courier_company_class( $order_id )::get_title();
		$tracking_num          = Helper::get_tracking_num( $order_id );
		$tracking_url          = Helper::get_tracking_url( $order_id );
		?>
		<div class="hezarfen-mst-tracking-info-wrapper">
			<h2 class="woocommerce-order-details__title"><?php esc_html_e( 'Tracking Information', 'hezarfen-for-woocommerce' ); ?></h2>		
			<?php if ( ! empty( $courier_company_title ) || ! empty( $tracking_num ) ) : ?>
				<h4><?php echo sprintf( '%s: %s', esc_html__( 'Courier Company', 'hezarfen-for-woocommerce' ), esc_html( $courier_company_title ) ); ?></h4>
				<h4><?php echo sprintf( '%s: %s', esc_html__( 'Tracking Number', 'hezarfen-for-woocommerce' ), esc_html( $tracking_num ) ); ?></h4>

				<?php if ( $tracking_url ) : ?>
					<h4><a class="hezarfen-mst-tracking-url" href="<?php echo esc_url( $tracking_url ); ?>" target="_blank"><?php esc_html_e( 'Click here to find out where your cargo is.', 'hezarfen-for-woocommerce' ); ?></a></h4>
					<?php 
				endif;
			else : 
				?>
				<p><?php esc_html_e( "Your order hasn't been shipped yet.", 'hezarfen-for-woocommerce' ); ?></p>
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
