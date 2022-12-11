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
	/**
	 * Constructor
	 */
	public function __construct() {
		self::intense_kargo_takip_support();
	}

	/**
	 * Adds support for the Intense Kargo Takip for WooCommerce plugin's data.
	 * 
	 * @return void
	 */
	public static function intense_kargo_takip_support() {
		add_filter( 'hezarfen_mst_get_courier_id', array( __CLASS__, 'get_intense_kargo_takip_data' ), 10, 2 );
		add_filter( 'hezarfen_mst_get_courier_title', array( __CLASS__, 'get_intense_kargo_takip_data' ), 10, 2 );
		add_filter( 'hezarfen_mst_get_tracking_num', array( __CLASS__, 'get_intense_kargo_takip_data' ), 10, 2 );
		add_filter( 'hezarfen_mst_get_tracking_url', array( __CLASS__, 'get_intense_kargo_takip_data' ), 10, 2 );
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
		if ( $hezarfen_data ) {
			return $hezarfen_data;
		}

		$filter_name = str_replace( 'hezarfen_mst_', '', current_filter() );

		switch ( $filter_name ) {
			case 'get_courier_id':
			case 'get_courier_title':
				$meta_key = 'shipping_company';
				break;
			case 'get_tracking_num':
				$meta_key = 'shipping_number';
				break;
			case 'get_tracking_url':
				$meta_key = 'in_kargotakip_tracking_url';
				break;
			default:
				$meta_key = '';
				break;  
		}

		$data = self::get_third_party_data( $order_id, $meta_key );     

		if ( 'get_courier_id' === $filter_name ) {
			return self::convert_intense_kargo_takip_courier( $data );
		}

		return $data;
	}

	/**
	 * Returns a third party plugin's data.
	 * 
	 * @param string|int $order_id Order ID.
	 * @param string     $meta_key Meta key.
	 * 
	 * @return string
	 */
	public static function get_third_party_data( $order_id, $meta_key ) {
		return $meta_key ? get_post_meta( $order_id, $meta_key, true ) : '';
	}

	/**
	 * Converts Intense Kargo Takip for WooCommerce plugin's courier company data to Hezarfen's courier company ID.
	 * 
	 * @param string $courier Courier company data.
	 * 
	 * @return string
	 */
	public static function convert_intense_kargo_takip_courier( $courier ) {
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
			'Kargo Türk'       => Courier_Kargo_Turk::$id,
			'Kurye'            => Courier_Kurye::$id,
			'DHL'              => Courier_DHL::$id,
			'TNT'              => Courier_TNT::$id,
			'BRINKS'           => Courier_Brinks::$id,
			'Sendeo'           => Courier_Sendeo::$id,
		);

		return $conversion_data[ $courier ] ?? '';
	}
}
