<?php
/**
 * The class manages plugin activation processes.
 * 
 * TODO: review this class and separate the logic of activation and installation.
 * 
 * @package Hezarfen\Inc
 */

defined( 'ABSPATH' ) || exit();

/**
 * Hezarfen_Install
 */
class Hezarfen_Install {
	
	/**
	 * Install
	 *
	 * @return void
	 */
	public static function install() {
		self::update_version();

		self::update_db_version();
		
		self::migrate_legacy_sms_settings();
		
		self::setup_mss_database();
	}
	
	/**
	 * Update Hezarfen version info on the options table.
	 *
	 * @return void
	 */
	public static function update_version() {
		delete_option( 'hezarfen_version' );
		add_option( 'hezarfen_version', WC_HEZARFEN_VERSION );
	}
	
	/**
	 * Update Hezarfen DB version info on the options table.
	 *
	 * @return void
	 */
	public static function update_db_version() {
		delete_option( 'hezarfen_db_version' );
		add_option( 'hezarfen_db_version', WC_HEZARFEN_VERSION );
	}
	
	/**
	 * Migrate legacy SMS settings to new SMS automation system
	 *
	 * @return void
	 */
	public static function migrate_legacy_sms_settings() {
		// Check if migration has already been done
		if ( get_option( 'hezarfen_sms_migration_completed', false ) ) {
			return;
		}
		
		// Check if legacy SMS settings exist
		$legacy_sms_enabled = get_option( 'hezarfen_mst_enable_sms_notification', 'no' );
		$legacy_provider = get_option( 'hezarfen_mst_notification_provider', '' );
		$legacy_content = get_option( 'hezarfen_mst_netgsm_sms_content', '' );
		
		// Get existing SMS rules
		$existing_rules = get_option( 'hezarfen_sms_rules', array() );
		$migration_performed = false;
		
		// Migrate NetGSM legacy settings
		if ( $legacy_sms_enabled === 'yes' && $legacy_provider === 'netgsm' && ! empty( $legacy_content ) ) {
			// Check if a NetGSM legacy rule already exists
			$netgsm_legacy_rule_exists = false;
			foreach ( $existing_rules as $rule ) {
				if ( isset( $rule['condition_status'] ) && $rule['condition_status'] === 'hezarfen_order_shipped' && 
					 isset( $rule['action_type'] ) && $rule['action_type'] === 'netgsm_legacy' ) {
					$netgsm_legacy_rule_exists = true;
					break;
				}
			}
			
			// Create new NetGSM rule if it doesn't exist
			if ( ! $netgsm_legacy_rule_exists ) {
				$new_rule = array(
					'condition_status' => 'hezarfen_order_shipped',
					'action_type' => 'netgsm_legacy',
					'phone_type' => 'billing', // Default to billing phone
					'netgsm_legacy_synced' => true,
				);
				
				// Add the new rule
				$existing_rules[] = $new_rule;
				$migration_performed = true;
				
				// Log the migration
				error_log( 'Hezarfen: Migrated legacy NetGSM SMS settings to new automation system' );
			}
		}
		
		// Migrate PandaSMS legacy settings
		if ( $legacy_sms_enabled === 'yes' && $legacy_provider === 'pandasms' ) {
			// Check if a PandaSMS legacy rule already exists
			$pandasms_legacy_rule_exists = false;
			foreach ( $existing_rules as $rule ) {
				if ( isset( $rule['condition_status'] ) && $rule['condition_status'] === 'hezarfen_order_shipped' && 
					 isset( $rule['action_type'] ) && $rule['action_type'] === 'pandasms_legacy' ) {
					$pandasms_legacy_rule_exists = true;
					break;
				}
			}
			
			// Create new PandaSMS rule if it doesn't exist
			if ( ! $pandasms_legacy_rule_exists ) {
				$new_rule = array(
					'condition_status' => 'hezarfen_order_shipped',
					'action_type' => 'pandasms_legacy',
					'phone_type' => 'billing', // Default to billing phone
					'pandasms_legacy_synced' => true,
				);
				
				// Add the new rule
				$existing_rules[] = $new_rule;
				$migration_performed = true;
				
				// Log the migration
				error_log( 'Hezarfen: Migrated legacy PandaSMS SMS settings to new automation system' );
			}
		}
		
		// Update rules if any migration was performed
		if ( $migration_performed ) {
			update_option( 'hezarfen_sms_rules', $existing_rules );
			
			// Automatically enable SMS automation when legacy settings are migrated
			update_option( 'hezarfen_sms_automation_enabled', 'yes' );
			
			// Set a transient to show admin notice
			set_transient( 'hezarfen_sms_migration_notice', true, 300 ); // Show for 5 minutes
		}
		
		// Mark migration as completed
		update_option( 'hezarfen_sms_migration_completed', true );
	}
	
	/**
	 * Setup MSS database tables
	 *
	 * @return void
	 */
	public static function setup_mss_database() {
		global $wpdb;
		
		$current_db_version = get_option( 'hezarfen_db_version', '0.0' );
		
		// Only create MSS table if we're upgrading to v2.5.0 or later
		if ( version_compare( $current_db_version, '2.5.0', '<' ) ) {
			$table_name = $wpdb->prefix . 'hezarfen_contracts';
			
			// Create dynamic contracts table (v2.5.0 - supports any agreement type)
			$sql = "CREATE TABLE $table_name (
				`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				`order_id` bigint(20) unsigned NOT NULL,
				`contract_type` varchar(50) NOT NULL,
				`contract_name` varchar(255) NOT NULL,
				`contract_content` longtext NOT NULL,
				`ip_address` varchar(45) NOT NULL,
				`user_agent` varchar(500) DEFAULT NULL,
				`created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (`id`),
				KEY `order_id` (`order_id`),
				KEY `contract_type` (`contract_type`),
				KEY `created_at` (`created_at`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
			
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}
	}
}
