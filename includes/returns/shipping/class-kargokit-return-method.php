<?php
/**
 * Contains the Kargokit_Return_Method.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Shipping;

use Hezarfen\Inc\Returns\Core\Return_Pickup_Address;
use Hezarfen\Inc\Returns\Core\Return_Settings;

defined( 'ABSPATH' ) || exit();

/**
 * Produces a Kargokit (hepsiJET) return label for the pickup day the
 * customer picked.
 *
 * The label itself is created by the manual-shipment-tracking package,
 * which already owns the Kargokit credentials and relay client. This class
 * is the adapter that lets the returns module drive it without knowing
 * anything about the carrier API.
 *
 * The label is deliberately not produced at approval time: the courier
 * collects the parcel from the customer's door, so the day has to be one
 * they will be home on. Approval only unlocks the booking; the customer
 * makes it from their account.
 */
class Kargokit_Return_Method implements Return_Shipping_Method_Interface {

	const KEY = 'kargokit';

	/**
	 * How far ahead to look for a pickup slot, in days.
	 */
	const PICKUP_SEARCH_DAYS = 14;

	/**
	 * How long a fetched slot list stays usable, in seconds.
	 */
	const OPTIONS_CACHE_TTL = 1800;

	/**
	 * Prefix of the transient the slot list is cached under.
	 */
	const OPTIONS_CACHE_PREFIX = 'hezarfen_returns_pickup_';

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
		return __( 'hepsiJET (Kargokit) ile otomatik iade barkodu', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Short explanation.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Talebi onayladığınızda müşteri hesabından kargo alım gününü seçer; iade barkodu hepsiJET (Kargokit) üzerinden otomatik oluşturulur ve kargo müşterinin adresinden alınır. Bu seçenek yalnızca Kargokit API bilgileri girildiğinde listelenir.', 'hezarfen-for-woocommerce' );
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
	 * The customer picks the pickup day themselves.
	 *
	 * @return bool
	 */
	public function requires_customer_booking() {
		return true;
	}

	/**
	 * The courier collects the parcel from the customer's door.
	 *
	 * @return bool
	 */
	public function requires_pickup_address() {
		return true;
	}

	/**
	 * The pickup days the carrier still offers for the customer's address,
	 * as `Y-m-d` keys with a localized label.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request Approved request.
	 *
	 * @return array<string, string>|\WP_Error
	 */
	public function get_booking_options( $request ) {
		if ( ! $this->is_available() ) {
			return new \WP_Error(
				'hezarfen_returns_kargokit_unavailable',
				__( 'Kargokit entegrasyonu yapılandırılmadığı için iade barkodu oluşturulamıyor.', 'hezarfen-for-woocommerce' )
			);
		}

		$order = $request->get_order();

		if ( ! $order ) {
			return new \WP_Error(
				'hezarfen_returns_kargokit_no_order',
				__( 'İade barkodu için sipariş bulunamadı.', 'hezarfen-for-woocommerce' )
			);
		}

		$pickup   = $this->get_pickup_address( $request );
		$city     = $pickup['city'];
		$district = $pickup['district'];

		if ( '' === $city || '' === $district ) {
			return new \WP_Error(
				'hezarfen_returns_kargokit_no_address',
				__( 'Kargo alım adresinizin il/ilçe bilgisi eksik olduğu için uygun günler getirilemedi.', 'hezarfen-for-woocommerce' )
			);
		}

		$cached = get_transient( $this->get_options_cache_key( $city, $district ) );

		if ( is_array( $cached ) ) {
			return $this->label_dates( $cached );
		}

		$integration = new \Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration();

		$dates = $integration->get_available_dates_for_return(
			gmdate( 'Y-m-d' ),
			gmdate( 'Y-m-d', time() + ( self::PICKUP_SEARCH_DAYS * DAY_IN_SECONDS ) ),
			$city,
			$district
		);

		if ( is_wp_error( $dates ) ) {
			return $dates;
		}

		$days = $this->flatten_dates( $dates );

		if ( ! $days ) {
			return new \WP_Error(
				'hezarfen_returns_kargokit_no_slot',
				__( 'Kargokit bu adres için şu an uygun bir iade alım günü döndürmedi. Lütfen daha sonra tekrar deneyin.', 'hezarfen-for-woocommerce' )
			);
		}

		// The list is identical for everyone in the district and each lookup
		// is a carrier round trip, so it is worth a short cache: an account
		// page that renders the picker must not wait on the relay on every
		// single load.
		set_transient( $this->get_options_cache_key( $city, $district ), $days, self::OPTIONS_CACHE_TTL );

		return $this->label_dates( $days );
	}

	/**
	 * Creates the return label for the picked day and writes the tracking
	 * details onto the request.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request Approved request.
	 * @param string                                    $choice  Pickup day, `Y-m-d`.
	 *
	 * @return true|\WP_Error
	 */
	public function book( $request, $choice ) {
		$options = $this->get_booking_options( $request );

		if ( is_wp_error( $options ) ) {
			return $options;
		}

		// The value came out of a form: it may be a day the carrier has
		// since filled up, or one that was never offered at all.
		if ( ! isset( $options[ $choice ] ) ) {
			return new \WP_Error(
				'hezarfen_returns_kargokit_slot_taken',
				__( 'Seçtiğiniz gün artık müsait değil. Lütfen listedeki günlerden birini seçin.', 'hezarfen-for-woocommerce' )
			);
		}

		$order = $request->get_order();

		if ( ! $order ) {
			return new \WP_Error(
				'hezarfen_returns_kargokit_no_order',
				__( 'İade barkodu için sipariş bulunamadı.', 'hezarfen-for-woocommerce' )
			);
		}

		$pickup_address = $this->get_pickup_address( $request );
		$valid          = Return_Pickup_Address::validate( $pickup_address );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$integration = new \Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration();

		// A return travels as a single parcel and the carrier prices it off
		// the outbound leg, so one package of the smallest billable desi is
		// what the relay expects here.
		$created = $integration->api_create_barcode(
			$order->get_id(),
			array( array( 'desi' => 1 ) ),
			'returned',
			'',
			$choice,
			'',
			Return_Pickup_Address::to_carrier( $pickup_address )
		);

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$barcode_no = is_array( $created ) && ! empty( $created['tracking_number'] ) ? (string) $created['tracking_number'] : '';

		if ( '' === $barcode_no ) {
			return new \WP_Error(
				'hezarfen_returns_kargokit_no_barcode',
				__( 'Kargokit iade barkodu numarası okunamadı.', 'hezarfen-for-woocommerce' )
			);
		}

		$request->set_tracking_number( $barcode_no );
		$request->set_courier( 'hepsijet-entegrasyon' );
		$request->set_pickup_date( $choice );

		// One slot fewer in this district: the cached list is now a set of
		// days the carrier may no longer honour.
		$this->flush_options_cache( $request );

		return true;
	}

	/**
	 * Cancels the booked pickup at the carrier and frees the request to be
	 * booked again.
	 *
	 * The carrier is told first: only once it has released the appointment
	 * is it safe to forget the tracking number locally, because a request
	 * that looks unbooked while a courier is still coming is worse than one
	 * the customer cannot cancel.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request Booked request.
	 *
	 * @return true|\WP_Error
	 */
	public function cancel_booking( $request ) {
		$tracking = $request->get_tracking_number();

		if ( '' === $tracking ) {
			return new \WP_Error(
				'hezarfen_returns_kargokit_nothing_booked',
				__( 'İptal edilecek bir kargo randevusu bulunamadı.', 'hezarfen-for-woocommerce' )
			);
		}

		if ( ! $this->is_available() ) {
			return new \WP_Error(
				'hezarfen_returns_kargokit_unavailable',
				__( 'Kargokit entegrasyonu yapılandırılmadığı için randevu iptal edilemiyor.', 'hezarfen-for-woocommerce' )
			);
		}

		$integration = new \Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration();
		$cancelled   = $integration->api_cancel_shipment( $tracking );

		if ( is_wp_error( $cancelled ) ) {
			return $cancelled;
		}

		$request->set_tracking_number( '' );
		$request->set_courier( '' );
		$request->set_pickup_date( '' );

		// One slot back in this district, so the cached list is stale in the
		// other direction now.
		$this->flush_options_cache( $request );

		return true;
	}

	/**
	 * Approval alone books nothing — the customer picks the pickup day
	 * afterwards, from their account.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request Approved request.
	 *
	 * @return true
	 */
	public function handle_approved( $request ) {
		unset( $request );

		return true;
	}

	/**
	 * Tells the customer what happens next: pick a day, then wait for the
	 * courier.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request The request.
	 *
	 * @return string
	 */
	public function get_customer_instructions( $request ) {
		if ( $request->get_tracking_number() ) {
			$lines = array( __( 'İade kargonuz seçtiğiniz gün adresinizden teslim alınacaktır. Ürünleri orijinal ambalajında hazır bulundurun.', 'hezarfen-for-woocommerce' ) );
		} else {
			$lines = array( __( 'İade kargonuzun adresinizden alınmasını istediğiniz günü aşağıdan seçin. Seçiminizin ardından iade kargo kodunuz oluşturulur.', 'hezarfen-for-woocommerce' ) );
		}

		$custom = Return_Settings::get_instructions();

		if ( '' !== trim( $custom ) ) {
			$lines[] = $custom;
		}

		return wpautop( esc_html( implode( "\n", $lines ) ) );
	}

	/**
	 * The address the courier collects the parcel from.
	 *
	 * The confirmed address on the request wins, because it is the one the
	 * customer was shown and corrected. Requests opened before the address
	 * was captured — and any the merchant created another way — still have
	 * the order's shipping address to fall back on.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request The request.
	 *
	 * @return array<string, string>
	 */
	private function get_pickup_address( $request ) {
		if ( $request->has_pickup_address() ) {
			return $request->get_pickup_address();
		}

		$order = $request->get_order();

		return $order ? Return_Pickup_Address::from_order( $order ) : Return_Pickup_Address::empty_address();
	}

	/**
	 * Drops the cached slot list of the pickup district.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request The request.
	 *
	 * @return void
	 */
	private function flush_options_cache( $request ) {
		$pickup = $this->get_pickup_address( $request );

		delete_transient( $this->get_options_cache_key( $pickup['city'], $pickup['district'] ) );
	}

	/**
	 * Cache key of one district's slot list.
	 *
	 * Today's date is part of the key so a list cached just before midnight
	 * cannot survive into a day whose first entry is already in the past.
	 *
	 * @param string $city     City name.
	 * @param string $district District name.
	 *
	 * @return string
	 */
	private function get_options_cache_key( $city, $district ) {
		return self::OPTIONS_CACHE_PREFIX . md5( $city . '|' . $district . '|' . gmdate( 'Y-m-d' ) );
	}

	/**
	 * Turns the carrier's per cross-dock day lists into one ordered list of
	 * days.
	 *
	 * Which cross-dock serves the address is the carrier's business; the
	 * customer only cares which days they can be visited on.
	 *
	 * @param array<string, string[]> $dates Days keyed by cross-dock name.
	 *
	 * @return string[] Ordered `Y-m-d` days.
	 */
	private function flatten_dates( $dates ) {
		$today = gmdate( 'Y-m-d' );
		$days  = array();

		foreach ( (array) $dates as $slots ) {
			foreach ( (array) $slots as $slot ) {
				$slot = trim( (string) $slot );

				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $slot ) || $slot < $today ) {
					continue;
				}

				$days[ $slot ] = $slot;
			}
		}

		ksort( $days );

		return array_values( $days );
	}

	/**
	 * Labels each day for the picker.
	 *
	 * @param string[] $days Ordered `Y-m-d` days.
	 *
	 * @return array<string, string>
	 */
	private function label_dates( $days ) {
		$options = array();

		foreach ( (array) $days as $day ) {
			$timestamp = strtotime( $day . ' 12:00:00' );

			$options[ $day ] = $timestamp ? date_i18n( 'j F Y, l', $timestamp ) : $day;
		}

		return $options;
	}
}
