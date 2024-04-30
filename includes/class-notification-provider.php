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
	 * Sends the notification.
	 *
	 * @param \WC_Order $order Order instance.
	 * @param  Shipment_Data $shipment_data Shipment data.
	 *
	 * @return void
	 */
	abstract public function send( $order,  $shipment_data );

	/**
	 * Override this method if notification provider requires 3rd party plugin(s) to work.
	 * 
	 * @return bool
	 */
	public static function is_plugin_ready() {
		return true;
	}
}
