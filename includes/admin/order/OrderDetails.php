<?php
/**
 * Class OrderDetails.
 * 
 * @package Hezarfen\Inc\Admin
 */

namespace Hezarfen\Inc\Admin;

use Hezarfen\Inc\Data\PostMetaEncryption;

defined( 'ABSPATH' ) || exit();

/**
 * OrderDetails
 */
class OrderDetails {
	
	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter(
			'woocommerce_admin_billing_fields',
			array(
				$this,
				'add_tax_fields_to_order_details',
			)
		);

		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'add_tc_number_field_to_order_details' ) );
	}

	/**
	 * Adds tax fields (except TC Number field) to billing form where in the admin order edit screen.
	 *
	 * @param  array<string, mixed> $fields WooCommerce current fields.
	 * @return array<string, mixed>
	 */
	public function add_tax_fields_to_order_details( $fields ) {
		global $post;

		$invoice_type = get_post_meta( $post->ID, '_billing_hez_invoice_type', true );

		if ( 'person' == $invoice_type ) {
			$invoice_type_human = __( 'Personal', 'hezarfen-for-woocommerce' );
		} elseif ( 'company' == $invoice_type ) {
			$invoice_type_human = __( 'Company', 'hezarfen-for-woocommerce' );
		} else {
			$invoice_type_human = '';
		}

		$tax_fields = array(
			'hez_invoice_type' => array(
				'label'   => __( 'Invoice type', 'hezarfen-for-woocommerce' ),
				'type'    => 'select',
				'options' => array(
					'person'  => __( 'Personal', 'hezarfen-for-woocommerce' ),
					'company' => __( 'Company', 'hezarfen-for-woocommerce' ),
				),
				'class'   => 'hezarfen_billing_invoice_type_field',
				'show'    => true,
				'value'   => $invoice_type_human,
			),
			'hez_tax_number'   => array(
				'label' => __( 'Tax Number', 'hezarfen-for-woocommerce' ),
				'show'  => true,
				'class' => 'hezarfen_billing_tax_number_field',
			),
			'hez_tax_office'   => array(
				'label' => __( 'TAX Office', 'hezarfen-for-woocommerce' ),
				'show'  => true,
				'class' => 'hezarfen_billing_tax_office_field',
			),
		);

		return array_merge( $fields, $tax_fields );
	}

	/**
	 * Adds decrypted TC Number information to the order details page.
	 * 
	 * @param \WC_Order $order Order object.
	 * 
	 * @return void
	 */
	public function add_tc_number_field_to_order_details( $order ) {
		if ( 'person' === $order->get_meta( '_billing_hez_invoice_type', true ) ) {
			$identity_number_field_value = $order->get_meta( '_billing_hez_TC_number', true );

			if ( $identity_number_field_value ) {
				// Try to decrypt the T.C number.
				$identity_number_field_decrypted_value = ( new PostMetaEncryption() )->decrypt(
					$identity_number_field_value
				);
			} else {
				$identity_number_field_decrypted_value = '';
			}

			printf( '<p class="hezarfen-tc-num-field"><strong>%s:</strong>%s</p>', esc_html__( 'T.C. Identity Number', 'hezarfen-for-woocommerce' ), esc_html( $identity_number_field_decrypted_value ) );
		}
	}
}

return new OrderDetails();
