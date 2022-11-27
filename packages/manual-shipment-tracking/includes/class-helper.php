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
	const DB_SHIPPED_ORDER_STATUS = 'wc-hezarfen-shipped';
	const SHIPPED_ORDER_STATUS    = 'hezarfen-shipped';

	const COURIER_COMPANY_KEY = 'hezarfen_mst_courier_company';
	const TRACKING_NUM_KEY    = 'hezarfen_mst_tracking_number';
	const TRACKING_URL_KEY    = 'hezarfen_mst_tracking_url';

	/**
	 * Returns courier companies array for using as select options.
	 * 
	 * @return array<string, string>
	 */
	public static function courier_company_options() {
		// prepare the "ID => Courier title" array.
		foreach ( self::courier_companies() as $id => $courier_class ) {
			$options[ $id ] = $courier_class::get_title();
		}

		$options[''] = __( 'Please choose a courier company', 'hezarfen-for-woocommerce' );

		return $options;
	}

	/**
	 * Returns courier companies array (ID => Class name).
	 * 
	 * @return array<string, string>
	 */
	public static function courier_companies() {
		return array(
			''                            => __NAMESPACE__ . '\Courier_Empty',
			Courier_Aras::$id             => __NAMESPACE__ . '\Courier_Aras',
			Courier_MNG::$id              => __NAMESPACE__ . '\Courier_MNG',
			Courier_Yurtici::$id          => __NAMESPACE__ . '\Courier_Yurtici',
			Courier_PTT::$id              => __NAMESPACE__ . '\Courier_PTT',
			Courier_UPS::$id              => __NAMESPACE__ . '\Courier_UPS',
			Courier_Surat::$id            => __NAMESPACE__ . '\Courier_Surat',
			Courier_Hepsijet::$id         => __NAMESPACE__ . '\Courier_Hepsijet',
			Courier_Trendyol_Express::$id => __NAMESPACE__ . '\Courier_Trendyol_Express',
			Courier_Kargoist::$id         => __NAMESPACE__ . '\Courier_Kargoist',
			Courier_Jetizz::$id           => __NAMESPACE__ . '\Courier_Jetizz',
			Courier_Gelal::$id            => __NAMESPACE__ . '\Courier_Gelal',
			Courier_Birgunde::$id         => __NAMESPACE__ . '\Courier_Birgunde',
			Courier_Scotty::$id           => __NAMESPACE__ . '\Courier_Scotty',
			Courier_Packupp::$id          => __NAMESPACE__ . '\Courier_Packupp',
			Courier_Kolay_Gelsin::$id     => __NAMESPACE__ . '\Courier_Kolay_Gelsin',
			Courier_CDEK::$id             => __NAMESPACE__ . '\Courier_CDEK',
			Courier_Fedex::$id            => __NAMESPACE__ . '\Courier_Fedex',
			Courier_Horoz_Lojistik::$id   => __NAMESPACE__ . '\Courier_Horoz_Lojistik',
			Courier_Kargo_Turk::$id       => __NAMESPACE__ . '\Courier_Kargo_Turk',
			Courier_Sendeo::$id           => __NAMESPACE__ . '\Courier_Sendeo',
			Courier_Brinks::$id           => __NAMESPACE__ . '\Courier_Brinks',
			Courier_DHL::$id              => __NAMESPACE__ . '\Courier_DHL',
			Courier_TNT::$id              => __NAMESPACE__ . '\Courier_TNT',
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
					$tracking_url = 'https://app.jetizz.com/gonderi-takip';
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
	 * Returns the courier company class.
	 * 
	 * @param int|string $id Order ID or Courier ID.
	 * 
	 * @return string
	 */
	public static function get_courier_company_class( $id ) {
		if ( is_numeric( $id ) ) { // $id is an oder ID.
			return self::courier_companies()[ self::get_courier_company_id( $id ) ];
		} else { // $id is a courier company ID.
			return self::courier_companies()[ $id ];
		}
	}

	/**
	 * Returns the courier company ID of the order.
	 * 
	 * @param int|string $order_id Order ID.
	 * 
	 * @return string
	 */
	public static function get_courier_company_id( $order_id ) {
		return get_post_meta( $order_id, self::COURIER_COMPANY_KEY, true );
	}

	/**
	 * Returns the default courier company ID.
	 * 
	 * @return string
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
