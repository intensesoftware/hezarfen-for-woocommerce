<?php
/**
 * Ödeme sayfasında sepet verilerine göre anlık sözleşme önizlemesi üretilmesini sağlar.
 *
 * @package Intense\MSS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IN_MSS_OdemeSayfasi_SepetDegiskenler
 */
class IN_MSS_OdemeSayfasi_SepetDegiskenler {
	/**
	 * Sipariş ürün kalemleri tablosu
	 *
	 * @return string
	 */
	public static function get_siparis_urun_ozeti() {
		$html_render = '<table class="productSummaryTable">';

		$html_render     .= '<thead>';
			$html_render .= '<th>' . __( 'Product Name', 'intense-mss-for-woocommerce' ) . '</th>';
			$html_render .= '<th>' . __( 'Quantity', 'intense-mss-for-woocommerce' ) . '</th>';
			$html_render .= '<th>' . __( 'Price Including VAT', 'intense-mss-for-woocommerce' ) . '</th>';
		$html_render     .= '</thead>';

		global $woocommerce;
		$items = $woocommerce->cart->get_cart();

		foreach ( $items  as $cart_item_key => $cart_item ) {
			$product = $cart_item['data'];

			if ( $product ) {
				$kdv_dahil_satir_toplam = $cart_item['line_total'] + $cart_item['line_tax'];

				$html_render .= '<tr>';
				$html_render .= '<td>' . $product->get_name() . ' ' . wc_get_formatted_cart_item_data( $cart_item ) . '</td>';
				$html_render .= '<td>' . $cart_item['quantity'] . '</td>';
				$html_render .= '<td>' . wc_price( round( $kdv_dahil_satir_toplam, 2 ) ) . '</td>';
				$html_render .= '</tr>';
			}
		}

		$html_render .= '</table>';

		return apply_filters( 'intense_mss_output_checkout_products_table', $html_render, $items );
	}

	/**
	 * Sipariş teslimat bedeli
	 *
	 * @return string
	 */
	public static function get_siparis_kargo_bedeli() {
		global $woocommerce;

		return $woocommerce->cart->get_cart_shipping_total();
	}

	/**
	 * Sipariş kargo hariç bedeli
	 *
	 * @return string
	 */
	public static function get_siparis_kargo_haric_bedel() {
		global $woocommerce;

		return $woocommerce->cart->get_cart_total();
	}

	/**
	 * Sipariş kargo dahil bedeli
	 * ödeme alınaacak sipariş genel toplam tutarı
	 *
	 * @return string
	 */
	public static function get_siparis_kargo_dahil_bedel() {
		global $woocommerce;
		return wc_price( $woocommerce->cart->get_totals()['total'] );
	}

	/**
	 * Sepet indirim toplamı
	 *
	 * @return float
	 */
	public static function get_indirim_toplami() {
		global $woocommerce;

		return wc_price( $woocommerce->cart->get_discount_total() );
	}

	/**
	 * İndirim vergileri
	 *
	 * @return string
	 */
	public static function get_indirim_toplam_vergisi() {
		global $woocommerce;

		return wc_price( $woocommerce->cart->get_discount_tax() );
	}

	/**
	 * Ek ücret detayları tablosu için
	 *
	 * @return string
	 */
	public static function get_ek_ucret_detaylari() {
		global $woocommerce;

		$fees = $woocommerce->cart->get_fees();

		if ( ! count( $fees ) > 0 ) {
			return;
		}

		$html = '<table>';

			$html .= '<thead>';

				$html .= '<tr>';

					$html .= '<th>';
					$html .= __( 'Tanım', 'intense-mss-for-woocommerce' );
					$html .= '</th>';

					$html .= '<th>';
					$html .= __( 'Ücret', 'intense-mss-for-woocommerce' );
					$html .= '</th>';

				$html .= '</tr>';

			$html .= '</thead>';

			$html .= '<tbody>';

		foreach ( $fees as $fee ) {

			$html .= sprintf( '<tr><td>%s</td><td>%s</td></tr>', $fee->name, wc_price( $fee->total ) );

		}

				$html .= '</tbody>';

		$html .= '</table>';

		return $html;
	}
}
