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
		require dirname( __FILE__ ) . '/Data/mahalle/tr-provinces.php';

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
		if ( self::check_city_plate_number( $city_plate_number ) ) {
			require dirname( __FILE__ ) . '/Data/mahalle/tr-province-districts.php';

			if ( isset( $tr_districts[ $city_plate_number ] ) ) {
				return $tr_districts[ $city_plate_number ];
			}
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
		if ( self::check_city_plate_number( $city_plate_number ) ) {
			require dirname( __FILE__ ) . "/Data/mahalle/tr-neighborhoods/tr-district-nbrhood-$city_plate_number.php";

			if ( isset( $tr_neighborhoods[ $district ] ) ) {
				return $tr_neighborhoods[ $district ];
			}
		}

		return array();
	}

	/**
	 * Checks validity of the city plate number.
	 * 
	 * @param string $city_plate_number City plate number.
	 * 
	 * @return bool
	 */
	private static function check_city_plate_number( $city_plate_number ) {
		return $city_plate_number &&
		is_string( $city_plate_number ) &&
		4 === strlen( $city_plate_number ) &&
		'TR' === substr( $city_plate_number, 0, 2 ) &&
		is_numeric( substr( $city_plate_number, 2, 2 ) );
	}
}
