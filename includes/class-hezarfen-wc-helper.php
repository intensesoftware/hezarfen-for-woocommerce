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
	public static function select2_option_format( $arr ) {
		$values = array( '' => __( 'Select an option', 'hezarfen-for-woocommerce' ) );

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
	 * Hooks into the necessary filters to sort address fields.
	 * 
	 * @return void
	 */
	public static function sort_address_fields() {
		if ( apply_filters( 'hezarfen_skip_sort_address_fields', false ) ) {
			return;
		}

		add_filter( 'woocommerce_get_country_locale', array( __CLASS__, 'assign_priorities_to_locale_fields' ), PHP_INT_MAX - 1 );
		add_filter( 'woocommerce_billing_fields', array( __CLASS__, 'assign_priorities_to_non_locale_fields' ), PHP_INT_MAX - 1, 2 );
		if ( is_checkout() ) {
			add_filter( 'woocommerce_shipping_fields', array( __CLASS__, 'assign_priorities_to_non_locale_fields' ), PHP_INT_MAX - 1, 2 );
		}
	}

	/**
	 * Assigns priorities to the locale address fields.
	 * 
	 * @param array<string, array<string, array<string, mixed>>> $locales Locale data of all countries.
	 * 
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public static function assign_priorities_to_locale_fields( $locales ) {
		$locales['TR']['state']['priority']     = 50;
		$locales['TR']['city']['priority']      = 60;
		$locales['TR']['address_1']['priority'] = 70;

		$locales['TR']['address_2'] = array_merge(
			$locales['TR']['address_2'] ?? array(),
			array( 'priority' => 80 )
		);

		if ( self::is_show_tax_fields() ) {
			$locales['TR']['hez_invoice_type'] = array_merge(
				$locales['TR']['hez_invoice_type'] ?? array(),
				array( 'priority' => 81 )
			);
	
			$locales['TR']['hezarfen_TC_number'] = array_merge(
				$locales['TR']['hezarfen_TC_number'] ?? array(),
				array( 'priority' => 81 )
			);
	
			$locales['TR']['billing_company'] = array_merge(
				$locales['TR']['billing_company'] ?? array(),
				array( 'priority' => 82 )
			);
	
			$locales['TR']['hezarfen_tax_number'] = array_merge(
				$locales['TR']['hezarfen_tax_number'] ?? array(),
				array( 'priority' => 83 )
			);
	
			$locales['TR']['hezarfen_tax_office'] = array_merge(
				$locales['TR']['hezarfen_tax_office'] ?? array(),
				array( 'priority' => 84 )
			);
		}

		$locales['TR']['postcode'] = array_merge(
			$locales['TR']['postcode'] ?? array(),
			array( 'priority' => 90 )
		);

		return $locales;
	}

	/**
	 * Assigns priorities to the billing phone, billing email and shipping company fields.
	 * These fields are not part of country locale fields by default. (see WC_Countries::get_country_locale_field_selectors() method)
	 * 
	 * @param array<string, array<string, mixed>> $address_fields Address fields.
	 * @param string                              $country Country.
	 * 
	 * @return array<string, array<string, mixed>>
	 */
	public static function assign_priorities_to_non_locale_fields( $address_fields, $country ) {
		if ( 'TR' === $country ) {
			$type = isset( $address_fields['billing_country'] ) ? 'billing' : 'shipping';

			if ( 'billing' === $type ) {
				if ( isset( $address_fields['billing_phone'] ) ) {
					$address_fields['billing_phone']['priority'] = 32;
				}

				$address_fields['billing_email']['priority'] = 34;
			} elseif ( isset( $address_fields['shipping_company'] ) ) {
				$address_fields['shipping_company']['priority'] = 5;
			}
		}

		return $address_fields;
	}

	/**
	 * Hides the postcode field.
	 * 
	 * @return void
	 */
	public static function hide_postcode_field() {
		if ( apply_filters( 'hezarfen_skip_hide_postcode_field', false ) ) {
			return;
		}

		add_filter(
			'woocommerce_get_country_locale',
			function ( $locales ) {
				if ( isset( $locales['TR']['postcode'] ) ) {
					$locales['TR']['postcode']['required'] = false;
					$locales['TR']['postcode']['hidden']   = true;
				}
	
				return $locales;
			},
			PHP_INT_MAX - 1 
		);
	}

	/**
	 * Is My Account > Edit Address page? (billing or shipping address).
	 * 
	 * @return bool
	 */
	public static function is_edit_address_page() {
		global $wp;
		return is_account_page() && ! empty( $wp->query_vars['edit-address'] );
	}

	/**
	 * Is Order Edit page?
	 *
	 * Note: Recent versions of Woocommerce has OrderUtil::is_order_edit_screen() method. That method must be used in the future.
	 * We're not using that now because we must support older Woocommerce versions.
	 * 
	 * @param string $current_admin_page The current admin page.
	 *
	 * @return bool
	 */
	public static function is_order_edit_page( $current_admin_page ) {
		global $post_type;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return ( 'woocommerce_page_wc-orders' === $current_admin_page && ! empty( $_GET['action'] ) ) || ( 'post.php' === $current_admin_page && 'shop_order' === $post_type );
	}

	/**
	 * Show tax fields in checkout?
	 * 
	 * @return bool
	 */
	public static function is_show_tax_fields() {
		return 'yes' === get_option( 'hezarfen_show_hezarfen_checkout_tax_fields' );
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

	/**
	 * Checks if Checkout Field Editor for WooCommerce plugin is active or not.
	 * 
	 * @return bool
	 */
	public static function is_cfe_plugin_active() {
		return defined( 'THWCFD_VERSION' );
	}
}
