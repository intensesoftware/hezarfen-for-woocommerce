<?php
/**
 * Contains the Return_Event entity.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

defined( 'ABSPATH' ) || exit();

/**
 * One entry of a return request's timeline.
 *
 * Timeline entries are append-only. Status changes, notes and the "extra
 * info" exchange are all built on top of them; an add-on can reuse the same
 * table for threaded messaging by registering new types.
 */
class Return_Event {

	use Hydrates_Props;

	const TYPE_CREATED       = 'created';
	const TYPE_STATUS_CHANGE = 'status-change';
	const TYPE_NOTE          = 'note';
	const TYPE_INFO_REQUEST  = 'info-request';
	const TYPE_INFO_RESPONSE = 'info-response';
	const TYPE_SHIPPING      = 'shipping';

	const ACTOR_CUSTOMER = 'customer';
	const ACTOR_ADMIN    = 'admin';
	const ACTOR_SYSTEM   = 'system';

	/**
	 * Row ID.
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
	 * Event type, one of the TYPE_* constants.
	 *
	 * @var string
	 */
	protected $type = self::TYPE_NOTE;

	/**
	 * Who caused the event, one of the ACTOR_* constants.
	 *
	 * @var string
	 */
	protected $actor_type = self::ACTOR_SYSTEM;

	/**
	 * User ID of the actor, zero for guests and system events.
	 *
	 * @var int
	 */
	protected $actor_id = 0;

	/**
	 * Display name of the actor captured at event time.
	 *
	 * @var string
	 */
	protected $actor_name = '';

	/**
	 * Previous status for status change events.
	 *
	 * @var string
	 */
	protected $from_status = '';

	/**
	 * New status for status change events.
	 *
	 * @var string
	 */
	protected $to_status = '';

	/**
	 * Human readable message.
	 *
	 * @var string
	 */
	protected $message = '';

	/**
	 * Whether the entry is visible to the customer.
	 *
	 * @var bool
	 */
	protected $is_customer_visible = true;

	/**
	 * Creation timestamp in MySQL format (site time).
	 *
	 * @var string
	 */
	protected $created_at = '';

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
		$this->id                  = $this->int_prop( $data, 'id', $this->id );
		$this->return_id           = $this->int_prop( $data, 'return_id', $this->return_id );
		$this->actor_id            = $this->int_prop( $data, 'actor_id', $this->actor_id );
		$this->is_customer_visible = $this->bool_prop( $data, 'is_customer_visible', $this->is_customer_visible );
		$this->type                = $this->string_prop( $data, 'type', $this->type );
		$this->actor_type          = $this->string_prop( $data, 'actor_type', $this->actor_type );
		$this->actor_name          = $this->string_prop( $data, 'actor_name', $this->actor_name );
		$this->from_status         = $this->string_prop( $data, 'from_status', $this->from_status );
		$this->to_status           = $this->string_prop( $data, 'to_status', $this->to_status );
		$this->message             = $this->string_prop( $data, 'message', $this->message );
		$this->created_at          = $this->string_prop( $data, 'created_at', $this->created_at );
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
	 * Event type.
	 *
	 * @return string
	 */
	public function get_type() {
		return $this->type;
	}

	/**
	 * Actor type.
	 *
	 * @return string
	 */
	public function get_actor_type() {
		return $this->actor_type;
	}

	/**
	 * Actor user ID.
	 *
	 * @return int
	 */
	public function get_actor_id() {
		return $this->actor_id;
	}

	/**
	 * Actor display name.
	 *
	 * @return string
	 */
	public function get_actor_name() {
		return $this->actor_name;
	}

	/**
	 * Previous status.
	 *
	 * @return string
	 */
	public function get_from_status() {
		return $this->from_status;
	}

	/**
	 * New status.
	 *
	 * @return string
	 */
	public function get_to_status() {
		return $this->to_status;
	}

	/**
	 * Human readable message.
	 *
	 * @return string
	 */
	public function get_message() {
		return $this->message;
	}

	/**
	 * Whether the customer may see this entry.
	 *
	 * @return bool
	 */
	public function is_customer_visible() {
		return $this->is_customer_visible;
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
	 * Serializes the entity into a DB row shape.
	 *
	 * @return array<string, mixed>
	 */
	public function to_row() {
		return array(
			'return_id'           => $this->return_id,
			'type'                => $this->type,
			'actor_type'          => $this->actor_type,
			'actor_id'            => $this->actor_id,
			'actor_name'          => $this->actor_name,
			'from_status'         => $this->from_status,
			'to_status'           => $this->to_status,
			'message'             => $this->message,
			'is_customer_visible' => $this->is_customer_visible ? 1 : 0,
			'created_at'          => $this->created_at ? $this->created_at : current_time( 'mysql' ),
		);
	}
}
