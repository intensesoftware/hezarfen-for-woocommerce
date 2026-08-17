<?php
/**
 * Contains the Return_Request entity.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

defined( 'ABSPATH' ) || exit();

/**
 * A customer's return request for one order.
 *
 * Holds state only. Persistence lives in Return_Repository, business rules
 * live in Return_Service — this object never touches the database and
 * never renders anything.
 */
class Return_Request {

	use Hydrates_Props;

	/**
	 * Row ID. Zero when not persisted yet.
	 *
	 * @var int
	 */
	protected $id = 0;

	/**
	 * Human readable reference, e.g. "IADE-1042-1".
	 *
	 * @var string
	 */
	protected $return_number = '';

	/**
	 * WooCommerce order ID.
	 *
	 * @var int
	 */
	protected $order_id = 0;

	/**
	 * Customer user ID, zero for guest requests.
	 *
	 * @var int
	 */
	protected $customer_id = 0;

	/**
	 * Billing email captured at request time.
	 *
	 * @var string
	 */
	protected $customer_email = '';

	/**
	 * Current status key.
	 *
	 * @var string
	 */
	protected $status = Return_Status::PENDING;

	/**
	 * How the customer gets the goods back to the merchant.
	 *
	 * @var string
	 */
	protected $shipping_method = '';

	/**
	 * Carrier the parcel was handed to.
	 *
	 * @var string
	 */
	protected $courier = '';

	/**
	 * Tracking number of the return parcel.
	 *
	 * @var string
	 */
	protected $tracking_number = '';

	/**
	 * Identifier of the return address the parcel is headed to. This module
	 * ships everything to the single configured address; an add-on can point
	 * different requests at different warehouses.
	 *
	 * @var string
	 */
	protected $return_address_id = '';

	/**
	 * Optional note the customer added to the whole request.
	 *
	 * @var string
	 */
	protected $customer_note = '';

	/**
	 * Total value of the returned lines.
	 *
	 * @var float
	 */
	protected $refund_amount = 0.0;

	/**
	 * Order currency captured at request time.
	 *
	 * @var string
	 */
	protected $currency = '';

	/**
	 * Creation timestamp in MySQL format (site time).
	 *
	 * @var string
	 */
	protected $created_at = '';

	/**
	 * Last update timestamp in MySQL format (site time).
	 *
	 * @var string
	 */
	protected $updated_at = '';

	/**
	 * Lines included in this request.
	 *
	 * @var Return_Item[]
	 */
	protected $items = array();

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $data Initial values.
	 */
	public function __construct( $data = array() ) {
		$this->set_props( $data );
	}

	/**
	 * Mass-assigns known properties.
	 *
	 * @param array<string, mixed> $data Values keyed by property name.
	 *
	 * @return void
	 */
	public function set_props( $data ) {
		$this->id                = $this->int_prop( $data, 'id', $this->id );
		$this->order_id          = $this->int_prop( $data, 'order_id', $this->order_id );
		$this->customer_id       = $this->int_prop( $data, 'customer_id', $this->customer_id );
		$this->refund_amount     = $this->float_prop( $data, 'refund_amount', $this->refund_amount );
		$this->return_number     = $this->string_prop( $data, 'return_number', $this->return_number );
		$this->customer_email    = $this->string_prop( $data, 'customer_email', $this->customer_email );
		$this->status            = $this->string_prop( $data, 'status', $this->status );
		$this->shipping_method   = $this->string_prop( $data, 'shipping_method', $this->shipping_method );
		$this->courier           = $this->string_prop( $data, 'courier', $this->courier );
		$this->tracking_number   = $this->string_prop( $data, 'tracking_number', $this->tracking_number );
		$this->return_address_id = $this->string_prop( $data, 'return_address_id', $this->return_address_id );
		$this->customer_note     = $this->string_prop( $data, 'customer_note', $this->customer_note );
		$this->currency          = $this->string_prop( $data, 'currency', $this->currency );
		$this->created_at        = $this->string_prop( $data, 'created_at', $this->created_at );
		$this->updated_at        = $this->string_prop( $data, 'updated_at', $this->updated_at );
	}

	/**
	 * Row ID.
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Sets the row ID after an insert.
	 *
	 * @param int $id Row ID.
	 *
	 * @return void
	 */
	public function set_id( $id ) {
		$this->id = (int) $id;
	}

	/**
	 * Human readable reference.
	 *
	 * @return string
	 */
	public function get_return_number() {
		return $this->return_number;
	}

	/**
	 * Sets the human readable reference.
	 *
	 * @param string $return_number Reference.
	 *
	 * @return void
	 */
	public function set_return_number( $return_number ) {
		$this->return_number = (string) $return_number;
	}

	/**
	 * WooCommerce order ID.
	 *
	 * @return int
	 */
	public function get_order_id() {
		return $this->order_id;
	}

	/**
	 * Loads the related order.
	 *
	 * @return \WC_Order|false
	 */
	public function get_order() {
		return wc_get_order( $this->order_id );
	}

	/**
	 * Customer user ID.
	 *
	 * @return int
	 */
	public function get_customer_id() {
		return $this->customer_id;
	}

