<?php
/**
 * Contains the Return_Shipping_Registry.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Shipping;

use Hezarfen\Inc\Returns\Core\Return_Settings;

defined( 'ABSPATH' ) || exit();

/**
 * Knows every registered return shipping method.
 *
 * The approval flow asks this registry for the store's active method, so
 * adding a carrier means registering a method — not editing the service.
 */
class Return_Shipping_Registry {

	/**
	 * Memoized methods keyed by their identifier.
	 *
	 * @var Return_Shipping_Method_Interface[]|null
	 */
	private $methods = null;

	/**
	 * Every registered method, including unavailable ones.
	 *
	 * @return Return_Shipping_Method_Interface[] Keyed by method key.
	 */
	public function get_methods() {
		if ( null !== $this->methods ) {
			return $this->methods;
		}

		/**
		 * Filters the registered return shipping methods.
		 *
		 * @param Return_Shipping_Method_Interface[] $methods Method instances.
		 */
		$methods = apply_filters(
			'hezarfen_returns_shipping_methods',
			array(
				new Customer_Ships_Method(),
				new Kargokit_Return_Method(),
			)
		);

		$this->methods = array();

		foreach ( (array) $methods as $method ) {
			if ( $method instanceof Return_Shipping_Method_Interface ) {
				$this->methods[ $method->get_key() ] = $method;
			}
		}

		return $this->methods;
	}

	/**
	 * Only the methods that are configured well enough to be picked.
	 *
	 * @return Return_Shipping_Method_Interface[] Keyed by method key.
	 */
	public function get_available_methods() {
		return array_filter(
			$this->get_methods(),
			function ( $method ) {
				return $method->is_available();
			}
		);
	}

	/**
	 * Method keys and labels, ready for a settings <select>.
	 *
	 * @return array<string, string>
	 */
	public function get_choices() {
		$choices = array();

		foreach ( $this->get_available_methods() as $key => $method ) {
			$choices[ $key ] = $method->get_label();
		}

		return $choices;
	}

	/**
	 * One method by key.
	 *
	 * @param string $key Method key.
	 *
	 * @return Return_Shipping_Method_Interface|null
	 */
	public function get( $key ) {
		$methods = $this->get_methods();

		return isset( $methods[ $key ] ) ? $methods[ $key ] : null;
	}

	/**
	 * The method the store currently uses, falling back to the always
	 * available "customer ships it" flow when the configured one went
	 * missing or lost its credentials.
	 *
	 * @return Return_Shipping_Method_Interface
	 */
	public function get_active_method() {
		$method = $this->get( Return_Settings::get_shipping_method_key() );

		if ( $method && $method->is_available() ) {
			return $method;
		}

		return $this->get_fallback_method();
	}

	/**
	 * The method to fall back on when an automated one is missing or fails
	 * mid-flight: the customer ships the parcel themselves, which needs
	 * nothing but a return address.
	 *
	 * @return Return_Shipping_Method_Interface
	 */
	public function get_fallback_method() {
		$fallback = $this->get( Customer_Ships_Method::KEY );

		return $fallback ? $fallback : new Customer_Ships_Method();
	}

	/**
	 * The method a stored request was created with, falling back to the
	 * store's active one.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request The request.
	 *
	 * @return Return_Shipping_Method_Interface
	 */
	public function get_for_request( $request ) {
		$method = $this->get( $request->get_shipping_method() );

		return $method ? $method : $this->get_active_method();
	}
}
