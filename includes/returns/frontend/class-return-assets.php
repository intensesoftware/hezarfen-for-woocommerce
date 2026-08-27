<?php
/**
 * Contains the Return_Assets loader.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Frontend;

use Hezarfen\Inc\Returns\Core\Return_Reasons;

defined( 'ABSPATH' ) || exit();

/**
 * Loads the returns stylesheet and script, and only where they are used.
 */
class Return_Assets {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueues the front-end assets on the pages that render returns UI.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! $this->should_load() ) {
			return;
		}

		// WooCommerce already ships selectWoo on account pages, so the
		// searchable address selects cost nothing extra — but the handles
		// are only depended on when they really exist, so a site that
		// dequeued them gets plain selects instead of a broken script.
		$style_deps  = wp_style_is( 'select2', 'registered' ) ? array( 'select2' ) : array();
		$script_deps = wp_script_is( 'selectWoo', 'registered' ) ? array( 'jquery', 'selectWoo' ) : array();

		wp_enqueue_style(
			'hezarfen-returns',
			HEZARFEN_RETURNS_ASSETS_URL . 'css/returns.css',
			$style_deps,
			self::asset_version( 'css/returns.css' )
		);

		wp_enqueue_script(
			'hezarfen-returns',
			HEZARFEN_RETURNS_ASSETS_URL . 'js/returns.js',
			$script_deps,
			self::asset_version( 'js/returns.js' ),
			true
		);

		$reasons = new Return_Reasons();

		wp_localize_script(
			'hezarfen-returns',
			'hezarfenReturns',
			array(
				'reasonsRequiringNote' => $reasons->get_keys_requiring_note(),
				'addressEndpoint'      => admin_url( 'admin-ajax.php' ),
				'addressNonce'         => wp_create_nonce( Return_Address_Ajax::NONCE ),
				'i18n'                 => array(
					/* translators: %d: number of selected items. */
					'summary'          => __( '%d ürün seçildi', 'hezarfen-for-woocommerce' ),
					'selectAtLeastOne' => __( 'İade etmek istediğiniz en az bir ürün seçin.', 'hezarfen-for-woocommerce' ),
					'reasonRequired'   => __( 'Seçtiğiniz her ürün için bir iade sebebi seçin.', 'hezarfen-for-woocommerce' ),
					'noteRequired'     => __( 'Seçtiğiniz sebep için açıklama yazmanız gerekiyor.', 'hezarfen-for-woocommerce' ),
					'confirmCancel'    => __( 'İade talebinizi iptal etmek istediğinize emin misiniz?', 'hezarfen-for-woocommerce' ),
					'selectDistrict'   => __( 'İlçe seçin', 'hezarfen-for-woocommerce' ),
					'selectNeighborhood' => __( 'Mahalle seçin', 'hezarfen-for-woocommerce' ),
					'searchNoResults'  => __( 'Sonuç bulunamadı', 'hezarfen-for-woocommerce' ),
					'searching'        => __( 'Aranıyor…', 'hezarfen-for-woocommerce' ),
					'copied'           => __( 'Kopyalandı', 'hezarfen-for-woocommerce' ),
					'copyFailed'       => __( 'Kopyalanamadı', 'hezarfen-for-woocommerce' ),
					'confirmUnbook'    => __( 'Kargo randevunuzu iptal etmek istediğinize emin misiniz? İptal ettikten sonra yeni bir alım günü seçmeniz gerekir.', 'hezarfen-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Cache busting version of one of the module's asset files.
	 *
	 * The plugin version alone is not enough: an asset that changes without
	 * a release — a patch applied in place, or anything edited during
	 * development — keeps its old version string, and every browser that
	 * already has the file goes on serving the stale copy. Appending the
	 * file's modification time makes the URL change whenever the file does.
	 *
	 * @param string $relative_path Path under the module's assets folder.
	 *
	 * @return string
	 */
	public static function asset_version( $relative_path ) {
		if ( ! defined( 'WC_HEZARFEN_FILE' ) ) {
			return WC_HEZARFEN_VERSION;
		}

		$file = plugin_dir_path( WC_HEZARFEN_FILE ) . 'assets/returns/' . $relative_path;

		return file_exists( $file )
			? WC_HEZARFEN_VERSION . '.' . filemtime( $file )
			: WC_HEZARFEN_VERSION;
	}

	/**
	 * Whether the current page can render returns UI.
	 *
	 * @return bool
	 */
	private function should_load() {
		return function_exists( 'is_account_page' ) && is_account_page();
	}
}
