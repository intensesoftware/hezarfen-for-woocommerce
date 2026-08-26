<?php
/**
 * Contains the Return_Pickup_Address value object.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

use Hezarfen\Inc\Mahalle_Local;

defined( 'ABSPATH' ) || exit();

/**
 * The address a carrier collects the return parcel from.
 *
 * This is the customer's address, not the store's: on a return leg the
 * courier drives to whoever is sending the goods back. The order's shipping
 * address is only a starting point — it can be stale, or carry a phone the
 * carrier will not accept — so the customer confirms it, and what they
 * confirm is what travels to the carrier.
 *
 * Every conversion the flow needs lives here: reading an order, reading a
 * form, validating, storing and handing the parts to the carrier client.
 * The rest of the module treats the address as an opaque array.
 */
class Return_Pickup_Address {

	/**
	 * The parts an address is made of, in form order.
	 */
	const FIELDS = array(
		'first_name',
		'last_name',
		'phone',
		'city_code',
		'city',
		'district',
		'neighborhood',
		'address',
	);

	/**
	 * An empty address in the canonical shape.
	 *
	 * @return array<string, string>
	 */
	public static function empty_address() {
		return array_fill_keys( self::FIELDS, '' );
	}

	/**
	 * The address parts of an order's shipping address.
	 *
	 * Turkey's WooCommerce convention puts the city in the state field, the
	 * district in the city field and the neighbourhood in address line 1 —
	 * the same mapping the order screen's shipment box uses.
	 *
	 * @param \WC_Order $order Order being returned from.
	 *
	 * @return array<string, string>
	 */
	public static function from_order( $order ) {
		$city_code = (string) $order->get_shipping_state();
		$phone     = $order->get_shipping_phone() ? $order->get_shipping_phone() : $order->get_billing_phone();

		return self::normalize(
			array(
				'first_name'   => $order->get_shipping_first_name(),
				'last_name'    => $order->get_shipping_last_name(),
				'phone'        => $phone,
				'city_code'    => $city_code,
				'city'         => Mahalle_Local::get_city_name_by_plate_num( $city_code ),
				'district'     => $order->get_shipping_city(),
				'neighborhood' => $order->get_shipping_address_1(),
				'address'      => $order->get_shipping_address_2(),
			)
		);
	}

	/**
	 * Reads an address out of submitted form input.
	 *
	 * The city name is resolved from the plate code rather than trusted from
	 * the form: the carrier matches cities by name, and a name that does not
	 * belong to the code would be rejected downstream with nothing pointing
	 * back at the form that caused it.
	 *
	 * @param array<string, mixed> $input Raw `$_POST` slice.
	 *
	 * @return array<string, string>
	 */
	public static function from_input( $input ) {
		$input = is_array( $input ) ? $input : array();

		$city_code = isset( $input['city_code'] ) ? sanitize_text_field( wp_unslash( $input['city_code'] ) ) : '';

		return self::normalize(
			array(
				'first_name'   => isset( $input['first_name'] ) ? sanitize_text_field( wp_unslash( $input['first_name'] ) ) : '',
				'last_name'    => isset( $input['last_name'] ) ? sanitize_text_field( wp_unslash( $input['last_name'] ) ) : '',
				'phone'        => isset( $input['phone'] ) ? sanitize_text_field( wp_unslash( $input['phone'] ) ) : '',
				'city_code'    => $city_code,
				'city'         => Mahalle_Local::get_city_name_by_plate_num( $city_code ),
				'district'     => isset( $input['district'] ) ? sanitize_text_field( wp_unslash( $input['district'] ) ) : '',
				'neighborhood' => isset( $input['neighborhood'] ) ? sanitize_text_field( wp_unslash( $input['neighborhood'] ) ) : '',
				'address'      => isset( $input['address'] ) ? sanitize_textarea_field( wp_unslash( $input['address'] ) ) : '',
			)
		);
	}

	/**
	 * Trims every part and keeps only the canonical keys.
	 *
	 * @param array<string, mixed> $address Address parts.
	 *
	 * @return array<string, string>
	 */
	public static function normalize( $address ) {
		$address = is_array( $address ) ? $address : array();
		$clean   = self::empty_address();

		foreach ( self::FIELDS as $field ) {
			$clean[ $field ] = isset( $address[ $field ] ) ? trim( (string) $address[ $field ] ) : '';
		}

		$clean['address'] = preg_replace( '/\s*\R\s*/u', ' ', $clean['address'] );

		return $clean;
	}

	/**
	 * Whether every part the carrier needs is filled in.
	 *
	 * @param array<string, string> $address Address parts.
	 *
	 * @return bool
	 */
	public static function is_complete( $address ) {
		return ! self::validate( $address ) instanceof \WP_Error;
	}

