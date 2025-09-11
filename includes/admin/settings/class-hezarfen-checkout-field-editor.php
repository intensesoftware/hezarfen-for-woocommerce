<?php
/**
 * Hezarfen Checkout Field Editor Settings
 *
 * @package Hezarfen\Inc\Admin\Settings
 */

namespace Hezarfen\Inc\Admin\Settings {

defined( 'ABSPATH' ) || exit();

use WC_Settings_Page;
use WC_Admin_Settings;

/**
 * Checkout Field Editor Settings Class
 */
class Hezarfen_Checkout_Field_Editor extends WC_Settings_Page {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'hezarfen-checkout-fields';
		$this->label = __( 'Checkout Field Editor', 'hezarfen-for-woocommerce' );

		parent::__construct();

		add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
		add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
		add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );
	}

	/**
	 * Get sections
	 *
	 * @return array
	 */
	public function get_sections() {
		$sections = array(
			'' => __( 'Custom Fields', 'hezarfen-for-woocommerce' ),
		);

		return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
	}

	/**
	 * Output the settings
	 */
	public function output() {
		global $current_section;

		$settings = $this->get_settings( $current_section );
		WC_Admin_Settings::output_fields( $settings );
	}

	/**
	 * Save settings
	 */
	public function save() {
		global $current_section;

		$settings = $this->get_settings( $current_section );
		WC_Admin_Settings::save_fields( $settings );
	}

	/**
	 * Get settings array
	 *
	 * @param string $current_section Current section.
	 * @return array
	 */
	public function get_settings( $current_section = '' ) {
		$settings = array();
		
		if ( '' === $current_section ) {
			$settings = array(
				array(
					'title' => __( 'Checkout Field Editor', 'hezarfen-for-woocommerce' ),
					'type'  => 'title',
					'desc'  => __( 'Manage custom checkout fields for your WooCommerce store.', 'hezarfen-for-woocommerce' ),
					'id'    => 'hezarfen_checkout_fields_title',
				),
				array(
					'type' => 'hezarfen_checkout_field_editor',
					'id'   => 'hezarfen_checkout_field_editor',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'hezarfen_checkout_fields_section_end',
				),
			);
		}

		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
	}
} // End of Hezarfen_Checkout_Field_Editor class

} // End namespace Hezarfen\Inc\Admin\Settings

namespace {
	// Add custom field type for the field editor (in global namespace)
	add_action( 'woocommerce_admin_field_hezarfen_checkout_field_editor', 'hezarfen_checkout_field_editor_field' );

	/**
	 * Output the checkout field editor field
	 * This function renders the checkout field editor interface in WooCommerce settings
	 * This function is defined in the global namespace to be accessible
	 *
	 * @param array $value Field configuration array
	 */
	function hezarfen_checkout_field_editor_field( $value ) {
		// Ensure the checkout field editor class is loaded
		if ( ! class_exists( '\Hezarfen\Inc\Checkout_Field_Editor' ) ) {
			include_once WC_HEZARFEN_UYGULAMA_YOLU . 'includes/class-checkout-field-editor.php';
		}
		
		$field_editor = new \Hezarfen\Inc\Checkout_Field_Editor();
		$field_editor->render_admin_interface();
	}
}