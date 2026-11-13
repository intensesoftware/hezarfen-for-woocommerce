<?php
/**
 * Contains the Rest_Api class.
 *
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes the shipment data on the WooCommerce order REST API responses.
 */
class Rest_Api {
	const FIELD_NAME = 'hezarfen_shipments';

	/**
	 * Initialization method.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_fields' ) );
	}

	/**
	 * Registers the shipment field on the order object.
	 *
	 * Every WooCommerce orders controller (wc/v1, wc/v2, wc/v3) shares the "shop_order"
	 * object type, so the field is added to all of them, legacy and HPOS stores alike.
	 *
	 * @return void
	 */
	public static function register_fields() {
		register_rest_field(
			'shop_order',
			self::FIELD_NAME,
			array(
				'get_callback' => array( __CLASS__, 'get_shipments' ),
				'schema'       => self::get_schema(),
			)
		);
	}

	/**
	 * Returns the shipments of the order in the response.
	 *
	 * @param array<string, mixed> $order_data Prepared order data.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_shipments( $order_data ) {
		$order_id = isset( $order_data['id'] ) ? (int) $order_data['id'] : 0;

		if ( ! $order_id ) {
			return array();
		}

		$shipments = array();

		foreach ( Helper::get_all_shipment_data( $order_id ) as $shipment_data ) {
			if ( ! $shipment_data instanceof Shipment_Data ) {
				continue;
			}

			$shipments[] = self::prepare_shipment( $shipment_data );
		}

		/**
		 * Filters the shipments exposed on the order REST API response.
		 *
		 * @param array<int, array<string, mixed>> $shipments Prepared shipments.
		 * @param int                              $order_id  Order ID.
		 */
		return apply_filters( 'hezarfen_mst_rest_order_shipments', $shipments, $order_id );
	}

	/**
	 * Prepares a single shipment for the response.
	 *
	 * @param Shipment_Data $shipment_data Shipment data.
	 *
	 * @return array<string, mixed>
	 */
	public static function prepare_shipment( Shipment_Data $shipment_data ) {
		return array(
			'id'              => $shipment_data->meta_id ? (int) $shipment_data->meta_id : null,
			'courier_id'      => (string) $shipment_data->courier_id,
			'courier_title'   => (string) $shipment_data->courier_title,
			'tracking_number' => (string) $shipment_data->tracking_num,
			'tracking_url'    => (string) $shipment_data->tracking_url,
		);
	}

	/**
	 * Returns the schema of the field.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_schema() {
		return array(
			'description' => __( 'Hezarfen shipment tracking data of the order.', 'hezarfen-for-woocommerce' ),
			'type'        => 'array',
			'context'     => array( 'view', 'edit' ),
			'readonly'    => true,
			'items'       => array(
				'type'       => 'object',
				'properties' => array(
					'id'              => array(
						'description' => __( 'Shipment ID (order meta ID). Null for data read from third-party plugins.', 'hezarfen-for-woocommerce' ),
						'type'        => array( 'integer', 'null' ),
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'courier_id'      => array(
						'description' => __( 'Courier company ID.', 'hezarfen-for-woocommerce' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'courier_title'   => array(
						'description' => __( 'Courier company title.', 'hezarfen-for-woocommerce' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'tracking_number' => array(
						'description' => __( 'Tracking number.', 'hezarfen-for-woocommerce' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'tracking_url'    => array(
						'description' => __( 'Tracking URL.', 'hezarfen-for-woocommerce' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
				),
			),
		);
	}
}
