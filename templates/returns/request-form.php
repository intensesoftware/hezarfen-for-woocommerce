<?php
/**
 * Return request form.
 *
 * Override at yourtheme/hezarfen/returns/request-form.php.
 *
 * @package Hezarfen\Inc\Returns
 *
 * @var WC_Order                                              $order           Order being returned from.
 * @var array<int, array<string, mixed>>                      $lines           Returnable lines keyed by order item ID.
 * @var \Hezarfen\Inc\Returns\Core\Return_Reasons             $reasons         Reason registry.
 * @var \Hezarfen\Inc\Returns\Shipping\Return_Shipping_Method_Interface $shipping_method Active shipping method.
 * @var int                                                   $deadline        Return window deadline as a Unix timestamp.
 * @var string                                                $cancel_url      Where the cancel link goes.
 * @var array<string, string>                                 $pickup_address  Pickup address prefilled from the order.
 */

use Hezarfen\Inc\Returns\Core\Return_Settings;
use Hezarfen\Inc\Returns\Frontend\Return_Form_Handler;

defined( 'ABSPATH' ) || exit();

$hez_reason_choices = $reasons->get_choices();
?>
<div class="hez-returns hez-returns--form">

	<header class="hez-returns__header">
		<h2 class="hez-returns__title">
			<?php
			printf(
				/* translators: %s: order number. */
				esc_html__( '%s numaralı sipariş için iade talebi', 'hezarfen-for-woocommerce' ),
				esc_html( $order->get_order_number() )
			);
			?>
		</h2>
		<p class="hez-returns__subtitle">
			<?php esc_html_e( 'İade etmek istediğiniz ürünleri ve adetlerini seçin. Talebiniz onaylandığında size e-posta ile bilgi vereceğiz.', 'hezarfen-for-woocommerce' ); ?>
		</p>
		<?php if ( $deadline ) : ?>
			<p class="hez-returns__deadline">
				<?php
				printf(
					/* translators: %s: formatted deadline date. */
					esc_html__( 'Bu sipariş için son iade tarihi: %s', 'hezarfen-for-woocommerce' ),
					esc_html( wp_date( get_option( 'date_format' ), $deadline ) )
				);
				?>
			</p>
		<?php endif; ?>
	</header>

	<form method="post" class="hez-return-form" data-hez-return-form>
		<?php wp_nonce_field( 'hezarfen_returns_' . Return_Form_Handler::ACTION_CREATE, Return_Form_Handler::NONCE_FIELD ); ?>
		<input type="hidden" name="<?php echo esc_attr( Return_Form_Handler::ACTION_FIELD ); ?>" value="<?php echo esc_attr( Return_Form_Handler::ACTION_CREATE ); ?>">
		<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>">

		<section class="hez-step" aria-labelledby="hez-step-items">
			<div class="hez-step__head">
				<span class="hez-step__num" aria-hidden="true">1</span>
				<h3 class="hez-step__title" id="hez-step-items"><?php esc_html_e( 'Ürünleri seçin', 'hezarfen-for-woocommerce' ); ?></h3>
			</div>

			<ul class="hez-items" data-hez-items>
				<?php foreach ( $lines as $hez_item_id => $hez_line ) : ?>
					<?php
					$hez_item    = $hez_line['item'];
					$hez_product = is_callable( array( $hez_item, 'get_product' ) ) ? $hez_item->get_product() : null;
					$hez_field   = 'items[' . $hez_item_id . ']';
					$hez_dom_id  = 'hez-item-' . $hez_item_id;
					?>
					<li class="hez-item" data-hez-item>
						<div class="hez-item__row">
							<input
								type="checkbox"
								class="hez-item__checkbox"
								id="<?php echo esc_attr( $hez_dom_id ); ?>"
								name="<?php echo esc_attr( $hez_field ); ?>[selected]"
								value="1"
								data-hez-item-toggle
							>
							<label class="hez-item__label" for="<?php echo esc_attr( $hez_dom_id ); ?>">
								<?php if ( $hez_product && $hez_product->get_image_id() ) : ?>
									<span class="hez-item__thumb"><?php echo wp_kses_post( $hez_product->get_image( 'woocommerce_gallery_thumbnail' ) ); ?></span>
								<?php endif; ?>
								<span class="hez-item__text">
									<span class="hez-item__name"><?php echo esc_html( $hez_item->get_name() ); ?></span>
									<span class="hez-item__meta">
										<?php
										printf(
											/* translators: %d: quantity available for return. */
											esc_html__( 'İade edilebilir: %d adet', 'hezarfen-for-woocommerce' ),
											absint( $hez_line['max_qty'] )
										);
										?>
										<span class="hez-item__price"><?php echo wp_kses_post( wc_price( $hez_line['unit_price'], array( 'currency' => $order->get_currency() ) ) ); ?></span>
									</span>
								</span>
							</label>
						</div>

						<div class="hez-item__details" data-hez-item-details>
							<p class="hez-field hez-field--qty">
								<label for="<?php echo esc_attr( $hez_dom_id . '-qty' ); ?>"><?php esc_html_e( 'Adet', 'hezarfen-for-woocommerce' ); ?></label>
								<input
									type="number"
									id="<?php echo esc_attr( $hez_dom_id . '-qty' ); ?>"
									name="<?php echo esc_attr( $hez_field ); ?>[quantity]"
									class="hez-input hez-input--qty"
									value="1"
									min="1"
									max="<?php echo esc_attr( $hez_line['max_qty'] ); ?>"
									step="1"
									inputmode="numeric"
								>
							</p>

							<p class="hez-field hez-field--reason">
								<label for="<?php echo esc_attr( $hez_dom_id . '-reason' ); ?>"><?php esc_html_e( 'İade sebebi', 'hezarfen-for-woocommerce' ); ?></label>
								<select
									id="<?php echo esc_attr( $hez_dom_id . '-reason' ); ?>"
									name="<?php echo esc_attr( $hez_field ); ?>[reason]"
									class="hez-input hez-select"
									data-hez-reason
								>
									<option value=""><?php esc_html_e( 'Sebep seçin', 'hezarfen-for-woocommerce' ); ?></option>
									<?php foreach ( $hez_reason_choices as $hez_key => $hez_label ) : ?>
										<option value="<?php echo esc_attr( $hez_key ); ?>"><?php echo esc_html( $hez_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</p>

							<p class="hez-field hez-field--note" data-hez-note-field>
								<label for="<?php echo esc_attr( $hez_dom_id . '-note' ); ?>"><?php esc_html_e( 'Açıklama', 'hezarfen-for-woocommerce' ); ?></label>
								<textarea
									id="<?php echo esc_attr( $hez_dom_id . '-note' ); ?>"
									name="<?php echo esc_attr( $hez_field ); ?>[note]"
									class="hez-input hez-textarea"
									rows="2"
									maxlength="500"
									placeholder="<?php esc_attr_e( 'Sorunu birkaç cümleyle anlatın', 'hezarfen-for-woocommerce' ); ?>"
								></textarea>
							</p>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>

		<section class="hez-step" aria-labelledby="hez-step-shipping">
			<div class="hez-step__head">
				<span class="hez-step__num" aria-hidden="true">2</span>
				<h3 class="hez-step__title" id="hez-step-shipping"><?php esc_html_e( 'Gönderim', 'hezarfen-for-woocommerce' ); ?></h3>
			</div>

			<div class="hez-shipping-card">
				<p class="hez-shipping-card__title"><?php echo esc_html( $shipping_method->get_label() ); ?></p>
				<p class="hez-shipping-card__desc"><?php echo esc_html( $shipping_method->get_description() ); ?></p>

				<?php if ( $shipping_method->requires_customer_tracking() && Return_Settings::has_return_address() ) : ?>
					<div class="hez-address">
						<span class="hez-address__label"><?php esc_html_e( 'İade adresi', 'hezarfen-for-woocommerce' ); ?></span>
						<address class="hez-address__body"><?php echo nl2br( esc_html( Return_Settings::get_formatted_return_address() ) ); ?></address>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $shipping_method->requires_pickup_address() ) : ?>
				<div class="hez-returns__pickup">
					<p class="hez-returns__pickup-title"><?php esc_html_e( 'Kargo alım adresi', 'hezarfen-for-woocommerce' ); ?></p>
					<p class="hez-returns__pickup-hint"><?php esc_html_e( 'Kurye ürünleri bu adresten teslim alacak. Sipariş adresiniz hazır geldi; farklı bir adresten alınmasını isterseniz değiştirin.', 'hezarfen-for-woocommerce' ); ?></p>

					<?php hezarfen_returns_get_template( 'returns/pickup-address-fields.php', array( 'address' => $pickup_address ) ); ?>
				</div>
			<?php endif; ?>
		</section>

		<section class="hez-step" aria-labelledby="hez-step-note">
			<div class="hez-step__head">
				<span class="hez-step__num" aria-hidden="true">3</span>
				<h3 class="hez-step__title" id="hez-step-note"><?php esc_html_e( 'Eklemek istedikleriniz', 'hezarfen-for-woocommerce' ); ?></h3>
			</div>

			<p class="hez-field">
				<label for="hez-customer-note" class="screen-reader-text"><?php esc_html_e( 'Talebinize eklemek istediğiniz not', 'hezarfen-for-woocommerce' ); ?></label>
				<textarea
					id="hez-customer-note"
					name="customer_note"
					class="hez-input hez-textarea"
					rows="3"
					maxlength="1000"
					placeholder="<?php esc_attr_e( 'İsteğe bağlı. Talebinizle ilgili eklemek istediğiniz bir şey varsa yazabilirsiniz.', 'hezarfen-for-woocommerce' ); ?>"
				></textarea>
			</p>
		</section>

		<div class="hez-return-form__footer">
			<p class="hez-return-form__summary" data-hez-summary aria-live="polite"></p>
			<div class="hez-return-form__actions">
				<?php if ( $cancel_url ) : ?>
					<a class="hez-btn hez-btn--ghost" href="<?php echo esc_url( $cancel_url ); ?>"><?php esc_html_e( 'Vazgeç', 'hezarfen-for-woocommerce' ); ?></a>
				<?php endif; ?>
				<button type="submit" class="hez-btn hez-btn--primary"><?php esc_html_e( 'İade talebini gönder', 'hezarfen-for-woocommerce' ); ?></button>
			</div>
		</div>
	</form>
</div>
