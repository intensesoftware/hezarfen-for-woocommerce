<?php
/**
 * Returns panel under the order table on the view-order page.
 *
 * This is the only entry point into the return flow, so it carries its own
 * heading and explanation rather than reading as a footnote to the order.
 *
 * Every block carries its own class so a theme can restyle single pieces
 * without copying the template, and the wrapper classes are filterable
 * through `hezarfen_returns_order_panel_classes`.
 *
 * Override at yourtheme/hezarfen/returns/order-page-panel.php.
 *
 * @package Hezarfen\Inc\Returns
 *
 * @var WC_Order                                     $order       Order.
 * @var \Hezarfen\Inc\Returns\Core\Return_Request[]  $requests    Requests already opened for this order.
 * @var bool                                         $returnable  Whether a new request can be opened.
 * @var \Hezarfen\Inc\Returns\Frontend\Return_Access $access      Access helper.
 * @var \Hezarfen\Inc\Returns\Shipping\Return_Shipping_Registry $shipping Shipping method registry.
 * @var string                                       $request_url URL of the request form for this order.
 * @var int                                          $deadline    Return window deadline as a Unix timestamp.
 */

defined( 'ABSPATH' ) || exit();

$hez_panel_classes = array(
	'hez-returns',
	'hez-returns--order-panel',
	$requests ? 'hez-returns--has-requests' : 'hez-returns--no-requests',
	$returnable ? 'hez-returns--returnable' : 'hez-returns--not-returnable',
);

/**
 * Filters the CSS classes on the returns panel of the view-order page.
 *
 * @param string[]                                     $hez_panel_classes Class names.
 * @param WC_Order                                     $order             Order the panel belongs to.
 * @param \Hezarfen\Inc\Returns\Core\Return_Request[]  $requests          Requests already opened for this order.
 * @param bool                                         $returnable        Whether a new request can be opened.
 */
$hez_panel_classes = (array) apply_filters( 'hezarfen_returns_order_panel_classes', $hez_panel_classes, $order, $requests, $returnable );
$hez_panel_classes = array_filter( array_unique( array_map( 'sanitize_html_class', $hez_panel_classes ) ) );
?>
<section class="<?php echo esc_attr( implode( ' ', $hez_panel_classes ) ); ?>" data-hez-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
	<header class="hez-returns__panel-header">
		<h2 class="hez-returns__title"><?php esc_html_e( 'İade', 'hezarfen-for-woocommerce' ); ?></h2>
	</header>

	<div class="hez-returns__panel-body">
		<?php if ( $requests ) : ?>
			<ul class="hez-cards hez-cards--compact hez-returns__requests">
				<?php foreach ( $requests as $hez_request ) : ?>
					<?php
					// A request waiting for its pickup day needs the customer
					// to act, and this panel is where they land: the card says
					// so instead of leaving the next step one click away.
					$hez_awaiting_booking = $shipping->get_for_request( $hez_request )->requires_customer_booking()
						&& $hez_request->is_bookable_by_customer();
					?>
					<li class="hez-card hez-returns__request hez-returns__request--<?php echo esc_attr( $hez_request->get_status() ); ?>">
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
							<?php if ( $hez_awaiting_booking ) : ?>
								<span class="hez-card__hint"><?php esc_html_e( 'Kargo alım gününü seçin', 'hezarfen-for-woocommerce' ); ?></span>
							<?php endif; ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $returnable ) : ?>
			<?php if ( ! $requests ) : ?>
				<p class="hez-returns__subtitle">
					<?php esc_html_e( 'Bu siparişteki ürünleri iade etmek isterseniz talebinizi buradan oluşturabilirsiniz.', 'hezarfen-for-woocommerce' ); ?>
				</p>
			<?php endif; ?>

			<?php if ( $deadline ) : ?>
				<p class="hez-returns__deadline">
					<?php
					printf(
						/* translators: %s: formatted deadline date. */
						esc_html__( 'Son iade tarihi: %s', 'hezarfen-for-woocommerce' ),
						esc_html( wp_date( get_option( 'date_format' ), $deadline ) )
					);
					?>
				</p>
			<?php endif; ?>

			<p class="hez-returns__actions">
				<a class="hez-btn hez-btn--primary hez-returns__action hez-returns__action--new" href="<?php echo esc_url( $request_url ); ?>">
					<?php echo $requests ? esc_html__( 'Yeni iade talebi oluştur', 'hezarfen-for-woocommerce' ) : esc_html__( 'İade talebi oluştur', 'hezarfen-for-woocommerce' ); ?>
				</a>
			</p>
		<?php endif; ?>
	</div>
</section>
