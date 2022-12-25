<?php
/**
 * Contains the Shipment_Data class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * The Shipment_Data class.
 */
class Shipment_Data {
	const DATA_SEPARATOR = '||';

	/**
	 * ID.
	 * 
	 * @var int
	 */
	public $id;

	/**
	 * Courier ID.
	 * 
	 * @var string
	 */
	public $courier_id;

	/**
	 * Courier title.
	 * 
	 * @var string
	 */
	public $courier_title;

	/**
	 * Tracking number.
	 * 
	 * @var string
	 */
	public $tracking_num;

	/**
	 * Tracking URL.
	 * 
	 * @var string
	 */
	public $tracking_url;

	/**
	 * Constructor
	 * 
	 * @param mixed[]|string $data Shipment data.
	 * 
	 * @return void
	 */
	public function __construct( $data = null ) {
		if ( ! $data ) {
			$data = array();
		}

		if ( is_string( $data ) ) {
			$data = explode( self::DATA_SEPARATOR, $data );
		}

		$id = (int) ( $data[0] ?? 1 );

		$this->id            = $id > 0 ? $id : 1;
		$this->courier_id    = $data[1] ?? '';
		$this->courier_title = $data[2] ?? '';
		$this->tracking_num  = $data[3] ?? '';
		$this->tracking_url  = $data[4] ?? '';
	}

	/**
	 * Prepares the shipment data for storing in the database.
	 * 
	 * @return string
	 */
	public function prapare_for_db() {
		return implode( self::DATA_SEPARATOR, get_object_vars( $this ) );
	}
}
