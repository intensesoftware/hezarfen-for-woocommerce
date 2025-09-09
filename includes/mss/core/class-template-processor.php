<?php
/**
 * Template Variable Processor
 *
 * @package Hezarfen\MSS
 */

namespace Hezarfen\Inc\MSS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template_Processor class
 */
class Template_Processor {

	/**
	 * Process template variables in content
	 *
	 * @param string $content Raw content.
	 * @param int    $order_id Optional order ID for order-specific variables.
	 * @return string
	 */
	public static function process_variables( $content, $order_id = null ) {
		// Basic variable replacements that don't require order context
		$content = self::process_basic_variables( $content );
		
		// Order-specific variables if order ID is provided
		if ( $order_id ) {
			$content = self::process_order_variables( $content, $order_id );
		}
		
		return $content;
	}

	/**
	 * Process basic template variables
	 *
	 * @param string $content Raw content.
	 * @return string
	 */
	private static function process_basic_variables( $content ) {
		$replacements = array(
			// Site Variables
			'{{site_adi}}' => get_bloginfo( 'name' ),
			'{{site_url}}' => home_url(),
			
			// New format - Date Variables
			'{{bugunun_tarihi}}' => date_i18n( 'd/m/Y' ),
			'{{su_an}}' => date_i18n( 'd/m/Y H:i:s' ),
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $content );
	}

	/**
	 * Process order-specific template variables
	 *
	 * @param string $content Raw content.
	 * @param int    $order_id Order ID.
	 * @return string
	 */
	private static function process_order_variables( $content, $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return $content;
		}

		// Get billing and shipping addresses
		$billing_address = $order->get_formatted_billing_address();
		$shipping_address = $order->get_formatted_shipping_address();
		
		// Get order totals
		$shipping_total = $order->get_shipping_total();
		$total_without_shipping = $order->get_total() - $shipping_total;
		
		$replacements = array(
			// Order Variables
			'{{siparis_no}}' => $order->get_order_number(),
			'{{siparis_tarihi}}' => $order->get_date_created()->date_i18n( 'd/m/Y' ),
			'{{siparis_saati}}' => $order->get_date_created()->date_i18n( 'H:i:s' ),
			'{{toplam_tutar}}' => wc_price( $order->get_total() ),
			'{{ara_toplam}}' => wc_price( $order->get_subtotal() ),
			'{{toplam_vergi_tutar}}' => wc_price( $order->get_total_tax() ),
			'{{kargo_ucreti}}' => wc_price( $shipping_total ),
			'{{urunler}}' => self::get_order_items_summary( $order ),
			'{{odeme_yontemi}}' => $order->get_payment_method_title(),
			'{{indirim_toplami}}' => wc_price( $order->get_total_discount() ),
			
			// New format - Billing Address Variables
			'{{fatura_adi}}' => $order->get_billing_first_name(),
			'{{fatura_soyadi}}' => $order->get_billing_last_name(),
			'{{fatura_sirket}}' => $order->get_billing_company(),
			'{{fatura_adres_1}}' => $order->get_billing_address_1(),
			'{{fatura_adres_2}}' => $order->get_billing_address_2(),
			'{{fatura_sehir}}' => $order->get_billing_city(),
			'{{fatura_posta_kodu}}' => $order->get_billing_postcode(),
			'{{fatura_ulke}}' => WC()->countries->countries[ $order->get_billing_country() ] ?? $order->get_billing_country(),
			
			// New format - Shipping Address Variables
			'{{teslimat_adi}}' => $order->get_shipping_first_name() ?: $order->get_billing_first_name(),
			'{{teslimat_soyadi}}' => $order->get_shipping_last_name() ?: $order->get_billing_last_name(),
			'{{teslimat_sirket}}' => $order->get_shipping_company() ?: $order->get_billing_company(),
			'{{teslimat_adres_1}}' => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
			'{{teslimat_adres_2}}' => $order->get_shipping_address_2() ?: $order->get_billing_address_2(),
			'{{teslimat_sehir}}' => $order->get_shipping_city() ?: $order->get_billing_city(),
			'{{teslimat_posta_kodu}}' => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
			'{{teslimat_ulke}}' => WC()->countries->countries[ $order->get_shipping_country() ?: $order->get_billing_country() ] ?? ($order->get_shipping_country() ?: $order->get_billing_country()),
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $content );
	}

	/**
	 * Get order items summary
	 *
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	private static function get_order_items_summary( $order ) {
		$items = array();
		
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$items[] = sprintf( 
				'%s x %d - %s', 
				$item->get_name(), 
				$item->get_quantity(),
				wc_price( $item->get_total() )
			);
		}
		
		return implode( '<br>', $items );
	}
}