<?php
/**
 * Contains Manual Shipment Tracking package main class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

require_once HEZARFEN_MST_PATH . 'models/class-shipment-data.php';
require_once 'class-courier-company.php';
require_once 'class-helper.php';
require_once 'class-shipping-details.php';
require_once 'trait-helper.php';
require_once 'admin/class-settings.php';
require_once 'email/class-email.php';
require_once 'class-my-account.php';
require_once 'admin/class-admin-ajax.php';
require_once 'admin/class-admin-orders.php';
require_once 'notification-providers/class-mst-notification-provider.php';
require_once 'notification-providers/class-netgsm.php';
require_once 'notification-providers/class-pandasms.php';
require_once 'class-third-party-data-support.php';
require_once 'class-deactivation-wizard.php';

/**
 * Manual Shipment Tracking package main class.
 */
class Manual_Shipment_Tracking {
	const ENABLE_DISABLE_OPTION = 'hezarfen_enable_shipment_tracking';

	const DB_SHIPPED_ORDER_STATUS = 'wc-hezarfen-shipped';
	const SHIPPED_ORDER_STATUS    = 'hezarfen-shipped';

	const SHIPMENT_DATA_KEY       = '_hezarfen_mst_shipment_data';

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
		new Settings();
		new Deactivation_Wizard(); // Initialize the deactivation wizard for Pro plugin

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
		add_action( 'hezarfen_mst_shipment_data_saved', array( __CLASS__, 'ship_order' ), 10, 2 );
		
		// Register webhook callback for Hepsijet shipment status updates
		add_action( 'woocommerce_api_ordermigo_shipment_status', array( 'Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration', 'handle_webhook' ) );
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
	private function initialize_classes() {
		Email::init();
		My_Account::init();
		Admin_Ajax::init();
		Admin_Orders::init();
		Third_Party_Data_Support::init();

		// Add PandaSMS filter globally if the plugin is ready
		if ( Pandasms::is_plugin_ready() ) {
			add_filter( 'pandasms_wc_siparis_bildirim_tetikleyicileri', array( Pandasms::class, 'add_new_trigger' ) );
		}

		if ( 'yes' === get_option( Settings::OPT_ENABLE_SMS ) ) {
			$selected_provider = get_option( Settings::OPT_NOTIF_PROVIDER );

			if ( Netgsm::$id === $selected_provider && Netgsm::is_plugin_ready() ) {
				$this->active_notif_provider = new Netgsm();
			} elseif ( Pandasms::$id === $selected_provider && Pandasms::is_plugin_ready() ) {
				$this->active_notif_provider = new Pandasms();
			}
		}
	}

	/**
	 * Ship order
	 * Internal use only
	 *
	 * @param  \WC_Order     $order Order instance.
	 * @param  Shipment_Data $shipment_data Shipment data.
	 * @return void
	 */
	public static function ship_order( $order, Shipment_Data $shipment_data ) {
		$order->update_status( apply_filters( 'hezarfen_mst_new_order_status', self::SHIPPED_ORDER_STATUS, $order, $shipment_data->courier_id, $shipment_data->tracking_num ) ); // @phpstan-ignore-line

		if ( 'yes' === get_option( Settings::OPT_ENABLE_SMS ) ) {
			Helper::send_notification( $order, $shipment_data );
		}

		do_action( 'hezarfen_mst_order_shipped', $order, $shipment_data );
	}

	/**
	 * Assigns callbacks to hooks.
	 * 
	 * @return void
	 */
	private function assign_callbacks_to_hooks() {
		add_action( 'init', array( $this, 'register_order_status' ), 20 );

		add_filter( 'wc_order_statuses', array( $this, 'add_to_woocommerce_statuses' ), 20 );
	}

	/**
	 * Registers new order status.
	 * 
	 * @return void
	 */
	public function register_order_status() {
		// Re-register with proper translations
		register_post_status( self::DB_SHIPPED_ORDER_STATUS, array(
			'label'                     => _x( 'Shipped', 'WooCommerce Order status', 'hezarfen-for-woocommerce' ),
			'public'                    => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of orders */
			'label_count'               => _n_noop( 'Shipped (%s)', 'Shipped (%s)', 'hezarfen-for-woocommerce' ),
		) );
	}
	
	/**
	 * Adds the custom status to WooCommerce order statuses list
	 * immediately after 'processing'.
	 *
	 * @param array $order_statuses Existing order statuses.
	 * @return array Modified order statuses.
	 */
	public function add_to_woocommerce_statuses( $order_statuses ) {
		$new_order_statuses = [];

		foreach ( $order_statuses as $key => $label ) {
			$new_order_statuses[ $key ] = $label;

			// Insert our status right after 'processing'
			if ( 'wc-processing' === $key ) {
				$new_order_statuses[ self::DB_SHIPPED_ORDER_STATUS ] = _x(
					'Shipped',
					'WooCommerce Order status',
					'hezarfen-for-woocommerce'
				);
			}
		}

		return $new_order_statuses;
	}

	/**
	 * Returns courier companies array (ID => Class name).
	 * 
	 * @return array<string, string>
	 */
	public static function courier_companies() {
		$courier_companies = array(
			''                            => Courier_Empty::class,
			Courier_Aras::$id             => Courier_Aras::class,
			Courier_MNG::$id              => Courier_MNG::class,
			Courier_Yurtici::$id          => Courier_Yurtici::class,
			Courier_PTT::$id              => Courier_PTT::class,
			Courier_UPS::$id              => Courier_UPS::class,
			Courier_Surat::$id            => Courier_Surat::class,
			Courier_Trendyol_Express::$id => Courier_Trendyol_Express::class,
			Courier_Kargoist::$id         => Courier_Kargoist::class,
			Courier_Jetizz::$id           => Courier_Jetizz::class,
			Courier_Gelal::$id            => Courier_Gelal::class,
			Courier_Birgunde::$id         => Courier_Birgunde::class,
			Courier_Hepsijet::$id         => Courier_Hepsijet::class,
			Courier_Hepsijet_Entegrasyon::$id => Courier_Hepsijet_Entegrasyon::class,
			Courier_Scotty::$id           => Courier_Scotty::class,
			Courier_Packupp::$id          => Courier_Packupp::class,
			Courier_Kolay_Gelsin::$id     => Courier_Kolay_Gelsin::class,
			Courier_CDEK::$id             => Courier_CDEK::class,
			Courier_Fedex::$id            => Courier_Fedex::class,
			Courier_Horoz_Lojistik::$id   => Courier_Horoz_Lojistik::class,
			Courier_Kargo_Turk::$id       => Courier_Kargo_Turk::class,
			Courier_Sendeo::$id           => Courier_Sendeo::class,
			Courier_Brinks::$id           => Courier_Brinks::class,
			Courier_DHL::$id              => Courier_DHL::class,
			Courier_TNT::$id              => Courier_TNT::class,
			Courier_Kurye::$id            => Courier_Kurye::class,
		);

		return apply_filters( 'hezarfen_mst_courier_companies', $courier_companies );
	}

	/**
	 * Returns notification providers (ID => Class name).
	 * 
	 * @return array<string, string>
	 */
	public static function notification_providers() {
		return array(
			Pandasms::$id => Pandasms::class,
			Netgsm::$id   => Netgsm::class,
		);
	}

	/**
	 * Is package enabled?
	 * 
	 * @return bool
	 */
	public static function is_enabled() {
		return 'yes' === get_option( self::ENABLE_DISABLE_OPTION, 'yes' );
	}
}
