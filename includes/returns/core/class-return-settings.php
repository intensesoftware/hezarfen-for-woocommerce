<?php
/**
 * Contains the Return_Settings accessor.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

defined( 'ABSPATH' ) || exit();

/**
 * Typed, filterable access to the module's options.
 *
 * Nothing else in the module calls get_option() for returns settings, so
 * defaults, casting and the option key names all live in one place.
 */
class Return_Settings {

	const OPTION_ENABLED           = 'hezarfen_returns_enabled';
	const OPTION_WINDOW_DAYS       = 'hezarfen_returns_window_days';
	const OPTION_WINDOW_REFERENCE  = 'hezarfen_returns_window_reference';
	const OPTION_ELIGIBLE_STATUSES = 'hezarfen_returns_eligible_order_statuses';
	const OPTION_GUEST_ENABLED     = 'hezarfen_returns_guest_enabled';
	const OPTION_SHIPPING_METHOD   = 'hezarfen_returns_shipping_method';
	const OPTION_INSTRUCTIONS      = 'hezarfen_returns_instructions';
	const OPTION_PAGE_ID           = 'hezarfen_returns_page_id';

	const OPTION_ADDRESS_LABEL        = 'hezarfen_returns_address_label';
	const OPTION_ADDRESS_CONTACT      = 'hezarfen_returns_address_contact';
	const OPTION_ADDRESS_PHONE        = 'hezarfen_returns_address_phone';
	const OPTION_ADDRESS_LINE         = 'hezarfen_returns_address_line';
	const OPTION_ADDRESS_NEIGHBORHOOD = 'hezarfen_returns_address_neighborhood';
	const OPTION_ADDRESS_DISTRICT     = 'hezarfen_returns_address_district';
	const OPTION_ADDRESS_CITY         = 'hezarfen_returns_address_city';
	const OPTION_ADDRESS_POSTCODE     = 'hezarfen_returns_address_postcode';

	const REFERENCE_COMPLETED = 'completed';
	const REFERENCE_PAID      = 'paid';
	const REFERENCE_CREATED   = 'created';

	/**
	 * Whether the returns module is switched on.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		/**
		 * Filters whether the returns module is active.
		 *
		 * @param bool $enabled Whether returns are enabled.
		 */
		return (bool) apply_filters( 'hezarfen_returns_enabled', 'yes' === get_option( self::OPTION_ENABLED, 'no' ) );
	}

	/**
	 * Whether customers without an account can open a request.
	 *
	 * @return bool
	 */
	public static function is_guest_enabled() {
		return 'yes' === get_option( self::OPTION_GUEST_ENABLED, 'yes' );
	}

	/**
	 * The store-wide return window in days.
	 *
	 * @return int
	 */
	public static function get_window_days() {
		return max( 0, (int) get_option( self::OPTION_WINDOW_DAYS, 14 ) );
	}

	/**
	 * Which order date the window counts from.
	 *
	 * @return string One of the REFERENCE_* constants.
	 */
	public static function get_window_reference() {
		$reference = (string) get_option( self::OPTION_WINDOW_REFERENCE, self::REFERENCE_COMPLETED );
		$allowed   = array( self::REFERENCE_COMPLETED, self::REFERENCE_PAID, self::REFERENCE_CREATED );

		return in_array( $reference, $allowed, true ) ? $reference : self::REFERENCE_COMPLETED;
	}

	/**
	 * Order statuses whose orders may be returned.
	 *
	 * @return string[] Status keys without the `wc-` prefix.
	 */
	public static function get_eligible_order_statuses() {
		$statuses = get_option( self::OPTION_ELIGIBLE_STATUSES, array( 'completed' ) );

		if ( ! is_array( $statuses ) ) {
			$statuses = array( 'completed' );
		}

		// Settings store the `wc-` prefixed form; the rest of the module
		// compares against WC_Order::get_status(), which never carries it.
		$statuses = array_values(
			array_filter(
				array_map(
					function ( $status ) {
						$status = (string) $status;

						return 0 === strpos( $status, 'wc-' ) ? substr( $status, 3 ) : $status;
					},
					$statuses
				)
			)
		);

		return $statuses ? $statuses : array( 'completed' );
	}

	/**
	 * Key of the return shipping method the store offers.
	 *
	 * @return string
	 */
	public static function get_shipping_method_key() {
		return (string) get_option( self::OPTION_SHIPPING_METHOD, 'customer-ships' );
	}

	/**
	 * Free text instructions shown to the customer after a request is
	 * approved.
	 *
	 * @return string
	 */
	public static function get_instructions() {
		return (string) get_option( self::OPTION_INSTRUCTIONS, '' );
	}

	/**
	 * ID of the page that hosts the guest return form.
	 *
	 * @return int
	 */
	public static function get_page_id() {
		return (int) get_option( self::OPTION_PAGE_ID, 0 );
	}

	/**
	 * Permalink of the guest return page, empty when unset.
	 *
	 * @return string
	 */
	public static function get_page_url() {
		$page_id = self::get_page_id();

		if ( ! $page_id ) {
			return '';
		}

		$permalink = get_permalink( $page_id );

		return $permalink ? $permalink : '';
	}

	/**
	 * The single return address the store publishes.
	 *
	 * The module ships everything to one address; the array shape already
	 * matches what a multi-warehouse add-on would return per warehouse.
	 *
	 * @return array<string, string>
	 */
	public static function get_return_address() {
		$address = array(
			'id'           => 'default',
			'label'        => (string) get_option( self::OPTION_ADDRESS_LABEL, '' ),
			'contact'      => (string) get_option( self::OPTION_ADDRESS_CONTACT, '' ),
			'phone'        => (string) get_option( self::OPTION_ADDRESS_PHONE, '' ),
			'address'      => (string) get_option( self::OPTION_ADDRESS_LINE, '' ),
			'neighborhood' => (string) get_option( self::OPTION_ADDRESS_NEIGHBORHOOD, '' ),
			'district'     => (string) get_option( self::OPTION_ADDRESS_DISTRICT, '' ),
			'city'         => (string) get_option( self::OPTION_ADDRESS_CITY, '' ),
			'postcode'     => (string) get_option( self::OPTION_ADDRESS_POSTCODE, '' ),
		);

		/**
		 * Filters the return address a request is shipped back to.
		 *
		 * Multi-warehouse add-ons hook here to swap the address per
		 * request.
		 *
		 * @param array<string, string> $address Address parts.
		 */
		return apply_filters( 'hezarfen_returns_return_address', $address );
	}

	/**
	 * Whether the merchant filled in enough of the return address to show
	 * it to a customer.
	 *
	 * @return bool
	 */
	public static function has_return_address() {
		$address = self::get_return_address();

		return '' !== trim( $address['address'] ) && '' !== trim( $address['city'] );
	}

	/**
	 * The return address rendered as a single human readable block.
	 *
	 * @return string
	 */
	public static function get_formatted_return_address() {
		$address = self::get_return_address();

		$lines = array_filter(
			array(
				$address['contact'],
				$address['address'],
				trim( $address['neighborhood'] . ' ' . $address['district'] ),
				trim( $address['postcode'] . ' ' . $address['city'] ),
				$address['phone'],
			),
			function ( $line ) {
				return '' !== trim( (string) $line );
			}
		);

		return implode( "\n", $lines );
	}
}
