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
	 * @param string[]|array<string, string> $arr array of the districts.
	 * @return array<string, string>
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
	 * @param array<array<string, string>> $notices Notices.
	 * @param bool                         $use_kses Use wp_kses_post for escaping.
	 * 
	 * @return void
	 */
	public static function render_admin_notices( $notices, $use_kses = false ) {
		foreach ( $notices as $notice ) {
			$class = 'error' === $notice['type'] ? 'notice-error' : 'notice-warning';
			$msg   = $use_kses ? wp_kses_post( $notice['message'] ) : esc_html( $notice['message'] );
			printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $class ), $msg ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Checks installed Hezarfen addons' versions. Returns notices if there are outdated addons.
	 * 
	 * @param array<array<string, mixed>> $addons Addons data to check.
	 * 
	 * @return array<array<string, string>>
	 */
	public static function check_addons( $addons ) {
		$notices = array();

		foreach ( self::find_outdated( $addons ) as $outdated_addon ) {
			$notices[] = array(
				'addon_short_name' => $outdated_addon['short_name'],
				/* translators: %s plugin name */
				'message'          => sprintf( __( '%s plugin has a new version available. In order to use the plugin, you must update it.', 'hezarfen-for-woocommerce' ), $outdated_addon['name'] ),
				'type'             => 'error',
			);
		}

		return $notices;
	}

	/**
	 * Finds outdated plugins
	 * 
	 * @param array<array<string, mixed>> $plugins Plugins data to check.
	 * 
	 * @return array<array<string, string>>
	 */
	public static function find_outdated( $plugins ) {
		$outdated = array();

		foreach ( $plugins as $plugin ) {
			if ( $plugin['activated']() ) {
				$version = $plugin['version']();
				if ( $version && version_compare( $version, $plugin['min_version'], '<' ) ) {
					$outdated[] = array(
						'name'       => $plugin['name'],
						'short_name' => isset( $plugin['short_name'] ) ? $plugin['short_name'] : '',
					);
				}
			}
		}

		return $outdated;
	}

	/**
	 * Checks if plugin is active.
	 * 
	 * @param string $plugin Plugin.
	 * 
	 * @return bool
	 */
	public static function is_plugin_active( $plugin ) {
		if ( in_array( $plugin, apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			return true;
		}
	
		return false;
	}
}
