<?php
/**
 * Contains the Return_Features capability gate.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

defined( 'ABSPATH' ) || exit();

/**
 * Which of the module's capabilities are switched on.
 *
 * The module is one codebase serving two products. Most of the free/Pro seam
 * is drawn with providers -- Pro supplies a better implementation of something
 * free already does. A capability is the other shape: free carries the whole
 * mechanism but never opens the door to it, and Pro opens the door.
 *
 * The mechanism stays here rather than moving to Pro because it reaches into
 * the state machine: asking a customer for more information writes an event,
 * resolves an actor and moves the request between statuses, all through
 * internals no add-on should be handed. So the gate sits on the ENTRY POINTS,
 * and what is behind them is unreachable without Pro.
 */
class Return_Features {

	/**
	 * Whether the merchant may ask a customer for more information.
	 *
	 * Note what this does NOT gate: the customer's answer. Only a merchant
	 * can park a request in `info-required`, so a store that never had Pro
	 * never renders the answer form anyway -- but a store that had it and
	 * lost it would otherwise leave people stuck halfway through a
	 * conversation, with a question on screen and no way to reply.
	 *
	 * @return bool
	 */
	public static function info_requests() {
		/**
		 * Filters whether information requests are available.
		 *
		 * @param bool $enabled Off in the free plugin; Hezarfen Pro turns it on.
		 */
		return (bool) apply_filters( 'hezarfen_returns_info_requests_enabled', false );
	}
}
