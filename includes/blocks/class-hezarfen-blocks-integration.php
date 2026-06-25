<?php
/**
 * Blocks integration that registers the Hezarfen checkout block script/styles
 * and passes server-side settings to it.
 *
 * @package Hezarfen\Inc\Blocks
 */

namespace Hezarfen\Inc\Blocks;

use Hezarfen\Inc\Helper;
use Hezarfen\Inc\Checkout;
use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

defined( 'ABSPATH' ) || exit();

/**
 * Hezarfen_Blocks_Integration
 *
 * Registers the compiled checkout block bundle and exposes the data the block
 * needs (district reference data, REST endpoint, feature flags and labels)
 * through `wc.wcSettings.getSetting( 'hezarfen-checkout_data' )`.
 */
class Hezarfen_Blocks_Integration implements IntegrationInterface {

	const SCRIPT_HANDLE = 'hezarfen-checkout-block';
	const STYLE_HANDLE  = 'hezarfen-checkout-block-style';

	/**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'hezarfen-checkout';
	}

	/**
	 * Registers the block script and styles.
	 *
	 * @return void
	 */
	public function initialize() {
		$build_path = WC_HEZARFEN_UYGULAMA_YOLU . 'assets/blocks/checkout/build/';
		$build_url  = WC_HEZARFEN_UYGULAMA_URL . 'assets/blocks/checkout/build/';

		$asset_file = $build_path . 'index.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array(),
				'version'      => WC_HEZARFEN_VERSION,
			);

		wp_register_script(
			self::SCRIPT_HANDLE,
			$build_url . 'index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		if ( file_exists( $build_path . 'style-index.css' ) ) {
			wp_register_style(
				self::STYLE_HANDLE,
				$build_url . 'style-index.css',
				array(),
				$asset['version']
			);
			wp_enqueue_style( self::STYLE_HANDLE );
		}
	}

	/**
	 * Frontend script handles for the integration.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return array( self::SCRIPT_HANDLE );
	}

	/**
	 * Editor script handles for the integration.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return array( self::SCRIPT_HANDLE );
	}

	/**
	 * Data exposed to the block via `getSetting( 'hezarfen-checkout_data' )`.
	 *
	 * @return array<string, mixed>
	 */
	public function get_script_data() {
		$neighborhood_enabled = 'yes' === apply_filters(
			'hezarfen_enable_district_neighborhood_fields',
			get_option( 'hezarfen_enable_district_neighborhood_fields', 'yes' )
		);

		return array(
			'restUrl'             => esc_url_raw( rest_url( Hezarfen_Locations_REST::REST_NAMESPACE ) ),
			'nonce'               => wp_create_nonce( 'wp_rest' ),
			'districts'           => $this->get_districts_map(),
			'neighborhoodEnabled' => $neighborhood_enabled,
			'taxFieldsEnabled'    => Helper::is_show_tax_fields(),
			'showIdentityField'   => Checkout::is_show_identity_field_on_checkout(),
			'identityRequired'    => Checkout::is_identity_number_field_required(),
			'labels'              => $this->get_labels(),
		);
	}

	/**
	 * Builds a map of `TRxx` plate number => district options for all provinces,
	 * so the district select can be populated client-side without a round-trip.
	 *
	 * @return array<string, array<int, array{value: string, label: string}>>
	 */
	protected function get_districts_map() {
		$districts_file = WC_HEZARFEN_UYGULAMA_YOLU . 'includes/Data/mahalle/tr-districts.php';

		if ( ! file_exists( $districts_file ) ) {
			return array();
		}

		// The file populates $tr_districts as array<string, string[]>.
		include $districts_file;

		if ( empty( $tr_districts ) || ! is_array( $tr_districts ) ) {
			return array();
		}

		$map = array();

		foreach ( $tr_districts as $plate => $districts ) {
			$options = array();

			foreach ( (array) $districts as $district ) {
				$options[] = array(
					'value' => $district,
					'label' => $district,
				);
			}

			$map[ $plate ] = $options;
		}

		return $map;
	}

	/**
	 * Translated labels passed to the block.
	 *
	 * @return array<string, string>
	 */
	protected function get_labels() {
		return array(
			'district'        => __( 'Town / City', 'hezarfen-for-woocommerce' ),
			'neighborhood'    => __( 'Neighborhood', 'hezarfen-for-woocommerce' ),
			'selectOption'    => __( 'Select an option', 'hezarfen-for-woocommerce' ),
			'noResults'       => __( 'No results found', 'hezarfen-for-woocommerce' ),
			'invoiceType'     => __( 'Invoice Type', 'hezarfen-for-woocommerce' ),
			'invoicePerson'   => __( 'Personal', 'hezarfen-for-woocommerce' ),
			'invoiceCompany'  => __( 'Company', 'hezarfen-for-woocommerce' ),
			'tcNumber'        => __( 'T.C. Identity Number', 'hezarfen-for-woocommerce' ),
			'tcPlaceholder'   => __( 'Enter T.C. Identity Number', 'hezarfen-for-woocommerce' ),
			'companyTitle'    => __( 'Title', 'hezarfen-for-woocommerce' ),
			'companyPlaceholder' => __( 'Enter invoice title', 'hezarfen-for-woocommerce' ),
			'taxNumber'       => __( 'Tax Number', 'hezarfen-for-woocommerce' ),
			'taxNumberPlaceholder' => __( 'Enter tax number', 'hezarfen-for-woocommerce' ),
			'taxOffice'       => __( 'Tax Office', 'hezarfen-for-woocommerce' ),
			'taxOfficePlaceholder' => __( 'Enter tax office', 'hezarfen-for-woocommerce' ),
			'tcInvalid'       => __( 'TC ID number is not valid', 'hezarfen-for-woocommerce' ),
			'taxNumberInvalid' => __( 'Tax number is not valid', 'hezarfen-for-woocommerce' ),
		);
	}
}
