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
		$contracts = $this->get_sozlesme_detaylar( $siparis_no );

		if ( empty( $contracts ) ) {
			?>
			<p><?php esc_html_e( 'After your payment is completed, the contracts regarding your order will be available here.', 'hezarfen-for-woocommerce' ); ?></p>
			<?php
			return;
		}

		// Display contract popups
		foreach ( $contracts as $index => $contract ) {
			?>
			<div id="contract-popup-<?php echo esc_attr( $contract->id ); ?>" class="white-popup mfp-hide">
				<h2><?php echo esc_html( $contract->contract_name ); ?></h2>
				<div><?php echo wp_kses_post( $contract->contract_content ); ?></div>
				<p><strong><?php esc_html_e( 'Confirmation Date and Time:', 'hezarfen-for-woocommerce' ); ?></strong> <?php echo esc_html( date_i18n( 'd/m/Y H:i:s', strtotime( $contract->created_at ) ) ); ?></p>
			</div>
			<?php
		}
		?>

		<div class="in-sozlesme-buton-div">
			<?php foreach ( $contracts as $contract ) : ?>
				<a href="#contract-popup-<?php echo esc_attr( $contract->id ); ?>" class="in-sozlesme-buton open-popup-link">
					<?php echo esc_html( $contract->contract_name ); ?>
				</a>
			<?php endforeach; ?>
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

		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}hezarfen_contracts WHERE order_id=%s ORDER BY created_at ASC", $siparis_no ) );
	}
}

new IN_MSS_KullaniciArayuz();
