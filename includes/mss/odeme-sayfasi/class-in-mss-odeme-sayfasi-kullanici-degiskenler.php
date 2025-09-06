<?php
/**
 * Ödeme sayfasında sözleşme önizlemesi gösterilebilmesi için kullanıcıya ait fatura,teslimat bilgilerini sağlar.
 *
 * @package Intense\MSS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IN_MSS_OdemeSayfasi_KullaniciDegiskenler
 */
class IN_MSS_OdemeSayfasi_KullaniciDegiskenler {

	/**
	 * String format
	 *
	 * @param  string $string düzenlenecek string.
	 * @return string
	 */
	private static function string_format( $string ) {
		return mb_convert_case( str_replace( array( 'i', 'I' ), array( 'İ', 'ı' ), $string ), MB_CASE_TITLE, 'UTF-8' );
	}

	/**
	 * Fatura ünvanı
	 *
	 * @return string
	 */
	public static function get_fatura_unvani() {
		global $woocommerce;

		return sprintf( '%s %s', $woocommerce->customer->get_billing_first_name(), $woocommerce->customer->get_billing_last_name() );
	}

	/**
	 * Teslimat ünvanı
	 *
	 * @return string
	 */
	public static function get_musteri_unvani() {
		global $woocommerce;

		return sprintf( '%s %s', $woocommerce->customer->get_shipping_first_name(), $woocommerce->customer->get_shipping_last_name() );
	}

	/**
	 * Müşteri teslimat adresi
	 *
	 * @return string
	 */
	public static function get_musteri_adres() {
		global $woocommerce;

		$states = $woocommerce->countries->get_states( 'TR' );

		return sprintf(
			'%s %s %s / %s',
			$woocommerce->customer->get_shipping_address_1(),
			$woocommerce->customer->get_shipping_address_2(),
			self::string_format( $woocommerce->customer->get_shipping_city() ),
			$states[ $woocommerce->customer->get_shipping_state() ] ?? ''
		);
	}

	/**
	 * Müşteri fatura adresi
	 *
	 * @return string
	 */
	public static function get_fatura_adres() {
		global $woocommerce;

		$states = $woocommerce->countries->get_states( 'TR' );

		return sprintf(
			'%s %s %s / %s',
			$woocommerce->customer->get_billing_address_1(),
			$woocommerce->customer->get_billing_address_2(),
			self::string_format( $woocommerce->customer->get_billing_city() ),
			$states[ $woocommerce->customer->get_billing_state() ] ?? ''
		);
	}

	/**
	 * Müşteri fatura telefonu
	 *
	 * @return string
	 */
	public static function get_musteri_telefon() {
		global $woocommerce;

		return $woocommerce->customer->get_billing_phone();
	}

	/**
	 * Müşteri fatura e-posta adresi
	 *
	 * @return string
	 */
	public static function get_musteri_email() {
		global $woocommerce;

		return $woocommerce->customer->get_billing_email();
	}
}
