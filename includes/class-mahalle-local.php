<?php
/**
 * Class responsible for getting province, district and neighborhood data.
 * 
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

class Mahalle_Local {
	/**
	 * Returns cities
	 * 
	 * @return array
	 */
	public static function get_cities() {
		require WC_HEZARFEN_UYGULAMA_YOLU . 'includes/Data/mahalle/tr-provinces.php';

		return $TRProvinces;
	}

	/**
	 * Get districts by TR city plate number
	 *
	 * @param string $city_plate_number The city plate number.
	 *
	 * @return array
	 */
	public static function get_districts( $city_plate_number ) {
		require WC_HEZARFEN_UYGULAMA_YOLU . 'includes/Data/mahalle/tr-province-districts.php';

		if ( isset( $TRPD[ $city_plate_number ] ) ) {
			return $TRPD[ $city_plate_number ];
		}

		return array();
	}

	/**
	 * Get neighborhoods by province and district
	 *
	 * @param string $city_plate_number The city plate number.
	 * @param string $district The district.
	 * 
	 * @return array
	 */
	public static function get_neighborhoods( $city_plate_number, $district ) {
		if ( $city_plate_number ) {
			require WC_HEZARFEN_UYGULAMA_YOLU . "includes/Data/mahalle/tr-neighborhoods/tr-district-nbrhood-$city_plate_number.php";

			if ( isset( $TRDN[ $district ] ) ) {
				return $TRDN[ $district ];
			}
		}

		return array();
	}
}
