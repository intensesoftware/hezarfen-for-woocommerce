<?php
/**
 * Contains the Email class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Email class.
 */
class Email {
	/**
	 * Initialization method.
	 * 
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'add_order_shipped_email' ) );
		add_filter( 'woocommerce_template_directory', array( __CLASS__, 'specify_template_dir_for_themes' ), 10, 2 );
		add_action( 'hezarfen_mst_order_shipped', array( __CLASS__, 'send_email' ) );
	}

	/**
	 * Adds Order Shipped email to the Woocommerce emails.
	 * 
	 * @param array<string, \WC_Email> $emails Emails.
	 * 
	 * @return array<string, \WC_Email>
	 */
	public static function add_order_shipped_email( $emails ) {
		$emails['Hezarfen_MST_Email_Order_Shipped'] = include HEZARFEN_MST_PATH . 'includes/email/class-email-order-shipped.php';
		return $emails;
	}

	/**
	 * Sends the Order Shipped email.
	 * 
	 * @param \WC_Order $order Order object.
	 * 
	 * @return void
	 */
	public static function send_email( $order ) {
		/**
		 * Email_Order_Shipped object instance.
		 * 
		 * @var Email_Order_Shipped 
		 */
		$email_object = \WC_Emails::instance()->get_emails()['Hezarfen_MST_Email_Order_Shipped'] ?? '';
		if ( $email_object && $email_object->is_enabled() ) { // @phpstan-ignore-line
			$email_object->trigger( $order );

			// Add order note.
			/* translators: %s billing email */
			$note = sprintf( __( 'Tracking information sent to %s', 'hezarfen-for-woocommerce' ), $order->get_billing_email() );
			$order->add_order_note( $note );
		}
	}

	/**
	 * Specifies the directory where the template file should be placed (to be overriden by the theme).
	 * (In short; the template file must be placed into 'hezarfen-for-woocommerce/emails' directory in the theme directory.)
	 * (For "Copy file to theme" feature to copy the template file to the correct path, this method is also required.)
	 * 
	 * @param string $woocommerce_dir The 'woocommerce' string.
	 * @param string $template_name Template name.
	 * 
	 * @return string
	 */
	public static function specify_template_dir_for_themes( $woocommerce_dir, $template_name ) {
		if ( 'emails/email-order-shipped.php' === $template_name ) {
			return 'hezarfen-for-woocommerce';
		}

		return $woocommerce_dir;
	}
}
