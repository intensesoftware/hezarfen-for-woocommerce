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
<div class="shipment-info" style="margin-bottom: 20px; padding: 10px; border: 2px solid #e5e5e5;">
	<?php foreach ( $shipment_data as $i => $data ) : ?>
		<p>
			<?php esc_html_e( 'Courier Company', 'hezarfen-for-woocommerce' ); ?>: <strong><?php echo esc_html( $data->courier_title ); ?></strong>
		</p>

		<?php if ( $data->tracking_num ) : ?>
			<p>
				<?php esc_html_e( 'Tracking Number', 'hezarfen-for-woocommerce' ); ?>: <strong><?php echo esc_html( $data->tracking_num ); ?></strong>
			</p>
		<?php endif; ?>

		<?php if ( $data->tracking_url ) : ?>
			<?php $background_color = get_option( 'woocommerce_email_base_color' ); ?>
			<?php $text_color = wc_light_or_dark( $background_color, '#202020', '#ffffff' ); ?>
			<p style="margin: 0;">
				<a
					style="display: inline-block; padding: 3px 10px; height: 35px; line-height: 35px; background: <?php echo esc_attr( $background_color ); ?>; color: <?php echo esc_attr( $text_color ); ?> !important; text-decoration: none;"
					href="<?php echo esc_url( $data->tracking_url ); ?>"
					target="_blank">
					<?php esc_html_e( 'Track Cargo', 'hezarfen-for-woocommerce' ); ?>
				</a>
			</p>
		<?php endif; ?>

		<?php if ( $i < count( $shipment_data ) - 1 ) : ?>
			<hr style="margin: 15px 0; color: #e5e5e5">
		<?php endif; ?>
	<?php endforeach; ?>
</div>

<?php
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

do_action( 'woocommerce_email_footer', $order );
