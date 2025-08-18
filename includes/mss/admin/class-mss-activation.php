<?php
/**
 * MSS Activation Handler (without licensing)
 * 
 * @package Hezarfen\Inc\MSS
 */

namespace Hezarfen\Inc\MSS;

defined( 'ABSPATH' ) || exit();

/**
 * MSS Activation class without licensing
 */
class MSS_Activation {
	
	/**
	 * Initialize activation/deactivation handling
	 */
	public static function init() {
		// Simplified activation - just ensure custom post types are registered
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		
		// Handle any migration or setup tasks
		add_action( 'admin_init', array( __CLASS__, 'maybe_run_setup' ) );
	}
	
	/**
	 * Register custom post types for contracts
	 */
	public static function register_post_types() {
		// This will be handled by the main MSS admin class
		// Just a placeholder for any activation-specific tasks
	}
	
	/**
	 * Maybe run setup tasks
	 */
	public static function maybe_run_setup() {
		$setup_done = get_option( 'hezarfen_mss_setup_done', false );
		
		if ( ! $setup_done ) {
			self::run_setup();
			update_option( 'hezarfen_mss_setup_done', true );
		}
	}
	
	/**
	 * Run initial setup
	 */
	private static function run_setup() {
		// Any initial setup tasks can go here
		// For now, just ensure options exist
		$default_options = array(
			'mss_taslak_id' => '',
			'obf_taslak_id' => '',
		);
		
		$existing_options = get_option( 'intense_mss_ayarlar', array() );
		$merged_options = wp_parse_args( $existing_options, $default_options );
		
		update_option( 'intense_mss_ayarlar', $merged_options );
	}
}