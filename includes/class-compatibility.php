<?php
/**
 * Contains the class that provides compatibility with third party themes and plugins.
 * 
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

/**
 * Provides compatibility with third party themes and plugins.
 */
class Compatibility {
	/**
	 * Constructor
	 * 
	 * @return void
	 */
	public function __construct() {
		$active_theme = wp_get_theme();
		if ( 'Cartzilla' === $active_theme->name || 'Cartzilla' === $active_theme->parent_theme ) {
			$this->cartzilla_support();
		}

		add_action( 'wp', array( $this, 'wp_action' ), 9 );
	}

	/**
	 * Adds Cartzilla theme support by adding necessary HTML classes.
	 * 
	 * @return void
	 */
	public function cartzilla_support() {
		add_filter( 'hezarfen_checkout_fields_class_billing_hez_invoice_type', array( $this, 'add_bootstrap_col_sm_12_class' ) );
		add_filter( 'hezarfen_checkout_fields_class_billing_hez_TC_number', array( $this, 'add_bootstrap_col_sm_6_class' ) );
		add_filter( 'hezarfen_checkout_fields_class_billing_hez_company', array( $this, 'add_bootstrap_col_sm_6_class' ) );
		add_filter( 'hezarfen_checkout_fields_class_billing_hez_tax_number', array( $this, 'add_bootstrap_col_sm_6_class' ) );
		add_filter( 'hezarfen_checkout_fields_class_billing_hez_tax_office', array( $this, 'add_bootstrap_col_sm_6_class' ) );

		add_filter( 'hezarfen_checkout_fields_input_class_billing_hez_tc_number', array( $this, 'add_bootstrap_form_control_class' ) );
		add_filter( 'hezarfen_checkout_fields_input_class_billing_hez_company', array( $this, 'add_bootstrap_form_control_class' ) );
		add_filter( 'hezarfen_checkout_fields_input_class_billing_hez_tax_number', array( $this, 'add_bootstrap_form_control_class' ) );
		add_filter( 'hezarfen_checkout_fields_input_class_billing_hez_tax_office', array( $this, 'add_bootstrap_form_control_class' ) );
	}

	/**
	 * Adds Checkout Field Editor for WooCommerce plugin support.
	 * 
	 * @return void
	 */
	public function checkout_field_editor_support() {
		if ( Helper::is_edit_address_page() && 'yes' === get_option( 'hezarfen_checkout_fields_auto_sort', 'no' ) ) {
			add_filter( 'thwcfd_address_field_override_priority', '__return_false' );
			add_filter( 'thwcfd_address_field_override_label', '__return_false' );
			add_filter( 'thwcfd_address_field_override_placeholder', '__return_false' );
			add_filter( 'thwcfd_address_field_override_class', '__return_false' );
		}

		if ( is_checkout() ) {
			add_filter( 'hezarfen_skip_hide_postcode_field', '__return_true' );
			add_filter( 'hezarfen_skip_sort_address_fields', '__return_true' );
		}
	}

	/**
	 * Runs when 'wp' action triggered.
	 * 
	 * @return void
	 */
	public function wp_action() {
		if ( Helper::is_cfe_plugin_active() ) {
			$this->checkout_field_editor_support();
		}
	}

	/**
	 * Adds bootstrap's "col-sm-12" HTML class.
	 * 
	 * @param string[] $classes HTML classes.
	 * 
	 * @return string[]
	 */
	public function add_bootstrap_col_sm_12_class( $classes ) {
		return $this->add_classes( $classes, 'col-sm-12' );
	}

	/**
	 * Adds bootstrap's "col-sm-6" HTML class.
	 * 
	 * @param string[] $classes HTML classes.
	 * 
	 * @return string[]
	 */
	public function add_bootstrap_col_sm_6_class( $classes ) {
		return $this->add_classes( $classes, 'col-sm-6' );
	}

	/**
	 * Adds bootstrap's "form-control" HTML class.
	 * 
	 * @param string[] $classes HTML classes.
	 * 
	 * @return string[]
	 */
	public function add_bootstrap_form_control_class( $classes ) {
		return $this->add_classes( $classes, 'form-control' );
	}

	/**
	 * Adds given HTML classes to the given array.
	 * 
	 * @param string[]        $classes HTML classes.
	 * @param string|string[] $classes_to_add Classes to add.
	 * 
	 * @return string[]
	 */
	private function add_classes( $classes, $classes_to_add ) {
		return array_merge( $classes, (array) $classes_to_add );
	}
}

new Compatibility();
