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

			try {
				$data = array_combine(
					array( 'id', 'order_id', 'courier_id', 'courier_title', 'tracking_num', 'tracking_url', 'sms_sent' ),
					explode( self::DATA_SEPARATOR, $data )
				);
			} catch ( \Throwable $e ) {
				$data = array();
			}
		}

		$id = (int) ( $data['id'] ?? 1 );

		$this->id            = $id > 0 ? $id : 1;
		$this->order_id      = (int) ( $data['order_id'] ?? 0 );
		$this->courier_id    = $data['courier_id'] ?? '';
		$this->courier_title = $data['courier_title'] ?? '';
		$this->tracking_num  = $data['tracking_num'] ?? '';
		$this->tracking_url  = $data['tracking_url'] ?? '';
		$this->sms_sent      = isset( $data['sms_sent'] ) ? boolval( $data['sms_sent'] ) : false;
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

		$order = wc_get_order( $this->order_id );

		if( ! $order ) {
			return false;
		}

		if ( $add_new ) {
			$saved = $order->add_meta_data( Manual_Shipment_Tracking::SHIPMENT_DATA_KEY, $this->prapare_for_db() );
			$saved = $order->save();

			if ( $saved ) {
				Helper::update_order_shipment_last_index( $this->order_id );
			}

			return $saved;
		}

		if ( $this->raw_data ) {
			$current_data = $order->get_meta( Manual_Shipment_Tracking::SHIPMENT_DATA_KEY, false );

			if ( ! in_array($this->raw_data, $current_data) ) {
				$order->add_meta_data( Manual_Shipment_Tracking::SHIPMENT_DATA_KEY, $this->prapare_for_db(), false );
				$order->save();
				$updated = true;
			} else {
				$updated = false;
			}

			if ( $updated ) {
				Helper::update_order_shipment_last_index( $this->order_id );
			}

			return $updated;
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
