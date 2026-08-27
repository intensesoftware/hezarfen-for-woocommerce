<?php
/**
 * Contains the Return_Reasons registry.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

defined( 'ABSPATH' ) || exit();

/**
 * Collects the reasons offered by every registered provider.
 *
 * Consumers ask this registry; they never instantiate a provider. Adding a
 * new source of reasons therefore means adding a provider, not editing the
 * form, the validator or the admin screen.
 */
class Return_Reasons {

	/**
	 * Memoized merged reason list.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private $reasons = null;

	/**
	 * The merged reason list, ordered by provider priority.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_reasons() {
		if ( null !== $this->reasons ) {
			return $this->reasons;
		}

		$providers = $this->get_providers();
		$reasons   = array();

		foreach ( $providers as $provider ) {
			foreach ( $provider->get_reasons() as $key => $reason ) {
				$reasons[ $key ] = wp_parse_args(
					$reason,
					array(
						'label'         => $key,
						'requires_note' => false,
					)
				);
			}
		}

		/**
		 * Filters the merged return reason list.
		 *
		 * @param array<string, array<string, mixed>> $reasons Reasons keyed by reason key.
		 */
		$this->reasons = apply_filters( 'hezarfen_returns_reasons', $reasons );

		return $this->reasons;
	}

	/**
	 * Reason keys and labels, ready for a <select>.
	 *
	 * @return array<string, string>
	 */
	public function get_choices() {
		$choices = array();

		foreach ( $this->get_reasons() as $key => $reason ) {
			// A hidden reason stays registered but leaves the customer's
			// list. A reason cannot simply be dropped: the request stores
			// only the key and the label is resolved live, so removing it
			// would make every past request show the raw key instead of its
			// wording. This is how an add-on replaces the preset list
			// without rewriting history.
			if ( ! empty( $reason['is_hidden'] ) ) {
				continue;
			}

			$choices[ $key ] = $reason['label'];
		}

		return $choices;
	}

	/**
	 * Whether a reason key is registered.
	 *
	 * @param string $key Reason key.
	 *
	 * @return bool
	 */
	public function exists( $key ) {
		return array_key_exists( $key, $this->get_reasons() );
	}

	/**
	 * Customer facing label of a reason.
	 *
	 * @param string $key Reason key.
	 *
	 * @return string
	 */
	public function get_label( $key ) {
		$reasons = $this->get_reasons();

		return isset( $reasons[ $key ]['label'] ) ? $reasons[ $key ]['label'] : $key;
	}

	/**
	 * Whether the reason demands a free text explanation.
	 *
	 * @param string $key Reason key.
	 *
	 * @return bool
	 */
	public function requires_note( $key ) {
		$reasons = $this->get_reasons();

		return ! empty( $reasons[ $key ]['requires_note'] );
	}

	/**
	 * Keys of the reasons that demand an explanation, for the front-end
	 * script that reveals the note field.
	 *
	 * @return string[]
	 */
	public function get_keys_requiring_note() {
		$keys = array();

		foreach ( $this->get_reasons() as $key => $reason ) {
			if ( ! empty( $reason['requires_note'] ) ) {
				$keys[] = $key;
			}
		}

		return $keys;
	}

	/**
	 * The registered providers, sorted by priority.
	 *
	 * @return Return_Reason_Provider_Interface[]
	 */
	private function get_providers() {
		/**
		 * Filters the registered return reason providers.
		 *
		 * @param Return_Reason_Provider_Interface[] $providers Providers.
		 */
		$providers = apply_filters( 'hezarfen_returns_reason_providers', array( new Default_Reason_Provider() ) );

		$providers = array_filter(
			(array) $providers,
			function ( $provider ) {
				return $provider instanceof Return_Reason_Provider_Interface;
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

		return $providers;
	}
}
