<?php
/**
 * Contains the Hezarfen main class.
 * 
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

/**
 * Hezarfen main class.
 */
class Hezarfen {
	/**
	 * Addons info
	 * 
	 * @var array
	 */
	private $addons;

	/**
	 * Notices related to addons.
	 * 
	 * @var array
	 */
	private $addon_notices;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->addons = array(
			array(
				'name'        => 'Mahalle Bazlı Gönderim Bedeli for Hezarfen',
				'short_name'  => 'MBGB',
				'version'     => function () {
					return defined( 'WC_HEZARFEN_MBGB_VERSION' ) ? WC_HEZARFEN_MBGB_VERSION : null;
				},
				'min_version' => WC_HEZARFEN_MIN_MBGB_VERSION,
				'activated'   => function () {
					return defined( 'WC_HEZARFEN_MBGB_VERSION' );
				},
			),
		);

		register_activation_hook( WC_HEZARFEN_FILE, array( 'Hezarfen_Install', 'install' ) );

		add_action( 'plugins_loaded', array( $this, 'check_addons_and_show_notices' ) );
		add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_hezarfen_setting_page' ) );
		add_filter( 'woocommerce_get_country_locale', array( $this, 'modify_tr_locale' ), PHP_INT_MAX - 2 );
	}

	/**
	 * Modifies TR country locale data.
	 * 
	 * @param array $locales Locale data of all countries.
	 * 
	 * @return array
	 */
	public function modify_tr_locale( $locales ) {
		$locales['TR']['city'] = array_merge(
			$locales['TR']['city'] ?? array(),
			array(
				'label' => __( 'Town / City', 'hezarfen-for-woocommerce' ),
			)
		);

		$locales['TR']['address_1'] = array_merge(
			$locales['TR']['address_1'] ?? array(),
			array(
				'label'       => __( 'Neighborhood', 'hezarfen-for-woocommerce' ),
				'placeholder' => __( 'Select an option', 'hezarfen-for-woocommerce' ),
			)
		);

		return $locales;
	}

	/**
	 * Checks addons and shows notices if necessary.
	 * Defines constants to disable outdated addons.
	 * 
	 * @return void
	 */
	public function check_addons_and_show_notices() {
		$this->addon_notices = Helper::check_addons( $this->addons );
		if ( $this->addon_notices ) {
			foreach ( $this->addon_notices as $notice ) {
				define( 'WC_HEZARFEN_OUTDATED_ADDON_' . $notice['addon_short_name'], true );
			}

			add_action(
				'admin_notices',
				function () {
					Helper::show_admin_notices( $this->addon_notices );
				}
			);
		}

		// Check Intense Türkiye İl İlçe Eklentisi For WooCommerce plugin.
		if ( defined( 'INTENSE_IL_ILCE_PLUGIN_PATH' ) ) {
			add_action(
				'admin_notices',
				function () {
					$notice = array(
						'message' => __( 'In order to <strong>Hezarfen for WooCommerce</strong> plugin work, please remove the <strong>Intense Türkiye İl İlçe Eklentisi For WooCommerce</strong> plugin. The <strong>Hezarfen</strong> plugin already has province, district and neighborhood data in it.', 'hezarfen-for-woocommerce' ),
						'type'    => 'error',
					);

					Helper::show_admin_notices( array( $notice ), true );
				}
			);
		}
	}

	/**
	 *
	 * Load Hezarfen Settings Page
	 *
	 * @param array $settings the current WC setting page paths.
	 * @return array
	 */
	public function add_hezarfen_setting_page( $settings ) {
		$settings[] = include_once WC_HEZARFEN_UYGULAMA_YOLU .
			'includes/admin/settings/class-hezarfen-settings-hezarfen.php';

		return $settings;
	}
}

new Hezarfen();
