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
	 * Is SMS sent for this data?
	 * 
	 * @var bool
	 */
	public $sms_sent;

	/**
	 * Raw string data.
	 * 
	 * @var string
	 */
	public $raw_data;

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
			$this->raw_data = $data;
			$data           = explode( self::DATA_SEPARATOR, $data );
		}

		$id = (int) ( $data[0] ?? 1 );

		$this->id            = $id > 0 ? $id : 1;
		$this->courier_id    = $data[1] ?? '';
		$this->courier_title = $data[2] ?? '';
		$this->tracking_num  = $data[3] ?? '';
		$this->tracking_url  = $data[4] ?? '';
		$this->sms_sent      = isset( $data[5] ) ? boolval( $data[5] ) : false;
	}

	/**
	 * Prepares the shipment data for storing in the database.
	 * 
	 * @return string
	 */
	public function prapare_for_db() {
		return implode( self::DATA_SEPARATOR, array( $this->id, $this->courier_id, $this->courier_title, $this->tracking_num, $this->tracking_url, intval( $this->sms_sent ) ) );
	}

	/**
	 * Checks equality with an other Shipment_Data object.
	 * 
	 * @param Shipment_Data $other_data Other Shipment_Data object.
	 * 
	 * @return bool
	 */
	public function is_equal( $other_data ) {
		return $this->courier_id === $other_data->courier_id && $this->tracking_num === $other_data->tracking_num;
	}
}
