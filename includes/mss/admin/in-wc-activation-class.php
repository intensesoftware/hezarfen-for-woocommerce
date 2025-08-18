<?php
/**
 * Eklentinin versiyonlarina gore, veritabani guncelleme islemlerini yurutur.
 *
 * @package Intense\MSS
 */

namespace MSS\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MSS\Admin\Upgrade_Functions;

/**
 * Aktivasyon_Deaktivasyon
 */
class Aktivasyon_Deaktivasyon {
	/**
	 * Eklenti versiyonlarinda uygulanacak veritabani guncellemeleri icin mapping
	 *
	 * @var array
	 */
	private static $db_updates = array(
		'1.0.0' => array(
			'intense_mss_db_upgrade_1_0_0_sozlesmeler_tablosunun_olusturulmasi',
		),
		'1.17.0-beta.1' => array(
			'intense_mss_db_upgrade_1_17_0_ozel_sozlesme_sutunlarinin_olusturulmasi'
		)
	);

	/**
	 * DB Upgrade
	 *
	 * @return void
	 */
	public static function db_upgrade( $mevcut_version ) {
		include_once INTENSE_MSS_UYGULAMA_PATH . 'fonksiyonlar/admin/migrate/in-wc-mss-class-upgrade-fonksiyonlar.php';

		foreach ( self::$db_updates as $version => $db_upgrade_functions ) {
			foreach ( $db_upgrade_functions as $db_upgrade_function_adi ) {
				if ( version_compare( $version, $mevcut_version, '>' ) ) {
					new Upgrade_Functions( $db_upgrade_function_adi );
				}
			}
		}
	}

	/**
	 * Activation
	 *
	 * @return void
	 */
	public static function on() {
		$mevcut_version = get_option( 'intense_wc_mss_versiyon', '0.0' );

		if( version_compare( $mevcut_version, INTENSE_MSS_VERSIYON, '>=' ) ) {
			return;
		}

		// veritabanı güncellemelerini başlat.
		self::db_upgrade( $mevcut_version );

		update_option( 'intense_wc_mss_versiyon', INTENSE_MSS_VERSIYON );
	}

	/**
	 * Deactivation
	 *
	 * @return void
	 */
	public static function off() {

	}
}
