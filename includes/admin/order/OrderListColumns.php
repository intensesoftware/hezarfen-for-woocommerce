<?php
/**
 * Class OrderListColumns.
 * 
 * @package Hezarfen\Inc\Admin
 */

namespace Hezarfen\Inc\Admin;

defined( 'ABSPATH' ) || exit();

/**
 * Adds custom columns to the orders list screen.
 */
class OrderListColumns {
	
	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		// Only show invoice type column if tax fields are enabled
		if ( 'yes' !== get_option( 'hezarfen_show_hezarfen_checkout_tax_fields' ) ) {
			return;
		}
		
		// Add invoice type column - supports both legacy and HPOS
		add_filter( 'manage_shop_order_posts_columns', array( $this, 'add_invoice_type_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_invoice_type_column' ), 10, 2 );
		add_filter( 'woocommerce_shop_order_list_table_columns', array( $this, 'add_invoice_type_column' ), 20 );
		add_action( 'woocommerce_shop_order_list_table_custom_column', array( $this, 'render_invoice_type_column' ), 10, 2 );
	}

	/**
	 * Adds the invoice type column to the orders list.
	 * 
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string> Modified columns.
	 */
	public function add_invoice_type_column( $columns ) {
		$new_columns = array();
		
		foreach ( $columns as $key => $title ) {
			$new_columns[ $key ] = $title;
			
			// Add after the order_total column
			if ( 'order_total' === $key ) {
				$new_columns['invoice_type'] = __( 'Invoice Type', 'hezarfen-for-woocommerce' );
			}
		}
		
		return $new_columns;
	}

	/**
	 * Renders the invoice type column content.
	 * 
	 * @param string        $column_key Current column key.
	 * @param int|\WC_Order $order Order ID or object.
	 * @return void
	 */
	public function render_invoice_type_column( $column_key, $order ) {
		if ( 'invoice_type' !== $column_key ) {
			return;
		}
		
		// Support both HPOS and legacy - get order object
		$order_obj = $order instanceof \WC_Order ? $order : wc_get_order( $order );
		
		if ( ! $order_obj ) {
			echo '—';
			return;
		}
		
		$invoice_type = $order_obj->get_meta( '_billing_hez_invoice_type' );
		
		if ( empty( $invoice_type ) ) {
			echo '—';
			return;
		}
		
		// Display minimal badge
		if ( 'person' === $invoice_type ) {
			echo '<span style="display:inline-block;padding:2px 6px;background:#e5f5ff;color:#0073aa;border-radius:2px;font-size:11px;">👤</span>';
		} elseif ( 'company' === $invoice_type ) {
			echo '<span style="display:inline-block;padding:2px 6px;background:#1e3a5f;color:#ffffff;border-radius:2px;font-size:11px;">🏢</span>';
		}
	}
}

return new OrderListColumns();