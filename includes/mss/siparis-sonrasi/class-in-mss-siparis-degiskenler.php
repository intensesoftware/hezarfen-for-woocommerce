<?php
/**
 * Siparis sonrasi sozlesmeleri olusturmak icin kullanilir.
 *
 * @package Intense\MSS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IN_MSS_SiparisDegiskenler
 */
class IN_MSS_SiparisDegiskenler {

	const HEZARFEN_TC_DECRYPTION_HATA_MSG = 'Teknik Hata: T.C. Kimlik No verisi okunamadı.';

	/**
	 * Order
	 *
	 * @var \WC_Order
	 */
	private $order;

	/**
	 * Constructor
	 *
	 * @param  int $order_id Order ID.
	 * @return void
	 */
	public function __construct( $order_id ) {
		$this->order = wc_get_order( $order_id );
	}

	/**
	 * String format
	 *
	 * @param  string $string formatlanacak string.
	 * @return string
	 */
	private static function string_format( $string ) {
		return mb_convert_case( str_replace( array( 'i', 'I' ), array( 'İ', 'ı' ), $string ), MB_CASE_TITLE, 'UTF-8' );
	}

	/**
	 * Siparis fatura unvani
	 *
	 * @return string
	 */
	public function get_fatura_unvani() {
		return sprintf( '%s %s', $this->order->get_billing_first_name(), $this->order->get_billing_last_name() );
	}

	/**
	 * Siparis teslimat unvani
	 *
	 * @return string
	 */
	public function get_musteri_unvani() {
		return sprintf( '%s %s', $this->order->get_shipping_first_name(), $this->order->get_shipping_last_name() );
	}

	/**
	 * Siparis teslimat adresi
	 *
	 * @return string
	 */
	public function get_musteri_adres() {
		global $woocommerce;

		$states = $woocommerce->countries->get_states( 'TR' );

		$city = isset( $states[ $this->order->get_shipping_state() ] ) ? $states[ $this->order->get_shipping_state() ] : '';

		$customer_address = sprintf(
			'%s %s %s / %s',
			$this->order->get_shipping_address_1(),
			$this->order->get_shipping_address_2(),
			self::string_format( $this->order->get_shipping_city() ),
			$city
		);

		return $customer_address;
	}

	/**
	 * Siparis fatura adresi
	 *
	 * @return string
	 */
	public function get_fatura_adres() {

		global $woocommerce;

		$states = $woocommerce->countries->get_states( 'TR' );

		$customer_address = sprintf(
			'%s %s %s / %s',
			$this->order->get_billing_address_1(),
			$this->order->get_billing_address_2(),
			self::string_format( $this->order->get_billing_city() ),
			$states[ $this->order->get_billing_state() ]
		);

		return $customer_address;
	}

	/**
	 * Siparis fatura telefonu
	 *
	 * @return string
	 */
	public function get_musteri_telefon() {
		return $this->order->get_billing_phone();
	}

	/**
	 * Siparis fatura email adresi
	 *
	 * @return string
	 */
	public function get_musteri_email() {
		return $this->order->get_billing_email();
	}

	/**
	 * Siparis urun ozet tablosu
	 *
	 * @return string
	 */
	public function get_siparis_urun_ozeti() {

		$html_render = '<table class="productSummaryTable">';

		$html_render     .= '<thead>';
			$html_render .= '<th>' . __( 'Product Name', 'intense-mss-for-woocommerce' ) . '</th>';
			$html_render .= '<th>' . __( 'Quantity', 'intense-mss-for-woocommerce' ) . '</th>';
			$html_render .= '<th>' . __( 'Price Including VAT', 'intense-mss-for-woocommerce' ) . '</th>';
		$html_render     .= '</thead>';

		$items = $this->order->get_items();

		foreach ( $items as $item_id => $item_data ) {

			$_product = $item_data->get_product();

			if ( $_product ) {
				$nitelikler = $item_data->get_formatted_meta_data();

				$nitelikler_arr = array();

				foreach ( $nitelikler as $variant ) {
					$nitelikler_arr[] = sprintf( '<strong>%s:</strong>%s', esc_html( $variant->display_key ), wp_strip_all_tags( $variant->display_value ) );
				}

				$nitelikler_text = (string) implode( ',', $nitelikler_arr );

				$kdv_dahil_satir_toplam = $item_data->get_total() + $item_data->get_total_tax();
				$urun_adi               = ( '' === $nitelikler_text ) ? $_product->get_name() : $_product->get_name() . sprintf( ' (%s)', $nitelikler_text );

				$html_render .= '<tr>';
				$html_render .= '<td>' . $urun_adi . '</td>';
				$html_render .= '<td>' . $item_data->get_quantity() . '</td>';
				$html_render .= '<td>' . wc_price( round( $kdv_dahil_satir_toplam, 2 ) ) . '</td>';
				$html_render .= '</tr>';
			}
		}

		$html_render .= '</table>';

		return apply_filters( 'intense_mss_output_order_products_table', $html_render, $this->order );
	}


