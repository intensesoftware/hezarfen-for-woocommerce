<?php
/**
 * Contains the Default_Reason_Provider.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

defined( 'ABSPATH' ) || exit();

/**
 * The preset return reasons every store gets out of the box.
 */
class Default_Reason_Provider implements Return_Reason_Provider_Interface {

	/**
	 * Key of the catch-all reason that always demands an explanation.
	 */
	const REASON_OTHER = 'other';

	/**
	 * The preset reason list.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_reasons() {
		return array(
			'defective'        => array(
				'label'         => __( 'Ürün arızalı / çalışmıyor', 'hezarfen-for-woocommerce' ),
				'requires_note' => false,
			),
			'damaged-shipping' => array(
				'label'         => __( 'Ürün kargoda hasar görmüş', 'hezarfen-for-woocommerce' ),
				'requires_note' => false,
			),
			'wrong-item'       => array(
				'label'         => __( 'Yanlış ürün gönderilmiş', 'hezarfen-for-woocommerce' ),
				'requires_note' => false,
			),
			'not-as-described' => array(
				'label'         => __( 'Ürün açıklamasıyla uyuşmuyor', 'hezarfen-for-woocommerce' ),
				'requires_note' => false,
			),
			'size-fit'         => array(
				'label'         => __( 'Beden / ölçü uymadı', 'hezarfen-for-woocommerce' ),
				'requires_note' => false,
			),
			'missing-parts'    => array(
				'label'         => __( 'Eksik parça veya aksesuar', 'hezarfen-for-woocommerce' ),
				'requires_note' => false,
			),
			'changed-mind'     => array(
				'label'         => __( 'Vazgeçtim / beğenmedim', 'hezarfen-for-woocommerce' ),
				'requires_note' => false,
			),
			self::REASON_OTHER => array(
				'label'         => __( 'Diğer', 'hezarfen-for-woocommerce' ),
				'requires_note' => true,
			),
		);
	}

	/**
	 * Runs first so add-on providers can override individual entries.
	 *
	 * @return int
	 */
	public function get_priority() {
		return 10;
	}
}
