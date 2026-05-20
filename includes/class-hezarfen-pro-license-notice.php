<?php
/**
 * Renders expiry warnings for Hezarfen Pro across three surfaces:
 *   1) global admin_notices (top of every admin screen)
 *   2) plugins list row under Hezarfen Pro
 *   3) in-page banner on the Pro license settings screen
 *
 * Dismissal is per-user and keyed to the current support_expires_ts so that
 * a refreshed timestamp (e.g. after subscription renewal) automatically
 * re-surfaces the notice.
 *
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

/**
 * Pro_License_Notice — UI layer for license expiry warnings.
 */
class Pro_License_Notice {

	const USER_META_DISMISSED    = 'hezarfen_pro_license_dismissed_for';
	const AJAX_ACTION_DISMISS    = 'hezarfen_dismiss_pro_license_notice';
	const NONCE_ACTION           = 'hezarfen_dismiss_pro_license_notice';
	const REFRESH_ACTION         = 'hezarfen_refresh_pro_license';
	const REFRESH_NONCE          = 'hezarfen_refresh_pro_license';
	const PRO_LICENSE_SCREEN_KEY = 'wc_am_client_18509_dashboard';
	const PURCHASE_URL           = 'https://intense.com.tr/odeme/?add-to-cart-multiple=18509,241';

	/**
	 * Monitor providing license state.
	 *
	 * @var Pro_License_Monitor
	 */
	private $monitor;

