<?php
/**
 * Store API extension that persists and validates Hezarfen invoice/tax fields
 * submitted through the block-based checkout.
 *
 * @package Hezarfen\Inc\Blocks
 */

namespace Hezarfen\Inc\Blocks;

use Hezarfen\Inc\Helper;
use Hezarfen\Inc\Checkout;
use Hezarfen\Inc\Data\PostMetaEncryption;
use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;

defined( 'ABSPATH' ) || exit();

/**
 * Hezarfen_Store_API
 *
 * The invoice type, T.C. identity number, tax number and tax office fields are
 * not core WooCommerce address fields, so they travel through the Store API as
 * extension data under the `hezarfen` namespace. District (ilçe), neighborhood
 * (mahalle) and the company title are mapped onto the core `city`, `address_1`
 * and `company` fields by the block, so WooCommerce persists those natively and
 * they are intentionally not handled here.
 */
class Hezarfen_Store_API {

	const NAMESPACE = 'hezarfen';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_endpoint_data' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'save_fields' ), 10, 2 );
	}

	/**
	 * Registers the extension schema on the Store API checkout endpoint.
	 *
	 * @return void
	 */
	public function register_endpoint_data() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => 'checkout',
				'namespace'       => self::NAMESPACE,
				'data_callback'   => array( $this, 'data_callback' ),
				'schema_callback' => array( $this, 'schema_callback' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * Data exposed to the client under `extensions.hezarfen` in the checkout response.
	 *
	 * @return array<string, string>
	 */
	public function data_callback() {
		return array(
			'invoice_type' => '',
			'tc_number'    => '',
			'tax_number'   => '',
			'tax_office'   => '',
		);
	}

	/**
	 * Schema describing the accepted extension data.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function schema_callback() {
		return array(
			'invoice_type' => array(
				'description' => __( 'Invoice type (person or company).', 'hezarfen-for-woocommerce' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'enum'        => array( '', 'person', 'company' ),
			),
			'tc_number'    => array(
				'description' => __( 'T.C. identity number.', 'hezarfen-for-woocommerce' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
			'tax_number'   => array(
				'description' => __( 'Tax number.', 'hezarfen-for-woocommerce' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
			'tax_office'   => array(
				'description' => __( 'Tax office.', 'hezarfen-for-woocommerce' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
		);
	}

	/**
	 * Validates and persists the Hezarfen invoice/tax fields to the order.
	 *
	 * @param \WC_Order        $order   Order object.
	 * @param \WP_REST_Request $request Store API request.
	 *
	 * @return void
	 *
	 * @throws RouteException When the submitted data is invalid.
	 */
	public function save_fields( $order, $request ) {
		if ( ! Helper::is_show_tax_fields() ) {
			return;
		}

		$data = isset( $request['extensions'][ self::NAMESPACE ] ) ? (array) $request['extensions'][ self::NAMESPACE ] : array();

		$invoice_type = isset( $data['invoice_type'] ) ? sanitize_key( $data['invoice_type'] ) : '';

		if ( ! in_array( $invoice_type, array( 'person', 'company' ), true ) ) {
			throw new RouteException(
				'hezarfen_invoice_type_required',
				esc_html__( 'Please select an invoice type.', 'hezarfen-for-woocommerce' ),
				400
			);
		}

		$order->update_meta_data( '_billing_hez_invoice_type', $invoice_type );

		if ( 'person' === $invoice_type ) {
			$this->save_person_fields( $order, $data );
		} else {
			$this->save_company_fields( $order, $data );
		}
	}

	/**
	 * Validates & saves fields for a personal invoice (T.C. identity number).
	 *
	 * @param \WC_Order            $order Order object.
	 * @param array<string, mixed> $data  Submitted extension data.
	 *
	 * @return void
	 *
	 * @throws RouteException When the T.C. number is invalid.
	 */
	protected function save_person_fields( $order, $data ) {
		// Clear any company-specific meta left from a previous selection.
		$order->delete_meta_data( '_billing_hez_tax_number' );
		$order->delete_meta_data( '_billing_hez_tax_office' );

		if ( ! Checkout::is_show_identity_field_on_checkout() ) {
			return;
		}

		$tc_number = isset( $data['tc_number'] ) ? sanitize_text_field( $data['tc_number'] ) : '';

		if ( $tc_number && ( 11 !== strlen( $tc_number ) || ! is_numeric( $tc_number ) ) ) {
			throw new RouteException(
				'hezarfen_tc_number_invalid',
				esc_html__( 'TC ID number is not valid', 'hezarfen-for-woocommerce' ),
				400
			);
		}

		if ( ! $tc_number ) {
			if ( Checkout::is_identity_number_field_required() ) {
				throw new RouteException(
					'hezarfen_tc_number_required',
					esc_html__( 'TC ID number is not valid', 'hezarfen-for-woocommerce' ),
					400
				);
			}

			$order->delete_meta_data( '_billing_hez_TC_number' );
			return;
		}

		$encryption = new PostMetaEncryption();

		if ( $encryption->health_check() ) {
			$order->update_meta_data( '_billing_hez_TC_number', $encryption->encrypt( $tc_number ) );
		} else {
			$order->update_meta_data( '_billing_hez_TC_number', '******' );
		}
	}

	/**
	 * Validates & saves fields for a company invoice (tax number & tax office).
	 *
	 * @param \WC_Order            $order Order object.
	 * @param array<string, mixed> $data  Submitted extension data.
	 *
	 * @return void
	 *
	 * @throws RouteException When the tax number is invalid.
	 */
	protected function save_company_fields( $order, $data ) {
		// Clear any personal-specific meta left from a previous selection.
		$order->delete_meta_data( '_billing_hez_TC_number' );

		$tax_number = isset( $data['tax_number'] ) ? sanitize_text_field( $data['tax_number'] ) : '';
		$tax_office = isset( $data['tax_office'] ) ? sanitize_text_field( $data['tax_office'] ) : '';

		if ( ! is_numeric( $tax_number ) || ! in_array( strlen( $tax_number ), array( 10, 11 ), true ) ) {
			throw new RouteException(
				'hezarfen_tax_number_invalid',
				esc_html__( 'Tax number is not valid', 'hezarfen-for-woocommerce' ),
				400
			);
		}

		if ( '' === $tax_office ) {
			throw new RouteException(
				'hezarfen_tax_office_required',
				esc_html__( 'Please enter the tax office.', 'hezarfen-for-woocommerce' ),
				400
			);
		}

		$order->update_meta_data( '_billing_hez_tax_number', $tax_number );
		$order->update_meta_data( '_billing_hez_tax_office', $tax_office );
	}
}
