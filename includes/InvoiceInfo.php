<?php
/**
 * Class InvoiceInfo.
 *
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

use Hezarfen\Inc\Data\PostMetaEncryption;

defined( 'ABSPATH' ) || exit();

/**
 * Outputs Hezarfen invoice fields (T.C. identity number for personal
 * invoices, tax office and tax number for company invoices) inside the
 * billing address block of WooCommerce order emails and of the order
 * view (thank you / my-account) pages.
 */
class InvoiceInfo {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_email_customer_address_section', array( $this, 'render_email_billing_section' ), 10, 4 );
		add_action( 'woocommerce_order_details_after_customer_address', array( $this, 'render_order_view_billing_section' ), 10, 2 );
	}

	/**
	 * Builds the label/value rows for the given order, based on the invoice type.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return array<int, array{label: string, value: string}>
	 */
	public static function get_rows( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return array();
		}

		$invoice_type = $order->get_meta( '_billing_hez_invoice_type', true );
		$rows         = array();

		if ( 'person' === $invoice_type ) {
			$tc_number = self::get_decrypted_tc_number( $order );

			if ( $tc_number ) {
				$rows[] = array(
					'label' => __( 'T.C. Identity Number', 'hezarfen-for-woocommerce' ),
					'value' => $tc_number,
				);
			}
		} elseif ( 'company' === $invoice_type ) {
			$tax_office = $order->get_meta( '_billing_hez_tax_office', true );
			$tax_number = $order->get_meta( '_billing_hez_tax_number', true );

			if ( $tax_office ) {
				$rows[] = array(
					'label' => __( 'Tax Office', 'hezarfen-for-woocommerce' ),
					'value' => $tax_office,
				);
			}

			if ( $tax_number ) {
				$rows[] = array(
					'label' => __( 'Tax Number', 'hezarfen-for-woocommerce' ),
					'value' => $tax_number,
				);
			}
		}

		return apply_filters( 'hezarfen_invoice_info_rows', $rows, $order );
	}

	/**
	 * Decrypts the stored T.C. identity number for the given order.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return string
	 */
	protected static function get_decrypted_tc_number( $order ) {
		$raw = $order->get_meta( '_billing_hez_TC_number', true );

		if ( ! $raw ) {
			return '';
		}

		return ( new PostMetaEncryption() )->decrypt( $raw );
	}

	/**
	 * Renders invoice rows inside the billing address block of WC order emails.
	 *
	 * @param string    $type          Address type ('billing' or 'shipping').
	 * @param \WC_Order $order         Order object.
	 * @param bool      $sent_to_admin Whether the email is being sent to admin.
	 * @param bool      $plain_text    Whether the email is plain text.
	 *
	 * @return void
	 */
	public function render_email_billing_section( $type, $order, $sent_to_admin, $plain_text ) {
		if ( 'billing' !== $type ) {
			return;
		}

		$rows = self::get_rows( $order );

		if ( empty( $rows ) ) {
			return;
		}

		if ( $plain_text ) {
			foreach ( $rows as $row ) {
				echo esc_html( $row['label'] ) . ': ' . esc_html( $row['value'] ) . "\n";
			}
		} else {
			foreach ( $rows as $row ) {
				printf(
					'<br/><strong>%s:</strong> %s',
					esc_html( $row['label'] ),
					esc_html( $row['value'] )
				);
			}
		}
	}

	/**
	 * Renders invoice rows inside the billing address block of the order view pages
	 * (order-received / thank you page and my-account order view).
	 *
	 * @param string    $address_type Address type ('billing' or 'shipping').
	 * @param \WC_Order $order        Order object.
	 *
	 * @return void
	 */
	public function render_order_view_billing_section( $address_type, $order ) {
		if ( 'billing' !== $address_type ) {
			return;
		}

		$rows = self::get_rows( $order );

		if ( empty( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			printf(
				'<p class="hezarfen-invoice-info"><strong>%s:</strong> %s</p>',
				esc_html( $row['label'] ),
				esc_html( $row['value'] )
			);
		}
	}
}

return new InvoiceInfo();