	/**
	 * Constructor.
	 *
	 * @param Pro_License_Monitor $monitor License monitor instance.
	 */
	public function __construct( Pro_License_Monitor $monitor ) {
		$this->monitor = $monitor;

		add_action( 'admin_notices', array( $this, 'render_admin_notice' ) );
		add_action( 'admin_notices', array( $this, 'render_refresh_notice' ) );
		add_action( 'all_admin_notices', array( $this, 'maybe_render_pro_page_banner' ) );
		add_action( 'admin_footer', array( $this, 'inject_license_card_into_tab' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION_DISMISS, array( $this, 'handle_dismiss' ) );
		add_action( 'admin_init', array( $this, 'handle_refresh_request' ) );

		$pro_file = $this->get_pro_plugin_file();
		if ( $pro_file ) {
			add_action( 'after_plugin_row_' . $pro_file, array( $this, 'render_plugin_row_notice' ), 10, 3 );
		}
	}

	/**
	 * Resolves the Pro plugin file path (directory/filename.php form).
	 *
	 * @return string
	 */
	private function get_pro_plugin_file() {
		if ( defined( 'HEZARFEN_PRO_FILE' ) ) {
			return plugin_basename( HEZARFEN_PRO_FILE );
		}
		return 'hezarfen-pro-for-woocommerce/hezarfen-pro-for-woocommerce.php';
	}

	/**
	 * Is the current admin screen the Pro license settings page?
	 *
	 * @return bool
	 */
	private function is_pro_license_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}
		return false !== strpos( (string) $screen->id, self::PRO_LICENSE_SCREEN_KEY );
	}

	/**
	 * Has the current user dismissed the notice for this support_expires_ts?
	 *
	 * @param int $support_expires_ts Current timestamp from cache.
	 * @return bool
	 */
	private function is_dismissed_for_current_expiry( $support_expires_ts ) {
		$dismissed_for = (int) get_user_meta( get_current_user_id(), self::USER_META_DISMISSED, true );
		return $dismissed_for === (int) $support_expires_ts;
	}

	/**
	 * Renders the global admin_notices banner (suppressed on the Pro license screen).
	 *
	 * @return void
	 */
	public function render_admin_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( $this->is_pro_license_screen() ) {
			return; // In-page banner already renders there.
		}

		$state = $this->monitor->get_license_state();
		if ( ! in_array( $state['status'], array( 'expiring_soon', 'expired' ), true ) ) {
			return;
		}

		if ( 'expiring_soon' === $state['status']
			&& $this->is_dismissed_for_current_expiry( (int) $state['support_expires_ts'] )
		) {
			return;
		}

		$this->print_standard_notice( $state );
	}

	/**
	 * Renders the in-page banner on the Pro license settings screen.
	 *
	 * - expiring_soon / expired → turuncu veya kırmızı vurgulu "Lisansı Yenile" banner
	 * - ok → yeşil vurgulu küçük "Lisansınız aktif" bilgi kartı
	 * - diğer state'ler → hiçbir şey render etmez
	 *
	 * @return void
	 */
	public function maybe_render_pro_page_banner() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( ! $this->is_pro_license_screen() ) {
			return;
		}

		$state = $this->monitor->get_license_state();

		// Beyaz bilgi kartı artık admin_footer üzerinden JS ile form üstüne taşınıyor.
		// Bu hook yalnızca uyarı banner'ı için (sayfa üstünde kalması uygun).
		if ( ! in_array( $state['status'], array( 'expiring_soon', 'expired', 'not_activated' ), true ) ) {
			return;
		}

		if ( 'not_activated' === $state['status'] ) {
			$this->render_not_activated_banner( $state );
			return;
		}

		$is_expired   = 'expired' === $state['status'];
		$class        = $is_expired ? 'notice-error' : 'notice-warning';
		$accent       = $is_expired ? '#d63638' : '#d54e21';
		$bg           = $is_expired ? '#fff0f0' : '#fff4e5';
		$style        = sprintf(
			'padding:20px 24px;border-left-width:6px;border-left-color:%s;background:%s;margin:20px 0;',
			$accent,
			$bg
		);
		$purchase_url = $this->build_purchase_url( $state );
		?>
		<div class="notice <?php echo esc_attr( $class ); ?> hezarfen-pro-license-banner" style="<?php echo esc_attr( $style ); ?>">
			<p style="font-size:14px;margin-top:0;">
				<?php
				echo wp_kses(
					$is_expired
						? __( 'Güvenlik yamaları, WooCommerce/WordPress uyumluluk güncellemeleri ve yeni kargo entegrasyonları artık bu siteye gelmiyor. Kesintisiz kullanım için lisansınızı yenileyin.', 'hezarfen-for-woocommerce' )
						: __( 'Süre bittiğinde güvenlik yamaları, WooCommerce uyumluluk güncellemeleri ve yeni kargo entegrasyonları bu siteye gelmez. Kesintisiz kullanım için lisansınızı yenileyin.', 'hezarfen-for-woocommerce' ),
					$this->allowed_html()
				);
				?>
			</p>

			<p style="margin-top:14px;">
				<a href="<?php echo esc_url( $purchase_url ); ?>" target="_blank" rel="noopener" class="button button-primary button-hero">
					<?php esc_html_e( 'Lisansı Yenile →', 'hezarfen-for-woocommerce' ); ?>
				</a>
			</p>

			<p class="description" style="margin-top:8px;">
				<?php
				esc_html_e(
					'Siteniz ve mevcut lisans bilgileriniz ödeme sayfasına otomatik iletilecek. Sipariş tamamlandıktan sonra size verilen yeni API anahtarını bu sayfadaki API anahtarı alanına yapıştırmanız yeterlidir. İşlemi mevcut intense.com.tr hesabınızdan veya yeni bir hesaptan yapabilirsiniz.',
					'hezarfen-for-woocommerce'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Lisans aktif değilken (yerel olarak hiç aktive edilmemiş ya da
	 * intense.com.tr'de pasif edilmiş) lisans ayarları sayfasında üstte
	 * gösterilen banner. Müşteri iki senaryodan hangisinde olduğunu kendisi
	 * bildiği için tek banner her iki yola da yönlendiriyor: geçerli anahtarı
	 * varsa aşağıdan aktif edebilir, aboneliği bitmişse yenileme akışına gidebilir.
	 *
	 * @param array<string, mixed> $state License state (sub_id metadata'sı yenileme URL'ine geçirilir).
	 * @return void
	 */
	private function render_not_activated_banner( array $state = array() ) {
		$accent       = '#d63638';
		$bg           = '#fff0f0';
		$style        = sprintf(
			'padding:20px 24px;border-left-width:6px;border-left-color:%s;background:%s;margin:20px 0;',
			$accent,
			$bg
		);
		$purchase_url = $this->build_purchase_url( $state );
		$email_masked = isset( $state['email_masked'] ) ? (string) $state['email_masked'] : '';
		?>
		<div class="notice notice-error hezarfen-pro-license-banner" style="<?php echo esc_attr( $style ); ?>">
			<p style="font-size:14px;margin-top:0;">
				<?php
				esc_html_e(
					'Hezarfen Pro lisansınız aktif değil. Güncellemeler, güvenlik güncellemeleri ve destek bu siteye gelmiyor.',
					'hezarfen-for-woocommerce'
				);
				?>
			</p>

			<?php if ( '' !== $email_masked ) : ?>
			<p style="margin-top:10px;font-size:13px;">
				<?php
				printf(
					/* translators: %s: maskelenmiş e-posta adresi */
					esc_html__( 'Bu lisans %s hesabına kayıtlı. Yenileme yapacaksanız bu hesapla oturum açmanız gerekecek.', 'hezarfen-for-woocommerce' ),
					'<code>' . esc_html( $email_masked ) . '</code>'
				);
				?>
			</p>
			<?php endif; ?>

			<p class="description" style="margin-top:8px;">
				<?php
				esc_html_e(
					'Geçerli bir API anahtarınız varsa aşağıdaki "API Anahtarı" alanına yapıştırıp kaydedin. Aboneliğiniz dolduysa yenileyerek yeni bir anahtar alabilirsiniz.',
					'hezarfen-for-woocommerce'
				);
				?>
			</p>

			<p style="margin-top:14px;">
				<a href="<?php echo esc_url( $purchase_url ); ?>" target="_blank" rel="noopener" class="button button-primary button-hero">
					<?php esc_html_e( 'Lisansı Yenile →', 'hezarfen-for-woocommerce' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * License info card'ını admin_footer'da gizli havuza yazar, sonra JS ile
	 * aktivasyon formunun hemen üstüne (title ve nav-tab sonrası) enjekte eder.
	 * Lisans aktif olduğu sürece (ok / expiring_soon) görünür.
	 *
	 * @return void
	 */
	public function inject_license_card_into_tab() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( ! $this->is_pro_license_screen() ) {
			return;
		}

		$state = $this->monitor->get_license_state();
		if ( ! in_array( $state['status'], array( 'ok', 'expiring_soon' ), true ) ) {
			return;
		}

		ob_start();
		$this->render_active_license_card( $state );
		$card_html = ob_get_clean();
		?>
		<div id="hezarfen-pro-license-card-pool" style="display:none;"><?php echo $card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — already escaped in render_active_license_card ?></div>
		<script type="text/javascript">
		(function(){
			var pool = document.getElementById('hezarfen-pro-license-card-pool');
			if ( ! pool ) { return; }
			// Spesifik olarak kartı bul (style tag gibi diğer elementler pool'da kalabilir)
			var card = pool.querySelector('.hezarfen-pro-license-info');
			if ( ! card ) { return; }
			var wrap = document.querySelector('.wrap');
			if ( ! wrap ) { return; }

			// Aktivasyon tab'ın formunu bul; onun hemen üstüne ekle
			var form = wrap.querySelector('form[action*="options.php"]');
			if ( form && form.parentNode ) {
				form.parentNode.insertBefore( card, form );
				return;
			}

			// Fallback: nav-tab-wrapper varsa ondan sonra
			var navTab = wrap.querySelector('.nav-tab-wrapper');
			if ( navTab && navTab.parentNode ) {
				navTab.parentNode.insertBefore( card, navTab.nextSibling );
				return;
			}

			// Son çare: h1 sonrası
			var h1 = wrap.querySelector('h1');
			if ( h1 && h1.parentNode ) {
				h1.parentNode.insertBefore( card, h1.nextSibling );
			}
		})();
		</script>
		<?php
	}

	/**
	 * Renders the compact license info card — white background, single-row layout.
	 *
	 * @param array<string, mixed> $state License state.
	 * @return void
	 */
	private function render_active_license_card( array $state ) {
		$expires_ts   = (int) $state['support_expires_ts'];
		$days_left    = max( 0, (int) $state['days_left'] );
		$expires_date = wp_date( 'j F Y', $expires_ts );
		$email_masked = (string) ( $state['email_masked'] ?? '' );
		$sub_id       = (int) ( $state['sub_id'] ?? 0 );
		$order_id     = (int) ( $state['order_id'] ?? 0 );

		// Bitime yakın değilse (ok) yeşil; yakında bitiyorsa (expiring_soon) beyaz (banner zaten uyarıyor)
		$is_ok  = 'ok' === $state['status'];
		$bg     = $is_ok ? '#f0faf0' : '#fff';
		$border = $is_ok ? '#46b450' : '#dcdcde';
		?>
		<style>
			.hezarfen-license-tooltip {
				position: relative;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 18px;
				height: 18px;
				border-radius: 50%;
				background: #dcdcde;
				color: #50575e;
				font-size: 12px;
				font-weight: 700;
				cursor: help;
				font-family: serif;
				font-style: italic;
			}
			.hezarfen-license-tooltip::after {
				content: attr(data-tooltip);
				position: absolute;
				bottom: calc(100% + 8px);
				left: 50%;
				transform: translateX(-50%);
				background: #1d2327;
				color: #fff;
				padding: 6px 10px;
				border-radius: 4px;
				white-space: nowrap;
				font-size: 12px;
				font-style: normal;
				font-weight: normal;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
				opacity: 0;
				pointer-events: none;
				transition: opacity 0.12s ease-in;
				z-index: 999;
			}
			.hezarfen-license-tooltip::before {
				content: '';
				position: absolute;
				bottom: calc(100% + 3px);
				left: 50%;
				transform: translateX(-50%);
				border: 4px solid transparent;
				border-top-color: #1d2327;
				opacity: 0;
				pointer-events: none;
				transition: opacity 0.12s ease-in;
			}
			.hezarfen-license-tooltip:hover::after,
			.hezarfen-license-tooltip:hover::before {
				opacity: 1;
			}
		</style>
		<div class="hezarfen-pro-license-info" style="padding:18px 22px;background:<?php echo esc_attr( $bg ); ?>;border:1px solid <?php echo esc_attr( $border ); ?>;border-radius:6px;margin:20px 0;">
			<?php if ( '' !== $email_masked ) : ?>
			<div style="margin-bottom:14px;">
				<div style="font-size:11px;color:#646970;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;font-weight:600;">
					<?php esc_html_e( 'Lisans sahibi', 'hezarfen-for-woocommerce' ); ?>
				</div>
				<div style="font-size:14px;display:flex;align-items:center;gap:6px;">
					<code style="background:#f6f7f7;padding:3px 8px;border-radius:3px;"><?php echo esc_html( $email_masked ); ?></code>
					<?php if ( $sub_id > 0 || $order_id > 0 ) : ?>
						<?php
						$tooltip_parts = array();
						if ( $order_id > 0 ) {
							$tooltip_parts[] = sprintf( __( 'Sipariş #%d', 'hezarfen-for-woocommerce' ), $order_id );
						}
						if ( $sub_id > 0 ) {
							$tooltip_parts[] = sprintf( __( 'Abonelik #%d', 'hezarfen-for-woocommerce' ), $sub_id );
						}
						$tooltip = implode( ' · ', $tooltip_parts );
						?>
						<span class="hezarfen-license-tooltip" data-tooltip="<?php echo esc_attr( $tooltip ); ?>">i</span>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>

			<div>
				<div style="font-size:11px;color:#646970;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;font-weight:600;">
					<?php esc_html_e( 'Destek süresi bitişi', 'hezarfen-for-woocommerce' ); ?>
				</div>
				<div style="font-size:14px;">
					<strong><?php echo esc_html( $expires_date ); ?></strong>
					<span style="color:#646970;margin-left:6px;">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: remaining days */
								_n( '%d gün kaldı', '%d gün kaldı', $days_left, 'hezarfen-for-woocommerce' ),
								$days_left
							)
						);
						?>
					</span>
				</div>
			</div>

			<div style="margin-top:16px;">
				<a href="<?php echo esc_url( $this->get_refresh_url() ); ?>"
				   class="button button-small"
				   title="<?php esc_attr_e( 'Lisans bilgilerini sunucudan yeniden çek', 'hezarfen-for-woocommerce' ); ?>">
					<?php esc_html_e( '↻ Lisans Bilgilerini Tazele', 'hezarfen-for-woocommerce' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Builds the checkout URL with renewal GET parameters (domain, license key, subscription id)
	 * so the intense.com.tr side can auto-prefill and track the renewal.
	 *
	 * @param array<string, mixed> $state License state.
	 * @return string
	 */
	private function build_purchase_url( array $state ) {
		$args = array(
			'renew_domain'              => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
			'renew_license_key'         => $this->monitor->get_api_key(),
			'renew_old_subscription_id' => (int) ( $state['sub_id'] ?? 0 ),
		);
		$args = array_filter( $args, static function ( $v ) { return '' !== $v && 0 !== $v; } );
		return add_query_arg( $args, $this->get_purchase_base_url() );
	}

	/**
	 * Purchase URL'inin base'i. API_BASE override aktifse aynı host'u kullanır
	 * ki local end-to-end testte renewal butonu intense.local'e gitsin.
	 *
	 * @return string
	 */
	private function get_purchase_base_url() {
		if ( Pro_License_Monitor::is_api_base_overridden() ) {
			return Pro_License_Monitor::get_api_base() . 'odeme/?add-to-cart-multiple=18509,241';
		}
		return self::PURCHASE_URL;
	}

	/**
	 * Builds the wp-admin URL of the Pro license settings screen.
	 *
	 * @return string
	 */
	private function get_license_page_url() {
		return admin_url( 'admin.php?page=' . self::PRO_LICENSE_SCREEN_KEY );
	}

	/**
	 * "Yenile" butonundan gelen isteği yakalar: cache'i temizler, taze veri çeker,
	 * sonra redirect eder ve başarı bildirimi gösterir.
	 *
	 * @return void
	 */
	public function handle_refresh_request() {
		if ( empty( $_GET['hezarfen_pro_refresh'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		check_admin_referer( self::REFRESH_NONCE );

		$this->monitor->force_refresh();

		$redirect = add_query_arg(
			array( 'hezarfen_pro_refreshed' => 1 ),
			$this->get_license_page_url()
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Yenile sonrası success notice (admin_notices hook'una bağlı — sayfa üstünde
	 * standart WP admin notice bölümünde gösterilir, kart akışını etkilemez).
	 *
	 * @return void
	 */
	public function render_refresh_notice() {
		if ( empty( $_GET['hezarfen_pro_refreshed'] ) ) {
			return;
		}
		if ( ! $this->is_pro_license_screen() ) {
			return;
		}
		echo '<div class="notice notice-success is-dismissible"><p>'
			. esc_html__( 'Lisans bilgileri yenilendi.', 'hezarfen-for-woocommerce' )
			. '</p></div>';
	}

	/**
	 * "Yenile" butonu URL'si (nonce'lı).
	 *
	 * @return string
	 */
	private function get_refresh_url() {
		return wp_nonce_url(
			add_query_arg( 'hezarfen_pro_refresh', '1', $this->get_license_page_url() ),
			self::REFRESH_NONCE
		);
	}

	/**
	 * Renders the plugins list row-under bar for Hezarfen Pro.
	 *
	 * @param string               $plugin_file Plugin file path (unused, signature requirement).
	 * @param array<string, mixed> $plugin_data Plugin data (unused).
	 * @param string               $status      Status (unused).
	 * @return void
	 */
	public function render_plugin_row_notice( $plugin_file, $plugin_data, $status ) {
		unset( $plugin_file, $plugin_data, $status );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$state = $this->monitor->get_license_state();
		if ( ! in_array( $state['status'], array( 'expiring_soon', 'expired', 'not_activated' ), true ) ) {
			return;
		}

		$is_expired = in_array( $state['status'], array( 'expired', 'not_activated' ), true );
		$class      = $is_expired ? 'notice-error' : 'notice-warning';
		$style      = $is_expired
			? ''
			: 'border-left-color:#d54e21;background:#fff4e5;';

		$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
		$colspan       = $wp_list_table ? (int) $wp_list_table->get_column_count() : 4;
		?>
		<tr class="plugin-update-tr active hezarfen-pro-license-row">
			<td colspan="<?php echo (int) $colspan; ?>" class="plugin-update colspanchange">
				<div class="update-message notice inline <?php echo esc_attr( $class ); ?> notice-alt" style="<?php echo esc_attr( $style ); ?>">
					<p><?php echo wp_kses( $this->get_message_html( $state, 'row' ), $this->allowed_html() ); ?></p>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * AJAX handler for dismissing the notice.
	 *
	 * @return void
	 */
	public function handle_dismiss() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( null, 403 );
		}

		$ts = isset( $_POST['support_expires_ts'] ) ? (int) $_POST['support_expires_ts'] : 0;
		if ( $ts <= 0 ) {
			wp_send_json_error( null, 400 );
		}

		update_user_meta( get_current_user_id(), self::USER_META_DISMISSED, $ts );
		wp_send_json_success();
	}

	/**
	 * Prints a dismissible (or non-dismissible) top-of-screen notice with inline dismiss JS.
	 *
	 * @param array<string, mixed> $state License state.
	 * @return void
	 */
	private function print_standard_notice( array $state ) {
		$is_expired   = 'expired' === $state['status'];
		$class        = $is_expired ? 'notice-error' : 'notice-warning';
		$dismissible  = $is_expired ? '' : 'is-dismissible';
		$ts           = (int) $state['support_expires_ts'];
		$nonce        = wp_create_nonce( self::NONCE_ACTION );
		$style        = $is_expired
			? 'border-left-width:6px;'
			: 'border-left-color:#d54e21;border-left-width:6px;background:#fff4e5;';
		?>
		<div class="notice <?php echo esc_attr( trim( $class . ' ' . $dismissible ) ); ?> hezarfen-pro-license-notice" data-support-expires="<?php echo esc_attr( (string) $ts ); ?>" style="<?php echo esc_attr( $style ); ?>">
			<p><?php echo wp_kses( $this->get_message_html( $state, 'admin_notice' ), $this->allowed_html() ); ?></p>
		</div>
		<?php if ( ! $is_expired ) : ?>
		<script type="text/javascript">
		(function($){
			$(document).on('click', '.hezarfen-pro-license-notice .notice-dismiss', function(){
				var ts = $(this).closest('.hezarfen-pro-license-notice').data('support-expires');
				$.post(ajaxurl, {
					action: '<?php echo esc_js( self::AJAX_ACTION_DISMISS ); ?>',
					nonce: '<?php echo esc_js( $nonce ); ?>',
					support_expires_ts: ts
				});
			});
		})(jQuery);
		</script>
			<?php
		endif;
	}

	/**
	 * Builds the message HTML per variant.
	 *
	 * @param array<string, mixed> $state   License state.
	 * @param string               $variant One of: admin_notice | banner | row.
	 * @return string
	 */
	private function get_message_html( array $state, $variant ) {
		$days         = max( 0, (int) $state['days_left'] );
		$expires_date = esc_html( wp_date( 'j F Y', (int) $state['support_expires_ts'] ) );
		$link         = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $this->get_license_page_url() ),
			esc_html__( 'Lisansı Yönet', 'hezarfen-for-woocommerce' )
		);

		if ( 'not_activated' === $state['status'] ) {
			if ( 'row' === $variant ) {
				return sprintf(
					/* translators: 1: link HTML. */
					__( '<strong>Hezarfen Pro:</strong> Lisansınız aktif değil — güncellemeler, güvenlik güncellemeleri ve destek aktif değil. %1$s', 'hezarfen-for-woocommerce' ),
					$link
				);
			}
			return sprintf(
				/* translators: 1: link HTML. */
				__( '<strong>Hezarfen Pro</strong> lisansınız aktif değil. Güncellemeler, güvenlik güncellemeleri ve destek bu siteye gelmiyor. %1$s', 'hezarfen-for-woocommerce' ),
				$link
			);
		}

		if ( 'expired' === $state['status'] ) {
			if ( 'row' === $variant ) {
				return sprintf(
					/* translators: 1: link HTML. */
					__( '<strong>Hezarfen Pro:</strong> Destek ve güncelleme süreniz doldu — güvenlik yamaları ve yeni özellikler artık gelmiyor. %1$s', 'hezarfen-for-woocommerce' ),
					$link
				);
			}
			return sprintf(
				/* translators: 1: link HTML. */
				__( '<strong>Hezarfen Pro</strong> destek ve güncelleme süreniz <strong>doldu</strong>. Güvenlik yamaları, WooCommerce/WordPress uyumluluk güncellemeleri ve yeni kargo entegrasyonları artık bu siteye gelmiyor. Mevcut aboneliğinizi yeniden ödeyebilir veya yeni bir sipariş oluşturabilirsiniz. %1$s', 'hezarfen-for-woocommerce' ),
				$link
			);
		}

		// expiring_soon
		if ( 'row' === $variant ) {
			return sprintf(
				/* translators: 1: expiry date, 2: remaining days, 3: link HTML. */
				__( '<strong>Hezarfen Pro:</strong> Destek süreniz %1$s tarihinde sona eriyor (%2$d gün kaldı). Güvenlik yamaları ve yeni özellikler kesilecek. %3$s', 'hezarfen-for-woocommerce' ),
				$expires_date,
				$days,
				$link
			);
		}

		return sprintf(
			/* translators: 1: expiry date, 2: remaining days, 3: link HTML. */
			__( '<strong>Hezarfen Pro</strong> destek ve güncelleme süreniz <strong>%1$s</strong> tarihinde sona eriyor (%2$d gün kaldı). Süre bittiğinde güvenlik yamaları, WooCommerce uyumluluk güncellemeleri ve yeni kargo entegrasyonları bu siteye gelmez. Kesintisiz kullanım için mevcut aboneliğinizi ödeyebilir veya yeni bir sipariş oluşturabilirsiniz. %3$s', 'hezarfen-for-woocommerce' ),
			$expires_date,
			$days,
			$link
		);
	}

	/**
	 * Allowed HTML tags for wp_kses in notice messages.
	 *
	 * @return array<string, array<string, bool>>
	 */
	private function allowed_html() {
		return array(
			'strong' => array(),
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
		);
	}
}
