<?php
/**
 * Contains the Return_Status registry.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

defined( 'ABSPATH' ) || exit();

/**
 * Single source of truth for return request statuses.
 *
 * Statuses are data, not code: both the list and the allowed transitions
 * are filterable, so an add-on can register extra statuses
 * such as "exchange sent" or "store credit issued" without touching this
 * class. Everything else in the module asks this registry instead of
 * hard-coding status strings.
 */
class Return_Status {

	const PENDING       = 'pending';
	const INFO_REQUIRED = 'info-required';
	const APPROVED      = 'approved';
	const REJECTED      = 'rejected';
	const SHIPPED       = 'shipped';
	const RECEIVED      = 'received';
	const COMPLETED     = 'completed';
	const CANCELLED     = 'cancelled';

	/**
	 * Cached status definitions.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static $definitions = null;

	/**
	 * Returns every registered status definition.
	 *
	 * Each definition carries:
	 *  - label     : admin facing label.
	 *  - customer  : customer facing label.
	 *  - tone      : UI tone token (neutral|info|success|warning|danger).
	 *  - is_open   : whether the request still needs attention.
	 *  - is_final  : whether the request is closed for good.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_statuses() {
		if ( null !== self::$definitions ) {
			return self::$definitions;
		}

		$statuses = array(
			self::PENDING       => array(
				'label'    => __( 'Beklemede', 'hezarfen-for-woocommerce' ),
				'customer' => __( 'Talebiniz alındı', 'hezarfen-for-woocommerce' ),
				'tone'     => 'warning',
				'is_open'  => true,
				'is_final' => false,
			),
			self::INFO_REQUIRED => array(
				'label'    => __( 'Ek bilgi bekleniyor', 'hezarfen-for-woocommerce' ),
				'customer' => __( 'Sizden ek bilgi bekleniyor', 'hezarfen-for-woocommerce' ),
				'tone'     => 'warning',
				'is_open'  => true,
				'is_final' => false,
			),
			self::APPROVED      => array(
				'label'    => __( 'Onaylandı', 'hezarfen-for-woocommerce' ),
				'customer' => __( 'Talebiniz onaylandı', 'hezarfen-for-woocommerce' ),
				'tone'     => 'info',
				'is_open'  => true,
				'is_final' => false,
			),
			self::SHIPPED       => array(
				'label'    => __( 'Kargoya verildi', 'hezarfen-for-woocommerce' ),
				'customer' => __( 'Ürünler kargoya verildi', 'hezarfen-for-woocommerce' ),
				'tone'     => 'info',
				'is_open'  => true,
				'is_final' => false,
			),
			self::RECEIVED      => array(
				'label'    => __( 'Tarafımıza ulaştı', 'hezarfen-for-woocommerce' ),
				'customer' => __( 'Ürünler tarafımıza ulaştı', 'hezarfen-for-woocommerce' ),
				'tone'     => 'info',
				'is_open'  => true,
				'is_final' => false,
			),
			self::COMPLETED     => array(
				'label'    => __( 'Tamamlandı', 'hezarfen-for-woocommerce' ),
				'customer' => __( 'İadeniz tamamlandı', 'hezarfen-for-woocommerce' ),
				'tone'     => 'success',
				'is_open'  => false,
				'is_final' => true,
			),
			self::REJECTED      => array(
				'label'    => __( 'Reddedildi', 'hezarfen-for-woocommerce' ),
				'customer' => __( 'Talebiniz reddedildi', 'hezarfen-for-woocommerce' ),
				'tone'     => 'danger',
				'is_open'  => false,
				'is_final' => true,
			),
			self::CANCELLED     => array(
				'label'    => __( 'İptal edildi', 'hezarfen-for-woocommerce' ),
				'customer' => __( 'Talebiniz iptal edildi', 'hezarfen-for-woocommerce' ),
				'tone'     => 'neutral',
				'is_open'  => false,
				'is_final' => true,
			),
		);

		/**
		 * Filters the registered return statuses.
		 *
		 * @param array<string, array<string, mixed>> $statuses Status definitions.
		 */
		self::$definitions = apply_filters( 'hezarfen_returns_statuses', $statuses );

