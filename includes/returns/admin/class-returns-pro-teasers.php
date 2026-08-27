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
				'title'    => __( 'İade edilebilir sipariş durumları', 'hezarfen-for-woocommerce' ),
				'desc'     => __( 'Hangi durumdaki siparişler için iade talebi açılabileceğini seçin. Ücretsiz sürümde yalnızca tamamlanmış siparişler iade edilebilir.', 'hezarfen-for-woocommerce' ),
				'examples' => array(
					__( 'Tamamlandı', 'hezarfen-for-woocommerce' ),
					__( 'İşleniyor', 'hezarfen-for-woocommerce' ),
				),
			),
			'reasons'  => array(
				'title'    => __( 'İade sebepleri', 'hezarfen-for-woocommerce' ),
				'desc'     => __( 'Kendi iade sebeplerinizi tanımlayın, sıralayın ve hangi sebepte müşteriden açıklama isteneceğini belirleyin.', 'hezarfen-for-woocommerce' ),
				'examples' => array(
					__( 'Beden uymadı', 'hezarfen-for-woocommerce' ),
					__( 'Üründe hasar var', 'hezarfen-for-woocommerce' ),
					__( 'Yanlış ürün gönderildi', 'hezarfen-for-woocommerce' ),
				),
			),
			'products' => array(
				'title'    => __( 'İade edilebilir ürünler', 'hezarfen-for-woocommerce' ),
				'desc'     => __( 'Hangi ürün ve kategorilerin iade edilebileceğini seçin; iade edilemeyen ürünler müşteriye hiç gösterilmez.', 'hezarfen-for-woocommerce' ),
				'examples' => array(
					__( 'Kategori bazlı iade kapatma', 'hezarfen-for-woocommerce' ),
					__( 'Ürün bazlı istisna', 'hezarfen-for-woocommerce' ),
					__( 'Ürüne özel iade süresi', 'hezarfen-for-woocommerce' ),
				),
			),
			'photos'   => array(
				'title'    => __( 'Fotoğraflı iade talebi', 'hezarfen-for-woocommerce' ),
				'desc'     => __( 'Müşteri iade talebine ürünün fotoğrafını ekleyebilsin. Hasar tartışmasını mesajlaşmaya taşımadan, talebin kendi geçmişinde çözün.', 'hezarfen-for-woocommerce' ),
				'examples' => array(
					__( 'Talep formunda fotoğraf yükleme', 'hezarfen-for-woocommerce' ),
					__( 'Bilgi isteğine fotoğrafla yanıt', 'hezarfen-for-woocommerce' ),
				),
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
				'examples'  => $field['examples'],
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
		$examples = isset( $field['examples'] ) ? (array) $field['examples'] : array();

		?>
		<tr valign="top" class="hez-locked-row">
			<th scope="row" class="titledesc">
				<label>
					<span class="hez-locked-icon" aria-hidden="true">&#128274;</span>
					<?php echo esc_html( $field['title'] ); ?>
					<span class="hez-locked-badge"><?php esc_html_e( 'Pro', 'hezarfen-for-woocommerce' ); ?></span>
				</label>
			</th>
			<td class="forminp">
				<div class="hez-locked">
					<?php if ( $examples ) : ?>
						<?php // A dead replica of the real control, so the row reads as a setting that is switched off rather than as an advert. ?>
						<select class="hez-locked__preview" disabled aria-hidden="true" tabindex="-1">
							<?php foreach ( $examples as $example ) : ?>
								<option><?php echo esc_html( $example ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>

					<p class="hez-locked__desc"><?php echo esc_html( $field['desc'] ); ?></p>

					<?php if ( self::may_promote() ) : ?>
						<a class="hez-locked__cta" href="<?php echo esc_url( admin_url( 'admin.php?page=hezarfen-upgrade' ) ); ?>">
							<?php esc_html_e( 'Hezarfen Pro ile açın', 'hezarfen-for-woocommerce' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</td>
		</tr>
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
			/* The row reads as a switched-off setting: same shape as its
			   neighbours, drained of colour and interaction. */
			.hez-locked-row .titledesc label {
				color: #8c8f94;
				font-weight: 400;
			}

			.hez-locked-icon {
				margin-right: 4px;
				opacity: 0.65;
				font-size: 12px;
			}

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

			.hez-locked {
				max-width: 420px;
			}

			.hez-locked__preview {
				width: 100%;
				max-width: 400px;
				margin-bottom: 6px;
				background: #f0f0f1;
				color: #8c8f94;
				/* pointer-events off as well as disabled: the select must not
				   even show a "not-allowed" cursor fight on some browsers. */
				pointer-events: none;
			}

			.hez-locked__desc {
				margin: 0;
				color: #8c8f94;
				font-size: 13px;
				font-style: italic;
			}

			.hez-locked__cta {
				display: inline-block;
				margin-top: 8px;
				font-size: 13px;
			}
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