	/**
	 * Checks an address against what the carrier will accept.
	 *
	 * The district and neighbourhood are matched against the official lists
	 * rather than merely required, because the carrier resolves them by
	 * exact name: a hand typed "Cankaya" reaches the API as an unknown
	 * district and fails there, long after the customer left the form.
	 *
	 * @param array<string, string> $address Address parts.
	 *
	 * @return true|\WP_Error
	 */
	public static function validate( $address ) {
		$address = self::normalize( $address );

		$required = array(
			'first_name'   => __( 'Ad', 'hezarfen-for-woocommerce' ),
			'last_name'    => __( 'Soyad', 'hezarfen-for-woocommerce' ),
			'phone'        => __( 'Telefon', 'hezarfen-for-woocommerce' ),
			'city_code'    => __( 'İl', 'hezarfen-for-woocommerce' ),
			'district'     => __( 'İlçe', 'hezarfen-for-woocommerce' ),
			'neighborhood' => __( 'Mahalle', 'hezarfen-for-woocommerce' ),
			'address'      => __( 'Açık adres', 'hezarfen-for-woocommerce' ),
		);

		foreach ( $required as $field => $label ) {
			if ( '' === $address[ $field ] ) {
				return new \WP_Error(
					'hezarfen_returns_pickup_address_incomplete',
					sprintf(
						/* translators: %s: name of the missing address field. */
						__( 'Kargo alım adresinde %s alanını doldurun.', 'hezarfen-for-woocommerce' ),
						$label
					)
				);
			}
		}

		if ( '' === $address['city'] ) {
			return new \WP_Error(
				'hezarfen_returns_pickup_address_city',
				__( 'Kargo alım adresi için listeden bir il seçin.', 'hezarfen-for-woocommerce' )
			);
		}

		if ( 10 > strlen( self::digits( $address['phone'] ) ) ) {
			return new \WP_Error(
				'hezarfen_returns_pickup_address_phone',
				__( 'Kargo alım adresi için geçerli bir cep telefonu numarası girin.', 'hezarfen-for-woocommerce' )
			);
		}

		if ( ! in_array( $address['district'], Mahalle_Local::get_districts( $address['city_code'] ), true ) ) {
			return new \WP_Error(
				'hezarfen_returns_pickup_address_district',
				__( 'Kargo alım adresi için listeden bir ilçe seçin.', 'hezarfen-for-woocommerce' )
			);
		}

		$neighborhoods = Mahalle_Local::get_neighborhoods( $address['city_code'], $address['district'], false );

		if ( $neighborhoods && ! in_array( $address['neighborhood'], $neighborhoods, true ) ) {
			return new \WP_Error(
				'hezarfen_returns_pickup_address_neighborhood',
				__( 'Kargo alım adresi için listeden bir mahalle seçin.', 'hezarfen-for-woocommerce' )
			);
		}

		return true;
	}

	/**
	 * The address in the shape the Kargokit client expects.
	 *
	 * @param array<string, string> $address Address parts.
	 *
	 * @return array<string, string>
	 */
	public static function to_carrier( $address ) {
		$address = self::normalize( $address );

		return array(
			'first_name'   => $address['first_name'],
			'last_name'    => $address['last_name'],
			'phone'        => self::digits( $address['phone'] ),
			'city'         => $address['city'],
			'district'     => $address['district'],
			'neighborhood' => $address['neighborhood'],
			'address'      => $address['address'],
		);
	}

	/**
	 * The address as a human readable block.
	 *
	 * @param array<string, string> $address Address parts.
	 *
	 * @return string Plain text, newline separated.
	 */
	public static function format( $address ) {
		$address = self::normalize( $address );

		$lines = array(
			trim( $address['first_name'] . ' ' . $address['last_name'] ),
			$address['address'],
			trim( $address['neighborhood'] . ' ' . $address['district'] . ' / ' . $address['city'] ),
			$address['phone'],
		);

		return implode( "\n", array_filter( array_map( 'trim', $lines ) ) );
	}

	/**
	 * Digits of a phone number, with Turkey's country and trunk prefixes
	 * dropped so the carrier always receives the bare subscriber number.
	 *
	 * @param string $phone Phone number as typed.
	 *
	 * @return string
	 */
	public static function digits( $phone ) {
		$digits = preg_replace( '/\D/', '', (string) $phone );

		if ( 12 === strlen( $digits ) && '90' === substr( $digits, 0, 2 ) ) {
			$digits = substr( $digits, 2 );
		}

		if ( 11 === strlen( $digits ) && '0' === substr( $digits, 0, 1 ) ) {
			$digits = substr( $digits, 1 );
		}

		return $digits;
	}
}
