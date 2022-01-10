<?php
/**
 * Contains the class responsible for getting province, district and neighborhood data.
 * 
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

/**
 * The class responsible for getting province, district and neighborhood data.
 */
class Mahalle_Local {
	/**
	 * Returns cities
	 * 
	 * @return array
	 */
	public static function get_cities() {
		require WC_HEZARFEN_UYGULAMA_YOLU . 'includes/Data/mahalle/tr-provinces.php';

		return $tr_provinces;
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

		if ( isset( $tr_districts[ $city_plate_number ] ) ) {
			return $tr_districts[ $city_plate_number ];
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

			if ( isset( $tr_neighborhoods[ $district ] ) ) {
				return $tr_neighborhoods[ $district ];
			}
		}

		return array();
	}
}
