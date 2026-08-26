<?php
/**
 * Return request detail view in the account area.
 *
 * Override at yourtheme/hezarfen/returns/detail.php.
 *
 * @package Hezarfen\Inc\Returns
 *
 * @var \Hezarfen\Inc\Returns\Core\Return_Request  $request         The request.
 * @var \Hezarfen\Inc\Returns\Core\Return_Event[]  $events          Customer visible timeline.
 * @var \Hezarfen\Inc\Returns\Core\Return_Reasons  $reasons         Reason registry.
 * @var \Hezarfen\Inc\Returns\Shipping\Return_Shipping_Method_Interface $shipping_method Shipping method.
 * @var array{needed: bool, options: array<string, string>, error: string} $booking Pickup day picker state.
 * @var array<string, string>                     $pickup_address  Address the courier collects from.
 * @var string[]                                   $progress_steps  Ordered progress statuses.
 * @var string                                     $back_url        Link back to the related order.
 */

use Hezarfen\Inc\Returns\Core\Return_Event;
use Hezarfen\Inc\Returns\Core\Return_Pickup_Address;
use Hezarfen\Inc\Returns\Core\Return_Settings;
use Hezarfen\Inc\Returns\Core\Return_Status;
use Hezarfen\Inc\Returns\Frontend\Return_Form_Handler;

defined( 'ABSPATH' ) || exit();

