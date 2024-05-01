<?php
/**
 * Contains the Third_Party_Data_Support class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * The Third_Party_Data_Support class.
 */
class Third_Party_Data_Support {
	const AUTO_ENABLE_THRESHOLD = 25;
	const OPT_SQL_RESULT_CACHE  = 'hezarfen_mst_third_party_data_sql_result';

	const INTENSE_KARGO_TAKIP = 'Intense Kargo Takip';
	const KARGO_TAKIP_TURKIYE = 'Kargo Takip Turkiye';
	const CUSTOM              = 'custom';

	const SUPPORTED_PLUGINS = array( self::INTENSE_KARGO_TAKIP, self::KARGO_TAKIP_TURKIYE );

	const INTENSE_KARGO_TAKIP_ORDER_STATUS          = 'wc-shipping-progress';
	const INTENSE_KARGO_TAKIP_COURIER_META_KEY      = 'shipping_company';
	const INTENSE_KARGO_TAKIP_TRACKING_NUM_META_KEY = 'shipping_number';
	const INTENSE_KARGO_TAKIP_TRACKING_URL_META_KEY = 'in_kargotakip_tracking_url';
	const KARGO_TAKIP_TURKIYE_ORDER_STATUS          = 'wc-kargo-verildi';
	const KARGO_TAKIP_TURKIYE_COURIER_META_KEY      = 'tracking_company';
	const KARGO_TAKIP_TURKIYE_TRACKING_NUM_META_KEY = 'tracking_code';

	const NONSENSE_STRING = 'hezarfen_mst_nonsense_string';

	/**
	 * Initialization method.
	 * 
	 * @return void
	 */
	public static function init() {
		$recog_data_option = get_option( Settings::OPT_RECOG_DATA ); // returns false if option is not set.
		if ( 'no' === $recog_data_option || ( false === $recog_data_option && ! self::should_enable_recognizing() ) ) {
			return;
		}

		$recognition_type = get_option( Settings::OPT_RECOGNITION_TYPE );

		if ( Settings::RECOG_TYPE_SUPPORTED_PLUGINS === $recognition_type ) {
			self::intense_kargo_takip_support();
			self::kargo_takip_turkiye_support();
		} elseif ( Settings::RECOG_TYPE_CUSTOM_META === $recognition_type ) {
			self::custom_meta_support();
		}
	}

	/**
	 * Adds support for the Intense Kargo Takip for WooCommerce plugin's data.
	 * 
	 * @return void
	 */
	public static function intense_kargo_takip_support() {
		self::register_order_status( self::INTENSE_KARGO_TAKIP );

		add_filter( 'hezarfen_mst_get_shipment_data', array( __CLASS__, 'get_intense_kargo_takip_data' ), 10, 2 );
	}

	/**
	 * Adds support for the Kargo Takip Türkiye plugin's data.
	 * 
	 * @return void
	 */
	public static function kargo_takip_turkiye_support() {
		self::register_order_status( self::KARGO_TAKIP_TURKIYE );

		add_filter( 'hezarfen_mst_get_shipment_data', array( __CLASS__, 'get_kargo_takip_turkiye_data' ), 11, 2 );
	}

	/**
	 * Adds custom data support.
	 * 
	 * @return void
	 */
	public static function custom_meta_support() {
		$order_status_id = get_option( Settings::OPT_ORDER_STATUS_ID );
		if ( $order_status_id ) {
			self::register_order_status( self::CUSTOM, $order_status_id );
		}

		add_filter( 'hezarfen_mst_get_shipment_data', array( __CLASS__, 'get_custom_meta_data' ), 10, 2 );
	}

	/**
	 * Returns Intense Kargo Takip for WooCommerce plugin's data.
	 * 
	 * @param Shipment_Data[] $data Shipment data (just an empty array if no plugin modified it).
	 * @param string|int      $order_id Order ID.
	 * 
	 * @return Shipment_Data[]
	 */
	public static function get_intense_kargo_takip_data( $data, $order_id ) {
		return self::get_supported_plugin_data( $data, $order_id, self::INTENSE_KARGO_TAKIP );
	}

	/**
	 * Returns Kargo Takip Türkiye plugin's data.
	 * 
	 * @param Shipment_Data[] $data Shipment data.
	 * @param string|int      $order_id Order ID.
	 * 
	 * @return Shipment_Data[]
	 */
	public static function get_kargo_takip_turkiye_data( $data, $order_id ) {
		return self::get_supported_plugin_data( $data, $order_id, self::KARGO_TAKIP_TURKIYE );
	}

