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
 * @var \Hezarfen\ManualShipmentTracking\Shipment_Data[] $shipment_data
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
<div class="shipment-info">
	<?php foreach ( $shipment_data as $data ) : ?>
		<p>
			<?php esc_html_e( 'Courier Company', 'hezarfen-for-woocommerce' ); ?>: <strong><?php echo esc_html( $data->courier_title ); ?></strong>
		</p>

		<?php if ( $data->tracking_num ) : ?>
			<p>
				<?php esc_html_e( 'Tracking Number', 'hezarfen-for-woocommerce' ); ?>: <strong><?php echo esc_html( $data->tracking_num ); ?></strong>
			</p>
		<?php endif; ?>

		<?php if ( $data->tracking_url ) : ?>
			<p>
				<a style="color:blue" href="<?php echo esc_url( $data->tracking_url ); ?>" target="_blank">
					<?php esc_html_e( 'Click here to find out where your cargo is.', 'hezarfen-for-woocommerce' ); ?>
				</a>
			</p>
			<?php 
		endif;
	endforeach; 
	?>
</div>

<?php
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

do_action( 'woocommerce_email_footer', $order );
