<?php
/**
 * Contains the Return_Policy_Resolver.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

defined( 'ABSPATH' ) || exit();

/**
 * Picks the policy that governs an order line.
 *
 * Walks the registered providers in priority order and returns the first
 * opinion. Also owns the single decision of *which order date* the return
 * window counts from, so admin, front-end and e-mails never disagree.
 */
class Return_Policy_Resolver {

	/**
	 * Memoized providers.
	 *
	 * @var Return_Policy_Provider_Interface[]|null
	 */
	private $providers = null;

	/**
	 * Resolves the policy that applies to one order line.
	 *
	 * @param \WC_Order      $order Parent order.
	 * @param \WC_Order_Item $item  Order line.
	 *
	 * @return Return_Policy
	 */
	public function resolve( $order, $item ) {
		foreach ( $this->get_providers() as $provider ) {
			$policy = $provider->get_policy( $order, $item );

			if ( $policy instanceof Return_Policy ) {
				/**
				 * Filters the resolved policy of an order line.
				 *
				 * @param Return_Policy          $policy Resolved policy.
				 * @param \WC_Order              $order  Parent order.
				 * @param \WC_Order_Item      $item   Order line.
				 */
				return apply_filters( 'hezarfen_returns_resolved_policy', $policy, $order, $item );
			}
		}

		return new Return_Policy( true, Return_Settings::get_window_days(), '', 'fallback' );
	}

	/**
	 * The timestamp the return window of an order counts from.
	 *
	 * Falls back through completed → paid → created so an order that has
	 * no completion date still yields a usable reference.
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return int Unix timestamp, zero when no date is available.
	 */
	public function get_window_reference_timestamp( $order ) {
		$reference = Return_Settings::get_window_reference();

		$candidates = array(
			Return_Settings::REFERENCE_COMPLETED => 'get_date_completed',
			Return_Settings::REFERENCE_PAID      => 'get_date_paid',
			Return_Settings::REFERENCE_CREATED   => 'get_date_created',
		);

		$order_of_preference = array( $reference );

		foreach ( array_keys( $candidates ) as $key ) {
			if ( $key !== $reference ) {
				$order_of_preference[] = $key;
			}
		}

		foreach ( $order_of_preference as $key ) {
			$getter = $candidates[ $key ];
			$date   = $order->{$getter}();

			if ( $date ) {
				return (int) $date->getTimestamp();
			}
		}

		return 0;
	}

	/**
	 * The registered providers, sorted by priority.
	 *
	 * @return Return_Policy_Provider_Interface[]
	 */
	private function get_providers() {
		if ( null !== $this->providers ) {
			return $this->providers;
		}

		/**
		 * Filters the registered return policy providers.
		 *
		 * Add-ons that implement per-product or per-category return rules
		 * register here with a priority lower than 100.
		 *
		 * @param Return_Policy_Provider_Interface[] $providers Providers.
		 */
		$providers = apply_filters( 'hezarfen_returns_policy_providers', array( new Global_Return_Policy_Provider() ) );

		$providers = array_filter(
			(array) $providers,
			function ( $provider ) {
				return $provider instanceof Return_Policy_Provider_Interface;
			}
		);

		usort(
			$providers,
			function ( $a, $b ) {
				$left  = $a->get_priority();
				$right = $b->get_priority();

				if ( $left === $right ) {
					return 0;
				}

				return $left < $right ? -1 : 1;
			}
		);

		$this->providers = $providers;

		return $this->providers;
	}
}
