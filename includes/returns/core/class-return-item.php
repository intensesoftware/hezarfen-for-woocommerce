<?php
/**
 * Contains the Return_Item entity.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

defined( 'ABSPATH' ) || exit();

/**
 * A single order line included in a return request.
 *
 * Plain data object: no persistence, no rendering. The repository builds
 * these from DB rows and the service layer builds them from request input.
 */
class Return_Item {

	use Hydrates_Props;

	/**
	 * Row ID. Zero when not persisted yet.
	 *
	 * @var int
	 */
	protected $id = 0;

	/**
	 * Parent return request ID.
	 *
	 * @var int
	 */
	protected $return_id = 0;

	/**
	 * WooCommerce order item ID.
	 *
	 * @var int
	 */
	protected $order_item_id = 0;

	/**
	 * Product ID.
	 *
	 * @var int
	 */
	protected $product_id = 0;

	/**
	 * Variation ID, zero for simple products.
	 *
	 * @var int
	 */
	protected $variation_id = 0;

	/**
	 * Product name captured at request time.
	 *
	 * @var string
	 */
	protected $product_name = '';

	/**
	 * SKU captured at request time.
	 *
	 * @var string
	 */
	protected $sku = '';

	/**
	 * Returned quantity.
	 *
	 * @var int
	 */
	protected $quantity = 0;

	/**
	 * Monetary value of the returned quantity.
	 *
	 * @var float
	 */
	protected $line_total = 0.0;

	/**
	 * Reason key as registered by a reason provider.
	 *
	 * @var string
	 */
	protected $reason_key = '';

	/**
	 * Free text explanation, required when the reason needs one.
	 *
	 * @var string
	 */
	protected $reason_note = '';

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
		$this->id            = $this->int_prop( $data, 'id', $this->id );
		$this->return_id     = $this->int_prop( $data, 'return_id', $this->return_id );
		$this->order_item_id = $this->int_prop( $data, 'order_item_id', $this->order_item_id );
		$this->product_id    = $this->int_prop( $data, 'product_id', $this->product_id );
		$this->variation_id  = $this->int_prop( $data, 'variation_id', $this->variation_id );
		$this->quantity      = $this->int_prop( $data, 'quantity', $this->quantity );
		$this->line_total    = $this->float_prop( $data, 'line_total', $this->line_total );
		$this->product_name  = $this->string_prop( $data, 'product_name', $this->product_name );
		$this->sku           = $this->string_prop( $data, 'sku', $this->sku );
		$this->reason_key    = $this->string_prop( $data, 'reason_key', $this->reason_key );
		$this->reason_note   = $this->string_prop( $data, 'reason_note', $this->reason_note );
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
	 * Parent return ID.
	 *
	 * @return int
	 */
	public function get_return_id() {
		return $this->return_id;
	}

	/**
	 * Sets the parent return ID.
	 *
	 * @param int $return_id Parent return ID.
	 *
	 * @return void
	 */
	public function set_return_id( $return_id ) {
		$this->return_id = (int) $return_id;
	}

	/**
	 * WooCommerce order item ID.
	 *
	 * @return int
	 */
	public function get_order_item_id() {
		return $this->order_item_id;
	}

	/**
	 * Product ID.
	 *
	 * @return int
	 */
	public function get_product_id() {
		return $this->product_id;
	}

	/**
	 * Variation ID.
	 *
	 * @return int
	 */
	public function get_variation_id() {
		return $this->variation_id;
	}

	/**
	 * Product name captured at request time.
	 *
	 * @return string
	 */
	public function get_product_name() {
		return $this->product_name;
	}

	/**
	 * SKU captured at request time.
	 *
	 * @return string
	 */
	public function get_sku() {
		return $this->sku;
	}

	/**
	 * Returned quantity.
	 *
	 * @return int
	 */
	public function get_quantity() {
		return $this->quantity;
	}

	/**
	 * Monetary value of the returned quantity.
	 *
	 * @return float
	 */
	public function get_line_total() {
		return $this->line_total;
	}

	/**
	 * Reason key.
	 *
	 * @return string
	 */
	public function get_reason_key() {
		return $this->reason_key;
	}

	/**
	 * Free text explanation.
	 *
	 * @return string
	 */
	public function get_reason_note() {
		return $this->reason_note;
	}

	/**
	 * Serializes the entity into a DB row shape.
	 *
	 * @return array<string, mixed>
	 */
	public function to_row() {
		return array(
			'return_id'     => $this->return_id,
			'order_item_id' => $this->order_item_id,
			'product_id'    => $this->product_id,
			'variation_id'  => $this->variation_id,
			'product_name'  => $this->product_name,
			'sku'           => $this->sku,
			'quantity'      => $this->quantity,
			'line_total'    => $this->line_total,
			'reason_key'    => $this->reason_key,
			'reason_note'   => $this->reason_note,
		);
	}
}
