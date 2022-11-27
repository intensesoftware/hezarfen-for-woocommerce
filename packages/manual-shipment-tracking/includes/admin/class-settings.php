<?php
/**
 * Contains the class that performs settings related actions.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Performs settings related actions.
 */
class Settings {
	const HEZARFEN_WC_SETTINGS_ID = 'hezarfen';

	/**
	 * Constructor
	 * 
	 * @return void
	 */
	public function __construct() {
		$this->assign_callbacks_to_hooks();
	}

	/**
	 * Assigns callbacks to hooks.
	 * 
	 * @return void
	 */
	public function assign_callbacks_to_hooks() {
		add_filter( 'woocommerce_get_sections_' . self::HEZARFEN_WC_SETTINGS_ID, array( $this, 'add_section' ) );
		add_filter( 'woocommerce_get_settings_' . self::HEZARFEN_WC_SETTINGS_ID, array( $this, 'add_settings_to_section' ), 10, 2 );
	}

	/**
	 * Adds a new section to Hezarfen's settings tab.
	 * 
	 * @param array<string, string> $hezarfen_sections Hezarfen's sections.
	 * 
	 * @return array<string, string>
	 */
	public function add_section( $hezarfen_sections ) {
		$hezarfen_sections['manual_shipment_tracking'] = __( 'Manual Shipment Tracking', 'hezarfen-for-woocommerce' );
		return $hezarfen_sections;
	}

	/**
	 * Adds settings to the new section.
	 * 
	 * @param array<array<string, string>> $settings Other sections' settings.
	 * @param string                       $current_section Current section.
	 * 
	 * @return array<array<string, string>>
	 */
	public function add_settings_to_section( $settings, $current_section ) {
		if ( 'manual_shipment_tracking' === $current_section ) {
			return array(
				array(
					'type'  => 'title',
					'title' => __( 'Manual Shipment Tracking Settings', 'hezarfen-for-woocommerce' ),
				),
				array(
					'type'    => 'select',
					'title'   => __( 'Default Courier Company', 'hezarfen-for-woocommerce' ),
					'id'      => 'hezarfen_mst_default_courier_company',
					'options' => Helper::courier_company_options(),
				),
				array(
					'type'  => 'checkbox',
					'title' => __( 'Show Shipment Tracking Column On My Account > Orders Page', 'hezarfen-for-woocommerce' ),
					'id'    => 'hezarfen_mst_show_shipment_tracking_column',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'hezarfen_manual_shipment_tracking_section_end',
				),
			);
		}

		return $settings;
	}
}
