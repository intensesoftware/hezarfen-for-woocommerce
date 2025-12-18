<?php
/**
 * Template Variable Processor
 *
 * @package Hezarfen\Contracts
 */

namespace Hezarfen\Inc\Contracts\Core;

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
		
		// Process form data variables if provided (this should override cart/order variables)
		if ( ! empty( $form_data ) ) {
			$content = self::process_form_variables( $content, $form_data );
		}
		
		// Process Hezarfen invoice field support (only when form data is available or for orders)
		if ( ! empty( $form_data ) || $order_id ) {
			$hezarfen_data = $form_data;
			// For orders, get the data from order meta instead of form data
			if ( $order_id && empty( $form_data ) ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					// Get invoice type from order
					$invoice_type = $order->get_meta( '_billing_hez_invoice_type' );
					
					// Get TC number and decrypt it if needed
					$tc_number = $order->get_meta( '_billing_hez_TC_number' );
					if ( $tc_number ) {
						// Try to decrypt the TC number
						$tc_number = ( new \Hezarfen\Inc\Data\PostMetaEncryption() )->decrypt( $tc_number );
					}
					
					// Conditional field values based on invoice type
					$tax_office = '';
					$tax_number = '';
					$tc_number_display = '';
					
					if ( 'company' === $invoice_type ) {
						// For company: show tax office and tax number, hide TC number
						$tax_office = $order->get_meta( '_billing_hez_tax_office' );
						$tax_number = $order->get_meta( '_billing_hez_tax_number' );
						$tc_number_display = ''; // Empty for company
					} elseif ( 'person' === $invoice_type ) {
						// For person: show TC number, hide tax office and tax number
						$tax_office = ''; // Empty for person
						$tax_number = ''; // Empty for person
						$tc_number_display = $tc_number;
					} else {
						// Fallback: show all fields if type is not determined
						$tax_office = $order->get_meta( '_billing_hez_tax_office' );
						$tax_number = $order->get_meta( '_billing_hez_tax_number' );
						$tc_number_display = $tc_number;
					}
					
					$hezarfen_data = array(
						'billing_hez_tax_office' => $tax_office,
						'billing_hez_tax_number' => $tax_number,
						'billing_hez_TC_number' => $tc_number_display,
					);
				}
			}
			$content = self::process_hezarfen_support( $content, $hezarfen_data );
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
			'{{fatura_ilce}}' => $order->get_billing_city(),
			'{{fatura_sehir}}' => WC()->countries->states[ $order->get_billing_country() ][ $order->get_billing_state() ] ?? $order->get_billing_state(),
			'{{fatura_posta_kodu}}' => $order->get_billing_postcode(),
			'{{fatura_ulke}}' => WC()->countries->countries[ $order->get_billing_country() ] ?? $order->get_billing_country(),
			'{{fatura_telefon}}' => $order->get_billing_phone(),
			'{{fatura_eposta}}' => $order->get_billing_email(),
			
			// New format - Shipping Address Variables
			'{{teslimat_adi}}' => $order->get_shipping_first_name() ?: $order->get_billing_first_name(),
			'{{teslimat_soyadi}}' => $order->get_shipping_last_name() ?: $order->get_billing_last_name(),
			'{{teslimat_sirket}}' => $order->get_shipping_company() ?: $order->get_billing_company(),
			'{{teslimat_adres_1}}' => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
			'{{teslimat_adres_2}}' => $order->get_shipping_address_2() ?: $order->get_billing_address_2(),
			'{{teslimat_ilce}}' => $order->get_shipping_city(),
			'{{teslimat_sehir}}' => WC()->countries->states[ $order->get_shipping_country() ][ $order->get_shipping_state() ?: $order->get_billing_state() ],
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
		$table_html = '<table style="width: 100%; border-collapse: collapse; margin: 10px 0;">';
		$table_html .= '<thead>';
		$table_html .= '<tr style="background-color: #f5f5f5;">';
		$table_html .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: left;">' . __( 'Product', 'hezarfen-for-woocommerce' ) . '</th>';
		$table_html .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . __( 'Quantity', 'hezarfen-for-woocommerce' ) . '</th>';
		$table_html .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . __( 'Price (incl. tax)', 'hezarfen-for-woocommerce' ) . '</th>';
		$table_html .= '</tr>';
		$table_html .= '</thead>';
		$table_html .= '<tbody>';
		
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$product_name = $item->get_name();
			
			// Get item meta data (variations, add-ons, custom fields)
			$item_meta = self::get_formatted_item_meta( $item );
			
			// Build product name with meta data
			$product_display = $product_name;
			if ( ! empty( $item_meta ) ) {
				$product_display .= '<br><small style="color: #666;">' . $item_meta . '</small>';
			}
			
			// Calculate total including tax
			$item_total_with_tax = $item->get_total() + $item->get_total_tax();
			
			$table_html .= '<tr>';
			$table_html .= '<td style="padding: 10px; border: 1px solid #ddd;">' . $product_display . '</td>';
			$table_html .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . $item->get_quantity() . '</td>';
			$table_html .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . wc_price( $item_total_with_tax ) . '</td>';
			$table_html .= '</tr>';
		}
		
		$table_html .= '</tbody>';
		$table_html .= '</table>';
		
		return $table_html;
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

		// Try to get the selected payment method from session/chosen method
		$chosen_payment_method = WC()->session ? WC()->session->get( 'chosen_payment_method' ) : '';
		$payment_method_title = '';
		
		if ( ! empty( $chosen_payment_method ) ) {
			$payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
			if ( isset( $payment_gateways[ $chosen_payment_method ] ) ) {
				$payment_method_title = $payment_gateways[ $chosen_payment_method ]->get_title();
			}
		}

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
			'{{odeme_yontemi}}' => ! empty( $payment_method_title ) ? $payment_method_title : __( 'Will be determined at payment', 'hezarfen-for-woocommerce' ),
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
		$table_html = '<table style="width: 100%; border-collapse: collapse; margin: 10px 0;">';
		$table_html .= '<thead>';
		$table_html .= '<tr style="background-color: #f5f5f5;">';
		$table_html .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: left;">' . __( 'Product', 'hezarfen-for-woocommerce' ) . '</th>';
		$table_html .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . __( 'Quantity', 'hezarfen-for-woocommerce' ) . '</th>';
		$table_html .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . __( 'Price (incl. tax)', 'hezarfen-for-woocommerce' ) . '</th>';
		$table_html .= '</tr>';
		$table_html .= '</thead>';
		$table_html .= '<tbody>';
		
		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$product = $cart_item['data'];
			$product_name = $product->get_name();
			
			// Get item meta data (variations, add-ons, custom fields)
			$item_meta = self::get_formatted_cart_item_meta( $cart_item );
			
			// Build product name with meta data
			$product_display = $product_name;
			if ( ! empty( $item_meta ) ) {
				$product_display .= '<br><small style="color: #666;">' . $item_meta . '</small>';
			}
			
			$table_html .= '<tr>';
			$table_html .= '<td style="padding: 10px; border: 1px solid #ddd;">' . $product_display . '</td>';
			$table_html .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . $cart_item['quantity'] . '</td>';
			$table_html .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . wc_price( $cart_item['line_total'] + $cart_item['line_tax'] ) . '</td>';
			$table_html .= '</tr>';
		}
		
		$table_html .= '</tbody>';
		$table_html .= '</table>';
		
		return $table_html;
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
		
		// Get payment method title if available
		$payment_method_title = '';
		if ( isset( $form_data['payment_method'] ) && ! empty( $form_data['payment_method'] ) ) {
			$payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
			$payment_method = sanitize_text_field( $form_data['payment_method'] );
			if ( isset( $payment_gateways[ $payment_method ] ) ) {
				$payment_method_title = $payment_gateways[ $payment_method ]->get_title();
			}
		}

		$billing_state = WC()->countries->states[ $form_data['billing_country'] ][ $form_data['billing_state'] ] ?? $form_data['billing_state'];
		$shipping_state = WC()->countries->states[ $form_data['shipping_country'] ][ $form_data['shipping_state'] ] ?? $form_data['shipping_state'];
		
		$replacements = array(
			// Form data variables (from checkout form)
			'{{fatura_adi}}' => isset( $form_data['billing_first_name'] ) ? sanitize_text_field( $form_data['billing_first_name'] ) : '',
			'{{fatura_soyadi}}' => isset( $form_data['billing_last_name'] ) ? sanitize_text_field( $form_data['billing_last_name'] ) : '',
			'{{fatura_sirket}}' => isset( $form_data['billing_company'] ) ? sanitize_text_field( $form_data['billing_company'] ) : '',
			'{{fatura_adres_1}}' => isset( $form_data['billing_address_1'] ) ? sanitize_text_field( $form_data['billing_address_1'] ) : '',
			'{{fatura_adres_2}}' => isset( $form_data['billing_address_2'] ) ? sanitize_text_field( $form_data['billing_address_2'] ) : '',
			'{{fatura_sehir}}' => $billing_state,
			'{{fatura_ilce}}' => isset( $form_data['billing_city'] ) ? sanitize_text_field( $form_data['billing_city'] ) : '',
			'{{fatura_posta_kodu}}' => isset( $form_data['billing_postcode'] ) ? sanitize_text_field( $form_data['billing_postcode'] ) : '',
			'{{fatura_ulke}}' => isset( $form_data['billing_country'] ) ? self::get_country_name( sanitize_text_field( $form_data['billing_country'] ) ) : '',
			'{{fatura_telefon}}' => isset( $form_data['billing_phone'] ) ? sanitize_text_field( $form_data['billing_phone'] ) : '',
			'{{fatura_eposta}}' => isset( $form_data['billing_email'] ) ? sanitize_email( $form_data['billing_email'] ) : '',
			
			// Payment method from form data
			'{{odeme_yontemi}}' => ! empty( $payment_method_title ) ? $payment_method_title : __( 'Will be determined at payment', 'hezarfen-for-woocommerce' ),
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
				'{{teslimat_sehir}}' => $shipping_state,
				'{{teslimat_ilce}}' => isset( $form_data['shipping_city'] ) ? sanitize_text_field( $form_data['shipping_city'] ) : '',
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
				'{{teslimat_sehir}}' => $billing_state,
				'{{teslimat_ilce}}' => isset( $form_data['shipping_city'] ) ? sanitize_text_field( $form_data['shipping_city'] ) : '',
				'{{teslimat_posta_kodu}}' => isset( $form_data['billing_postcode'] ) ? sanitize_text_field( $form_data['billing_postcode'] ) : '',
				'{{teslimat_ulke}}' => isset( $form_data['billing_country'] ) ? self::get_country_name( sanitize_text_field( $form_data['billing_country'] ) ) : '',
			) );
		}
		
		return str_replace( array_keys( $replacements ), array_values( $replacements ), $content );
	}

	/**
	 * Get formatted meta data for order item
	 *
	 * @param \WC_Order_Item_Product $item Order item.
	 * @return string
	 */
	private static function get_formatted_item_meta( $item ) {
		$meta_data = array();
		$product = $item->get_product();
		
		// Get item meta data
		$item_meta = $item->get_meta_data();
		
		foreach ( $item_meta as $meta ) {
			$meta_data_array = $meta->get_data();
			$key = $meta_data_array['key'];
			
			// Skip hidden meta (starts with _)
			if ( strpos( $key, '_' ) === 0 ) {
				continue;
			}

			$should_include = apply_filters( 'hezarfen_contracts_include_item_meta', true, $key, $meta_data_array, $item );
			if ( ! $should_include ) {
				continue;
			}
			
			// Get human-readable label
			$display_key = $key;
			
			// Check if it's an attribute
			if ( strpos( $key, 'pa_' ) === 0 || strpos( $key, 'attribute_' ) === 0 ) {
				// It's a product attribute - get the proper label
				$attribute_name = str_replace( 'attribute_', '', $key );
				$display_key = wc_attribute_label( $attribute_name, $product );
			} else {
				// Check if there's a display_key available
				if ( isset( $meta_data_array['display_key'] ) && ! empty( $meta_data_array['display_key'] ) ) {
					$display_key = $meta_data_array['display_key'];
				} else {
					// Convert snake_case or kebab-case to Title Case
					$display_key = ucwords( str_replace( array( '_', '-' ), ' ', $key ) );
				}
			}
			
			// Get display value
			$display_value = isset( $meta_data_array['display_value'] ) ? $meta_data_array['display_value'] : $meta_data_array['value'];
			
			// For attribute values, try to get the human-readable term name
			if ( ( strpos( $key, 'pa_' ) === 0 || strpos( $key, 'attribute_' ) === 0 ) && ! is_array( $display_value ) ) {
				$attribute_name = str_replace( 'attribute_', '', $key );
				$term = get_term_by( 'slug', $display_value, $attribute_name );
				if ( $term && ! is_wp_error( $term ) ) {
					$display_value = $term->name;
				}
			}
			
			// Format value if it's an array
			if ( is_array( $display_value ) ) {
				$display_value = implode( ', ', $display_value );
			}
			
			// Add to meta data array
			if ( ! empty( $display_key ) && ! empty( $display_value ) ) {
				$meta_data[] = '<strong>' . esc_html( $display_key ) . ':</strong> ' . esc_html( $display_value );
			}
		}
		
		return ! empty( $meta_data ) ? implode( '<br>', $meta_data ) : '';
	}

	/**
	 * Get formatted meta data for cart item
	 *
	 * @param array $cart_item Cart item array.
	 * @return string
	 */
	private static function get_formatted_cart_item_meta( $cart_item ) {
		// Use WooCommerce's built-in function to get formatted cart item data
		$item_data_html = wc_get_formatted_cart_item_data( $cart_item, true );
		
		// If we got formatted data, clean it up and return
		if ( ! empty( $item_data_html ) ) {
			// Remove the dl/dt/dd tags and convert to our format
			// wc_get_formatted_cart_item_data returns HTML with <dt> and <dd> tags
			// We want to convert it to our <strong>Label:</strong> Value format
			
			// Strip the outer dl tags
			$item_data_html = preg_replace( '/<\/?dl[^>]*>/', '', $item_data_html );
			
			// Convert <dt>Label</dt><dd>Value</dd> to <strong>Label:</strong> Value<br>
			$item_data_html = preg_replace( '/<dt[^>]*>(.*?)<\/dt>\s*<dd[^>]*>(.*?)<\/dd>/', '<strong>$1:</strong> $2<br>', $item_data_html );
			
			// Remove any remaining HTML tags except strong and br
			$item_data_html = strip_tags( $item_data_html, '<strong><br>' );
			
			// Remove trailing <br> tags
			$item_data_html = rtrim( $item_data_html, '<br>' );
			
			return $item_data_html;
		}
		
		return '';
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

	/**
	 * Process Hezarfen invoice field support
	 *
	 * @param string $content Form content.
	 * @param array  $form_data Optional form data for real-time processing.
	 * @return string
	 */
	private static function process_hezarfen_support( $content, $form_data = array() ) {
		// Get invoice type to determine which fields to show
		$invoice_type = isset( $form_data['billing_hez_invoice_type'] ) ? $form_data['billing_hez_invoice_type'] : '';
		
		// Get TC number and decrypt it if needed (for form data)
		$tc_number = isset( $form_data['billing_hez_TC_number'] ) ? $form_data['billing_hez_TC_number'] : '';
		if ( $tc_number ) {
			// Try to decrypt the TC number if it's encrypted
			$tc_number = ( new \Hezarfen\Inc\Data\PostMetaEncryption() )->decrypt( $tc_number );
		}
		
		// Conditional field values based on invoice type
		$tax_office = '';
		$tax_number = '';
		$tc_number_display = '';
		
		if ( 'company' === $invoice_type ) {
			// For company: show tax office and tax number, hide TC number
			$tax_office = isset( $form_data['billing_hez_tax_office'] ) ? sanitize_text_field( $form_data['billing_hez_tax_office'] ) : '';
			$tax_number = isset( $form_data['billing_hez_tax_number'] ) ? sanitize_text_field( $form_data['billing_hez_tax_number'] ) : '';
			$tc_number_display = ''; // Empty for company
		} elseif ( 'person' === $invoice_type ) {
			// For person: show TC number, hide tax office and tax number
			$tax_office = ''; // Empty for person
			$tax_number = ''; // Empty for person
			$tc_number_display = sanitize_text_field( $tc_number );
		} else {
			// Fallback: show all fields if type is not determined
			$tax_office = isset( $form_data['billing_hez_tax_office'] ) ? sanitize_text_field( $form_data['billing_hez_tax_office'] ) : '';
			$tax_number = isset( $form_data['billing_hez_tax_number'] ) ? sanitize_text_field( $form_data['billing_hez_tax_number'] ) : '';
			$tc_number_display = sanitize_text_field( $tc_number );
		}
		
		// Hezarfen invoice field replacements
		$hezarfen_replacements = array(
			'{{hezarfen_kurumsal_vergi_daire}}' => $tax_office,
			'{{hezarfen_kurumsal_vergi_no}}' => $tax_number,
			'{{hezarfen_bireysel_tc}}' => $tc_number_display,
		);

		// Replace Hezarfen field placeholders with actual values
		return str_replace( array_keys( $hezarfen_replacements ), array_values( $hezarfen_replacements ), $content );
	}
}