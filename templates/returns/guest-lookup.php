<?php
/**
 * Guest order lookup form on the public return page.
 *
 * Override at yourtheme/hezarfen/returns/guest-lookup.php.
 *
 * @package Hezarfen\Inc\Returns
 *
 * @var bool   $guest_enabled Whether guest returns are switched on.
 * @var string $account_url   My Account permalink.
 * @var int    $window_days   Store-wide return window in days.
 */

use Hezarfen\Inc\Returns\Frontend\Return_Form_Handler;

defined( 'ABSPATH' ) || exit();
?>
<div class="hez-returns hez-returns--lookup">

	<header class="hez-returns__header">
		<h2 class="hez-returns__title"><?php esc_html_e( 'İade talebi oluştur', 'hezarfen-for-woocommerce' ); ?></h2>
		<?php if ( $window_days ) : ?>
			<p class="hez-returns__subtitle">
				<?php
				printf(
					/* translators: %d: return window in days. */
					esc_html__( 'Siparişinizi teslim aldıktan sonra %d gün içinde iade talebi oluşturabilirsiniz.', 'hezarfen-for-woocommerce' ),
					absint( $window_days )
				);
				?>
			</p>
		<?php endif; ?>
	</header>

	<?php if ( $guest_enabled ) : ?>
		<form method="post" class="hez-lookup-form">
			<?php wp_nonce_field( 'hezarfen_returns_' . Return_Form_Handler::ACTION_LOOKUP, Return_Form_Handler::NONCE_FIELD ); ?>
			<input type="hidden" name="<?php echo esc_attr( Return_Form_Handler::ACTION_FIELD ); ?>" value="<?php echo esc_attr( Return_Form_Handler::ACTION_LOOKUP ); ?>">

			<p class="hez-field">
				<label for="hez-order-number"><?php esc_html_e( 'Sipariş numarası', 'hezarfen-for-woocommerce' ); ?></label>
				<input type="text" id="hez-order-number" name="order_number" class="hez-input" inputmode="numeric" autocomplete="off" required>
			</p>

			<p class="hez-field">
				<label for="hez-billing-email"><?php esc_html_e( 'Siparişteki e-posta adresi', 'hezarfen-for-woocommerce' ); ?></label>
				<input type="email" id="hez-billing-email" name="billing_email" class="hez-input" autocomplete="email" required>
			</p>

			<button type="submit" class="hez-btn hez-btn--primary"><?php esc_html_e( 'Siparişimi bul', 'hezarfen-for-woocommerce' ); ?></button>
		</form>
	<?php else : ?>
		<div class="hez-callout hez-callout--info">
			<p><?php esc_html_e( 'İade talebi oluşturmak için hesabınıza giriş yapmanız gerekiyor.', 'hezarfen-for-woocommerce' ); ?></p>
		</div>
	<?php endif; ?>

	<p class="hez-returns__footnote">
		<?php
		printf(
			/* translators: %s: My Account link. */
			wp_kses_post( __( 'Hesabınız varsa <a href="%s">giriş yaparak</a> tüm iade taleplerinizi tek yerden takip edebilirsiniz.', 'hezarfen-for-woocommerce' ) ),
			esc_url( $account_url )
		);
		?>
	</p>
</div>
