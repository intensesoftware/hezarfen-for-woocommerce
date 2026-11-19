<?php
/**
 * Contains the Return_Approval_Policy_Interface contract.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

defined( 'ABSPATH' ) || exit();

/**
 * Decides whether a new request is approved the moment it is created.
 *
 * Free has no policy of its own and approves every request. Hezarfen Pro
 * hands one in through `Returns_Module::set_approval_policy()` when the
 * merchant chose to review requests first.
 *
 * @internal Pro seam, not a public extension point.
 */
interface Return_Approval_Policy_Interface {

	/**
	 * Whether the request is approved on creation.
	 *
	 * @param Return_Request|null $request The new request, or null when
	 *                                     asking about the store in general
	 *                                     (e.g. to word the form).
	 *
	 * @return bool
	 */
	public function approves_automatically( $request = null );
}
