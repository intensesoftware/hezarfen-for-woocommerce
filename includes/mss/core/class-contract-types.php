<?php
/**
 * Contract Types Management
 *
 * @package Hezarfen\MSS
 */

namespace Hezarfen\Inc\MSS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract_Types class
 */
class Contract_Types {

	/**
	 * Available contract types
	 */
	const TYPES = array(
		'mesafeli_satis_sozlesmesi' => array(
			'label'            => 'Mesafeli Satış Sözleşmesi',
			'description'      => 'Distance Sales Agreement',
			'default_required' => true,
			'icon'            => 'dashicons-cart',
		),
		'on_bilgilendirme_formu'    => array(
			'label'            => 'Ön Bilgilendirme Formu',
			'description'      => 'Pre-Information Form',
			'default_required' => true,
			'icon'            => 'dashicons-info',
		),
		'cayma_hakki'               => array(
			'label'            => 'Cayma Hakkı',
			'description'      => 'Right of Withdrawal',
			'default_required' => false,
			'icon'            => 'dashicons-undo',
		),
		'custom'                    => array(
			'label'            => 'Özel Sözleşme',
			'description'      => 'Custom Contract',
			'default_required' => false,
			'icon'            => 'dashicons-edit',
		),
	);

	/**
	 * Get all contract types
	 *
	 * @return array
	 */
	public static function get_types() {
		return apply_filters( 'hezarfen_mss_contract_types', self::TYPES );
	}

	/**
	 * Get contract type by key
	 *
	 * @param string $type_key Contract type key.
	 * @return array|null
	 */
	public static function get_type( $type_key ) {
		$types = self::get_types();
		return isset( $types[ $type_key ] ) ? $types[ $type_key ] : null;
	}

	/**
	 * Get contract types for dropdown
	 *
	 * @return array
	 */
	public static function get_types_for_dropdown() {
		$types   = self::get_types();
		$options = array();

		foreach ( $types as $key => $type ) {
			$options[ $key ] = $type['label'];
		}

		return $options;
	}

	/**
	 * Check if contract type exists
	 *
	 * @param string $type_key Contract type key.
	 * @return bool
	 */
	public static function type_exists( $type_key ) {
		$types = self::get_types();
		return isset( $types[ $type_key ] );
	}

	/**
	 * Get default contract structure
	 *
	 * @param string $type_key Contract type key.
	 * @return array
	 */
	public static function get_default_contract( $type_key ) {
		$type = self::get_type( $type_key );
		if ( ! $type ) {
			return array();
		}

		return array(
			'id'            => uniqid( 'contract_' ),
			'name'          => $type['label'],
			'type'          => $type_key,
			'template_id'   => 0,
			'enabled'       => true,
			'required'      => $type['default_required'],
			'display_order' => 999,
			'custom_label'  => '',
			'created_at'    => current_time( 'mysql' ),
			'updated_at'    => current_time( 'mysql' ),
		);
	}
}