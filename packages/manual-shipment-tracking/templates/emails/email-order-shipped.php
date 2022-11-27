<?php
/**
 * Order Shipped email
 *
 * This template can be overridden by copying it to yourtheme/hezarfen-for-woocommerce/emails/email-order-shipped.php.
 *
 * @package Hezarfen\ManualShipmentTracking
 */

/**
 * Variables.
 * 
 * @var WC_Email $email
 * @var string $email_heading Email heading.
 * @var string $courier_title Courier company title.
 * @var string $tracking_number Tracking number.
 * @var WC_Order $order Order instance.
 * @var bool $plain_text If is plain text email.
 * @var bool $sent_to_admin If should sent to admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p>
	<?php esc_html_e( 'Your order has been shipped. Tracking information is below:', 'hezarfen-for-woocommerce' ); ?>
</p>
<p>
	<?php esc_html_e( 'Courier Company', 'hezarfen-for-woocommerce' ); ?>: <strong><?php echo esc_html( $courier_title ); ?></strong>
</p>
<p>
	<?php esc_html_e( 'Tracking Number', 'hezarfen-for-woocommerce' ); ?>: <strong><?php echo esc_html( $tracking_number ); ?></strong>
</p>

<?php

$tracking_url = \Hezarfen\ManualShipmentTracking\Helper::get_tracking_url( $order->get_id() );
if ( $tracking_url ) : 
	?>
	<p>
		<a style="color:blue" href="<?php echo esc_url( $tracking_url ); ?>" target="_blank">
			<?php esc_html_e( 'Click here to find out where your cargo is.', 'hezarfen-for-woocommerce' ); ?>
		</a>
	</p>
	<?php 
endif;

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

do_action( 'woocommerce_email_footer', $order );
