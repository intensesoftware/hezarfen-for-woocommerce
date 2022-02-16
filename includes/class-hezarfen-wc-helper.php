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
	public static function checkout_select2_option_format( $arr ) {
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
	 * @param array $addons Addons data to check.
	 * 
	 * @return array
	 */
	public static function check_addons( $addons ) {
		$notices = array();

		foreach ( self::find_outdated( $addons ) as $outdated_addon ) {
			$notices[] = array(
				/* translators: %s plugin name */
				'message' => sprintf( __( '%s plugin has a new version available. Please update it.', 'hezarfen-for-woocommerce' ), $outdated_addon ),
				'type'    => 'warning',
			);
		}

		return $notices;
	}

	/**
	 * Finds outdated plugins
	 * 
	 * @param array $plugins Plugins data to check.
	 * 
	 * @return array
	 */
	public static function find_outdated( $plugins ) {
		$outdated = array();

		foreach ( $plugins as $plugin ) {
			if ( $plugin['activated']() ) {
				$version = $plugin['version']();
				if ( $version && version_compare( $version, $plugin['min_version'], '<' ) ) {
					$outdated[] = $plugin['name'];
				}
			}
		}

		return $outdated;
	}
}
