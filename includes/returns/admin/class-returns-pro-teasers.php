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
 * A placeholder nobody claimed renders as the locked upsell, or as nothing at
 * all when promotions are off.
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
	}

	/**
	 * Whether the locked rows should be rendered at all.
	 *
	 * @return bool
	 */
	public static function should_show() {
		if ( ! function_exists( 'hezarfen_show_pro_promotions' ) || ! hezarfen_show_pro_promotions() ) {
			return false;
		}

		// The same signal the upgrade menu uses, so a site with Pro never
		// sees a locked row next to the working setting Pro just added.
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
		// Reaching the renderer means no add-on claimed this placeholder. With
		// promotions off there is nothing to say about it, so the row is left
		// out entirely rather than drawn as a dead box.
		if ( ! self::should_show() ) {
			return;
		}

		$examples = isset( $field['examples'] ) ? (array) $field['examples'] : array();

		$this->print_styles_once();
		?>
		<tr valign="top" class="hez-locked-row">
			<th scope="row" class="titledesc">
				<label>
					<?php echo esc_html( $field['title'] ); ?>
					<span class="hez-locked-badge">
						<span aria-hidden="true">&#128274;</span> <?php esc_html_e( 'Pro', 'hezarfen-for-woocommerce' ); ?>
					</span>
				</label>
			</th>
			<td class="forminp">
				<div class="hez-locked">
					<p class="hez-locked__desc"><?php echo esc_html( $field['desc'] ); ?></p>

					<?php if ( $examples ) : ?>
						<ul class="hez-locked__examples">
							<?php foreach ( $examples as $example ) : ?>
								<li><?php echo esc_html( $example ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<a class="button hez-locked__cta" href="<?php echo esc_url( admin_url( 'admin.php?page=hezarfen-upgrade' ) ); ?>">
						<?php esc_html_e( 'Hezarfen Pro ile açın', 'hezarfen-for-woocommerce' ); ?>
					</a>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Prints the row styles the first time a row is rendered.
	 *
	 * The section can hold several locked rows and there is no stylesheet
	 * enqueued on WooCommerce's settings screen for this module, so the CSS
	 * rides along with the markup — once, not once per row.
	 *
	 * @return void
	 */
	private function print_styles_once() {
		static $printed = false;

		if ( $printed ) {
			return;
		}

		$printed = true;
		?>
		<style>
			.hez-locked-row .titledesc label {
				color: #646970;
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
				max-width: 520px;
				padding: 14px 16px;
				border: 1px dashed #c3c4c7;
				border-radius: 6px;
				background: #f6f7f7;
			}

			.hez-locked__desc {
				margin: 0;
				color: #50575e;
			}

			.hez-locked__examples {
				margin: 10px 0 0;
				padding: 0;
				list-style: none;
				display: flex;
				flex-wrap: wrap;
				gap: 6px;
			}

			.hez-locked__examples li {
				margin: 0;
				padding: 3px 10px;
				border-radius: 999px;
				background: #fff;
				border: 1px solid #dcdcde;
				color: #646970;
				font-size: 12px;
			}

			.hez-locked__cta {
				margin-top: 14px !important;
			}
		</style>
		<?php
	}
}
