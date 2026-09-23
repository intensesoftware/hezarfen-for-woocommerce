<?php
/**
 * Return notification e-mail (HTML).
 *
 * Override at yourtheme/emails/return-notification.php.
 *
 * @package Hezarfen\Inc\Returns
 *
 * @var \Hezarfen\Inc\Returns\Core\Return_Request $request                The request.
 * @var WC_Order|false                            $order                  Parent order.
 * @var string                                    $intro                  Opening paragraph.
 * @var string                                    $email_heading          Heading.
 * @var string                                    $view_url               Link to the request.
 * @var string                                    $inline_message         Customer-visible message to quote.
 * @var string                                    $inline_message_heading Heading for the quoted message.
 * @var string                                    $refund_display         Formatted refund amount, if any.
 * @var string                                    $additional_content     Merchant supplied footer text.
 * @var bool                                      $sent_to_admin          Whether the admin is the recipient.
 * @var WC_Email                                  $email                  Email object.
 */

use Hezarfen\Inc\Returns\Core\Return_Status;

defined( 'ABSPATH' ) || exit();

do_action( 'woocommerce_email_header', $email_heading, $email );

if ( ! $request ) {
	if ( $additional_content ) {
		echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
	}

	do_action( 'woocommerce_email_footer', $email );

	return;
}

$hez_reasons = new \Hezarfen\Inc\Returns\Core\Return_Reasons();
?>

<p><?php echo esc_html( $email->format_string( $intro ) ); ?></p>

<?php if ( $inline_message ) : ?>
	<h2><?php echo esc_html( $inline_message_heading ); ?></h2>
	<p style="margin:0 0 16px;padding:12px 16px;border-left:4px solid #cccccc;background:#f8f8f8;">
		<?php echo nl2br( esc_html( $inline_message ) ); ?>
	</p>
<?php endif; ?>

<table cellspacing="0" cellpadding="6" border="1" style="width:100%;border-collapse:collapse;margin-bottom:16px;">
	<tr>
		<th scope="row" style="text-align:left;"><?php esc_html_e( 'Talep numarası', 'hezarfen-for-woocommerce' ); ?></th>
		<td><?php echo esc_html( $request->get_return_number() ); ?></td>
	</tr>
	<tr>
		<th scope="row" style="text-align:left;"><?php esc_html_e( 'Sipariş', 'hezarfen-for-woocommerce' ); ?></th>
		<td><?php echo esc_html( $order ? $order->get_order_number() : $request->get_order_id() ); ?></td>
	</tr>
	<tr>
		<th scope="row" style="text-align:left;"><?php esc_html_e( 'Durum', 'hezarfen-for-woocommerce' ); ?></th>
		<td><?php echo esc_html( Return_Status::get_customer_label( $request->get_status() ) ); ?></td>
	</tr>
	<?php if ( $refund_display ) : ?>
		<tr>
			<th scope="row" style="text-align:left;"><?php esc_html_e( 'İade tutarı', 'hezarfen-for-woocommerce' ); ?></th>
			<td><?php echo esc_html( $refund_display ); ?></td>
		</tr>
	<?php endif; ?>
	<?php if ( $request->get_tracking_number() ) : ?>
		<tr>
			<th scope="row" style="text-align:left;"><?php esc_html_e( 'Kargo takip no', 'hezarfen-for-woocommerce' ); ?></th>
			<td><?php echo esc_html( $request->get_tracking_number() ); ?></td>
		</tr>
	<?php endif; ?>
</table>

<h2><?php esc_html_e( 'İade edilen ürünler', 'hezarfen-for-woocommerce' ); ?></h2>

<table cellspacing="0" cellpadding="6" border="1" style="width:100%;border-collapse:collapse;margin-bottom:16px;">
	<thead>
		<tr>
			<th scope="col" style="text-align:left;"><?php esc_html_e( 'Ürün', 'hezarfen-for-woocommerce' ); ?></th>
			<th scope="col" style="text-align:left;"><?php esc_html_e( 'Adet', 'hezarfen-for-woocommerce' ); ?></th>
			<th scope="col" style="text-align:left;"><?php esc_html_e( 'Sebep', 'hezarfen-for-woocommerce' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $request->get_items() as $hez_item ) : ?>
			<tr>
				<td><?php echo esc_html( $hez_item->get_product_name() ); ?></td>
				<td><?php echo esc_html( $hez_item->get_quantity() ); ?></td>
				<td>
					<?php echo esc_html( $hez_reasons->get_label( $hez_item->get_reason_key() ) ); ?>
					<?php if ( $hez_item->get_reason_note() ) : ?>
						<br><small><?php echo esc_html( $hez_item->get_reason_note() ); ?></small>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<?php if ( $view_url ) : ?>
	<p>
		<a href="<?php echo esc_url( $view_url ); ?>">
			<?php
			echo $sent_to_admin
				? esc_html__( 'Talebi yönetim panelinde aç', 'hezarfen-for-woocommerce' )
				: esc_html__( 'İade talebimi görüntüle', 'hezarfen-for-woocommerce' );
			?>
		</a>
	</p>
<?php endif; ?>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
