<?php
/**
 * Contains the helper class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Helper class.
 */
class Helper {
	const COURIER_COMPANY_KEY = 'hezarfen_mst_courier_company';
	const TRACKING_NUM_KEY    = 'hezarfen_mst_tracking_number';
	const TRACKING_URL_KEY    = 'hezarfen_mst_tracking_url';

	/**
	 * Returns courier companies array.
	 * 
	 * @return array<string, string>
	 */
	public static function courier_companies() {
		return array(
			''                 => __( 'Please choose a courier company', 'hezarfen-for-woocommerce' ),
			'Aras Kargo'       => __( 'Aras Kargo', 'hezarfen-for-woocommerce' ),
			'MNG Kargo'        => __( 'MNG Kargo', 'hezarfen-for-woocommerce' ),
			'Yurtiçi Kargo'    => __( 'Yurtiçi Kargo', 'hezarfen-for-woocommerce' ),
			'PTT Kargo'        => __( 'PTT Kargo', 'hezarfen-for-woocommerce' ),
			'UPS Kargo'        => __( 'UPS Kargo', 'hezarfen-for-woocommerce' ),
			'SÜRAT Kargo'      => __( 'SÜRAT Kargo', 'hezarfen-for-woocommerce' ),
			'hepsiJET'         => __( 'hepsiJET', 'hezarfen-for-woocommerce' ),
			'Trendyol Express' => __( 'Trendyol Express', 'hezarfen-for-woocommerce' ),
			'Kargoist'         => __( 'Kargoist', 'hezarfen-for-woocommerce' ),
			'Jetizz'           => __( 'Jetizz', 'hezarfen-for-woocommerce' ),
			'Gelal'            => __( 'Gelal', 'hezarfen-for-woocommerce' ),
			'Birgünde Kargo'   => __( 'Birgünde Kargo', 'hezarfen-for-woocommerce' ),
			'Scotty'           => __( 'Scotty', 'hezarfen-for-woocommerce' ),
			'PackUpp'          => __( 'PackUpp', 'hezarfen-for-woocommerce' ),
			'Kolay Gelsin'     => __( 'Kolay Gelsin', 'hezarfen-for-woocommerce' ),
			'CDEK'             => __( 'CDEK', 'hezarfen-for-woocommerce' ),
			'FedEx'            => __( 'FedEx', 'hezarfen-for-woocommerce' ),
			'Horoz Lojistik'   => __( 'Horoz Lojistik', 'hezarfen-for-woocommerce' ),
			'Kargo Türk'       => __( 'Kargo Türk', 'hezarfen-for-woocommerce' ),
			'Kurye'            => __( 'Kurye', 'hezarfen-for-woocommerce' ),
			'DHL'              => __( 'DHL', 'hezarfen-for-woocommerce' ),
			'TNT'              => __( 'TNT', 'hezarfen-for-woocommerce' ),
			'BRINKS'           => __( 'BRINKS Kargo', 'hezarfen-for-woocommerce' ),
			'Sendeo'           => __( 'Sendeo Kargo', 'hezarfen-for-woocommerce' ),
		);
	}

