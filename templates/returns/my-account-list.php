<?php
/**
 * "İadelerim" list in the account area.
 *
 * Override at yourtheme/hezarfen/returns/my-account-list.php.
 *
 * @package Hezarfen\Inc\Returns
 *
 * @var \Hezarfen\Inc\Returns\Core\Return_Request[]  $requests   Customer's requests.
 * @var \Hezarfen\Inc\Returns\Frontend\Return_Access $access     Access helper.
 * @var string                                       $orders_url Link to the orders list.
 */

defined( 'ABSPATH' ) || exit();
?>
<div class="hez-returns hez-returns--list">

	<?php if ( $requests ) : ?>
		<ul class="hez-cards">
			<?php foreach ( $requests as $hez_request ) : ?>
				<li class="hez-card">
					<a class="hez-card__link" href="<?php echo esc_url( $access->get_request_url( $hez_request ) ); ?>">
						<span class="hez-card__head">
							<span class="hez-card__number"><?php echo esc_html( $hez_request->get_return_number() ); ?></span>
							<?php echo wp_kses_post( hezarfen_returns_status_badge( $hez_request->get_status(), true ) ); ?>
						</span>
						<span class="hez-card__meta">
							<?php
							printf(
								/* translators: 1: item count, 2: creation date. */
								esc_html__( '%1$d ürün · %2$s', 'hezarfen-for-woocommerce' ),
								absint( $hez_request->get_total_quantity() ),
								esc_html( hezarfen_returns_format_datetime( $hez_request->get_created_at() ) )
							);
							?>
						</span>
						<span class="hez-card__total">
							<?php echo wp_kses_post( wc_price( $hez_request->get_refund_amount(), array( 'currency' => $hez_request->get_currency() ) ) ); ?>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<div class="hez-empty">
			<p class="hez-empty__title"><?php esc_html_e( 'Henüz bir iade talebiniz yok.', 'hezarfen-for-woocommerce' ); ?></p>
			<p class="hez-empty__text"><?php esc_html_e( 'İade talebini, iade etmek istediğiniz ürünün bulunduğu siparişin detay sayfasından oluşturabilirsiniz.', 'hezarfen-for-woocommerce' ); ?></p>
		</div>
	<?php endif; ?>

	<p class="hez-returns__footnote">
		<?php
		printf(
			/* translators: %s: orders page link. */
			wp_kses_post( __( 'Yeni bir iade talebi için <a href="%s">siparişlerinizden</a> ilgili siparişi açın.', 'hezarfen-for-woocommerce' ) ),
			esc_url( $orders_url )
		);
		?>
	</p>
</div>
