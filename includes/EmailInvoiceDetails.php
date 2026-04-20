<?php
/**
 * Class EmailInvoiceDetails.
 *
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

use Hezarfen\Inc\Data\PostMetaEncryption;

defined( 'ABSPATH' ) || exit();

/**
 * Outputs Hezarfen invoice fields (TC number, tax office, tax number) inside
 * the billing address block of WooCommerce order emails.
 */
class EmailInvoiceDetails {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_email_customer_address_section', array( $this, 'render_invoice_info' ), 10, 4 );
	}

	/**
	 * Renders invoice information after the billing address in emails.
	 *
	 * @param string    $type          Address type ('billing' or 'shipping').
	 * @param \WC_Order $order         Order object.
	 * @param bool      $sent_to_admin Whether the email is being sent to admin.
	 * @param bool      $plain_text    Whether the email is plain text.
	 *
	 * @return void
	 */
	public function render_invoice_info( $type, $order, $sent_to_admin, $plain_text ) {
		if ( 'billing' !== $type || ! $order instanceof \WC_Order ) {
			return;
		}

		$invoice_type = $order->get_meta( '_billing_hez_invoice_type', true );

		if ( 'person' === $invoice_type ) {
			$tc_number = $this->get_decrypted_tc_number( $order );
			$rows      = array();

			if ( $tc_number ) {
				$rows[] = array(
					'label' => __( 'T.C. Identity Number', 'hezarfen-for-woocommerce' ),
					'value' => $tc_number,
				);
			}
		} elseif ( 'company' === $invoice_type ) {
			$tax_office = $order->get_meta( '_billing_hez_tax_office', true );
			$tax_number = $order->get_meta( '_billing_hez_tax_number', true );
			$rows       = array();

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
		} else {
			return;
		}

		$rows = apply_filters( 'hezarfen_email_billing_invoice_rows', $rows, $order, $sent_to_admin, $plain_text );

		if ( empty( $rows ) ) {
			return;
		}

		if ( $plain_text ) {
			$this->render_plain_text( $rows );
		} else {
			$this->render_html( $rows );
		}
	}

	/**
	 * Decrypts the stored T.C. identity number for the given order.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return string
	 */
	protected function get_decrypted_tc_number( $order ) {
		$raw = $order->get_meta( '_billing_hez_TC_number', true );

		if ( ! $raw ) {
			return '';
		}

		return ( new PostMetaEncryption() )->decrypt( $raw );
	}

	/**
	 * Renders the invoice rows as HTML.
	 *
	 * @param array<int, array<string, string>> $rows Invoice rows.
	 *
	 * @return void
	 */
	protected function render_html( $rows ) {
		foreach ( $rows as $row ) {
			printf(
				'<br/><strong>%s:</strong> %s',
				esc_html( $row['label'] ),
				esc_html( $row['value'] )
			);
		}
	}

	/**
	 * Renders the invoice rows as plain text.
	 *
	 * @param array<int, array<string, string>> $rows Invoice rows.
	 *
	 * @return void
	 */
	protected function render_plain_text( $rows ) {
		foreach ( $rows as $row ) {
			echo esc_html( $row['label'] ) . ': ' . esc_html( $row['value'] ) . "\n";
		}
	}
}

return new EmailInvoiceDetails();