		return self::$definitions;
	}

	/**
	 * Whether the given status is registered.
	 *
	 * @param string $status Status key.
	 *
	 * @return bool
	 */
	public static function exists( $status ) {
		return array_key_exists( $status, self::get_statuses() );
	}

	/**
	 * Returns the admin facing label of a status.
	 *
	 * @param string $status Status key.
	 *
	 * @return string
	 */
	public static function get_label( $status ) {
		$statuses = self::get_statuses();

		return isset( $statuses[ $status ]['label'] ) ? $statuses[ $status ]['label'] : $status;
	}

	/**
	 * Returns the customer facing label of a status.
	 *
	 * @param string $status Status key.
	 *
	 * @return string
	 */
	public static function get_customer_label( $status ) {
		$statuses = self::get_statuses();

		if ( isset( $statuses[ $status ]['customer'] ) ) {
			return $statuses[ $status ]['customer'];
		}

		return self::get_label( $status );
	}

	/**
	 * Returns the UI tone token of a status.
	 *
	 * @param string $status Status key.
	 *
	 * @return string
	 */
	public static function get_tone( $status ) {
		$statuses = self::get_statuses();

		return isset( $statuses[ $status ]['tone'] ) ? $statuses[ $status ]['tone'] : 'neutral';
	}

	/**
	 * Status keys that still need attention.
	 *
	 * @return string[]
	 */
	public static function get_open_statuses() {
		$open = array();

		foreach ( self::get_statuses() as $key => $definition ) {
			if ( ! empty( $definition['is_open'] ) ) {
				$open[] = $key;
			}
		}

		return $open;
	}

	/**
	 * Allowed transitions, keyed by the current status.
	 *
	 * @return array<string, string[]>
	 */
	public static function get_transitions() {
		$transitions = array(
			self::PENDING       => array( self::APPROVED, self::REJECTED, self::INFO_REQUIRED, self::CANCELLED ),
			self::INFO_REQUIRED => array( self::PENDING, self::APPROVED, self::REJECTED, self::CANCELLED ),
			self::APPROVED      => array( self::SHIPPED, self::RECEIVED, self::COMPLETED, self::REJECTED, self::CANCELLED ),
			self::SHIPPED       => array( self::RECEIVED, self::COMPLETED, self::CANCELLED ),
			self::RECEIVED      => array( self::COMPLETED, self::REJECTED ),
			self::COMPLETED     => array(),
			self::REJECTED      => array(),
			self::CANCELLED     => array(),
		);

		/**
		 * Filters the allowed return status transitions.
		 *
		 * @param array<string, string[]> $transitions Allowed target statuses keyed by source status.
		 */
		return apply_filters( 'hezarfen_returns_status_transitions', $transitions );
	}

	/**
	 * Whether a transition between two statuses is allowed.
	 *
	 * @param string $from Current status.
	 * @param string $to   Target status.
	 *
	 * @return bool
	 */
	public static function can_transition( $from, $to ) {
		if ( $from === $to || ! self::exists( $to ) ) {
			return false;
		}

		$transitions = self::get_transitions();

		return isset( $transitions[ $from ] ) && in_array( $to, $transitions[ $from ], true );
	}

	/**
	 * Target statuses reachable from the given status.
	 *
	 * @param string $from Current status.
	 *
	 * @return string[]
	 */
	public static function get_allowed_transitions( $from ) {
		$transitions = self::get_transitions();

		return isset( $transitions[ $from ] ) ? $transitions[ $from ] : array();
	}

	/**
	 * The ordered milestones shown in the customer facing progress bar.
	 * Statuses outside this list (rejected, cancelled) render as a single
	 * closing state instead.
	 *
	 * @return string[]
	 */
	public static function get_progress_steps() {
		/**
		 * Filters the customer facing progress steps.
		 *
		 * @param string[] $steps Ordered status keys.
		 */
		return apply_filters(
			'hezarfen_returns_progress_steps',
			array( self::PENDING, self::APPROVED, self::SHIPPED, self::RECEIVED, self::COMPLETED )
		);
	}
}
