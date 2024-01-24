<?php
/*
Plugin Name: Hezarfen For Woocommerce
Description: Hezarfen, WooCommerce eklentisini Türkiye için daha kullanılabilir kılmayı amaçlar.
Version: 2.0.0-beta.3
Author: Intense Yazılım Ltd.
Author URI: http://intense.com.tr
Developer: Intense Yazılım Ltd.
Developer URI: http://intense.com.tr
License: GPL2
Text Domain: hezarfen-for-woocommerce
Domain Path: /languages
Requires PHP: 7.0
Requires at least: 5.7

WC tested up to: 8.2
*/

defined('ABSPATH') || exit();

define('WC_HEZARFEN_VERSION', '2.0.0-beta.3');
define('WC_HEZARFEN_MIN_MBGB_VERSION', '0.6.1');
define('WC_HEZARFEN_FILE', __FILE__);
define('WC_HEZARFEN_UYGULAMA_YOLU', plugin_dir_path(__FILE__));
define('WC_HEZARFEN_UYGULAMA_URL', plugin_dir_url(__FILE__));
define('WC_HEZARFEN_NEIGH_API_URL', plugin_dir_url(__FILE__) . 'api/get-mahalle-data.php');

add_action('plugins_loaded', 'hezarfen_load_plugin_textdomain');

add_action('admin_post_in_hezarfen_pro_presale_ad_dismiss', 'hez_ad_hezarfen_pro_presale_dismiss');

function hez_ad_hezarfen_pro_presale_dismiss() {
	update_option( 'hez_ad_hezarfen_pro_presale_dismiss', 'yes', false );
	wp_redirect(admin_url('/'));
}

add_action('admin_notices', 'hez_ad_hezarfen_pro_presale' );

function hez_ad_hezarfen_pro_presale() {
	if( get_option( 'hez_ad_hezarfen_pro_presale_dismiss', 'no' ) === 'yes' ) {
		return;
	}

	$timezone = new DateTimeZone('Europe/Istanbul');
	$end = new DateTime('2023-12-06 23:59', $timezone);

	if( current_datetime() >= $end ) {
		return;
	}
	?>
	<div id="in-hezarfen-pro-presale">
		<img style="height:200px" src="<?php echo WC_HEZARFEN_UYGULAMA_URL . 'assets/admin/hezarfen-pro.png'; ?>" />
		<div id="benefits-container">
			<h2>Hezarfen Pro Ön Satışta!</h2>
			<h4>Özellikler</h4>
			<div id="benefits">
				<ul>
					<li>5 Farklı kargo entegrasyonu tek pakette; Hepsijet, Sendeo, Yurtiçi, Aras ve MNG Kargo</li>
					<li>Ödeme ekranında müşterilerinize kargo seçimi sunun, seçilen kargo için otomatik barkod oluşsun</li>
					<li>Uçtan uca entegrasyon sayesinde işgücünden tasarruf edin, hataları azaltın.</li>
					<li>İlçe/mahalle seçimi sayesinde kargo başarılı teslimat oranını arttırarak iade oranını azaltın</li>
					<li>Sipariş durumlar otomatik olarak güncellenir ve müşterilerinize SMS/E-Posta bildirimi yapılır.</li>
				</ul>
				<div id="campaign">
					Bugün 23:59'a kadar geçerli, ilk 20 satın alım için 1000TL indirim
					<a target="_blank" href="https://intense.com.tr/urun/hezarfen-pro/" rel="noopener noreferrer" class="button">Hemen satın al</a>

					<p>Kampanya uzatılmayacaktır.</p>
				</div>

			</div>
			<a href="<?php echo admin_url('admin-post.php?action=in_hezarfen_pro_presale_ad_dismiss'); ?>">Reklamı kalıcı olarak gizle</a>
		</div>
	</div>

	<style>
		#in-hezarfen-pro-presale {
			background-color: #fff;
			display:flex;
			padding: 10px;
			align-items: center;
		}

		#in-hezarfen-pro-presale ul {
			list-style-type: disc;
			padding-left: 15px;
		}

		#in-hezarfen-pro-presale h2,#in-hezarfen-pro-presale h4  {
			padding: 0;
			margin: 5px;
		}

		#in-hezarfen-pro-presale #benefits {
			display: flex;
			gap: 20px;
		}

		#benefits-container {
			padding: 20px 0;
		}

		#campaign {
			background-color: #f9f987;
			font-size: 20px;
			padding: 20px;
		}

		#campaign a {
			margin: 10px 0;
			padding: 10px 20px;
		}
	</style>
	<?php
}

function hezarfen_load_plugin_textdomain()
{
	load_plugin_textdomain(
		'hezarfen-for-woocommerce',
		false,
		basename( dirname( __FILE__ ) ) . '/languages/'
	);
}

require_once 'includes/Autoload.php';