	/**
	 * Returns custom meta data.
	 * 
	 * @param Shipment_Data[] $data Shipment data (just an empty array if no plugin modified it).
	 * @param string|int      $order_id Order ID.
	 * 
	 * @return Shipment_Data[]
	 */
	public static function get_custom_meta_data( $data, $order_id ) {
		if ( $data ) {
			return $data;
		}

		$courier_title = Courier_Custom::get_title( $order_id );
		if ( ! $courier_title ) {
			return array();
		}

		$order = wc_get_order( $order_id );

		if( ! $order ) {
			return array();
		}

		return array(
			new Shipment_Data(
				array(
					'id'            => null,
					'order_id'      => 0,
					'courier_id'    => Courier_Custom::$id,
					'courier_title' => $courier_title,
					'tracking_num'  => $order->get_meta( get_option( Settings::OPT_TRACKING_NUM_CUSTOM_META, self::NONSENSE_STRING ), true ),
				),
				null
			),
		);
	}

	/**
	 * Registers order status of the given third party plugin.
	 * 
	 * @param string $plugin Plugin.
	 * @param string $order_status_id Order status ID.
	 * 
	 * @return void
	 */
	public static function register_order_status( $plugin, $order_status_id = '' ) {
		$data = array(
			self::INTENSE_KARGO_TAKIP => array(
				'id'          => self::INTENSE_KARGO_TAKIP_ORDER_STATUS,
				'label'       => _x( 'Shipped (Intense Kargo Takip Plugin)', 'WooCommerce Order status', 'hezarfen-for-woocommerce' ),
				/* translators: %s: number of orders */
				'label_count' => _n_noop( 'Shipped (Intense Kargo Takip Plugin) (%s)', 'Shipped (Intense Kargo Takip Plugin) (%s)', 'hezarfen-for-woocommerce' ),
			),
			self::KARGO_TAKIP_TURKIYE => array(
				'id'          => self::KARGO_TAKIP_TURKIYE_ORDER_STATUS,
				'label'       => _x( 'Shipped (Kargo Takip Turkiye Plugin)', 'WooCommerce Order status', 'hezarfen-for-woocommerce' ),
				/* translators: %s: number of orders */
				'label_count' => _n_noop( 'Shipped (Kargo Takip Turkiye Plugin) (%s)', 'Shipped (Kargo Takip Turkiye Plugin) (%s)', 'hezarfen-for-woocommerce' ),
			),
			self::CUSTOM              => array(
				'id'          => $order_status_id,
				'label'       => _x( 'Shipped (Custom Status)', 'WooCommerce Order status', 'hezarfen-for-woocommerce' ),
				/* translators: %s: number of orders */
				'label_count' => _n_noop( 'Shipped (Custom Status) (%s)', 'Shipped (Custom Status) (%s)', 'hezarfen-for-woocommerce' ),
			),
		);

		$status_data = array(
			'id'    => $data[ $plugin ]['id'],
			'label' => $data[ $plugin ]['label'],
			'data'  => array(
				'label'                     => $data[ $plugin ]['label'],
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => $data[ $plugin ]['label_count'],
			),
		);

		Helper::register_new_order_status( $status_data );
	}

