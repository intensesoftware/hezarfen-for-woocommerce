<?php
/**
 * Contains the Return_Assets loader.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Frontend;

use Hezarfen\Inc\Returns\Core\Return_Reasons;
use Hezarfen\Inc\Returns\Core\Return_Settings;

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

		wp_enqueue_style(
			'hezarfen-returns',
			HEZARFEN_RETURNS_ASSETS_URL . 'css/returns.css',
			array(),
			WC_HEZARFEN_VERSION
		);

		wp_enqueue_script(
			'hezarfen-returns',
			HEZARFEN_RETURNS_ASSETS_URL . 'js/returns.js',
			array(),
			WC_HEZARFEN_VERSION,
			true
		);

		$reasons = new Return_Reasons();

		wp_localize_script(
			'hezarfen-returns',
			'hezarfenReturns',
			array(
				'reasonsRequiringNote' => $reasons->get_keys_requiring_note(),
				'i18n'                 => array(
					/* translators: %d: number of selected items. */
					'summary'          => __( '%d ürün seçildi', 'hezarfen-for-woocommerce' ),
					'selectAtLeastOne' => __( 'İade etmek istediğiniz en az bir ürün seçin.', 'hezarfen-for-woocommerce' ),
					'reasonRequired'   => __( 'Seçtiğiniz her ürün için bir iade sebebi seçin.', 'hezarfen-for-woocommerce' ),
					'noteRequired'     => __( 'Seçtiğiniz sebep için açıklama yazmanız gerekiyor.', 'hezarfen-for-woocommerce' ),
					'confirmCancel'    => __( 'İade talebinizi iptal etmek istediğinize emin misiniz?', 'hezarfen-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Whether the current page can render returns UI.
	 *
	 * @return bool
	 */
	private function should_load() {
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return true;
		}

		$page_id = Return_Settings::get_page_id();

		if ( $page_id && is_page( $page_id ) ) {
			return true;
		}

		$post = get_post();

		return $post && has_shortcode( (string) $post->post_content, Guest_Returns::SHORTCODE );
	}
}
