<?php
/**
 * Contains the Manual Shipment Tracking Notification Provider abstract class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit();

/**
 * The Manual Shipment Tracking Notification Provider abstract class.
 */
abstract class MST_Notification_Provider extends \Hezarfen\Inc\Notification_Provider {
	/**
	 * Sends the notification.
	 *
	 * @param \WC_Order $order Order instance.
	 * @param  Shipment_Data $shipment_data Shipment data.
	 *
	 * @return void
	 */
	public function send( $order, $shipment_data ) {
		if ( $shipment_data->sms_sent ) {
			return;
		}

		$result = $this->perform_sending( $order, $shipment_data );

		if ( $result ) {
			$shipment_data->sms_sent = true;

			$shipment_data->save();

			/* translators: %s billing phone */
			$note = sprintf( __( 'Tracking information SMS sent to %s', 'hezarfen-for-woocommerce' ), $order->get_billing_phone() );
			$order->add_order_note( $note );
		}
	}

	/**
	 * Performs the actual sending.
	 * 
	 * @param \WC_Order     $order Order object.
	 * @param Shipment_Data $shipment_data Shipment data.
	 * 
	 * @return bool
	 */
	abstract public function perform_sending( $order, $shipment_data );
}
