<?php
/**
 * Admin detail screen of a single return request.
 *
 * @package Hezarfen\Inc\Returns
 *
 * @var \Hezarfen\Inc\Returns\Core\Return_Request $request         The request.
 * @var \Hezarfen\Inc\Returns\Core\Return_Event[] $events          Full timeline.
 * @var \Hezarfen\Inc\Returns\Core\Return_Reasons $reasons         Reason registry.
 * @var \Hezarfen\Inc\Returns\Shipping\Return_Shipping_Method_Interface $shipping_method Shipping method.
 * @var string[]                                  $transitions     Statuses reachable from here.
 * @var string                                    $list_url        Back link.
 */

use Hezarfen\Inc\Returns\Admin\Returns_Admin;
use Hezarfen\Inc\Returns\Core\Return_Event;
use Hezarfen\Inc\Returns\Core\Return_Pickup_Address;
use Hezarfen\Inc\Returns\Core\Return_Status;

defined( 'ABSPATH' ) || exit();

$hez_order = $request->get_order();

$hez_primary_actions = array(
	Return_Status::APPROVED  => __( 'Onayla', 'hezarfen-for-woocommerce' ),
	Return_Status::REJECTED  => __( 'Reddet', 'hezarfen-for-woocommerce' ),
	Return_Status::RECEIVED  => __( 'Ürünler ulaştı', 'hezarfen-for-woocommerce' ),
	Return_Status::COMPLETED => __( 'Tamamlandı olarak işaretle', 'hezarfen-for-woocommerce' ),
	Return_Status::CANCELLED => __( 'İptal et', 'hezarfen-for-woocommerce' ),
);

