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
	 * @param string    $status_transition Status transition.
	 * 
	 * @return void
	 */
	public function send( $order, $status_transition = '' ) {
		$order_id      = $order->get_id();
		$shipment_data = Helper::get_all_shipment_data( $order_id );
		foreach ( $shipment_data as $data ) {
			if ( $data->sms_sent ) {
				continue;
			}

			$result = $this->perform_sending( $order, $data );

			if ( $result ) {
				$data->sms_sent = true;
				update_post_meta( $order_id, Manual_Shipment_Tracking::SHIPMENT_DATA_KEY, $data->prapare_for_db(), $data->raw_data );

				/* translators: %s billing email */
				$note = sprintf( __( 'Tracking information SMS sent to %s', 'hezarfen-for-woocommerce' ), $order->get_billing_phone() );
				$order->add_order_note( $note );
			}
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
