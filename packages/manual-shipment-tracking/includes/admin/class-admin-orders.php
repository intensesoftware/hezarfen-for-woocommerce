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
	/**
	 * Constructor
	 */
	public function __construct() {
		if ( is_admin() ) {
			add_filter( 'woocommerce_reports_order_statuses', array( $this, 'append_order_status_to_reports' ), 20 );
			add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'admin_order_details' ) );
			add_action( 'woocommerce_process_shop_order_meta', array( $this, 'order_save' ), PHP_INT_MAX - 1 );
		}
	}

	/**
	 * Shows new order status in reports.
	 *
	 * @param string[] $statuses Current order report statuses.
	 * 
	 * @return string[]
	 */
	public function append_order_status_to_reports( $statuses ) {
		$statuses[] = Helper::SHIPPED_ORDER_STATUS;
		return $statuses;
	}

	/**
	 * Adds necessary HTML to the admin order details page.
	 * 
	 * @param \WC_Order $order Order.
	 * 
	 * @return void
	 */
	public function admin_order_details( $order ) {
		$order_id = $order->get_id();
		?>
		<br class="clear" />
		<h4><?php esc_html_e( 'Cargo Informations', 'hezarfen-for-woocommerce' ); ?> <a href="#" class="edit_address"><?php esc_html_e( 'Edit', 'hezarfen-for-woocommerce' ); ?></a></h4>
		<?php
		$courier_company_info = Helper::get_courier_company( $order_id, true );
		$tracking_num         = Helper::get_tracking_num( $order_id );
		$tracking_url         = Helper::get_tracking_url( $order_id );
		?>
		<div class="address">
			<p><strong><?php esc_html_e( 'Courier Company', 'hezarfen-for-woocommerce' ); ?>:</strong> <?php echo esc_html( $courier_company_info['label'] ); ?></p>
			<p>
				<strong><?php esc_html_e( 'Tracking Number', 'hezarfen-for-woocommerce' ); ?>:</strong>
				<?php if ( $tracking_url ) : ?>
					<a href="<?php echo esc_url( $tracking_url ); ?>" target="_blank"><?php echo esc_html( $tracking_num ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $tracking_num ); ?>
				<?php endif; ?>
			</p>
		</div>
		<div class="edit_address">
		<?php
			woocommerce_wp_select(
				array(
					'id'            => 'courier_company',
					'label'         => __( 'Courier Company', 'hezarfen-for-woocommerce' ) . ':',
					'value'         => $courier_company_info['value'] ? $courier_company_info['value'] : Helper::get_default_courier_company(),
					'options'       => Helper::courier_companies(),
					'wrapper_class' => 'form-field-wide',
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'            => 'tracking_number',
					'label'         => __( 'Tracking Number', 'hezarfen-for-woocommerce' ) . ':',
					'value'         => $tracking_num,
					'wrapper_class' => 'form-field-wide',
				)
			);
		?>
		</div>
		<?php
	}

	/**
	 * Saves the data.
	 * 
	 * @param int|string $order_id Order ID.
	 * 
	 * @return void
	 */
	public function order_save( $order_id ) {
		$order               = new \WC_Order( $order_id );
		$old_courier_company = Helper::get_courier_company( $order_id );
		$old_tracking_num    = Helper::get_tracking_num( $order_id );
		$new_courier_company = ! empty( $_POST['courier_company'] ) ? sanitize_text_field( $_POST['courier_company'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$new_tracking_num    = ! empty( $_POST['tracking_number'] ) ? sanitize_text_field( $_POST['tracking_number'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if (
			( $new_courier_company && $new_courier_company !== $old_courier_company ) ||
			( $new_tracking_num && $new_tracking_num !== $old_tracking_num )
		) {
			update_post_meta( $order_id, Helper::COURIER_COMPANY_KEY, $new_courier_company );
			update_post_meta( $order_id, Helper::TRACKING_NUM_KEY, $new_tracking_num );
			update_post_meta( $order_id, Helper::TRACKING_URL_KEY, Helper::create_tracking_url( $new_courier_company, $new_tracking_num ) );

			do_action( 'hezarfen_mst_tracking_data_saved', $order, $new_courier_company, $new_tracking_num );

			if ( $new_courier_company && ( $new_tracking_num || 'Kurye' === $new_courier_company ) ) {
				$order->update_status( apply_filters( 'hezarfen_mst_new_order_status', 'shipping-progress', $order, $new_courier_company, $new_tracking_num ) );
			}

			do_action( 'hezarfen_mst_order_shipped', $order );
		}
	}
}
