<?php

use Hezarfen\Inc\Mahalle_Local;

define( 'ABSPATH', '' );

require dirname( __DIR__ ) . '/includes/class-mahalle-local.php';

/**
 * Returns district and neighborhood data.
 * 
 * @param string $requested_data_type Requested data type (district or neighborhood).
 * @param string $city_plate_number City plate number.
 * @param string $district District name for getting neighborhood data.
 * @param bool   $return_nbrhood_ids Return neighborhood ids.
 * 
 * @return array|null
 */
function get_data( $requested_data_type, $city_plate_number, $district = null, $return_nbrhood_ids = true ) {
	if ( 'district' === $requested_data_type ) {
		return Mahalle_Local::get_districts( $city_plate_number );
	} elseif ( 'neighborhood' === $requested_data_type && $district ) {
		return Mahalle_Local::get_neighborhoods( $city_plate_number, $district, $return_nbrhood_ids );
	}

	return null;
}

$requested_data_type = isset( $_GET['dataType'] ) ? $_GET['dataType'] : null;
$city_plate_number   = isset( $_GET['cityPlateNumber'] ) ? $_GET['cityPlateNumber'] : null;
$district            = isset( $_GET['district'] ) ? $_GET['district'] : null;
$return_nbrhood_ids  = isset( $_GET['return_nbrhood_ids'] ) ? filter_var( $_GET['return_nbrhood_ids'], FILTER_VALIDATE_BOOLEAN ) : true;

if ( $requested_data_type && $city_plate_number ) {
	echo json_encode( get_data( $requested_data_type, $city_plate_number, $district, $return_nbrhood_ids ) );
}
