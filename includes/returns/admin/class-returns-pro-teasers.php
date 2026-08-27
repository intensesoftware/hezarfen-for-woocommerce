<?php
/**
 * Contains the Returns_Pro_Teasers screen helper.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Admin;

defined( 'ABSPATH' ) || exit();

/**
 * Shows the returns features that live in Pro as locked rows in the free
 * settings screen.
 *
 * They sit where the real setting would sit rather than in a promo box at
 * the bottom, because the merchant only wonders "can I choose my own return
 * reasons?" while they are configuring returns — that is the moment worth
 * answering, and answering it with silence reads as "the plugin cannot do
 * this at all".
 *
 * The rows double as PLACEHOLDERS. They are always emitted into the section,
 * at the position their real setting belongs to, and Pro swaps the ones it
 * implements for working fields — which is why every returns setting stays on
 * this one screen instead of scattering across two plugins' settings pages.
 * A placeholder nobody claimed renders as a locked row: greyed out, with a
 * lock and a Pro badge, and a disabled preview of the control it stands for.
 * It is shown whether or not Pro promotions are enabled — a setting that
 * silently disappears is worse than one the merchant is told they cannot
 * reach. The promotions flag only decides whether the upgrade link rides
 * along.
 *
 * They are flagged `is_option => false` so WooCommerce skips them when the
 * section is saved; whatever replaces one brings its own storage.
 */
class Returns_Pro_Teasers {

