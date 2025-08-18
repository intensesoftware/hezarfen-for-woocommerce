<?php
/**
 * Kullanici hesabim sayfasiyla ilgili islemler
 *
 * @package Intense\MSS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IN_MSS_KullaniciArayuz
 */
class IN_MSS_KullaniciArayuz {

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'woocommerce_order_details_after_customer_details', array( $this, 'render_sozlesmeler' ) );
	}

	/**
	 * Sozlesmeleri hesabim sayfasinda goster.
	 *
	 * @param  \WC_Order $siparis WC Order instance.
	 * @return void
	 */
	public function render_sozlesmeler( $siparis ) {

		$siparis_no = $siparis->get_id();

		$sozlesme_detaylar = $this->get_sozlesme_detaylar( $siparis_no );

		$ayarlar = get_option( 'intense_mss_ayarlar', array() );
		$gosterilmeyecek_sozlesmeler = array_key_exists( 'gosterilmeyecek_sozlesmeler', $ayarlar ) ? $ayarlar['gosterilmeyecek_sozlesmeler'] : array();

		if ( empty( $sozlesme_detaylar ) ) {
			?>
			<p><?php esc_html_e( 'After your payment is completed, the distance sales agreement and preliminary information form regarding your order will be available here.', 'intense-mss-for-woocommerce' ); ?></p>
			<?php
			return;
		}
		?>

		<?php if( ! in_array( 'mss', $gosterilmeyecek_sozlesmeler, true ) ): ?>
		<div id="in-mss-icerik" class="white-popup mfp-hide">
			<h2><?php esc_html_e( 'Distance Sales Agreement', 'intense-mss-for-woocommerce' ); ?></h2>
			<p><?php echo wp_kses_post( $sozlesme_detaylar->mss_icerik ); ?></p>
			<p><strong><?php esc_html_e( 'Confirmation Date and Time', 'intense-mss-for-woocommerce' ); ?>:</strong> <?php echo esc_html( date_i18n( 'd/m/Y H:i:s', strtotime( $sozlesme_detaylar->islem_zaman ) ) ); ?></p>
		</div>
		<?php endif; ?>

		<?php if( ! in_array( 'obf', $gosterilmeyecek_sozlesmeler, true ) ): ?>
		<div id="in-obf-icerik" class="white-popup mfp-hide">
			<h2><?php esc_html_e( 'Preliminary Information Form', 'intense-mss-for-woocommerce' ); ?></h2>
			<p><?php echo wp_kses_post( $sozlesme_detaylar->obf_icerik ); ?></p>
			<p><strong><?php esc_html_e( 'Confirmation Date and Time', 'intense-mss-for-woocommerce' ); ?>:</strong> <?php echo esc_html( date_i18n( 'd/m/Y H:i:s', strtotime( $sozlesme_detaylar->islem_zaman ) ) ); ?></p>
		</div>
		<?php endif; ?>

		<?php if ( ! in_array( 'custom_1', $gosterilmeyecek_sozlesmeler, true ) &&  ! is_null( $sozlesme_detaylar->ozel_sozlesme_1_baslik ) ): ?>
		<div id="in-custom-1-icerik" class="white-popup mfp-hide">
			<h2><?php echo esc_html( $sozlesme_detaylar->ozel_sozlesme_1_baslik ); ?></h2>
			<p><?php echo wp_kses_post( $sozlesme_detaylar->ozel_sozlesme_1_icerik ); ?></p>
			<p><strong><?php esc_html_e( 'Confirmation Date and Time', 'intense-mss-for-woocommerce' ); ?>:</strong> <?php echo esc_html( date_i18n( 'd/m/Y H:i:s', strtotime( $sozlesme_detaylar->islem_zaman ) ) ); ?></p>
		</div>
		<?php endif; ?>

		<?php if ( ! in_array( 'custom_2', $gosterilmeyecek_sozlesmeler, true ) &&  ! is_null( $sozlesme_detaylar->ozel_sozlesme_2_baslik ) ): ?>
		<div id="in-custom-2-icerik" class="white-popup mfp-hide">
			<h2><?php echo esc_html( $sozlesme_detaylar->ozel_sozlesme_2_baslik ); ?></h2>
			<p><?php echo wp_kses_post( $sozlesme_detaylar->ozel_sozlesme_2_icerik ); ?></p>
			<p><strong><?php esc_html_e( 'Confirmation Date and Time', 'intense-mss-for-woocommerce' ); ?>:</strong> <?php echo esc_html( date_i18n( 'd/m/Y H:i:s', strtotime( $sozlesme_detaylar->islem_zaman ) ) ); ?></p>
		</div>
		<?php endif; ?>

		<div class="in-sozlesme-buton-div">
			<?php if( ! in_array( 'mss', $gosterilmeyecek_sozlesmeler, true ) ): ?>
				<a href="#in-mss-icerik" class="in-sozlesme-buton open-popup-link"><?php esc_html_e( 'Distance Sales Agreement', 'intense-mss-for-woocommerce' ); ?></a>
			<?php endif; ?>

			<?php if( ! in_array( 'obf', $gosterilmeyecek_sozlesmeler, true ) ): ?>
			<a href="#in-obf-icerik" class="in-sozlesme-buton open-popup-link"><?php esc_html_e( 'Preliminary Information Form', 'intense-mss-for-woocommerce' ); ?></a>
			<?php endif; ?>

			<?php if( ! in_array( 'custom_1', $gosterilmeyecek_sozlesmeler, true ) &&  ! is_null( $sozlesme_detaylar->ozel_sozlesme_1_baslik ) ): ?>
				<a href="#in-custom-1-icerik" class="in-sozlesme-buton open-popup-link"><?php echo esc_html( $sozlesme_detaylar->ozel_sozlesme_1_baslik ); ?></a>
			<?php endif; ?>

			<?php if( ! in_array( 'custom_2', $gosterilmeyecek_sozlesmeler, true ) &&  ! is_null( $sozlesme_detaylar->ozel_sozlesme_2_baslik ) ): ?>
				<a href="#in-custom-2-icerik" class="in-sozlesme-buton open-popup-link"><?php echo esc_html( $sozlesme_detaylar->ozel_sozlesme_2_baslik  ); ?></a>
			<?php endif; ?>
		</div>

		<?php
	}

	/**
	 * Sozlesme detaylari
	 *
	 * @param  int $siparis_no WC Order ID.
	 * @return object
	 */
	private function get_sozlesme_detaylar( $siparis_no ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}intense_sozlesmeler WHERE order_id=%s", $siparis_no ) );
	}
}

new IN_MSS_KullaniciArayuz();