$hez_action_keys = array(
	Return_Status::APPROVED  => 'approve',
	Return_Status::REJECTED  => 'reject',
	Return_Status::RECEIVED  => 'received',
	Return_Status::COMPLETED => 'complete',
	Return_Status::CANCELLED => 'cancel',
);
?>
<div class="wrap hez-returns-admin hez-returns-admin--detail">

	<?php Returns_Admin::render_brand(); ?>

	<h1 class="wp-heading-inline">
		<?php echo esc_html( $request->get_return_number() ); ?>
		<span class="hez-admin-badge hez-admin-badge--<?php echo esc_attr( Return_Status::get_tone( $request->get_status() ) ); ?>">
			<?php echo esc_html( Return_Status::get_label( $request->get_status() ) ); ?>
		</span>
	</h1>
	<a href="<?php echo esc_url( $list_url ); ?>" class="page-title-action"><?php esc_html_e( 'Listeye dön', 'hezarfen-for-woocommerce' ); ?></a>
	<hr class="wp-header-end">

	<div class="hez-admin-grid">
		<div class="hez-admin-main">

			<div class="postbox">
				<div class="postbox-header"><h2><?php esc_html_e( 'İade edilen ürünler', 'hezarfen-for-woocommerce' ); ?></h2></div>
				<div class="inside">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Ürün', 'hezarfen-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'SKU', 'hezarfen-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Adet', 'hezarfen-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Sebep', 'hezarfen-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Tutar', 'hezarfen-for-woocommerce' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $request->get_items() as $hez_item ) : ?>
								<tr>
									<td>
										<?php if ( $hez_item->get_product_id() ) : ?>
											<a href="<?php echo esc_url( get_edit_post_link( $hez_item->get_product_id() ) ); ?>">
												<?php echo esc_html( $hez_item->get_product_name() ); ?>
											</a>
										<?php else : ?>
											<?php echo esc_html( $hez_item->get_product_name() ); ?>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $hez_item->get_sku() ? $hez_item->get_sku() : '—' ); ?></td>
									<td><?php echo esc_html( (string) $hez_item->get_quantity() ); ?></td>
									<td>
										<?php echo esc_html( $reasons->get_label( $hez_item->get_reason_key() ) ); ?>
										<?php if ( $hez_item->get_reason_note() ) : ?>
											<p class="description"><?php echo esc_html( $hez_item->get_reason_note() ); ?></p>
										<?php endif; ?>
									</td>
									<td><?php echo wp_kses_post( wc_price( $hez_item->get_line_total(), array( 'currency' => $request->get_currency() ) ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
						<tfoot>
							<tr>
								<th colspan="4"><?php esc_html_e( 'Toplam', 'hezarfen-for-woocommerce' ); ?></th>
								<th><?php echo wp_kses_post( wc_price( $request->get_refund_amount(), array( 'currency' => $request->get_currency() ) ) ); ?></th>
							</tr>
						</tfoot>
					</table>

					<?php if ( $request->get_customer_note() ) : ?>
						<p class="hez-admin-note">
							<strong><?php esc_html_e( 'Müşteri notu:', 'hezarfen-for-woocommerce' ); ?></strong>
							<?php echo esc_html( $request->get_customer_note() ); ?>
						</p>
					<?php endif; ?>
				</div>
			</div>

			<div class="postbox">
				<div class="postbox-header"><h2><?php esc_html_e( 'Talep geçmişi', 'hezarfen-for-woocommerce' ); ?></h2></div>
				<div class="inside">
					<ol class="hez-admin-timeline">
						<?php foreach ( $events as $hez_event ) : ?>
							<li class="hez-admin-timeline__item hez-admin-timeline__item--<?php echo esc_attr( hezarfen_returns_event_icon( $hez_event ) ); ?><?php echo $hez_event->is_customer_visible() ? '' : ' is-internal'; ?>">
								<p class="hez-admin-timeline__head">
									<strong>
										<?php
										if ( $hez_event->get_to_status() ) {
											echo esc_html( Return_Status::get_label( $hez_event->get_to_status() ) );
										} elseif ( Return_Event::TYPE_INFO_REQUEST === $hez_event->get_type() ) {
											esc_html_e( 'Ek bilgi istendi', 'hezarfen-for-woocommerce' );
										} elseif ( Return_Event::TYPE_INFO_RESPONSE === $hez_event->get_type() ) {
											esc_html_e( 'Müşteri yanıtı', 'hezarfen-for-woocommerce' );
										} elseif ( Return_Event::TYPE_SHIPPING === $hez_event->get_type() ) {
											esc_html_e( 'Kargo', 'hezarfen-for-woocommerce' );
										} else {
											esc_html_e( 'Not', 'hezarfen-for-woocommerce' );
										}
										?>
									</strong>
									<span class="hez-admin-timeline__meta">
										<?php echo esc_html( Returns_Admin::get_actor_label( $hez_event ) ); ?>
										&middot;
										<?php echo esc_html( hezarfen_returns_format_datetime( $hez_event->get_created_at() ) ); ?>
										<?php if ( ! $hez_event->is_customer_visible() ) : ?>
											&middot; <em><?php esc_html_e( 'sadece mağaza görür', 'hezarfen-for-woocommerce' ); ?></em>
										<?php endif; ?>
									</span>
								</p>
								<?php if ( $hez_event->get_message() ) : ?>
									<p class="hez-admin-timeline__message"><?php echo esc_html( $hez_event->get_message() ); ?></p>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ol>

					<form method="post" class="hez-admin-form">
						<?php wp_nonce_field( Returns_Admin::NONCE_ACTION ); ?>
						<input type="hidden" name="<?php echo esc_attr( Returns_Admin::ACTION_FIELD ); ?>" value="note">
						<input type="hidden" name="return_id" value="<?php echo esc_attr( (string) $request->get_id() ); ?>">

						<p>
							<label for="hez-admin-note" class="screen-reader-text"><?php esc_html_e( 'Not', 'hezarfen-for-woocommerce' ); ?></label>
							<textarea id="hez-admin-note" name="message" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Talep geçmişine not ekleyin', 'hezarfen-for-woocommerce' ); ?>"></textarea>
						</p>
						<p>
							<label>
								<input type="checkbox" name="customer_visible" value="1">
								<?php esc_html_e( 'Müşteri de görebilsin', 'hezarfen-for-woocommerce' ); ?>
							</label>
						</p>
						<p><button type="submit" class="button"><?php esc_html_e( 'Not ekle', 'hezarfen-for-woocommerce' ); ?></button></p>
					</form>
				</div>
			</div>
		</div>

		<div class="hez-admin-side">

			<div class="postbox">
				<div class="postbox-header"><h2><?php esc_html_e( 'İşlemler', 'hezarfen-for-woocommerce' ); ?></h2></div>
				<div class="inside">
					<?php if ( $transitions ) : ?>
						<div class="hez-admin-actions">
							<?php foreach ( $transitions as $hez_target ) : ?>
								<?php
								if ( ! isset( $hez_action_keys[ $hez_target ] ) ) {
									continue;
								}
								?>
								<form method="post">
									<?php wp_nonce_field( Returns_Admin::NONCE_ACTION ); ?>
									<input type="hidden" name="<?php echo esc_attr( Returns_Admin::ACTION_FIELD ); ?>" value="<?php echo esc_attr( $hez_action_keys[ $hez_target ] ); ?>">
									<input type="hidden" name="return_id" value="<?php echo esc_attr( (string) $request->get_id() ); ?>">
									<button type="submit" class="button <?php echo Return_Status::APPROVED === $hez_target ? 'button-primary' : ''; ?>">
										<?php echo esc_html( $hez_primary_actions[ $hez_target ] ); ?>
									</button>
								</form>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Bu talep kapandı, yeni bir işlem yapılamaz.', 'hezarfen-for-woocommerce' ); ?></p>
					<?php endif; ?>

					<?php if ( Return_Status::can_transition( $request->get_status(), Return_Status::INFO_REQUIRED ) ) : ?>
						<hr>
						<form method="post" class="hez-admin-form">
							<?php wp_nonce_field( Returns_Admin::NONCE_ACTION ); ?>
							<input type="hidden" name="<?php echo esc_attr( Returns_Admin::ACTION_FIELD ); ?>" value="request-info">
							<input type="hidden" name="return_id" value="<?php echo esc_attr( (string) $request->get_id() ); ?>">

							<p>
								<label for="hez-admin-info"><strong><?php esc_html_e( 'Müşteriden ek bilgi iste', 'hezarfen-for-woocommerce' ); ?></strong></label>
								<textarea id="hez-admin-info" name="message" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Örn. Ürünün kutusunun fotoğrafını paylaşır mısınız?', 'hezarfen-for-woocommerce' ); ?>"></textarea>
							</p>
							<p><button type="submit" class="button"><?php esc_html_e( 'Bilgi iste', 'hezarfen-for-woocommerce' ); ?></button></p>
						</form>
					<?php endif; ?>
				</div>
			</div>

			<div class="postbox">
				<div class="postbox-header"><h2><?php esc_html_e( 'Özet', 'hezarfen-for-woocommerce' ); ?></h2></div>
				<div class="inside">
					<ul class="hez-admin-summary">
						<li>
							<span><?php esc_html_e( 'Sipariş', 'hezarfen-for-woocommerce' ); ?></span>
							<strong>
								<?php if ( $hez_order ) : ?>
									<a href="<?php echo esc_url( $hez_order->get_edit_order_url() ); ?>">#<?php echo esc_html( $hez_order->get_order_number() ); ?></a>
								<?php else : ?>
									<?php echo esc_html( (string) $request->get_order_id() ); ?>
								<?php endif; ?>
							</strong>
						</li>
						<li>
							<span><?php esc_html_e( 'Müşteri', 'hezarfen-for-woocommerce' ); ?></span>
							<strong><?php echo esc_html( $request->get_customer_email() ); ?></strong>
						</li>
						<li>
							<span><?php esc_html_e( 'Oluşturulma', 'hezarfen-for-woocommerce' ); ?></span>
							<strong><?php echo esc_html( hezarfen_returns_format_datetime( $request->get_created_at() ) ); ?></strong>
						</li>
						<li>
							<span><?php esc_html_e( 'Gönderim', 'hezarfen-for-woocommerce' ); ?></span>
							<strong><?php echo esc_html( $shipping_method->get_label() ); ?></strong>
						</li>
					</ul>
				</div>
			</div>

			<div class="postbox">
				<div class="postbox-header"><h2><?php esc_html_e( 'Kargo', 'hezarfen-for-woocommerce' ); ?></h2></div>
				<div class="inside">
					<?php if ( $request->get_tracking_number() ) : ?>
						<p class="hez-admin-tracking">
							<strong><?php echo esc_html( $request->get_tracking_number() ); ?></strong>
							<?php if ( $request->get_courier() ) : ?>
								<span class="description"><?php echo esc_html( $request->get_courier() ); ?></span>
							<?php endif; ?>
						</p>
					<?php endif; ?>

					<?php if ( $request->get_pickup_date() ) : ?>
						<p class="hez-admin-pickup">
							<?php
							printf(
								/* translators: %s: pickup date the customer picked. */
								esc_html__( 'Müşterinin seçtiği kargo alım günü: %s', 'hezarfen-for-woocommerce' ),
								'<strong>' . esc_html( hezarfen_returns_format_date( $request->get_pickup_date() ) ) . '</strong>'
							);
							?>
						</p>
					<?php elseif ( $shipping_method->requires_customer_booking() && Return_Status::APPROVED === $request->get_status() ) : ?>
						<p class="hez-admin-pickup description">
							<?php esc_html_e( 'Müşteri henüz kargo alım günü seçmedi; iade barkodu seçimden sonra oluşturulur.', 'hezarfen-for-woocommerce' ); ?>
						</p>
					<?php endif; ?>

					<?php if ( $shipping_method->requires_pickup_address() && $request->has_pickup_address() ) : ?>
						<p class="hez-admin-pickup-address">
							<span class="description"><?php esc_html_e( 'Kargo alım adresi (müşterinin onayladığı):', 'hezarfen-for-woocommerce' ); ?></span><br>
							<?php echo nl2br( esc_html( Return_Pickup_Address::format( $request->get_pickup_address() ) ) ); ?>
						</p>
					<?php endif; ?>

					<form method="post" class="hez-admin-form">
						<?php wp_nonce_field( Returns_Admin::NONCE_ACTION ); ?>
						<input type="hidden" name="<?php echo esc_attr( Returns_Admin::ACTION_FIELD ); ?>" value="tracking">
						<input type="hidden" name="return_id" value="<?php echo esc_attr( (string) $request->get_id() ); ?>">

						<p>
							<label for="hez-admin-courier"><?php esc_html_e( 'Kargo firması', 'hezarfen-for-woocommerce' ); ?></label>
							<input type="text" id="hez-admin-courier" name="courier" class="widefat" value="<?php echo esc_attr( $request->get_courier() ); ?>">
						</p>
						<p>
							<label for="hez-admin-tracking"><?php esc_html_e( 'Takip numarası', 'hezarfen-for-woocommerce' ); ?></label>
							<input type="text" id="hez-admin-tracking" name="tracking_number" class="widefat" value="<?php echo esc_attr( $request->get_tracking_number() ); ?>">
						</p>
						<p><button type="submit" class="button"><?php esc_html_e( 'Kaydet', 'hezarfen-for-woocommerce' ); ?></button></p>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
