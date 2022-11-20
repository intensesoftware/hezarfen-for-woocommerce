<?php
/**
 * Contains Manual Shipment Tracking package main class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

require_once 'class-helper.php';
require_once 'admin/class-settings.php';

/**
 * Manual Shipment Tracking package main class.
 */
class Manual_Shipment_Tracking {
	const ENABLE_DISABLE_OPTION = 'hezarfen_enable_manual_shipment_tracking';

	const DB_SHIPPED_ORDER_STATUS = 'wc-shipping-progress';
	const SHIPPED_ORDER_STATUS    = 'shipping-progress';

	/**
	 * Constructor
	 * 
	 * @return void
	 */
	public function __construct() {
		$this->add_enable_disable_option();

		if ( $this->is_enabled() ) {
			$this->initialize_classes();
			$this->assign_callbacks_to_hooks();
		}
	}

	/**
	 * Initializes classes.
	 * 
	 * @return void
	 */
	public function initialize_classes() {
		new Settings();
	}

	/**
	 * Assigns callbacks to hooks.
	 * 
	 * @return void
	 */
	public function assign_callbacks_to_hooks() {
		add_filter( 'woocommerce_register_shop_order_post_statuses', array( $this, 'register_order_status' ) );
		add_filter( 'wc_order_statuses', array( $this, 'append_order_status' ) );
		add_filter( 'woocommerce_reports_order_statuses', array( $this, 'append_order_status_to_reports' ), 20 );

		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'order_details' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save' ), PHP_INT_MAX - 1 );
	}

	/**
	 * Registers new order status.
	 * 
	 * @param array<string, array<string, mixed>> $wc_order_statuses WC order status properties.
	 * 
	 * @return array<string, array<string, mixed>>
	 */
	public function register_order_status( $wc_order_statuses ) {
		$wc_order_statuses[ self::DB_SHIPPED_ORDER_STATUS ] = array(
			'label'                     => _x( 'Kargoya Verildi', 'WooCommerce Order status', 'hezarfen-for-woocommerce' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of orders */
			'label_count'               => _n_noop( 'Kargoya Verildi (%s)', 'Kargoya Verildi (%s)', 'hezarfen-for-woocommerce' ),
		);

		return $wc_order_statuses;
	}

	/**
	 * Appends new order status to WC order statuses.
	 * 
	 * @param array<string, string> $wc_order_statuses WC order statuses.
	 * 
	 * @return array<string, string>
	 */
	public function append_order_status( $wc_order_statuses ) {
		$wc_order_statuses[ self::DB_SHIPPED_ORDER_STATUS ] = _x( 'Kargoya Verildi', 'WooCommerce Order status', 'hezarfen-for-woocommerce' );
		return $wc_order_statuses;
	}

	/**
	 * Shows new order status in reports.
	 *
	 * @param string[] $statuses Current order report statuses.
	 * 
	 * @return string[]
	 */
	public function append_order_status_to_reports( $statuses ) {
		$statuses[] = self::SHIPPED_ORDER_STATUS;
		return $statuses;
	}

	/**
	 * Adds necessary HTML to the order details page.
	 * 
	 * @param WC_Order $order Order.
	 * 
	 * @return void
	 */
	public function order_details( $order ) {
		?>
		<br class="clear" />
		<h4><?php esc_html_e( 'Cargo Informations', 'hezarfen-for-woocommerce' ); ?> <a href="#" class="edit_address"><?php esc_html_e( 'Edit', 'hezarfen-for-woocommerce' ); ?></a></h4>
		<?php
		$courier_company = Helper::get_courier_company( $order->get_id() );
		$tracking_num    = Helper::get_tracking_num( $order->get_id() );
		?>
		<div class="address">
			<p><strong><?php esc_html_e( 'Courier Company', 'hezarfen-for-woocommerce' ); ?>:</strong> <?php echo esc_html( $courier_company ); ?></p>
			<p><strong><?php esc_html_e( 'Tracking Number', 'hezarfen-for-woocommerce' ); ?>:</strong> <?php echo esc_html( $tracking_num ); ?></p>
		</div>
		<div class="edit_address">
		<?php
			woocommerce_wp_select(
				array(
					'id'            => 'shipping_company',
					'label'         => __( 'Courier Company', 'hezarfen-for-woocommerce' ) . ':',
					'value'         => $courier_company,
					'options'       => Helper::courier_companies(),
					'wrapper_class' => 'form-field-wide',
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'            => 'shipping_number',
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
	public function save( $order_id ) {
		$order               = new \WC_Order( $order_id );
		$old_courier_company = Helper::get_courier_company( $order_id );
		$old_tracking_num    = Helper::get_tracking_num( $order_id );
		$new_courier_company = ! empty( $_POST['shipping_company'] ) ? sanitize_text_field( $_POST['shipping_company'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$new_tracking_num    = ! empty( $_POST['shipping_number'] ) ? sanitize_text_field( $_POST['shipping_number'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if (
			( $new_courier_company && $new_courier_company !== $old_courier_company ) ||
			( $new_tracking_num && $new_tracking_num !== $old_tracking_num )
		) {
			update_post_meta( $order_id, Helper::COURIER_COMPANY_KEY, $new_courier_company );
			update_post_meta( $order_id, Helper::TRACKING_NUM_KEY, $new_tracking_num );

			do_action( 'in_hez_mst_tracking_data_saved', $order, $new_courier_company, $new_tracking_num );

			if ( $new_courier_company && ( $new_tracking_num || 'Kurye' === $new_courier_company ) ) {
				$order->update_status( apply_filters( 'in_hez_mst_new_order_status', 'shipping-progress', $order, $new_courier_company, $new_tracking_num ) );
			}

			do_action( 'in_hez_mst_order_shipped', $order_id );
		}
	}

	/**
	 * Adds a checkbox to enable/disable the package.
	 * 
	 * @return void
	 */
	private function add_enable_disable_option() {
		add_filter(
			'hezarfen_general_settings',
			function ( $hezarfen_settings ) {
				$hezarfen_settings[] = array(
					'title'   => __(
						'Enable Manual Shipment Tracking feature',
						'hezarfen-for-woocommerce'
					),
					'type'    => 'checkbox',
					'desc'    => '',
					'id'      => self::ENABLE_DISABLE_OPTION,
					'default' => 'yes',
					'std'     => 'yes',
				);
	
				return $hezarfen_settings;
			} 
		);
	}

	/**
	 * Is package enabled?
	 * 
	 * @return bool
	 */
	public static function is_enabled() {
		return filter_var( get_option( self::ENABLE_DISABLE_OPTION, true ), FILTER_VALIDATE_BOOLEAN );
	}
}