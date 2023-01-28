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
class Shipment_Data implements \JsonSerializable {
	const DATA_SEPARATOR = '||';

	/**
	 * ID.
	 * 
	 * @var int
	 */
	public $id;

	/**
	 * The order ID that this object belongs to.
	 * 
	 * @var int
	 */
	public $order_id;

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
		$this->order_id      = (int) ( $data[1] ?? 0 );
		$this->courier_id    = $data[2] ?? '';
		$this->courier_title = $data[3] ?? '';
		$this->tracking_num  = $data[4] ?? '';
		$this->tracking_url  = $data[5] ?? '';
		$this->sms_sent      = isset( $data[6] ) ? boolval( $data[6] ) : false;
	}

	/**
	 * Saves this shipment data object to db as a postmeta.
	 * 
	 * @param bool $add_new Add a new post meta?.
	 * 
	 * @return bool
	 */
	public function save( $add_new = false ) {
		if ( ! $this->order_id ) {
			return false;
		}

		if ( $add_new ) {
			return add_post_meta( $this->order_id, Manual_Shipment_Tracking::SHIPMENT_DATA_KEY, $this->prapare_for_db() );
		}

		if ( $this->raw_data ) {
			return update_post_meta( $this->order_id, Manual_Shipment_Tracking::SHIPMENT_DATA_KEY, $this->prapare_for_db(), $this->raw_data );
		}

		return false;
	}

	/**
	 * Removes this shipment data from the db.
	 * 
	 * @return bool
	 */
	public function remove() {
		if ( ! $this->order_id || ! $this->raw_data ) {
			return false;
		}

		return delete_post_meta( $this->order_id, Manual_Shipment_Tracking::SHIPMENT_DATA_KEY, $this->raw_data );
	}

	/**
	 * Prepares the shipment data for storing in the database.
	 * 
	 * @return string
	 */
	public function prapare_for_db() {
		return implode( self::DATA_SEPARATOR, array( $this->id, $this->order_id, $this->courier_id, $this->courier_title, $this->tracking_num, $this->tracking_url, intval( $this->sms_sent ) ) );
	}

	/**
	 * Returns data which should be serialized to JSON.
	 * 
	 * It hides sensitive and unnecessary data in ajax responses, and escapes the data.
	 * 
	 * @return array<string, string>
	 */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return array(
			'courier_title' => esc_html( $this->courier_title ),
			'tracking_num'  => esc_html( $this->tracking_num ),
			'tracking_url'  => esc_url( $this->tracking_url ),
		);
	}
}
