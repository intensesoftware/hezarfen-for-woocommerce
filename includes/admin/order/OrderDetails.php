<?php

namespace Hezarfen\Inc\Admin;

defined( 'ABSPATH' ) || exit;

class OrderDetails
{

	public function __construct()
	{
		add_filter('woocommerce_admin_billing_fields', array( $this, 'add_tax_fields_to_order_details' ) );
	}

	function add_tax_fields_to_order_details( $fields ){

		$tax_fields = array(
			'invoice_type' => array(
				'label' => __( 'Invoice type', 'hezarfen-for-woocommerce'),
				'type' => 'select',
				'options' => array(

					'person' => __('Personal', 'hezarfen-for-woocommerce'),
					'company' => __('Company', 'hezarfen-for-woocommerce')

				),
				'class' => 'hezarfen_billing_invoice_type_field',
				'show' => true
			),
			'TC_number' => array(
				'label' => __('T.C. Identity Number', 'hezarfen-for-woocommerce'),
				'show' => true,
				'class' => 'hezarfen_billing_TC_number_field'
			),
			'tax_number' => array(
				'label' => __('Tax Number', 'hezarfen-for-woocommerce'),
				'show' => true,
				'class' => 'hezarfen_billing_tax_number_field'
			),
			'tax_office' => array(
				'label' => __('TAX Office', 'hezarfen-for-woocommerce'),
				'show' => true,
				'class' => 'hezarfen_billing_tax_office_field'
			),
		);

		return array_merge( $fields, $tax_fields );

	}

}

return new OrderDetails();