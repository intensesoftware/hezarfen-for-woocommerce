<?php
/**
 * Contains Manual Shipment Tracking package main class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

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

	/**
	 * Assigns callbacks to hooks.
	 * 
	 * @return void
	 */
	public function assign_callbacks_to_hooks() {
		add_filter( 'woocommerce_register_shop_order_post_statuses', array( $this, 'register_order_status' ) );
		add_filter( 'wc_order_statuses', array( $this, 'append_order_status' ) );
		add_filter( 'woocommerce_reports_order_statuses', array( $this, 'append_order_status_to_reports' ), 20 );
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
}
