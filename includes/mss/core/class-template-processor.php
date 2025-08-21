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
			'{GUNCEL_TARIH}' => date_i18n( 'd/m/Y' ),
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
			'{FATURA_TAM_AD_UNVANI}' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'{FATURA_ADRESI}'        => $billing_address,
			'{ALICI_ADRESI}'         => $shipping_address ?: $billing_address,
			'{ALICI_TELEFONU}'       => $order->get_billing_phone(),
			'{ALICI_EPOSTA}'         => $order->get_billing_email(),
			'{ALICI_TAM_AD_UNVANI}'  => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
			'{URUNLER}'              => self::get_order_items_summary( $order ),
			'{ODEME_YONTEMI}'        => $order->get_payment_method_title(),
			'{KARGO_BEDELI}'         => wc_price( $shipping_total ),
			'{KARGO_HARIC_SIPARIS_TUTARI}' => wc_price( $total_without_shipping ),
			'{KARGO_DAHIL_SIPARIS_TUTARI}' => wc_price( $order->get_total() ),
			'{INDIRIM_TOPLAMI}'      => wc_price( $order->get_total_discount() ),
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