	const FIELD_TYPE = 'hezarfen_returns_locked';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_admin_field_' . self::FIELD_TYPE, array( $this, 'render_field' ) );
		add_action( 'admin_print_styles', array( $this, 'print_styles' ) );
	}

	/**
	 * Whether the upgrade link may ride along with a locked row.
	 *
	 * The row itself is never gated on this: the merchant is told the setting
	 * exists either way. Only the sales pitch answers to the promotions flag,
	 * and only while Pro is absent.
	 *
	 * @return bool
	 */
	public static function may_promote() {
		if ( ! function_exists( 'hezarfen_show_pro_promotions' ) || ! hezarfen_show_pro_promotions() ) {
			return false;
		}

		return false === get_option( 'hezarfen_pro_db_version', false );
	}

	/**
	 * The locked rows, ready to be merged into the section's fields.
	 *
	 * @param string $placement Which row to return: `statuses`, `products`,
	 *                          `reasons` or `photos`.
	 *
	 * @return array<int, array<string, mixed>> Empty for an unknown placement.
	 */
	public static function get_fields( $placement ) {
		$fields = array(
			'statuses' => array(
				'title' => __( 'İade edilebilir sipariş durumları', 'hezarfen-for-woocommerce' ),
				// Tek cümle, somut sonuç: özellik listesi değil, mağazanın
				// bugün yaşadığı kısıt.
				'desc'  => __( 'Ücretsiz sürümde yalnızca tamamlanmış siparişler iade edilebilir. Kargoya verilmiş bir siparişte müşteri talep açamaz.', 'hezarfen-for-woocommerce' ),
			),
			'products' => array(
				'title' => __( 'İade edilebilir ürünler', 'hezarfen-for-woocommerce' ),
				'desc'  => __( 'İç giyim, kozmetik gibi iade alınamayan ürünler de müşteriye iade edilebilir görünür; talep açıldıktan sonra reddetmek zorunda kalırsınız.', 'hezarfen-for-woocommerce' ),
			),
			'reasons'  => array(
				'title' => __( 'İade sebepleri', 'hezarfen-for-woocommerce' ),
				'desc'  => __( 'Sebep listesi sabittir. Kendi sebeplerinizi yazamaz, sıralayamaz, hangi sebepte açıklama isteneceğini belirleyemezsiniz.', 'hezarfen-for-woocommerce' ),
			),
			'photos'   => array(
				'title' => __( 'Fotoğraflı iade talebi', 'hezarfen-for-woocommerce' ),
				'desc'  => __( 'Müşteri hasarın fotoğrafını ekleyemez. Kusurlu ürün tartışması WhatsApp’a taşınır ve talebin geçmişinde iz bırakmaz.', 'hezarfen-for-woocommerce' ),
			),
		);

		if ( ! isset( $fields[ $placement ] ) ) {
			return array();
		}

		$field = $fields[ $placement ];

		return array(
			array(
				'type'      => self::FIELD_TYPE,
				'id'        => 'hezarfen_returns_locked_' . $placement,
				// Without this WooCommerce runs the row through its default
				// save branch, where wc_clean( null ) yields '' rather than
				// null and an empty option is written on every save.
				'is_option' => false,
				'title'     => $field['title'],
				'desc'      => $field['desc'],
				'placement' => $placement,
			),
		);
	}

	/**
	 * Renders one locked row.
	 *
	 * WooCommerce hands custom field types straight through to this hook.
	 *
	 * @param array<string, mixed> $field Field definition.
	 *
	 * @return void
	 */
	public function render_field( $field ) {
		$placement = isset( $field['placement'] ) ? $field['placement'] : '';

		?>
		<tr valign="top" class="hez-locked-row">
			<th scope="row" class="titledesc">
				<label>
					<?php echo esc_html( $field['title'] ); ?>
					<span class="hez-locked-badge"><?php esc_html_e( 'Pro', 'hezarfen-for-woocommerce' ); ?></span>
				</label>
			</th>
			<td class="forminp">
				<div class="hez-locked">
					<?php
					// Rozet ve madde listesi yerine ÖZELLİĞİN KENDİSİ devre dışı
					// gösteriliyor: mağaza neyi kaçırdığını okuyarak değil
					// görerek anlıyor, üstelik açtığında karşılaşacağı ekranın
					// aynısını görüyor.
					$this->render_preview( $placement );
					?>

					<p class="hez-locked__desc"><?php echo esc_html( $field['desc'] ); ?></p>

					<?php if ( self::may_promote() ) : ?>
						<a class="button button-primary hez-locked__cta" href="<?php echo esc_url( admin_url( 'admin.php?page=hezarfen-upgrade' ) ); ?>">
							<?php esc_html_e( 'Hezarfen Pro ile açın', 'hezarfen-for-woocommerce' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * The disabled preview of the setting this row stands for.
	 *
	 * @param string $placement Which row is being drawn.
	 *
	 * @return void
	 */
	private function render_preview( $placement ) {
		echo '<div class="hez-locked__preview" aria-hidden="true">';

		switch ( $placement ) {
			case 'statuses':
				$this->render_chip_preview(
					array(
						__( 'Tamamlandı', 'hezarfen-for-woocommerce' ),
						__( 'İşleniyor', 'hezarfen-for-woocommerce' ),
						__( 'Beklemede', 'hezarfen-for-woocommerce' ),
					),
					array( 0 )
				);
				break;

			case 'products':
				$this->render_product_preview();
				break;

			case 'reasons':
				$this->render_reason_preview();
				break;

			case 'photos':
				$this->render_photo_preview();
				break;
		}

		echo '</div>';
	}

	/**
	 * Seçili ve seçilebilir durumları çip olarak gösterir.
	 *
	 * @param string[] $labels   Etiketler.
	 * @param int[]    $selected Seçili olanların dizinleri.
	 *
	 * @return void
	 */
	private function render_chip_preview( $labels, $selected ) {
		echo '<div class="hez-locked__chips">';

		foreach ( $labels as $index => $label ) {
			printf(
				'<span class="hez-locked__chip%1$s">%2$s</span>',
				in_array( $index, $selected, true ) ? ' is-on' : '',
				esc_html( $label )
			);
		}

		echo '</div>';
	}

	/**
	 * Ürün ekranındaki iade kuralının küçük bir kopyası.
	 *
	 * @return void
	 */
	private function render_product_preview() {
		?>
		<div class="hez-locked__line">
			<span class="hez-locked__line-label"><?php esc_html_e( 'İç Giyim', 'hezarfen-for-woocommerce' ); ?></span>
			<span class="hez-locked__pill is-off"><?php esc_html_e( 'İade edilemez', 'hezarfen-for-woocommerce' ); ?></span>
		</div>
		<div class="hez-locked__line">
			<span class="hez-locked__line-label"><?php esc_html_e( 'Kışlık Mont', 'hezarfen-for-woocommerce' ); ?></span>
			<span class="hez-locked__pill"><?php esc_html_e( '30 gün', 'hezarfen-for-woocommerce' ); ?></span>
		</div>
		<?php
	}

	/**
	 * Pro'daki sebep listesinin devre dışı kopyası.
	 *
	 * @return void
	 */
	private function render_reason_preview() {
		$rows = array(
			array( __( 'Beden uymadı', 'hezarfen-for-woocommerce' ), false ),
			array( __( 'Üründe hasar var', 'hezarfen-for-woocommerce' ), true ),
		);

		foreach ( $rows as $row ) {
			?>
			<div class="hez-locked__row">
				<span class="hez-locked__grip">&#8942;&#8942;</span>
				<span class="hez-locked__input"><?php echo esc_html( $row[0] ); ?></span>
				<span class="hez-locked__check">
					<input type="checkbox" disabled <?php checked( $row[1] ); ?>>
					<?php esc_html_e( 'Açıklama iste', 'hezarfen-for-woocommerce' ); ?>
				</span>
			</div>
			<?php
		}
	}

	/**
	 * Müşterinin ekleyeceği fotoğrafın küçük bir temsili.
	 *
	 * @return void
	 */
	private function render_photo_preview() {
		?>
		<div class="hez-locked__thumbs">
			<span class="hez-locked__thumb"></span>
			<span class="hez-locked__thumb"></span>
			<span class="hez-locked__thumb is-add">+</span>
		</div>
		<?php
	}

	/**
	 * Prints the row styles in the document head.
	 *
	 * Not from inside the field renderer: WooCommerce calls that while the
	 * settings `<table>` is open, and a `<style>` element dropped straight
	 * into a table is invalid markup the browser hoists back out, dragging
	 * the layout with it.
	 *
	 * @return void
	 */
	public function print_styles() {
		if ( ! $this->is_returns_section() ) {
			return;
		}

		?>
		<style>
			/* Satır, kapatılmış bir ayar gibi okunur: komşularıyla aynı şekil,
			   rengi çekilmiş, etkileşimi alınmış. */
			.hez-locked-row .titledesc label { color: #50575e; }

			.hez-locked-badge {
				display: inline-block;
				margin-left: 6px;
				padding: 1px 7px;
				border-radius: 999px;
				background: #eff6ff;
				color: #1e40af;
				font-size: 11px;
				font-weight: 600;
				vertical-align: middle;
				white-space: nowrap;
			}

			.hez-locked { max-width: 460px; }

			/* Önizleme: Pro'daki gerçek ekranın küçültülmüş, dokunulamaz hâli. */
			.hez-locked__preview {
				padding: 10px 12px;
				border: 1px solid #e0e0e1;
				border-radius: 4px;
				background: #fbfbfc;
				user-select: none;
				pointer-events: none;
			}

			.hez-locked__chips { display: flex; flex-wrap: wrap; gap: 6px; }

			.hez-locked__chip {
				padding: 3px 10px;
				border: 1px solid #dcdcde;
				border-radius: 999px;
				background: #fff;
				color: #8c8f94;
				font-size: 12px;
			}

			.hez-locked__chip.is-on {
				border-color: #c3dcf0;
				background: #f0f6fc;
				color: #2271b1;
			}

			.hez-locked__row,
			.hez-locked__line {
				display: flex;
				align-items: center;
				gap: 8px;
				padding: 5px 0;
			}

			.hez-locked__row + .hez-locked__row,
			.hez-locked__line + .hez-locked__line { border-top: 1px solid #f0f0f1; }

			.hez-locked__grip { color: #c3c4c7; font-size: 11px; letter-spacing: -2px; }

			.hez-locked__input {
				flex: 1 1 auto;
				padding: 4px 8px;
				border: 1px solid #dcdcde;
				border-radius: 3px;
				background: #fff;
				color: #646970;
				font-size: 12px;
			}

			.hez-locked__check {
				display: flex;
				align-items: center;
				gap: 4px;
				color: #8c8f94;
				font-size: 11px;
				white-space: nowrap;
			}

			.hez-locked__line-label { flex: 1 1 auto; color: #646970; font-size: 12px; }

			.hez-locked__pill {
				padding: 2px 8px;
				border-radius: 3px;
				background: #f0f0f1;
				color: #646970;
				font-size: 11px;
			}

			.hez-locked__pill.is-off { background: #fcf0f1; color: #b32d2e; }

			.hez-locked__thumbs { display: flex; gap: 6px; }

			.hez-locked__thumb {
				width: 44px;
				height: 44px;
				border: 1px solid #dcdcde;
				border-radius: 3px;
				background: #f0f0f1;
			}

			.hez-locked__thumb.is-add {
				display: flex;
				align-items: center;
				justify-content: center;
				border-style: dashed;
				background: #fff;
				color: #c3c4c7;
				font-size: 18px;
			}

			.hez-locked__desc {
				margin: 8px 0 0;
				color: #646970;
				font-size: 12px;
			}

			.hez-locked__cta { margin-top: 10px !important; }
		</style>
		<?php
	}

	/**
	 * Whether the returns settings section is the screen being drawn.
	 *
	 * @return bool
	 */
	private function is_returns_section() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading which screen is open, not acting on it.
		$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return 'wc-settings' === $page && 'hezarfen' === $tab && 'returns' === $section;
	}
}
