<?php
/**
 * Contains the Hydrates_Props trait.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

defined( 'ABSPATH' ) || exit();

/**
 * Typed reads out of a loosely typed input array.
 *
 * Entities are built both from `$wpdb` rows — where every column arrives
 * as a string — and from sanitised request input. These helpers keep the
 * casting in one place and let each entity list its properties explicitly
 * instead of assigning them through a variable property name, which no
 * reader (human or static analyser) can follow.
 */
trait Hydrates_Props {

	/**
	 * Integer value of a key, or the current value when absent.
	 *
	 * @param array<string, mixed> $data    Input data.
	 * @param string               $key     Key to read.
	 * @param int                  $current Value to keep when the key is absent.
	 *
	 * @return int
	 */
	protected function int_prop( $data, $key, $current ) {
		return array_key_exists( $key, $data ) ? (int) $data[ $key ] : $current;
	}

	/**
	 * Float value of a key, or the current value when absent.
	 *
	 * @param array<string, mixed> $data    Input data.
	 * @param string               $key     Key to read.
	 * @param float                $current Value to keep when the key is absent.
	 *
	 * @return float
	 */
	protected function float_prop( $data, $key, $current ) {
		return array_key_exists( $key, $data ) ? (float) $data[ $key ] : $current;
	}

	/**
	 * String value of a key, or the current value when absent.
	 *
	 * @param array<string, mixed> $data    Input data.
	 * @param string               $key     Key to read.
	 * @param string               $current Value to keep when the key is absent.
	 *
	 * @return string
	 */
	protected function string_prop( $data, $key, $current ) {
		return array_key_exists( $key, $data ) ? (string) $data[ $key ] : $current;
	}

	/**
	 * Boolean value of a key, or the current value when absent.
	 *
	 * @param array<string, mixed> $data    Input data.
	 * @param string               $key     Key to read.
	 * @param bool                 $current Value to keep when the key is absent.
	 *
	 * @return bool
	 */
	protected function bool_prop( $data, $key, $current ) {
		return array_key_exists( $key, $data ) ? (bool) $data[ $key ] : $current;
	}
}