	/**
	 * Creates tracking URL.
	 * 
	 * @param string $courier_company Courier company.
	 * @param string $tracking_number Tracking number.
	 * 
	 * @return string
	 */
	public static function create_tracking_url( $courier_company, $tracking_number ) {
		if ( $courier_company && $tracking_number ) {
			switch ( $courier_company ) {
				case 'MNG Kargo':
					$tracking_url = 'http://service.mngkargo.com.tr/iactive/popup/kargotakip.asp?k=' . $tracking_number;
					break;
				case 'Yurtiçi Kargo':
					$tracking_url = 'https://yurticikargo.com/tr/online-servisler/gonderi-sorgula?code=' . $tracking_number;
					break;
				case 'Aras Kargo':
					$tracking_url = 'http://kargotakip.araskargo.com.tr/mainpage.aspx?code=' . $tracking_number;
					break;
				case 'UPS Kargo':
					$tracking_url = 'https://www.ups.com/track?loc=tr_TR&tracknum=' . $tracking_number . '&requester=WT/trackdetails';
					break;
				case 'SÜRAT Kargo':
					$tracking_url = 'http://www.suratkargo.com.tr/kargoweb/bireysel.aspx?no=' . $tracking_number;
					break;
				case 'Sürat Kargo':
					$tracking_url = 'http://www.suratkargo.com.tr/kargoweb/bireysel.aspx?no=' . $tracking_number;
					break;
				case 'PTT Kargo':
					$tracking_url = 'https://gonderitakip.ptt.gov.tr/Track/Verify?q=' . $tracking_number;
					break;
				case 'hepsiJET':
					$tracking_url = 'https://www.hepsijet.com/gonderi-takibi/' . $tracking_number;
					break;
				case 'Trendyol Express':
					$tracking_url = 'https://kargotakip.trendyol.com/?orderNumber=' . $tracking_number;
					break;
				case 'Kargoist':
					$tracking_url = 'https://kargotakip.kargoist.com/tracking?har_kod=' . $tracking_number;
					break;
				case 'Jetizz':
					$tracking_url = 'https://app.jetizz.com/JetizzEvrakTakip';
					break;
				case 'Gelal':
					$tracking_url = 'https://gelal.com/api/map/v1/map/' . $tracking_number;
					break;
				case 'Birgünde Kargo':
					$response = wp_remote_post(
						'https://birgundekargo.com/tr-TR/KargoApi/Gonder',
						array(
							'body'    => wp_json_encode( array( 'takipno' => $tracking_number ) ),
							'headers' => array( 'Content-Type' => 'application/json' ),
						)
					);
					$body     = json_decode( wp_remote_retrieve_body( $response ), true );

					$tracking_url = ! empty( $body['url'] ) ? $body['url'] : 'https://www.birgundekargo.com/online-takip';
					break;
				case 'Scotty':
					$tracking_url = 'https://nerede.scotty.com.tr/kargom-nerede?tracking_code=' . $tracking_number;
					break;
				case 'PackUpp':
					$tracking_url = 'https://tracking.packupp.com/' . $tracking_number;
					break;
				case 'Kolay Gelsin':
					$tracking_url = 'https://esube.kolaygelsin.com/shipments?trackingId=' . $tracking_number;
					break;
				case 'CDEK':
					$tracking_url = 'https://cdek.com.tr/tr#tracking';
					break;
				case 'FedEx':
					$tracking_url = 'https://www.fedex.com/tr-tr/home.html';
					break;
				case 'Sendeo':
					$tracking_url = 'https://kargotakip.sendeo.com.tr/kargo-takip-popup';
					break;
			}
		}

		return isset( $tracking_url ) ? $tracking_url : '';
	}

	/**
	 * Returns courier company of the order.
	 * 
	 * @param int|string $order_id Order ID.
	 * 
	 * @return string|false
	 */
	public static function get_courier_company( $order_id ) {
		return get_post_meta( $order_id, self::COURIER_COMPANY_KEY, true );
	}

	/**
	 * Returns the default courier company.
	 * 
	 * @return string|false
	 */
	public static function get_default_courier_company() {
		return get_option( 'hezarfen_mst_default_courier_company', '' );
	}

	/**
	 * Returns tracking number of the order.
	 * 
	 * @param int|string $order_id Order ID.
	 * 
	 * @return string|false
	 */
	public static function get_tracking_num( $order_id ) {
		return get_post_meta( $order_id, self::TRACKING_NUM_KEY, true );
	}

	/**
	 * Returns tracking URL of the order.
	 * 
	 * @param int|string $order_id Order ID.
	 * 
	 * @return string|false
	 */
	public static function get_tracking_url( $order_id ) {
		return get_post_meta( $order_id, self::TRACKING_URL_KEY, true );
	}
}
