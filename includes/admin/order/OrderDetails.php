<?php

namespace Hezarfen\Inc\Admin;

use Hezarfen\Inc\Data\PostMetaEncryption;

defined('ABSPATH') || exit();

class OrderDetails
{
	public function __construct()
	{
		add_filter('woocommerce_admin_billing_fields', [
			$this,
			'add_tax_fields_to_order_details',
		]);
	}

	function add_tax_fields_to_order_details($fields)
	{
		global $post;

		$TC_number_field_value = get_post_meta(
			$post->ID,
			'_billing_hez_TC_number',
			true
		);

		if($TC_number_field_value)
		{
			// Try to decroypt the T.C number
			$TC_number_field_decrypted_value = ( new PostMetaEncryption() )->decrypt(
				$TC_number_field_value
			);
		}else
		{
			$TC_number_field_decrypted_value = '';
		}

		$invoice_type = get_post_meta( $post->ID, '_billing_hez_invoice_type', true );

		if( $invoice_type == 'person' )
		{
			$invoice_type_human = __( 'Personal', 'hezarfen-for-woocommerce' );
		}else if( $invoice_type == 'company' )
		{
			$invoice_type_human = __( 'Company', 'hezarfen-for-woocommerce' );
		}else
		{
			$invoice_type_human = '';
		}

		$tax_fields = [
			'hez_invoice_type' => [
				'label' => __('Invoice type', 'hezarfen-for-woocommerce'),
				'type' => 'select',
				'options' => [
					'person' => __('Personal', 'hezarfen-for-woocommerce'),
					'company' => __('Company', 'hezarfen-for-woocommerce'),
				],
				'class' => 'hezarfen_billing_invoice_type_field',
				'show' => true,
				'value' => $invoice_type_human
			],
			'hez_TC_number' => [
				'label' => __(
					'T.C. Identity Number',
					'hezarfen-for-woocommerce'
				),
				'show' => true,
				'value' => $TC_number_field_decrypted_value,
				'class' => 'hezarfen_billing_TC_number_field',
			],
			'hez_tax_number' => [
				'label' => __('Tax Number', 'hezarfen-for-woocommerce'),
				'show' => true,
				'class' => 'hezarfen_billing_tax_number_field',
			],
			'hez_tax_office' => [
				'label' => __('TAX Office', 'hezarfen-for-woocommerce'),
				'show' => true,
				'class' => 'hezarfen_billing_tax_office_field',
			],
		];

		return array_merge($fields, $tax_fields);
	}
}

return new OrderDetails();