	/**
	 * Returns a supported third party plugin's data.
	 * 
	 * @param Shipment_Data[] $data Shipment data.
	 * @param string|int      $order_id Order ID.
	 * @param string          $plugin Plugin.
	 * 
	 * @return Shipment_Data[]
	 */
	public static function get_supported_plugin_data( $data, $order_id, $plugin ) {
		if ( $data ) {
			return $data;
		}

		$plugin_data = array(
			self::INTENSE_KARGO_TAKIP => array(
				'courier_id'    => self::INTENSE_KARGO_TAKIP_COURIER_META_KEY,
				'courier_title' => self::INTENSE_KARGO_TAKIP_COURIER_META_KEY,
				'tracking_num'  => self::INTENSE_KARGO_TAKIP_TRACKING_NUM_META_KEY,
				'tracking_url'  => self::INTENSE_KARGO_TAKIP_TRACKING_URL_META_KEY,
			),
			self::KARGO_TAKIP_TURKIYE => array(
				'courier_id'    => self::KARGO_TAKIP_TURKIYE_COURIER_META_KEY,
				'courier_title' => self::KARGO_TAKIP_TURKIYE_COURIER_META_KEY,
				'tracking_num'  => self::KARGO_TAKIP_TURKIYE_TRACKING_NUM_META_KEY,
			),
		);

		$order = wc_get_order( $order_id );

		if( ! $order ) {
			return array();
		}

		$courier_id = $order->get_meta( $plugin_data[ $plugin ]['courier_id'], true );

		if ( ! $courier_id ) {
			return array();
		}

		$courier_id    = self::convert_courier( $courier_id );
		$courier_class = Helper::get_courier_class( $courier_id );

		$courier_title = $courier_class::get_title();

		$tracking_num = $order->get_meta( $plugin_data[ $plugin ]['tracking_num'], true );

		$tracking_url = $order->get_meta( $plugin_data[ $plugin ]['tracking_url'], true ) ?? self::NONSENSE_STRING;

		if ( ! $tracking_url ) {
			// try to create tracking url.
			$tracking_url = $tracking_num ? $courier_class::create_tracking_url( $tracking_num ) : '';
		}

		$data = array(
			'id'            => null,
			'order_id'      => $order_id,
			'courier_id'    => $courier_id,
			'courier_title' => $courier_title,
			'tracking_num'  => $tracking_num,
			'tracking_url'  => $tracking_url,
		);

		return array( new Shipment_Data( $data, null ) );
	}

	/**
	 * Converts courier company data to Hezarfen's courier company ID.
	 * 
	 * @param string $courier Courier company data.
	 * 
	 * @return string
	 */
	public static function convert_courier( $courier ) {
		$conversion_data = array(
			''                 => Courier_Empty::$id,
			'Aras Kargo'       => Courier_Aras::$id,
			'MNG Kargo'        => Courier_MNG::$id,
			'Yurtiçi Kargo'    => Courier_Yurtici::$id,
			'PTT Kargo'        => Courier_PTT::$id,
			'UPS Kargo'        => Courier_UPS::$id,
			'SÜRAT Kargo'      => Courier_Surat::$id,
			'hepsiJET'         => Courier_Hepsijet::$id,
			'Trendyol Express' => Courier_Trendyol_Express::$id,
			'tex'              => Courier_Trendyol_Express::$id,
			'Kargoist'         => Courier_Kargoist::$id,
			'Jetizz'           => Courier_Jetizz::$id,
			'Gelal'            => Courier_Gelal::$id,
			'Birgünde Kargo'   => Courier_Birgunde::$id,
			'Scotty'           => Courier_Scotty::$id,
			'PackUpp'          => Courier_Packupp::$id,
			'Kolay Gelsin'     => Courier_Kolay_Gelsin::$id,
			'CDEK'             => Courier_CDEK::$id,
			'FedEx'            => Courier_Fedex::$id,
			'Horoz Lojistik'   => Courier_Horoz_Lojistik::$id,
			'horoz'            => Courier_Horoz_Lojistik::$id,
			'Kargo Türk'       => Courier_Kargo_Turk::$id,
			'Kurye'            => Courier_Kurye::$id,
			'DHL'              => Courier_DHL::$id,
			'TNT'              => Courier_TNT::$id,
			'BRINKS'           => Courier_Brinks::$id,
			'Sendeo'           => Courier_Sendeo::$id,
		);

		return $conversion_data[ $courier ] ?? $courier;
	}

	/**
	 * Scans the database and automatically enables recognizing if necessary.
	 * 
	 * @return bool
	 */
	private static function should_enable_recognizing() {
		global $wpdb;

		$result = get_option( self::OPT_SQL_RESULT_CACHE );

		if ( false === $result ) {
			$result = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key IN (%s,%s,%s,%s,%s)",
					array(
						self::INTENSE_KARGO_TAKIP_COURIER_META_KEY,
						self::INTENSE_KARGO_TAKIP_TRACKING_NUM_META_KEY,
						self::INTENSE_KARGO_TAKIP_TRACKING_URL_META_KEY,
						self::KARGO_TAKIP_TURKIYE_COURIER_META_KEY,
						self::KARGO_TAKIP_TURKIYE_TRACKING_NUM_META_KEY,
					)
				)
			);

			update_option( self::OPT_SQL_RESULT_CACHE, $result );
		}

		if ( $result > self::AUTO_ENABLE_THRESHOLD ) {
			update_option( Settings::OPT_RECOGNITION_TYPE, Settings::RECOG_TYPE_SUPPORTED_PLUGINS );
			update_option( Settings::OPT_RECOG_DATA, 'yes' );
			return true;
		}

		return false;
	}
}
