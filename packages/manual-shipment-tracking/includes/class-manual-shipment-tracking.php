<?php
/**
 * Contains Manual Shipment Tracking package main class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

require_once 'class-courier-company.php';
require_once 'class-helper.php';
require_once 'admin/class-settings.php';
require_once 'email/class-email.php';
require_once 'class-my-account.php';
require_once 'admin/class-admin-orders.php';
require_once 'class-netgsm.php';

/**
 * Manual Shipment Tracking package main class.
 */
class Manual_Shipment_Tracking {
	const ENABLE_DISABLE_OPTION = 'hezarfen_enable_manual_shipment_tracking';

	/**
	 * The single instance of the class
	 *
	 * @var Manual_Shipment_Tracking
	 */
	private static $instance = null;

	/**
	 * Currently active SMS notification provider.
	 * 
	 * @var null|\Hezarfen\Inc\Notification_Provider
	 */
	public $active_notif_provider;

	/**
	 * Constructor
	 * 
	 * @return void
	 */
	private function __construct() {
		self::add_enable_disable_option();

		if ( self::is_enabled() ) {
			$this->initialize_classes();
			$this->assign_callbacks_to_hooks();
		}
	}

	/**
	 * Initializes the package.
	 * 
	 * @return void
	 */
	public static function init() {
		self::instance();
	}

	/**
	 * Returns the class instance.
	 * 
	 * @return Manual_Shipment_Tracking
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initializes classes.
	 * 
	 * @return void
	 */
	public function initialize_classes() {
		new Settings();
		new Email();
		new My_Account();
		new Admin_Orders();

		if ( 'yes' === get_option( 'hezarfen_mst_enable_sms_notification' ) && 'netgsm' === get_option( 'hezarfen_mst_notification_provider' ) ) {
			$this->active_notif_provider = new Netgsm();
		}
	}

	/**
	 * Assigns callbacks to hooks.
	 * 
	 * @return void
	 */
	public function assign_callbacks_to_hooks() {
		add_filter( 'woocommerce_register_shop_order_post_statuses', array( __CLASS__, 'register_order_status' ) );
		add_filter( 'wc_order_statuses', array( __CLASS__, 'append_order_status' ) );
	}

	/**
	 * Registers new order status.
	 * 
	 * @param array<string, array<string, mixed>> $wc_order_statuses WC order status properties.
	 * 
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_order_status( $wc_order_statuses ) {
		$wc_order_statuses[ Helper::DB_SHIPPED_ORDER_STATUS ] = array(
			'label'                     => _x( 'Shipped', 'WooCommerce Order status', 'hezarfen-for-woocommerce' ),
			'public'                    => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of orders */
			'label_count'               => _n_noop( 'Shipped (%s)', 'Shipped (%s)', 'hezarfen-for-woocommerce' ),
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
	public static function append_order_status( $wc_order_statuses ) {
		$wc_order_statuses[ Helper::DB_SHIPPED_ORDER_STATUS ] = _x( 'Shipped', 'WooCommerce Order status', 'hezarfen-for-woocommerce' );
		return $wc_order_statuses;
	}

	/**
	 * Adds a checkbox to enable/disable the package.
	 * 
	 * @return void
	 */
	private static function add_enable_disable_option() {
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
		return 'yes' === get_option( self::ENABLE_DISABLE_OPTION, true );
	}
}
