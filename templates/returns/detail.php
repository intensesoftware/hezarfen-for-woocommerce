<?php
/**
 * Return request detail view, shared by the account area and the guest page.
 *
 * Override at yourtheme/hezarfen/returns/detail.php.
 *
 * @package Hezarfen\Inc\Returns
 *
 * @var \Hezarfen\Inc\Returns\Core\Return_Request  $request         The request.
 * @var \Hezarfen\Inc\Returns\Core\Return_Event[]  $events          Customer visible timeline.
 * @var \Hezarfen\Inc\Returns\Core\Return_Reasons  $reasons         Reason registry.
 * @var \Hezarfen\Inc\Returns\Shipping\Return_Shipping_Method_Interface $shipping_method Shipping method.
 * @var string[]                                   $progress_steps  Ordered progress statuses.
 * @var string                                     $back_url        Link back to the list, empty for guests.
 * @var bool                                       $is_guest_view   Whether the guest page is rendering.
 */

use Hezarfen\Inc\Returns\Core\Return_Event;
use Hezarfen\Inc\Returns\Core\Return_Settings;
use Hezarfen\Inc\Returns\Core\Return_Status;
use Hezarfen\Inc\Returns\Frontend\Return_Access;
use Hezarfen\Inc\Returns\Frontend\Return_Form_Handler;

defined( 'ABSPATH' ) || exit();

$hez_status       = $request->get_status();
$hez_is_derailed  = in_array( $hez_status, array( Return_Status::REJECTED, Return_Status::CANCELLED ), true );
$hez_current_step = array_search( $hez_status, $progress_steps, true );
$hez_token        = $is_guest_view ? $request->get_access_token() : '';
$hez_info_pending = Return_Status::INFO_REQUIRED === $hez_status;
$hez_order        = $request->get_order();
$hez_order_number = $hez_order ? $hez_order->get_order_number() : (string) $request->get_order_id();
?>
<div class="hez-returns hez-returns--detail">

	<header class="hez-returns__header">
		<?php if ( $back_url ) : ?>
			<a class="hez-returns__back" href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'İadelerim', 'hezarfen-for-woocommerce' ); ?></a>
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
				<?php if ( $hez_token ) : ?>
					<input type="hidden" name="<?php echo esc_attr( Return_Access::QUERY_KEY ); ?>" value="<?php echo esc_attr( $hez_token ); ?>">
				<?php endif; ?>

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
				<p class="hez-tracking">
					<span class="hez-tracking__label"><?php esc_html_e( 'Kargo takip numarası', 'hezarfen-for-woocommerce' ); ?></span>
					<span class="hez-tracking__value"><?php echo esc_html( $request->get_tracking_number() ); ?></span>
				</p>
			<?php elseif ( $shipping_method->requires_customer_tracking() ) : ?>
				<form method="post" class="hez-inline-form hez-inline-form--tracking">
					<?php wp_nonce_field( 'hezarfen_returns_' . Return_Form_Handler::ACTION_TRACKING, Return_Form_Handler::NONCE_FIELD ); ?>
					<input type="hidden" name="<?php echo esc_attr( Return_Form_Handler::ACTION_FIELD ); ?>" value="<?php echo esc_attr( Return_Form_Handler::ACTION_TRACKING ); ?>">
					<input type="hidden" name="return_id" value="<?php echo esc_attr( $request->get_id() ); ?>">
					<?php if ( $hez_token ) : ?>
						<input type="hidden" name="<?php echo esc_attr( Return_Access::QUERY_KEY ); ?>" value="<?php echo esc_attr( $hez_token ); ?>">
					<?php endif; ?>

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
			<?php if ( $hez_token ) : ?>
				<input type="hidden" name="<?php echo esc_attr( Return_Access::QUERY_KEY ); ?>" value="<?php echo esc_attr( $hez_token ); ?>">
			<?php endif; ?>

			<button type="submit" class="hez-btn hez-btn--danger-ghost"><?php esc_html_e( 'Talebi iptal et', 'hezarfen-for-woocommerce' ); ?></button>
		</form>
	<?php endif; ?>
</div>
