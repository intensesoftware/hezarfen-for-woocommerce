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
	 * @param bool   $use_cart_data Whether to use cart data for checkout scenarios.
	 * @param array  $form_data Optional form data for real-time processing.
	 * @return string
	 */
	public static function process_variables( $content, $order_id = null, $use_cart_data = false, $form_data = array() ) {
		// Basic variable replacements that don't require order context
		$content = self::process_basic_variables( $content );
		
		// Order-specific variables if order ID is provided
		if ( $order_id ) {
			$content = self::process_order_variables( $content, $order_id );
		}
		// Cart-specific variables for checkout scenarios
		elseif ( $use_cart_data && is_checkout() ) {
			$content = self::process_cart_variables( $content );
		}
		
		// Process form data variables if provided
		if ( ! empty( $form_data ) ) {
			$content = self::process_form_variables( $content, $form_data );
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
		// Use placeholder for su_an on checkout page
		$su_an_value = is_checkout() ? __( 'Will be determined after order', 'hezarfen-for-woocommerce' ) : date_i18n( 'd/m/Y H:i:s' );
		
		$replacements = array(
			// Site Variables
			'{{site_adi}}' => get_bloginfo( 'name' ),
			'{{site_url}}' => home_url(),
			
			// New format - Date Variables
			'{{bugunun_tarihi}}' => date_i18n( 'd/m/Y' ),
			'{{su_an}}' => $su_an_value,
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

	/**
	 * Process cart variables for checkout scenarios
	 *
	 * @param string $content Raw content.
	 * @return string
	 */
	private static function process_cart_variables( $content ) {
		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return $content;
		}

		// Get cart totals
		$cart_total = $cart->get_total( 'edit' );
		$cart_subtotal = $cart->get_subtotal();
		$cart_tax = $cart->get_total_tax();
		$shipping_total = $cart->get_shipping_total();
		$discount_total = $cart->get_discount_total();

		// Get cart items summary
		$items_summary = self::get_cart_items_summary( $cart );

		$replacements = array(
			// Order Variables (cart equivalents)
			'{{siparis_no}}' => __( 'Will be assigned after order', 'hezarfen-for-woocommerce' ),
			'{{siparis_tarihi}}' => date_i18n( 'd/m/Y' ),
			'{{siparis_saati}}' => __( 'Will be determined after order', 'hezarfen-for-woocommerce' ),
			'{{toplam_tutar}}' => wc_price( $cart_total ),
			'{{ara_toplam}}' => wc_price( $cart_subtotal ),
			'{{toplam_vergi_tutar}}' => wc_price( $cart_tax ),
			'{{kargo_ucreti}}' => wc_price( $shipping_total ),
			'{{urunler}}' => $items_summary,
			'{{odeme_yontemi}}' => __( 'Will be determined at payment', 'hezarfen-for-woocommerce' ),
			'{{indirim_toplami}}' => wc_price( $discount_total ),
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $content );
	}

	/**
	 * Get cart items summary
	 *
	 * @param \WC_Cart $cart WooCommerce cart object.
	 * @return string
	 */
	private static function get_cart_items_summary( $cart ) {
		$items = array();
		
		foreach ( $cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			$items[] = sprintf( 
				'%s x %d - %s', 
				$product->get_name(),
				$cart_item['quantity'],
				wc_price( $cart_item['line_total'] + $cart_item['line_tax'] )
			);
		}
		
		return implode( '<br>', $items );
	}

	/**
	 * Process form data variables for real-time updates
	 *
	 * @param string $content Raw content.
	 * @param array  $form_data Form data from checkout.
	 * @return string
	 */
	private static function process_form_variables( $content, $form_data ) {
		// Check if "Ship to different address" is enabled
		$ship_to_different = isset( $form_data['ship_to_different_address'] ) && ! empty( $form_data['ship_to_different_address'] );
		
		$replacements = array(
			// Form data variables (from checkout form)
			'{{fatura_adi}}' => isset( $form_data['billing_first_name'] ) ? sanitize_text_field( $form_data['billing_first_name'] ) : '',
			'{{fatura_soyadi}}' => isset( $form_data['billing_last_name'] ) ? sanitize_text_field( $form_data['billing_last_name'] ) : '',
			'{{fatura_sirket}}' => isset( $form_data['billing_company'] ) ? sanitize_text_field( $form_data['billing_company'] ) : '',
			'{{fatura_adres_1}}' => isset( $form_data['billing_address_1'] ) ? sanitize_text_field( $form_data['billing_address_1'] ) : '',
			'{{fatura_adres_2}}' => isset( $form_data['billing_address_2'] ) ? sanitize_text_field( $form_data['billing_address_2'] ) : '',
			'{{fatura_sehir}}' => isset( $form_data['billing_city'] ) ? sanitize_text_field( $form_data['billing_city'] ) : '',
			'{{fatura_posta_kodu}}' => isset( $form_data['billing_postcode'] ) ? sanitize_text_field( $form_data['billing_postcode'] ) : '',
			'{{fatura_ulke}}' => isset( $form_data['billing_country'] ) ? self::get_country_name( sanitize_text_field( $form_data['billing_country'] ) ) : '',
		);
		
		// Handle shipping address - use billing if not shipping to different address
		if ( $ship_to_different ) {
			// Use actual shipping data when ship to different address is enabled
			$replacements = array_merge( $replacements, array(
				'{{teslimat_adi}}' => isset( $form_data['shipping_first_name'] ) ? sanitize_text_field( $form_data['shipping_first_name'] ) : '',
				'{{teslimat_soyadi}}' => isset( $form_data['shipping_last_name'] ) ? sanitize_text_field( $form_data['shipping_last_name'] ) : '',
				'{{teslimat_sirket}}' => isset( $form_data['shipping_company'] ) ? sanitize_text_field( $form_data['shipping_company'] ) : '',
				'{{teslimat_adres_1}}' => isset( $form_data['shipping_address_1'] ) ? sanitize_text_field( $form_data['shipping_address_1'] ) : '',
				'{{teslimat_adres_2}}' => isset( $form_data['shipping_address_2'] ) ? sanitize_text_field( $form_data['shipping_address_2'] ) : '',
				'{{teslimat_sehir}}' => isset( $form_data['shipping_city'] ) ? sanitize_text_field( $form_data['shipping_city'] ) : '',
				'{{teslimat_posta_kodu}}' => isset( $form_data['shipping_postcode'] ) ? sanitize_text_field( $form_data['shipping_postcode'] ) : '',
				'{{teslimat_ulke}}' => isset( $form_data['shipping_country'] ) ? self::get_country_name( sanitize_text_field( $form_data['shipping_country'] ) ) : '',
			) );
		} else {
			// Use billing data for shipping when not shipping to different address
			$replacements = array_merge( $replacements, array(
				'{{teslimat_adi}}' => isset( $form_data['billing_first_name'] ) ? sanitize_text_field( $form_data['billing_first_name'] ) : '',
				'{{teslimat_soyadi}}' => isset( $form_data['billing_last_name'] ) ? sanitize_text_field( $form_data['billing_last_name'] ) : '',
				'{{teslimat_sirket}}' => isset( $form_data['billing_company'] ) ? sanitize_text_field( $form_data['billing_company'] ) : '',
				'{{teslimat_adres_1}}' => isset( $form_data['billing_address_1'] ) ? sanitize_text_field( $form_data['billing_address_1'] ) : '',
				'{{teslimat_adres_2}}' => isset( $form_data['billing_address_2'] ) ? sanitize_text_field( $form_data['billing_address_2'] ) : '',
				'{{teslimat_sehir}}' => isset( $form_data['billing_city'] ) ? sanitize_text_field( $form_data['billing_city'] ) : '',
				'{{teslimat_posta_kodu}}' => isset( $form_data['billing_postcode'] ) ? sanitize_text_field( $form_data['billing_postcode'] ) : '',
				'{{teslimat_ulke}}' => isset( $form_data['billing_country'] ) ? self::get_country_name( sanitize_text_field( $form_data['billing_country'] ) ) : '',
			) );
		}
		
		return str_replace( array_keys( $replacements ), array_values( $replacements ), $content );
	}

	/**
	 * Get country name from country code
	 *
	 * @param string $country_code Country code.
	 * @return string
	 */
	private static function get_country_name( $country_code ) {
		$countries = WC()->countries->get_countries();
		return isset( $countries[ $country_code ] ) ? $countries[ $country_code ] : $country_code;
	}
}