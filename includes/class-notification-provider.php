<?php
/**
 * Contains the Notification_Provider abstract class.
 * 
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

/**
 * Notification_Provider class
 */
abstract class Notification_Provider {
	/**
	 * Notification provider ID.
	 * 
	 * @var string
	 */
	public static $id;

	/**
	 * Notification provider title.
	 * 
	 * @var string
	 */
	public static $title;

	/**
	 * Notification type
	 * 
	 * @var string
	 */
	public static $notif_type;

	/**
	 * Sends the notification.
	 * 
	 * @param \WC_Order $order Order instance.
	 * @param string    $status_transition Status transition.
	 * 
	 * @return void
	 */
	abstract public function send( $order, $status_transition = '');

	/**
	 * Override this method if notification provider requires 3rd party plugin(s) to work.
	 * 
	 * @return bool
	 */
	public static function is_plugin_ready() {
		return true;
	}

	/**
	 * Adds order note.
	 * 
	 * @param \WC_Order $order Order object.
	 * 
	 * @return void
	 */
	public function add_order_note( $order ) {
		$note = '';

		if ( 'sms' === self::$notif_type ) {
			/* translators: %s billing email */
			$note = sprintf( __( 'Tracking information SMS sent to %s', 'hezarfen-for-woocommerce' ), $order->get_billing_phone() );
		}

		if ( $note ) {
			$order->add_order_note( $note );
		}
	}
}
