<?php
/**
 * Return notification e-mail (plain text).
 *
 * Override at yourtheme/emails/plain/return-notification.php.
 *
 * @package Hezarfen\Inc\Returns
 *
 * @var \Hezarfen\Inc\Returns\Core\Return_Request $request            The request.
 * @var WC_Order|false                            $order              Parent order.
 * @var string                                    $intro              Opening paragraph.
 * @var string                                    $email_heading      Heading.
 * @var string                                    $view_url           Link to the request.
 * @var string                                    $additional_content Merchant supplied footer text.
 * @var WC_Email                                  $email              Email object.
 */

use Hezarfen\Inc\Returns\Core\Return_Reasons;
use Hezarfen\Inc\Returns\Core\Return_Status;

defined( 'ABSPATH' ) || exit();

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

if ( ! $request ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );

	return;
}

echo esc_html( wp_strip_all_tags( $email->format_string( $intro ) ) ) . "\n\n";

echo esc_html__( 'Talep numarası', 'hezarfen-for-woocommerce' ) . ': ' . esc_html( $request->get_return_number() ) . "\n";
echo esc_html__( 'Sipariş', 'hezarfen-for-woocommerce' ) . ': ' . esc_html( $order ? $order->get_order_number() : $request->get_order_id() ) . "\n";
echo esc_html__( 'Durum', 'hezarfen-for-woocommerce' ) . ': ' . esc_html( Return_Status::get_customer_label( $request->get_status() ) ) . "\n";

if ( $request->get_tracking_number() ) {
	echo esc_html__( 'Kargo takip no', 'hezarfen-for-woocommerce' ) . ': ' . esc_html( $request->get_tracking_number() ) . "\n";
}

echo "\n" . esc_html__( 'İade edilen ürünler', 'hezarfen-for-woocommerce' ) . "\n";
echo "----------------------------------------\n";

$hez_reasons = new Return_Reasons();

foreach ( $request->get_items() as $hez_item ) {
	echo esc_html(
		sprintf(
			'%1$s x%2$d — %3$s',
			$hez_item->get_product_name(),
			$hez_item->get_quantity(),
			$hez_reasons->get_label( $hez_item->get_reason_key() )
		)
	) . "\n";

	if ( $hez_item->get_reason_note() ) {
		echo '  ' . esc_html( $hez_item->get_reason_note() ) . "\n";
	}
}

if ( $view_url ) {
	echo "\n" . esc_url_raw( $view_url ) . "\n";
}

if ( $additional_content ) {
	echo "\n----------------------------------------\n\n";
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n";
}
