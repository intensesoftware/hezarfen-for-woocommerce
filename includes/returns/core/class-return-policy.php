<?php
/**
 * Contains the Return_Policy value object.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

defined( 'ABSPATH' ) || exit();

/**
 * The rule that decides whether one order line may still be returned.
 *
 * Immutable on purpose: a resolver hands out policies, callers only read
 * them. This module resolves the same store-wide policy for every line; an
 * add-on can resolve a different one per product or category without any
 * consumer changing.
 */
class Return_Policy {

	/**
	 * Whether the line may be returned at all.
	 *
	 * @var bool
	 */
	protected $returnable;

	/**
	 * Length of the return window in days. Zero means "no time limit".
	 *
	 * @var int
	 */
	protected $window_days;

	/**
	 * Why the line cannot be returned, shown to the customer.
	 *
	 * @var string
	 */
	protected $reason;

	/**
	 * Identifier of the policy source, for debugging and add-ons.
	 *
	 * @var string
	 */
	protected $source;

	/**
	 * Constructor.
	 *
	 * @param bool   $returnable  Whether returns are allowed.
	 * @param int    $window_days Window length in days, zero for unlimited.
	 * @param string $reason      Customer facing explanation when blocked.
	 * @param string $source      Policy source identifier.
	 */
	public function __construct( $returnable, $window_days = 0, $reason = '', $source = 'global' ) {
		$this->returnable  = (bool) $returnable;
		$this->window_days = max( 0, (int) $window_days );
		$this->reason      = (string) $reason;
		$this->source      = (string) $source;
	}

	/**
	 * Convenience constructor for a blocking policy.
	 *
	 * @param string $reason Customer facing explanation.
	 * @param string $source Policy source identifier.
	 *
	 * @return Return_Policy
	 */
	public static function blocked( $reason, $source = 'global' ) {
		return new self( false, 0, $reason, $source );
	}

	/**
	 * Whether the line may be returned.
	 *
	 * @return bool
	 */
	public function is_returnable() {
		return $this->returnable;
	}

	/**
	 * Window length in days, zero for unlimited.
	 *
	 * @return int
	 */
	public function get_window_days() {
		return $this->window_days;
	}

	/**
	 * Customer facing explanation when blocked.
	 *
	 * @return string
	 */
	public function get_reason() {
		return $this->reason;
	}

	/**
	 * Policy source identifier.
	 *
	 * @return string
	 */
	public function get_source() {
		return $this->source;
	}

	/**
	 * The moment the window closes, relative to a reference timestamp.
	 *
	 * @param int $reference_timestamp Unix timestamp the window counts from.
	 *
	 * @return int Unix timestamp, or zero when the window never closes.
	 */
	public function get_deadline( $reference_timestamp ) {
		if ( ! $this->window_days || ! $reference_timestamp ) {
			return 0;
		}

		return (int) $reference_timestamp + ( $this->window_days * DAY_IN_SECONDS );
	}

	/**
	 * Whether the window is still open at the given moment.
	 *
	 * @param int $reference_timestamp Unix timestamp the window counts from.
	 * @param int $now                 Unix timestamp to compare against.
	 *
	 * @return bool
	 */
	public function is_within_window( $reference_timestamp, $now ) {
		$deadline = $this->get_deadline( $reference_timestamp );

		if ( ! $deadline ) {
			return true;
		}

		return $now <= $deadline;
	}
}
