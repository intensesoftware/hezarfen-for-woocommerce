<?php
/**
 * Contains the class responsible for getting city, district and neighborhood data.
 * 
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

/**
 * The class responsible for getting city, district and neighborhood data.
 */
class Mahalle_Local {
	/**
	 * Returns cities
	 * 
	 * @return array<string, string>
	 */
	public static function get_cities() {
		require dirname( __FILE__ ) . '/Data/mahalle/tr-cities.php';

		return $tr_cities;
	}

	/**
	 * Get districts by TR city plate number
	 *
	 * @param string $city_plate_number The city plate number.
	 *
	 * @return string[]
	 */
	public static function get_districts( $city_plate_number ) {
		if ( self::check_city_plate_number( $city_plate_number ) ) {
			require dirname( __FILE__ ) . '/Data/mahalle/tr-districts.php';

			if ( isset( $tr_districts[ $city_plate_number ] ) ) {
				return $tr_districts[ $city_plate_number ];
			}
		}

		return array();
	}

	/**
	 * Get neighborhoods by city and district
	 *
	 * @param string $city_plate_number The city plate number.
	 * @param string $district The district.
	 * @param bool   $return_ids Return neighborhood ids.
	 * 
	 * @return string[]|array<string, string>
	 */
	public static function get_neighborhoods( $city_plate_number, $district, $return_ids = true ) {
		if ( self::check_city_plate_number( $city_plate_number ) ) {
			require dirname( __FILE__ ) . "/Data/mahalle/tr-neighborhoods/tr-neighborhood-$city_plate_number.php";

			if ( isset( $tr_neighborhoods[ $district ] ) ) {
				if ( $return_ids ) {
					return $tr_neighborhoods[ $district ];
				} else {
					return array_values( $tr_neighborhoods[ $district ] );
				}
			}
		}

		return array();
	}

	/**
	 * Returns the ID of the given neighborhood.
	 * 
	 * @param string $city_plate_number The city plate number.
	 * @param string $district The district.
	 * @param string $neighborhood The neighborhood.
	 * 
	 * @return int|null
	 */
	public static function get_neighborhood_id( $city_plate_number, $district, $neighborhood ) {
		$neighborhoods = self::get_neighborhoods( $city_plate_number, $district );

		foreach ( $neighborhoods as $id => $_neighborhood ) {
			if ( $neighborhood === $_neighborhood ) {
				return intval( $id );
			}
		}

		return null;
	}

	/**
	 * Returns city name by plate number.
	 * 
	 * @param string $city_plate_number City plate number (e.g "TR34").
	 * 
	 * @return string
	 */
	public static function get_city_name_by_plate_num( $city_plate_number ) {
		if ( self::check_city_plate_number( $city_plate_number ) ) {
			$cities = self::get_cities();
			return isset( $cities[ $city_plate_number ] ) ? $cities[ $city_plate_number ] : '';
		}

		return '';
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
