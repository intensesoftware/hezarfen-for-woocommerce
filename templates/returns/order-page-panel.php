<?php
/**
 * Returns panel under the order table on the view-order page.
 *
 * Override at yourtheme/hezarfen/returns/order-page-panel.php.
 *
 * @package Hezarfen\Inc\Returns
 *
 * @var WC_Order                                    $order       Order.
 * @var \Hezarfen\Inc\Returns\Core\Return_Request[] $requests    Requests already opened for this order.
 * @var bool                                        $returnable  Whether a new request can be opened.
 * @var \Hezarfen\Inc\Returns\Frontend\Return_Access $access     Access helper.
 * @var string                                      $request_url URL of the request form for this order.
 */

defined( 'ABSPATH' ) || exit();
?>
<section class="hez-returns hez-returns--order-panel">
	<h2 class="hez-returns__title hez-returns__title--small"><?php esc_html_e( 'İade', 'hezarfen-for-woocommerce' ); ?></h2>

	<?php if ( $requests ) : ?>
		<ul class="hez-cards hez-cards--compact">
			<?php foreach ( $requests as $hez_request ) : ?>
				<li class="hez-card">
					<a class="hez-card__link" href="<?php echo esc_url( $access->get_request_url( $hez_request ) ); ?>">
						<span class="hez-card__head">
							<span class="hez-card__number"><?php echo esc_html( $hez_request->get_return_number() ); ?></span>
							<?php echo wp_kses_post( hezarfen_returns_status_badge( $hez_request->get_status(), true ) ); ?>
						</span>
						<span class="hez-card__meta"><?php echo esc_html( hezarfen_returns_format_datetime( $hez_request->get_created_at() ) ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $returnable ) : ?>
		<a class="hez-btn hez-btn--primary hez-btn--small" href="<?php echo esc_url( $request_url ); ?>">
			<?php echo $requests ? esc_html__( 'Yeni iade talebi oluştur', 'hezarfen-for-woocommerce' ) : esc_html__( 'İade talebi oluştur', 'hezarfen-for-woocommerce' ); ?>
		</a>
	<?php endif; ?>
</section>
