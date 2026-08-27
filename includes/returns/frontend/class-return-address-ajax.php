<?php
/**
 * Contains the Return_Address_Ajax endpoint.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Frontend;

use Hezarfen\Inc\Mahalle_Local;

defined( 'ABSPATH' ) || exit();

/**
 * Feeds the pickup address selects their districts and neighbourhoods.
 *
 * The checkout's own endpoint answers a different question — it reacts to a
 * chosen neighbourhood and refreshes the cart — so the address form gets a
 * plain read-only lookup of its own instead. Guests reach it too: a return
 * can be opened from an order key without an account.
 */
class Return_Address_Ajax {

	const ACTION = 'hezarfen_returns_address_options';
	const NONCE  = 'hezarfen_returns_address_options';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'respond' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'respond' ) );
	}

	/**
	 * Returns the districts of a city, or the neighbourhoods of a district.
	 *
	 * @return void
	 */
	public function respond() {
		check_ajax_referer( self::NONCE, 'security' );

		$city_code = isset( $_GET['city_code'] ) ? sanitize_text_field( wp_unslash( $_GET['city_code'] ) ) : '';
		$district  = isset( $_GET['district'] ) ? sanitize_text_field( wp_unslash( $_GET['district'] ) ) : '';

		$districts = Mahalle_Local::get_districts( $city_code );

		if ( '' === $district ) {
			wp_send_json_success( array( 'districts' => array_values( $districts ) ) );
		}

		// A district that does not belong to the city would otherwise send
		// the lookup into another city's neighbourhood file.
		if ( ! in_array( $district, $districts, true ) ) {
			wp_send_json_success( array( 'neighborhoods' => array() ) );
		}

		wp_send_json_success(
			array(
				'neighborhoods' => array_values( Mahalle_Local::get_neighborhoods( $city_code, $district, false ) ),
			)
		);
	}
}
