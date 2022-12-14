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
	const INTENSE_KARGO_TAKIP = 'Intense Kargo Takip';
	const KARGO_TAKIP_TURKIYE = 'Kargo Takip Turkiye';

	const SUPPORTED_PLUGINS = array( self::INTENSE_KARGO_TAKIP, self::KARGO_TAKIP_TURKIYE );

	const INTENSE_KARGO_TAKIP_ORDER_STATUS = 'wc-shipping-progress';
	const KARGO_TAKIP_TURKIYE_ORDER_STATUS = 'wc-kargo-verildi';

	/**
	 * Constructor
	 */
	public function __construct() {
		if ( 'yes' === get_option( Settings::OPT_RECOG_DATA ) && Settings::RECOG_TYPE_SUPPORTED_PLUGINS === get_option( Settings::OPT_RECOGNITION_TYPE ) ) {
			self::intense_kargo_takip_support();
			self::kargo_takip_turkiye_support();
		}
	}

	/**
	 * Adds support for the Intense Kargo Takip for WooCommerce plugin's data.
	 * 
	 * @return void
	 */
	public static function intense_kargo_takip_support() {
		self::register_order_status( self::INTENSE_KARGO_TAKIP );

		add_filter( 'hezarfen_mst_get_courier_id', array( __CLASS__, 'get_intense_kargo_takip_data' ), 10, 2 );
		add_filter( 'hezarfen_mst_get_courier_title', array( __CLASS__, 'get_intense_kargo_takip_data' ), 10, 2 );
		add_filter( 'hezarfen_mst_get_tracking_num', array( __CLASS__, 'get_intense_kargo_takip_data' ), 10, 2 );
		add_filter( 'hezarfen_mst_get_tracking_url', array( __CLASS__, 'get_intense_kargo_takip_data' ), 10, 2 );
	}

	/**
	 * Adds support for the Kargo Takip Türkiye plugin's data.
	 * 
	 * @return void
	 */
	public static function kargo_takip_turkiye_support() {
		self::register_order_status( self::KARGO_TAKIP_TURKIYE );

		add_filter( 'hezarfen_mst_get_courier_id', array( __CLASS__, 'get_kargo_takip_turkiye_data' ), 11, 2 );
		add_filter( 'hezarfen_mst_get_courier_title', array( __CLASS__, 'get_kargo_takip_turkiye_data' ), 11, 2 );
		add_filter( 'hezarfen_mst_get_tracking_num', array( __CLASS__, 'get_kargo_takip_turkiye_data' ), 11, 2 );
		add_filter( 'hezarfen_mst_get_tracking_url', array( __CLASS__, 'get_kargo_takip_turkiye_data' ), 11, 2 );
	}

	/**
	 * Registers order status of the given third party plugin.
	 * 
	 * @param string $plugin Plugin.
	 * 
	 * @return void
	 */
	public static function register_order_status( $plugin ) {
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
	 * Returns Intense Kargo Takip for WooCommerce plugin's data.
	 * 
	 * @param string     $hezarfen_data Hezarfen's order data.
	 * @param string|int $order_id Order ID.
	 * 
	 * @return string
	 */
	public static function get_intense_kargo_takip_data( $hezarfen_data, $order_id ) {
		return self::get_third_party_data( $hezarfen_data, $order_id, self::INTENSE_KARGO_TAKIP );
	}

	/**
	 * Returns Kargo Takip Türkiye plugin's data.
	 * 
	 * @param string     $hezarfen_data Hezarfen's order data.
	 * @param string|int $order_id Order ID.
	 * 
	 * @return string
	 */
	public static function get_kargo_takip_turkiye_data( $hezarfen_data, $order_id ) {
		return self::get_third_party_data( $hezarfen_data, $order_id, self::KARGO_TAKIP_TURKIYE );
	}

	/**
	 * Returns a third party plugin's data.
	 * 
	 * @param string     $hezarfen_data Hezarfen's order data.
	 * @param string|int $order_id Order ID.
	 * @param string     $plugin Plugin.
	 * 
	 * @return string
	 */
	public static function get_third_party_data( $hezarfen_data, $order_id, $plugin ) {
		if ( $hezarfen_data ) {
			return $hezarfen_data;
		}

		$plugin_data = array(
			self::INTENSE_KARGO_TAKIP => array(
				'courier_company_key' => 'shipping_company',
				'tracking_number_key' => 'shipping_number',
				'tracking_url_key'    => 'in_kargotakip_tracking_url',
			),
			self::KARGO_TAKIP_TURKIYE => array(
				'courier_company_key' => 'tracking_company',
				'tracking_number_key' => 'tracking_code',
			),
		);

		$filter_name = str_replace( 'hezarfen_mst_', '', current_filter() );

		switch ( $filter_name ) {
			case 'get_courier_id':
			case 'get_courier_title':
				$meta_key = $plugin_data[ $plugin ]['courier_company_key'];
				break;
			case 'get_tracking_num':
				$meta_key = $plugin_data[ $plugin ]['tracking_number_key'];
				break;
			case 'get_tracking_url':
				$meta_key = $plugin_data[ $plugin ]['tracking_url_key'] ?? '';
				break;
			default:
				$meta_key = '';
				break;  
		}

		$data = $meta_key ? get_post_meta( $order_id, $meta_key, true ) : '';

		if ( ! $data && 'get_tracking_url' === $filter_name ) {
			// try to create tracking url.
			$courier_company = self::convert_courier( get_post_meta( $order_id, $plugin_data[ $plugin ]['courier_company_key'], true ) );
			$tracking_number = $courier_company ? get_post_meta( $order_id, $plugin_data[ $plugin ]['tracking_number_key'], true ) : '';
			return $tracking_number ? Helper::get_courier_class( $courier_company )::create_tracking_url( $tracking_number ) : '';
		}

		if ( $data ) {
			if ( 'get_courier_id' === $filter_name ) {
				$data = self::convert_courier( $data );
			} elseif ( 'get_courier_title' === $filter_name ) {
				$data = Helper::get_courier_class( self::convert_courier( $data ) )::get_title();
			}
		}

		return $data;
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
}
