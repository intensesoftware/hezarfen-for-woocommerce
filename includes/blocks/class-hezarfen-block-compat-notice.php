<?php
/**
 * Admin notice shown when the store uses the block-based checkout but the
 * WooCommerce version is too old for Hezarfen's block checkout fields.
 *
 * @package Hezarfen\Inc\Blocks
 */

namespace Hezarfen\Inc\Blocks;

defined( 'ABSPATH' ) || exit();

/**
 * Hezarfen_Block_Compat_Notice
 *
 * Hezarfen's district/neighborhood and invoice/tax fields on the block checkout
 * rely on the block Checkout being production-ready, which happened in
 * WooCommerce 8.3. When the merchant runs an older WooCommerce *and* their
 * checkout page uses the block, the fields cannot work — so we surface a notice
 * offering the two ways forward: upgrade WooCommerce, or switch the checkout
 * page back to the classic shortcode (where Hezarfen's fields work fine).
 */
class Hezarfen_Block_Compat_Notice {

	/**
	 * Minimum WooCommerce version that supports the block checkout fields.
	 */
	const MIN_WC_VERSION = '8.3';

	/**
	 * Action name used for the "switch to classic checkout" handler.
	 */
	const SWITCH_ACTION = 'hezarfen_switch_to_classic_checkout';

	/**
	 * Post meta key holding the checkout page content prior to switching it to the
	 * classic shortcode, so the original block content can be restored.
	 */
	const BACKUP_META_KEY = '_hezarfen_pre_classic_checkout_content';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'maybe_render_notice' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_switch_result' ) );
		add_action( 'admin_post_' . self::SWITCH_ACTION, array( $this, 'handle_switch_to_classic' ) );
	}

	/**
	 * Renders the confirmation/failure message after a "switch to classic" action.
	 *
	 * @return void
	 */
	public function maybe_render_switch_result() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result = isset( $_GET['hezarfen_checkout'] ) ? sanitize_key( wp_unslash( $_GET['hezarfen_checkout'] ) ) : '';

		if ( 'switched' === $result ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'The checkout page now uses the classic checkout. Hezarfen\'s fields are active there.', 'hezarfen-for-woocommerce' )
			);
		} elseif ( 'error' === $result ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html__( 'Could not switch the checkout page. Please edit the checkout page manually.', 'hezarfen-for-woocommerce' )
			);
		}
	}

	/**
	 * Whether the running WooCommerce version supports the block checkout fields.
	 *
	 * @return bool
	 */
	protected function wc_supports_block_fields() {
		$version = defined( 'WC_VERSION' ) ? WC_VERSION : ( function_exists( 'WC' ) ? WC()->version : '0' );

		return version_compare( $version, self::MIN_WC_VERSION, '>=' );
	}

	/**
	 * Whether the checkout page uses the block-based checkout.
	 *
	 * @return bool
	 */
	protected function checkout_uses_block() {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return false;
		}

		$checkout_page_id = wc_get_page_id( 'checkout' );

		return $checkout_page_id > 0 && has_block( 'woocommerce/checkout', $checkout_page_id );
	}

	/**
	 * Renders the notice when WooCommerce is too old and the block checkout is in use.
	 *
	 * @return void
	 */
	public function maybe_render_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( $this->wc_supports_block_fields() || ! $this->checkout_uses_block() ) {
			return;
		}

		$current_version = defined( 'WC_VERSION' ) ? WC_VERSION : '';

		$update_url  = esc_url( admin_url( 'update-core.php' ) );
		$switch_url  = esc_url(
			wp_nonce_url(
				admin_url( 'admin-post.php?action=' . self::SWITCH_ACTION ),
				self::SWITCH_ACTION
			)
		);
		$edit_url    = esc_url( get_edit_post_link( wc_get_page_id( 'checkout' ) ) );
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Hezarfen', 'hezarfen-for-woocommerce' ); ?>:</strong>
				<?php
				printf(
					/* translators: 1: current WooCommerce version, 2: minimum required version. */
					esc_html__( 'Your WooCommerce version (%1$s) does not support Hezarfen\'s checkout fields (district, neighborhood and invoice fields) on the block-based checkout. The block checkout requires WooCommerce %2$s or higher.', 'hezarfen-for-woocommerce' ),
					esc_html( $current_version ),
					esc_html( self::MIN_WC_VERSION )
				);
				?>
			</p>
			<p>
				<?php esc_html_e( 'You have two options:', 'hezarfen-for-woocommerce' ); ?>
			</p>
			<p>
				<a href="<?php echo $update_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="button button-primary">
					<?php
					printf(
						/* translators: %s: minimum required WooCommerce version. */
						esc_html__( 'Update WooCommerce to %s+', 'hezarfen-for-woocommerce' ),
						esc_html( self::MIN_WC_VERSION )
					);
					?>
				</a>
				<a href="<?php echo $switch_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="button">
					<?php esc_html_e( 'Switch to the classic checkout', 'hezarfen-for-woocommerce' ); ?>
				</a>
			</p>
			<p class="description">
				<?php
				printf(
					/* translators: %s: opening and closing anchor tags for the checkout page edit link. */
					esc_html__( 'Switching replaces the checkout page content with the classic %1$s[woocommerce_checkout]%2$s shortcode, where Hezarfen\'s fields work without any WooCommerce upgrade.', 'hezarfen-for-woocommerce' ),
					'<code>',
					'</code>'
				);

				if ( $edit_url ) {
					echo ' ';
					printf(
						'<a href="%1$s">%2$s</a>',
						$edit_url, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						esc_html__( 'Review the checkout page first', 'hezarfen-for-woocommerce' )
					);
				}
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Replaces the checkout page content with the classic checkout shortcode.
	 *
	 * @return void
	 */
	public function handle_switch_to_classic() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'hezarfen-for-woocommerce' ) );
		}

		check_admin_referer( self::SWITCH_ACTION );

		$checkout_page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'checkout' ) : 0;

		$status = 'error';

		if ( $checkout_page_id > 0 ) {
			// Back up the existing (block) checkout content before overwriting it,
			// so the merchant can restore their original page if needed. This is a
			// one-way action otherwise.
			$previous_content = get_post_field( 'post_content', $checkout_page_id );

			if ( is_string( $previous_content ) && '' !== $previous_content ) {
				update_post_meta( $checkout_page_id, self::BACKUP_META_KEY, $previous_content );
			}

			$result = wp_update_post(
				array(
					'ID'           => $checkout_page_id,
					'post_content' => '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->',
				),
				true
			);

			if ( ! is_wp_error( $result ) ) {
				$status = 'switched';
			}
		}

		wp_safe_redirect(
			add_query_arg(
				'hezarfen_checkout',
				$status,
				admin_url( 'admin.php?page=wc-settings' )
			)
		);
		exit;
	}
}
