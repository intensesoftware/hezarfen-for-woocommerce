<?php
/**
 * Contains the Kargokit_Return_Method.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Shipping;

use Hezarfen\Inc\Returns\Core\Return_Settings;

defined( 'ABSPATH' ) || exit();

/**
 * Produces a Kargokit (hepsiJET) return label when a request is approved.
 *
 * The label itself is created by the manual-shipment-tracking package,
 * which already owns the Kargokit credentials and relay client. This class
 * is the adapter that lets the returns module drive it without knowing
 * anything about the carrier API.
 */
class Kargokit_Return_Method implements Return_Shipping_Method_Interface {

	const KEY = 'kargokit';

	/**
	 * How far ahead to look for a pickup slot, in days.
	 */
	const PICKUP_SEARCH_DAYS = 14;

	/**
	 * Stable identifier.
	 *
	 * @return string
	 */
	public function get_key() {
		return self::KEY;
	}

	/**
	 * Merchant facing label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Kargokit ile otomatik iade barkodu', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Short explanation.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Talebi onayladığınızda Kargokit üzerinden iade barkodu otomatik oluşturulur; kargo müşteriden adresinden alınır.', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Selectable only when the Kargokit / hepsiJET integration is wired up.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( ! class_exists( '\Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration' ) ) {
			return false;
		}

		$key    = (string) get_option( 'hezarfen_hepsijet_consumer_key', '' );
		$secret = (string) get_option( 'hezarfen_hepsijet_consumer_secret', '' );

		return '' !== $key && '' !== $secret;
	}

	/**
	 * The label carries its own tracking number, so the customer does not
	 * type one in.
	 *
	 * @return bool
	 */
	public function requires_customer_tracking() {
		return false;
	}

	/**
	 * Creates the return label and writes the tracking details onto the
	 * request.
	 *
	 * A carrier hiccup must never block a merchant from approving a
	 * request, so failures come back as a WP_Error the caller records on
	 * the timeline instead of an exception.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request Approved request.
	 *
	 * @return true|\WP_Error
	 */
	public function handle_approved( $request ) {
		if ( ! $this->is_available() ) {
			return new \WP_Error(
				'hezarfen_returns_kargokit_unavailable',
				__( 'Kargokit entegrasyonu yapılandırılmadığı için iade barkodu oluşturulamadı.', 'hezarfen-for-woocommerce' )
			);
		}

		$order = $request->get_order();

		if ( ! $order ) {
			return new \WP_Error(
				'hezarfen_returns_kargokit_no_order',
				__( 'İade barkodu için sipariş bulunamadı.', 'hezarfen-for-woocommerce' )
			);
		}

		$integration = new \Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration();
		$pickup_date = $this->find_pickup_date( $integration, $order );

		if ( is_wp_error( $pickup_date ) ) {
			return $pickup_date;
		}

		$created = $integration->api_create_return_barcode( $order->get_id(), $pickup_date );

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		if ( ! $created ) {
			return new \WP_Error(
				'hezarfen_returns_kargokit_failed',
				__( 'Kargokit iade barkodu oluşturulamadı. Siparişin gönderi kaydı bulunduğundan emin olun.', 'hezarfen-for-woocommerce' )
			);
		}

		$barcode_no = (string) $order->get_meta( '_hezarfen_hepsijet_return_barcode_no' );

		if ( '' === $barcode_no ) {
			return new \WP_Error(
				'hezarfen_returns_kargokit_no_barcode',
				__( 'Kargokit iade barkodu numarası okunamadı.', 'hezarfen-for-woocommerce' )
			);
		}

		$request->set_tracking_number( $barcode_no );
		$request->set_courier( 'hepsijet-entegrasyon' );

		return true;
	}

	/**
	 * Tells the customer that pickup is arranged.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request The request.
	 *
	 * @return string
	 */
	public function get_customer_instructions( $request ) {
		$lines = array( __( 'İade kargonuz adresinizden teslim alınacaktır. Ürünleri orijinal ambalajında hazır bulundurun.', 'hezarfen-for-woocommerce' ) );

		if ( $request->get_tracking_number() ) {
			$lines[] = sprintf(
				/* translators: %s: return shipment tracking number. */
				__( 'İade kargo takip numaranız: %s', 'hezarfen-for-woocommerce' ),
				$request->get_tracking_number()
			);
		}

		$custom = Return_Settings::get_instructions();

		if ( '' !== trim( $custom ) ) {
			$lines[] = $custom;
		}

		return wpautop( esc_html( implode( "\n", $lines ) ) );
	}

	/**
	 * Picks the earliest pickup slot the carrier still offers for the
	 * customer's district.
	 *
	 * @param \Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration $integration Carrier client.
	 * @param \WC_Order                                                     $order       Parent order.
	 *
	 * @return string|\WP_Error Carrier supplied date string.
	 */
	private function find_pickup_date( $integration, $order ) {
		$details  = new \Hezarfen\ManualShipmentTracking\Shipping_Details( $order->get_id() );
		$city     = $details->get_city();
		$district = $details->get_district();

		if ( ! $city || ! $district ) {
			return new \WP_Error(
				'hezarfen_returns_kargokit_no_address',
				__( 'İade barkodu için siparişin il/ilçe bilgisi eksik.', 'hezarfen-for-woocommerce' )
			);
		}

		$dates = $integration->get_available_dates_for_return(
			gmdate( 'Y-m-d' ),
			gmdate( 'Y-m-d', time() + ( self::PICKUP_SEARCH_DAYS * DAY_IN_SECONDS ) ),
			$city,
			$district
		);

		if ( is_wp_error( $dates ) ) {
			return $dates;
		}

		// The carrier groups slots per cross-dock; any of them will do and
		// the earliest one keeps the customer waiting the least.
		foreach ( (array) $dates as $slots ) {
			foreach ( (array) $slots as $slot ) {
				if ( $slot ) {
					return (string) $slot;
				}
			}
		}

		return new \WP_Error(
			'hezarfen_returns_kargokit_no_slot',
			__( 'Kargokit bu adres için uygun bir iade alım günü döndürmedi.', 'hezarfen-for-woocommerce' )
		);
	}
}
