<?php
/**
 * Helper class
 * 
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

/**
 * Helper class
 */
class Helper {
	/**
	 *
	 * Update array keys for select option values
	 *
	 * @param array $arr array of the districts.
	 * @return array
	 */
	public static function hezarfen_wc_checkout_select2_option_format( $arr ) {
		$values = array();

		foreach ( $arr as $key => $value ) {
			$values[ $value ] = $value;
		}

		return $values;
	}

	/**
	 * Displays admin notices.
	 * 
	 * @param array $notices Notices.
	 * 
	 * @return void
	 */
	public static function show_admin_notices( $notices ) {
		foreach ( $notices as $notice ) {
			$class = 'error' === $notice['type'] ? 'notice-error' : 'notice-warning';
			printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $class ), esc_html( $notice['message'] ) );
		}
	}

	/**
	 * Checks installed Hezarfen addons' versions. Returns notices if there are outdated addons.
	 * 
	 * @param array $addons Addons.
	 * 
	 * @return array
	 */
	public static function check_addons( $addons ) {
		$notices        = array();
		$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );

		foreach ( $addons as $addon ) {
			if ( in_array( $addon['file'], $active_plugins ) ) {
				if ( ! function_exists( 'get_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$addon_info = get_plugins()[ $addon['file'] ];

				if ( $addon_info['Version'] && version_compare( $addon_info['Version'], $addon['min_version'], '<' ) ) {
					$notices[] = array(
						/* translators: %s plugin name */
						'message' => sprintf( __( '%s plugin has a new version available. Please update it.', 'hezarfen-for-woocommerce' ), $addon_info['Name'] ),
						'type'    => 'warning',
					);
				}
			}
		}

		return $notices;
	}
}