	/**
	 * Siparis teslimat bedeli
	 *
	 * @return string
	 */
	public function get_siparis_kargo_bedeli() {
		return $this->order->get_shipping_total();
	}

	/**
	 * Siparis genel toplami (teslimat dahil)
	 *
	 * @return float
	 */
	public function get_siparis_kargo_dahil_bedel() {

		return $this->order->get_total();

	}

	/**
	 * Teslimat haric siparis bedeli
	 *
	 * @return string
	 */
	public function get_siparis_kargo_haric_bedel() {
		return $this->get_siparis_kargo_dahil_bedel() - $this->get_siparis_kargo_bedeli();
	}

	/**
	 * Siparis odeme yontemi
	 *
	 * @return string
	 */
	public function get_siparis_odeme_yontemi() {
		return $this->order->get_payment_method_title();
	}

	/**
	 * Indirim toplami
	 *
	 * @return string
	 */
	public function get_indirim_toplami() {
		return $this->order->get_discount_total();
	}

	/**
	 * Indirim toplam vergisi
	 *
	 * @return string
	 */
	public function get_indirim_toplam_vergisi() {
		return $this->order->get_discount_tax();
	}

	/**
	 * Ek ucret detay tablosu
	 *
	 * @return string
	 */
	public function get_ek_ucret_detaylari() {

		$fees = $this->order->get_fees();

		if ( ! count( $fees ) > 0 ) {
			return;
		}

		$html = "<table border='1' style='border-collapse: collapse'>";

		$html .= '<thead>';

			$html .= '<tr>';

				$html     .= '<th>';
					$html .= __( 'Tanım', 'intense-mss-for-woocommerce' );
				$html     .= '</th>';

				$html     .= '<th>';
					$html .= __( 'Ücret', 'intense-mss-for-woocommerce' );
				$html     .= '</th>';

			$html .= '</tr>';

		$html .= '</thead>';

		$html .= '<tbody>';

		foreach ( $fees as $fee ) {

			$html .= sprintf( '<tr><td>%s</td><td>%s</td></tr>', $fee->get_name(), wc_price( $fee->get_total() ) );

		}

		$html .= '</tbody>';

		$html .= '</table>';

		return $html;

	}

	/**
	 * Hearfen Fatura Tipi (Kurumsal/Bireysel)
	 *
	 * @return string
	 */
	public function get_hezarfen_fatura_TC() {
		if( ! class_exists('\Hezarfen\Inc\Data\PostMetaEncryption') ) {
			return self::HEZARFEN_TC_DECRYPTION_HATA_MSG;
		}

		$encrypted = $this->order->get_meta( '_billing_hez_TC_number', true );

		if ( $encrypted ) {
			// Try to decrypt the T.C number.
			return ( new \Hezarfen\Inc\Data\PostMetaEncryption() )->decrypt(
				$encrypted
			);
		}

		return self::HEZARFEN_TC_DECRYPTION_HATA_MSG;
	}

	/**
	 * Hezarfen Kurumsal Fatura Vergi Dairesi
	 *
	 * @return string
	 */
	public function get_hezarfen_fatura_vergi_daire() {
		return $this->order->get_meta( '_billing_hez_tax_office', true );
	}

	/**
	 * Hezarfen Kurumsal Fatura Vergi No
	 *
	 * @return string
	 */
	public function get_hezarfen_fatura_vergi_no() {
		return $this->order->get_meta( '_billing_hez_tax_number', true );
	}
}
