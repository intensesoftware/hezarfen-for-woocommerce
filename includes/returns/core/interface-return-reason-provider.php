<?php
/**
 * Contains the Return_Reason_Provider_Interface contract.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

defined( 'ABSPATH' ) || exit();

/**
 * Supplies the reasons a customer can pick when returning a line.
 *
 * This module ships a single provider with a fixed preset list. Add-ons
 * register further providers through the `hezarfen_returns_reason_providers`
 * filter to offer merchant-defined reasons, without the module knowing they
 * exist.
 */
interface Return_Reason_Provider_Interface {

	/**
	 * The reasons this provider contributes.
	 *
	 * Each entry is keyed by a stable reason key and holds:
	 *  - label         : customer facing text.
	 *  - requires_note : whether a free text explanation is mandatory.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_reasons();

	/**
	 * Sort weight; lower runs first and wins on key collisions.
	 *
	 * @return int
	 */
	public function get_priority();
}