	/**
	 * Billing email.
	 *
	 * @return string
	 */
	public function get_customer_email() {
		return $this->customer_email;
	}

	/**
	 * Current status key.
	 *
	 * @return string
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * Sets the status without validating the transition. Callers must go
	 * through Return_Service::change_status() for the guarded path.
	 *
	 * @param string $status Status key.
	 *
	 * @return void
	 */
	public function set_status( $status ) {
		$this->status = (string) $status;
	}

	/**
	 * Shipping method key.
	 *
	 * @return string
	 */
	public function get_shipping_method() {
		return $this->shipping_method;
	}

	/**
	 * Sets the shipping method key.
	 *
	 * @param string $shipping_method Method key.
	 *
	 * @return void
	 */
	public function set_shipping_method( $shipping_method ) {
		$this->shipping_method = (string) $shipping_method;
	}

	/**
	 * Carrier name.
	 *
	 * @return string
	 */
	public function get_courier() {
		return $this->courier;
	}

	/**
	 * Sets the carrier name.
	 *
	 * @param string $courier Carrier name.
	 *
	 * @return void
	 */
	public function set_courier( $courier ) {
		$this->courier = (string) $courier;
	}

	/**
	 * Tracking number.
	 *
	 * @return string
	 */
	public function get_tracking_number() {
		return $this->tracking_number;
	}

	/**
	 * Sets the tracking number.
	 *
	 * @param string $tracking_number Tracking number.
	 *
	 * @return void
	 */
	public function set_tracking_number( $tracking_number ) {
		$this->tracking_number = (string) $tracking_number;
	}

	/**
	 * Return address identifier.
	 *
	 * @return string
	 */
	public function get_return_address_id() {
		return $this->return_address_id;
	}

	/**
	 * Sets the return address identifier.
	 *
	 * @param string $return_address_id Address identifier.
	 *
	 * @return void
	 */
	public function set_return_address_id( $return_address_id ) {
		$this->return_address_id = (string) $return_address_id;
	}

	/**
	 * Customer note.
	 *
	 * @return string
	 */
	public function get_customer_note() {
		return $this->customer_note;
	}

	/**
	 * Total value of the returned lines.
	 *
	 * @return float
	 */
	public function get_refund_amount() {
		return $this->refund_amount;
	}

	/**
	 * Sets the total value of the returned lines.
	 *
	 * @param float $refund_amount Amount.
	 *
	 * @return void
	 */
	public function set_refund_amount( $refund_amount ) {
		$this->refund_amount = (float) $refund_amount;
	}

	/**
	 * Order currency.
	 *
	 * @return string
	 */
	public function get_currency() {
		return $this->currency;
	}

	/**
	 * Creation timestamp in MySQL format.
	 *
	 * @return string
	 */
	public function get_created_at() {
		return $this->created_at;
	}

	/**
	 * Last update timestamp in MySQL format.
	 *
	 * @return string
	 */
	public function get_updated_at() {
		return $this->updated_at;
	}

	/**
	 * Lines included in this request.
	 *
	 * @return Return_Item[]
	 */
	public function get_items() {
		return $this->items;
	}

	/**
	 * Replaces the lines of this request.
	 *
	 * @param Return_Item[] $items Lines.
	 *
	 * @return void
	 */
	public function set_items( $items ) {
		$this->items = array();

		foreach ( $items as $item ) {
			if ( $item instanceof Return_Item ) {
				$this->items[] = $item;
			}
		}
	}

	/**
	 * Total quantity across all lines.
	 *
	 * @return int
	 */
	public function get_total_quantity() {
		$total = 0;

		foreach ( $this->items as $item ) {
			$total += $item->get_quantity();
		}

		return $total;
	}

	/**
	 * Whether the customer may still cancel the request themselves.
	 *
	 * @return bool
	 */
	public function is_cancellable_by_customer() {
		$cancellable = in_array(
			$this->status,
			array( Return_Status::PENDING, Return_Status::INFO_REQUIRED ),
			true
		);

		/**
		 * Filters whether the customer can cancel their own request.
		 *
		 * @param bool           $cancellable Whether cancelling is allowed.
		 * @param Return_Request $request     The request.
		 */
		return (bool) apply_filters( 'hezarfen_returns_is_cancellable_by_customer', $cancellable, $this );
	}

	/**
	 * Serializes the entity into a DB row shape. The ID and timestamps are
	 * managed by the repository and therefore excluded.
	 *
	 * @return array<string, mixed>
	 */
	public function to_row() {
		return array(
			'return_number'     => $this->return_number,
			'order_id'          => $this->order_id,
			'customer_id'       => $this->customer_id,
			'customer_email'    => $this->customer_email,
			'status'            => $this->status,
			'shipping_method'   => $this->shipping_method,
			'courier'           => $this->courier,
			'tracking_number'   => $this->tracking_number,
			'return_address_id' => $this->return_address_id,
			'customer_note'     => $this->customer_note,
			'refund_amount'     => $this->refund_amount,
			'currency'          => $this->currency,
		);
	}
}
