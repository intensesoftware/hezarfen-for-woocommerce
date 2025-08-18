<?php
/**
 * Upgrade işlemlerini yürütür.
 *
 * @package Intense\MSS
 */

namespace MSS\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Upgrade_Functions
 */
class Upgrade_Functions {
	/**
	 * Constructor
	 *
	 * @param  string $fonksiyon_adi Bu class'a ait, cagirilacak fonksiyon adi.
	 * @return void
	 */
	public function __construct( $fonksiyon_adi ) {
		$this->$fonksiyon_adi();
	}

	/**
	 * Sözleşmelerin saklanacağı tablonun oluşturulması
	 *
	 * @return void
	 */
	private function intense_mss_db_upgrade_1_0_0_sozlesmeler_tablosunun_olusturulmasi() {
		global $wpdb;

		$tablo_ad = $wpdb->prefix . 'intense_sozlesmeler';

		$sql = "CREATE TABLE $tablo_ad (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `mss_icerik` text COLLATE utf8_turkish_ci NOT NULL,
  `obf_icerik` text COLLATE utf8_turkish_ci NOT NULL,
  `ip_adresi` varchar(128) COLLATE utf8_turkish_ci NOT NULL,
  `islem_zaman` datetime NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Sözleşmelerin saklanacağı tablonun oluşturulması
	 *
	 * @return void
	 */
	private function intense_mss_db_upgrade_1_17_0_ozel_sozlesme_sutunlarinin_olusturulmasi() {
		global $wpdb;

		$tablo_ad = $wpdb->prefix . 'intense_sozlesmeler';

		$sql = "ALTER TABLE $tablo_ad ADD `ozel_sozlesme_1_baslik` VARCHAR(64) NULL AFTER `ip_adresi`, ADD `ozel_sozlesme_1_icerik` TEXT NULL AFTER `ozel_sozlesme_1_baslik`, ADD `ozel_sozlesme_2_baslik` VARCHAR(64) NULL AFTER `ozel_sozlesme_1_icerik`, ADD `ozel_sozlesme_2_icerik` TEXT NULL AFTER `ozel_sozlesme_2_baslik`;";

		global $wpdb;
		$wpdb->query($sql);
	}
}
