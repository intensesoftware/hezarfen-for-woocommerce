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
	const ADDONS_NOTICES_TRANSIENT = 'hezarfen_addons_notices';

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
	 * @param array $addons Addons. Example array: [['file' => 'some_dir/some_file.php', 'min_version' => '1.2.3']].
	 * 
	 * @return array
	 */
	public static function check_addons( $addons ) {
		$notices = get_transient( self::ADDONS_NOTICES_TRANSIENT );

		if ( is_array( $notices ) ) {
			return $notices;
		} else {
			$notices = array();

			foreach ( self::find_outdated( $addons ) as $outdated_addon ) {
				$notices[] = array(
					/* translators: %s plugin name */
					'message' => sprintf( __( '%s plugin has a new version available. Please update it.', 'hezarfen-for-woocommerce' ), $outdated_addon ),
					'type'    => 'warning',
				);
			}

			set_transient( self::ADDONS_NOTICES_TRANSIENT, $notices );

			return $notices;
		}
	}

	/**
	 * Finds outdated plugins
	 * 
	 * @param array $plugins Plugins to check. Example array: [['file' => 'some_dir/some_file.php', 'min_version' => '1.2.3']].
	 * 
	 * @return array
	 */
	public static function find_outdated( $plugins ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$outdated          = array();
		$installed_plugins = get_plugins();
		$active_plugins    = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );

		foreach ( $plugins as $plugin ) {
			if ( in_array( $plugin['file'], $active_plugins ) ) {
				$plugin_info = $installed_plugins[ $plugin['file'] ];
				if ( $plugin_info['Version'] && version_compare( $plugin_info['Version'], $plugin['min_version'], '<' ) ) {
					$outdated[] = $plugin_info['Name'];
				}
			}
		}

		return $outdated;
	}

	/**
	 * Empties the notices transient.
	 * 
	 * @return void
	 */
	public static function empty_notices_transient() {
		delete_transient( self::ADDONS_NOTICES_TRANSIENT );
	}
}