$hez_status       = $request->get_status();
$hez_is_derailed  = in_array( $hez_status, array( Return_Status::REJECTED, Return_Status::CANCELLED ), true );
$hez_current_step = Return_Status::get_progress_index( $hez_status, $progress_steps );
$hez_info_pending = Return_Status::INFO_REQUIRED === $hez_status;
$hez_order        = $request->get_order();
$hez_order_number = $hez_order ? $hez_order->get_order_number() : (string) $request->get_order_id();
?>
<div class="hez-returns hez-returns--detail">

	<header class="hez-returns__header">
		<?php if ( $back_url ) : ?>
			<a class="hez-returns__back" href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Siparişe dön', 'hezarfen-for-woocommerce' ); ?></a>
		<?php endif; ?>

		<div class="hez-returns__headline">
			<h2 class="hez-returns__title"><?php echo esc_html( $request->get_return_number() ); ?></h2>
			<?php echo wp_kses_post( hezarfen_returns_status_badge( $hez_status, true ) ); ?>
		</div>

		<p class="hez-returns__subtitle">
			<?php
			printf(
				/* translators: 1: order number, 2: request date. */
				esc_html__( '%1$s numaralı sipariş · %2$s tarihinde oluşturuldu', 'hezarfen-for-woocommerce' ),
				esc_html( $hez_order_number ),
				esc_html( hezarfen_returns_format_datetime( $request->get_created_at() ) )
			);
			?>
		</p>
	</header>

	<?php if ( ! $hez_is_derailed ) : ?>
		<ol class="hez-progress" aria-label="<?php esc_attr_e( 'İade durumu', 'hezarfen-for-woocommerce' ); ?>">
			<?php foreach ( $progress_steps as $hez_index => $hez_step ) : ?>
				<?php
				$hez_state = 'upcoming';

				if ( false !== $hez_current_step && $hez_index < $hez_current_step ) {
					$hez_state = 'done';
				} elseif ( $hez_index === $hez_current_step ) {
					$hez_state = 'current';
				}
				?>
				<li class="hez-progress__step hez-progress__step--<?php echo esc_attr( $hez_state ); ?>"
					<?php echo 'current' === $hez_state ? 'aria-current="step"' : ''; ?>>
					<span class="hez-progress__dot" aria-hidden="true"></span>
					<span class="hez-progress__label"><?php echo esc_html( Return_Status::get_customer_label( $hez_step ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php else : ?>
		<div class="hez-callout hez-callout--<?php echo esc_attr( Return_Status::get_tone( $hez_status ) ); ?>">
			<p><?php echo esc_html( Return_Status::get_customer_label( $hez_status ) ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $hez_info_pending ) : ?>
		<?php
		$hez_info_event = null;

		foreach ( array_reverse( $events ) as $hez_event ) {
			if ( Return_Event::TYPE_INFO_REQUEST === $hez_event->get_type() ) {
				$hez_info_event = $hez_event;
				break;
			}
		}
		?>
		<section class="hez-panel hez-panel--action">
			<h3 class="hez-panel__title"><?php esc_html_e( 'Sizden ek bilgi bekleniyor', 'hezarfen-for-woocommerce' ); ?></h3>

			<?php if ( $hez_info_event && $hez_info_event->get_message() ) : ?>
				<blockquote class="hez-quote"><?php echo esc_html( $hez_info_event->get_message() ); ?></blockquote>
			<?php endif; ?>

			<form method="post" class="hez-inline-form">
				<?php wp_nonce_field( 'hezarfen_returns_' . Return_Form_Handler::ACTION_INFO, Return_Form_Handler::NONCE_FIELD ); ?>
				<input type="hidden" name="<?php echo esc_attr( Return_Form_Handler::ACTION_FIELD ); ?>" value="<?php echo esc_attr( Return_Form_Handler::ACTION_INFO ); ?>">
				<input type="hidden" name="return_id" value="<?php echo esc_attr( $request->get_id() ); ?>">

				<p class="hez-field">
					<label for="hez-info-response"><?php esc_html_e( 'Yanıtınız', 'hezarfen-for-woocommerce' ); ?></label>
					<textarea id="hez-info-response" name="info_response" class="hez-input hez-textarea" rows="3" maxlength="1000" required></textarea>
				</p>

				<button type="submit" class="hez-btn hez-btn--primary"><?php esc_html_e( 'Gönder', 'hezarfen-for-woocommerce' ); ?></button>
			</form>
		</section>
	<?php endif; ?>

	<?php if ( in_array( $hez_status, array( Return_Status::APPROVED, Return_Status::SHIPPED ), true ) ) : ?>
		<section class="hez-panel">
			<h3 class="hez-panel__title"><?php esc_html_e( 'Gönderim', 'hezarfen-for-woocommerce' ); ?></h3>

			<div class="hez-panel__prose"><?php echo wp_kses_post( $shipping_method->get_customer_instructions( $request ) ); ?></div>

			<?php if ( $shipping_method->requires_customer_tracking() && Return_Settings::has_return_address() ) : ?>
				<div class="hez-address">
					<span class="hez-address__label"><?php esc_html_e( 'İade adresi', 'hezarfen-for-woocommerce' ); ?></span>
					<address class="hez-address__body"><?php echo nl2br( esc_html( Return_Settings::get_formatted_return_address() ) ); ?></address>
				</div>
			<?php endif; ?>

			<?php if ( $request->get_tracking_number() ) : ?>
				<?php
				$hez_cancel_deadline = $request->get_booking_cancel_deadline();
				$hez_can_unbook      = $request->is_booking_cancellable_by_customer();
				?>
				<div class="hez-code">
					<div class="hez-code__head">
						<span class="hez-code__label"><?php esc_html_e( 'İade kargo kodunuz', 'hezarfen-for-woocommerce' ); ?></span>
						<span class="hez-return-badge hez-return-badge--<?php echo $hez_can_unbook ? 'info' : 'success'; ?>">
							<?php
							echo $hez_can_unbook
								? esc_html__( 'Randevu alındı', 'hezarfen-for-woocommerce' )
								: esc_html__( 'Kurye yolda', 'hezarfen-for-woocommerce' );
							?>
						</span>
					</div>

					<div class="hez-code__row">
						<code class="hez-code__value" data-hez-copy-source><?php echo esc_html( $request->get_tracking_number() ); ?></code>
						<button
							type="button"
							class="hez-btn hez-btn--ghost hez-btn--small hez-code__copy"
							data-hez-copy
							aria-label="<?php esc_attr_e( 'İade kargo kodunu kopyala', 'hezarfen-for-woocommerce' ); ?>"
						><?php esc_html_e( 'Kopyala', 'hezarfen-for-woocommerce' ); ?></button>
					</div>

					<p class="hez-code__hint">
						<?php esc_html_e( 'Kurye geldiğinde bu kodu görevliye söyleyin. Yazdırmanıza veya etiket çıkarmanıza gerek yok.', 'hezarfen-for-woocommerce' ); ?>
					</p>

					<?php if ( $request->get_pickup_date() ) : ?>
						<p class="hez-code__meta">
							<?php
							printf(
								/* translators: %s: pickup date. */
								esc_html__( 'Kargonuz %s tarihinde adresinizden teslim alınacak.', 'hezarfen-for-woocommerce' ),
								'<strong>' . esc_html( hezarfen_returns_format_date( $request->get_pickup_date() ) ) . '</strong>'
							);
							?>
						</p>
					<?php endif; ?>

					<?php if ( $hez_can_unbook ) : ?>
						<form method="post" class="hez-code__cancel" data-hez-confirm-unbook>
							<?php wp_nonce_field( 'hezarfen_returns_' . Return_Form_Handler::ACTION_UNBOOK, Return_Form_Handler::NONCE_FIELD ); ?>
							<input type="hidden" name="<?php echo esc_attr( Return_Form_Handler::ACTION_FIELD ); ?>" value="<?php echo esc_attr( Return_Form_Handler::ACTION_UNBOOK ); ?>">
							<input type="hidden" name="return_id" value="<?php echo esc_attr( $request->get_id() ); ?>">

							<button type="submit" class="hez-btn hez-btn--danger-ghost hez-btn--small">
								<?php esc_html_e( 'Kargo randevusunu iptal et', 'hezarfen-for-woocommerce' ); ?>
							</button>
							<span class="hez-code__cancel-hint">
								<?php
								printf(
									/* translators: %s: last moment the pickup can be called off. */
									esc_html__( '%s tarihine kadar iptal edip başka bir gün seçebilirsiniz.', 'hezarfen-for-woocommerce' ),
									esc_html( wp_date( get_option( 'date_format' ) . ' H:i', $hez_cancel_deadline ) )
								);
								?>
							</span>
						</form>
					<?php elseif ( $hez_cancel_deadline ) : ?>
						<p class="hez-code__cancel-hint hez-code__cancel-hint--closed">
							<?php esc_html_e( 'Alım günü geldiği için randevu artık iptal edilemiyor. Kurye ile ilgili bir sorun olursa mağazayla iletişime geçin.', 'hezarfen-for-woocommerce' ); ?>
						</p>
					<?php endif; ?>
				</div>
			<?php elseif ( $booking['needed'] ) : ?>
				<?php
				// The day list is fetched for this address's district, so it
				// is shown next to the picker rather than somewhere further
				// down: a customer who corrects the district has to see the
				// days change with it.
				$hez_address_open = ! Return_Pickup_Address::is_complete( $pickup_address );
				?>
				<details class="hez-pickup-address" <?php echo $hez_address_open ? 'open' : ''; ?>>
					<summary class="hez-pickup-address__summary">
						<span class="hez-pickup-address__label"><?php esc_html_e( 'Kargo alım adresi', 'hezarfen-for-woocommerce' ); ?></span>
						<span class="hez-pickup-address__value">
							<?php
							echo $hez_address_open
								? esc_html__( 'Adresinizi tamamlayın', 'hezarfen-for-woocommerce' )
								: esc_html( str_replace( "\n", ' · ', Return_Pickup_Address::format( $pickup_address ) ) );
							?>
						</span>
						<span class="hez-pickup-address__toggle"><?php esc_html_e( 'Değiştir', 'hezarfen-for-woocommerce' ); ?></span>
					</summary>

					<form method="post" class="hez-pickup-address__form">
						<?php wp_nonce_field( 'hezarfen_returns_' . Return_Form_Handler::ACTION_ADDRESS, Return_Form_Handler::NONCE_FIELD ); ?>
						<input type="hidden" name="<?php echo esc_attr( Return_Form_Handler::ACTION_FIELD ); ?>" value="<?php echo esc_attr( Return_Form_Handler::ACTION_ADDRESS ); ?>">
						<input type="hidden" name="return_id" value="<?php echo esc_attr( $request->get_id() ); ?>">

						<?php hezarfen_returns_get_template( 'returns/pickup-address-fields.php', array( 'address' => $pickup_address ) ); ?>

						<button type="submit" class="hez-btn hez-btn--ghost hez-btn--small"><?php esc_html_e( 'Adresi kaydet', 'hezarfen-for-woocommerce' ); ?></button>
					</form>
				</details>

				<?php if ( $hez_address_open ) : ?>
					<div class="hez-callout hez-callout--info">
						<p><?php esc_html_e( 'Kargo alım gününü seçebilmeniz için önce alım adresinizi tamamlayın.', 'hezarfen-for-woocommerce' ); ?></p>
					</div>
				<?php elseif ( $booking['options'] ) : ?>
					<form method="post" class="hez-inline-form hez-inline-form--booking">
						<?php wp_nonce_field( 'hezarfen_returns_' . Return_Form_Handler::ACTION_BOOKING, Return_Form_Handler::NONCE_FIELD ); ?>
						<input type="hidden" name="<?php echo esc_attr( Return_Form_Handler::ACTION_FIELD ); ?>" value="<?php echo esc_attr( Return_Form_Handler::ACTION_BOOKING ); ?>">
						<input type="hidden" name="return_id" value="<?php echo esc_attr( $request->get_id() ); ?>">

						<p class="hez-field">
							<label for="hez-pickup-date"><?php esc_html_e( 'Kargonuzun alınacağı gün', 'hezarfen-for-woocommerce' ); ?></label>
							<select id="hez-pickup-date" name="pickup_date" class="hez-input hez-select" required>
								<option value=""><?php esc_html_e( 'Gün seçin', 'hezarfen-for-woocommerce' ); ?></option>
								<?php foreach ( $booking['options'] as $hez_value => $hez_label ) : ?>
									<option value="<?php echo esc_attr( $hez_value ); ?>"><?php echo esc_html( $hez_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>

						<button type="submit" class="hez-btn hez-btn--primary"><?php esc_html_e( 'İade kargomu oluştur', 'hezarfen-for-woocommerce' ); ?></button>
					</form>
				<?php else : ?>
					<div class="hez-callout hez-callout--warning">
						<p>
							<?php
							echo esc_html(
								$booking['error']
									? $booking['error']
									: __( 'Şu an uygun bir kargo alım günü bulunamadı. Lütfen daha sonra tekrar deneyin.', 'hezarfen-for-woocommerce' )
							);
							?>
						</p>
					</div>
				<?php endif; ?>
			<?php elseif ( $shipping_method->requires_customer_tracking() ) : ?>
				<form method="post" class="hez-inline-form hez-inline-form--tracking">
					<?php wp_nonce_field( 'hezarfen_returns_' . Return_Form_Handler::ACTION_TRACKING, Return_Form_Handler::NONCE_FIELD ); ?>
					<input type="hidden" name="<?php echo esc_attr( Return_Form_Handler::ACTION_FIELD ); ?>" value="<?php echo esc_attr( Return_Form_Handler::ACTION_TRACKING ); ?>">
					<input type="hidden" name="return_id" value="<?php echo esc_attr( $request->get_id() ); ?>">

					<p class="hez-field">
						<label for="hez-courier"><?php esc_html_e( 'Kargo firması', 'hezarfen-for-woocommerce' ); ?></label>
						<input type="text" id="hez-courier" name="courier" class="hez-input" maxlength="64">
					</p>
					<p class="hez-field">
						<label for="hez-tracking-number"><?php esc_html_e( 'Takip numarası', 'hezarfen-for-woocommerce' ); ?></label>
						<input type="text" id="hez-tracking-number" name="tracking_number" class="hez-input" maxlength="100" required>
					</p>

					<button type="submit" class="hez-btn hez-btn--primary"><?php esc_html_e( 'Kargo bilgisini kaydet', 'hezarfen-for-woocommerce' ); ?></button>
				</form>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<section class="hez-panel">
		<h3 class="hez-panel__title"><?php esc_html_e( 'İade edilen ürünler', 'hezarfen-for-woocommerce' ); ?></h3>

		<table class="hez-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Ürün', 'hezarfen-for-woocommerce' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Adet', 'hezarfen-for-woocommerce' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Sebep', 'hezarfen-for-woocommerce' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Tutar', 'hezarfen-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $request->get_items() as $hez_item ) : ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Ürün', 'hezarfen-for-woocommerce' ); ?>"><?php echo esc_html( $hez_item->get_product_name() ); ?></td>
						<td data-label="<?php esc_attr_e( 'Adet', 'hezarfen-for-woocommerce' ); ?>"><?php echo esc_html( $hez_item->get_quantity() ); ?></td>
						<td data-label="<?php esc_attr_e( 'Sebep', 'hezarfen-for-woocommerce' ); ?>">
							<?php echo esc_html( $reasons->get_label( $hez_item->get_reason_key() ) ); ?>
							<?php if ( $hez_item->get_reason_note() ) : ?>
								<span class="hez-table__note"><?php echo esc_html( $hez_item->get_reason_note() ); ?></span>
							<?php endif; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Tutar', 'hezarfen-for-woocommerce' ); ?>">
							<?php echo wp_kses_post( wc_price( $hez_item->get_line_total(), array( 'currency' => $request->get_currency() ) ) ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th scope="row" colspan="3"><?php esc_html_e( 'Toplam', 'hezarfen-for-woocommerce' ); ?></th>
					<td><?php echo wp_kses_post( wc_price( $request->get_refund_amount(), array( 'currency' => $request->get_currency() ) ) ); ?></td>
				</tr>
			</tfoot>
		</table>

		<?php if ( $request->get_customer_note() ) : ?>
			<p class="hez-panel__note">
				<strong><?php esc_html_e( 'Notunuz:', 'hezarfen-for-woocommerce' ); ?></strong>
				<?php echo esc_html( $request->get_customer_note() ); ?>
			</p>
		<?php endif; ?>
	</section>

	<section class="hez-panel">
		<h3 class="hez-panel__title"><?php esc_html_e( 'Talep geçmişi', 'hezarfen-for-woocommerce' ); ?></h3>

		<ol class="hez-timeline">
			<?php foreach ( $events as $hez_event ) : ?>
				<li class="hez-timeline__item hez-timeline__item--<?php echo esc_attr( hezarfen_returns_event_icon( $hez_event ) ); ?>">
					<span class="hez-timeline__dot" aria-hidden="true"></span>
					<div class="hez-timeline__body">
						<p class="hez-timeline__title">
							<?php
							if ( $hez_event->get_to_status() ) {
								echo esc_html( Return_Status::get_customer_label( $hez_event->get_to_status() ) );
							} elseif ( Return_Event::TYPE_INFO_REQUEST === $hez_event->get_type() ) {
								esc_html_e( 'Ek bilgi istendi', 'hezarfen-for-woocommerce' );
							} elseif ( Return_Event::TYPE_INFO_RESPONSE === $hez_event->get_type() ) {
								esc_html_e( 'Yanıtınız iletildi', 'hezarfen-for-woocommerce' );
							} elseif ( Return_Event::TYPE_SHIPPING === $hez_event->get_type() ) {
								esc_html_e( 'Kargo bilgisi', 'hezarfen-for-woocommerce' );
							} else {
								esc_html_e( 'Güncelleme', 'hezarfen-for-woocommerce' );
							}
							?>
						</p>
						<?php if ( $hez_event->get_message() ) : ?>
							<p class="hez-timeline__message"><?php echo esc_html( $hez_event->get_message() ); ?></p>
						<?php endif; ?>
						<time class="hez-timeline__time" datetime="<?php echo esc_attr( $hez_event->get_created_at() ); ?>">
							<?php echo esc_html( hezarfen_returns_format_datetime( $hez_event->get_created_at() ) ); ?>
						</time>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>
	</section>

	<?php if ( $request->is_cancellable_by_customer() ) : ?>
		<form method="post" class="hez-returns__cancel" data-hez-confirm-cancel>
			<?php wp_nonce_field( 'hezarfen_returns_' . Return_Form_Handler::ACTION_CANCEL, Return_Form_Handler::NONCE_FIELD ); ?>
			<input type="hidden" name="<?php echo esc_attr( Return_Form_Handler::ACTION_FIELD ); ?>" value="<?php echo esc_attr( Return_Form_Handler::ACTION_CANCEL ); ?>">
			<input type="hidden" name="return_id" value="<?php echo esc_attr( $request->get_id() ); ?>">

			<button type="submit" class="hez-btn hez-btn--danger-ghost"><?php esc_html_e( 'Talebi iptal et', 'hezarfen-for-woocommerce' ); ?></button>
		</form>
	<?php endif; ?>
</div>
