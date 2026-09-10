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
	 * Where a locked row sends a merchant who wants the setting.
	 *
	 * An external page rather than the bundled upgrade screen: that screen is
	 * itself behind the promotions flag, so a link to it would dead-end on
	 * exactly the installs a locked row appears on.
	 */
	const PRO_URL = 'https://intense.com.tr/hezarfen-pro';

	/**
	 * Whether the upgrade link may ride along with a locked row.
	 *
	 * The row itself is never gated on this: the merchant is told the setting
	 * exists either way. The link is dropped only where it would be noise --
	 * on a store that already has Pro, where the row is about to be replaced
	 * by the real setting anyway.
	 *
	 * @return bool
	 */
	public static function may_promote() {
		return false === get_option( 'hezarfen_pro_db_version', false );
	}

	/**
	 * The upgrade link, ready to print.
	 *
	 * `noreferrer` keeps the store's own admin URL -- which carries the site
	 * address and the screen being looked at -- out of the request, and
	 * closes `window.opener` on the new tab. `nofollow` because this is a
	 * commercial link printed on every install.
	 *
	 * @param string $label Link text.
	 * @param string $class Extra class names.
	 *
	 * @return string
	 */
	public static function pro_link( $label, $class = '' ) {
		return sprintf(
			'<a class="%1$s" href="%2$s" target="_blank" rel="nofollow noreferrer">%3$s</a>',
			esc_attr( trim( 'hez-locked__cta ' . $class ) ),
			esc_url( self::PRO_URL ),
			esc_html( $label )
		);
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
		// Metinler komşu ayarlarla aynı dilde: satırın NE OLDUĞUNU söylüyorlar,
		// eksikliği anlatmıyorlar. Kilidi rozet ve önizleme zaten gösteriyor;
		// açıklamanın ayrıca ikna etmeye çalışması hem gereksiz hem de yanlışa
		// açık -- "kargoya verilmiş sipariş iade edilemez" gibi bir cümle,
		// siparişi kargolarken "tamamlandı" işaretleyen mağazalar için doğru
		// bile değildi.
		$fields = array(
			'statuses' => array(
				'title' => __( 'İade edilebilir sipariş durumları', 'hezarfen-for-woocommerce' ),
				'desc'  => __( 'Hangi durumdaki siparişler için iade talebi açılabileceği.', 'hezarfen-for-woocommerce' ),
			),
			'products' => array(
				'title' => __( 'İade edilebilir ürünler', 'hezarfen-for-woocommerce' ),
				'desc'  => __( 'Hangi ürün ve kategorilerin iade edilebileceği.', 'hezarfen-for-woocommerce' ),
			),
			'reasons'  => array(
				'title' => __( 'İade sebepleri', 'hezarfen-for-woocommerce' ),
				'desc'  => __( 'Müşterinin talep oluştururken seçeceği sebepler.', 'hezarfen-for-woocommerce' ),
			),
			'photos'   => array(
				'title' => __( 'Fotoğraflı iade talebi', 'hezarfen-for-woocommerce' ),
				'desc'  => __( 'Müşterinin talebe ürün fotoğrafı ekleyebilmesi.', 'hezarfen-for-woocommerce' ),
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
						<?php
						echo wp_kses_post(
							self::pro_link(
								__( 'Hezarfen Pro ile açın', 'hezarfen-for-woocommerce' ),
								'button button-primary'
							)
						);
						?>
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
				$this->render_status_preview();
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
	 * Mağazanın gerçek sipariş durumları, gerçekten iade edilebilir olanlar
	 * işaretli.
	 *
	 * Uydurma etiketler yerine kayıtlı ayar gösteriliyor: satır zaten bu ayarı
	 * temsil ediyor, temsilin de doğru olması gerekiyor.
	 *
	 * @return void
	 */
	private function render_status_preview() {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return;
		}

		$eligible = (array) get_option( 'hezarfen_returns_eligible_order_statuses', array( 'wc-completed' ) );
		$statuses = wc_get_order_statuses();

		// Uzun listeyi kısaltırken uygun olanlar önce geliyor; ayarın etkisi
		// ilk bakışta görünsün.
		uksort(
			$statuses,
			static function ( $left, $right ) use ( $eligible ) {
				return (int) in_array( $right, $eligible, true ) - (int) in_array( $left, $eligible, true );
			}
		);

		echo '<div class="hez-locked__chips">';

		foreach ( array_slice( $statuses, 0, 4, true ) as $key => $label ) {
			printf(
				'<span class="hez-locked__chip%1$s">%2$s</span>',
				in_array( $key, $eligible, true ) ? ' is-on' : '',
				esc_html( $label )
			);
		}

		echo '</div>';
	}

	/**
	 * Mağazanın gerçek ürün kategorileri, kuralın uygulanacağı denetimle.
	 *
	 * Kategori adları gerçek; kuralın kendisi ücretsiz sürümde bulunmadığı için
	 * denetim "Mağaza ayarını kullan" konumunda gösteriliyor. Var olmayan bir
	 * durumu varmış gibi göstermek yanlış olurdu.
	 *
	 * @return void
	 */
	private function render_product_preview() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 2,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		if ( is_wp_error( $terms ) || ! $terms ) {
			return;
		}

		foreach ( $terms as $term ) {
			?>
			<div class="hez-locked__line">
				<span class="hez-locked__line-label"><?php echo esc_html( $term->name ); ?></span>
				<span class="hez-locked__pill"><?php esc_html_e( 'Mağaza ayarını kullan', 'hezarfen-for-woocommerce' ); ?></span>
			</div>
			<?php
		}
	}

	/**
	 * Müşterinin ŞU AN gördüğü sebeplerin devre dışı kopyası.
	 *
	 * Örnek metin uydurulmuyor: satır bu listeyi düzenlemeyi vadediyor, o
	 * hâlde gösterdiği liste de gerçek olmalı. Mağaza kendi listesini
	 * tanıyarak neyi düzenleyeceğini anlıyor.
	 *
	 * @return void
	 */
	private function render_reason_preview() {
		if ( ! class_exists( '\Hezarfen\Inc\Returns\Core\Return_Reasons' ) ) {
			return;
		}

		$reasons = new \Hezarfen\Inc\Returns\Core\Return_Reasons();
		$choices = $reasons->get_choices();
		$notes   = $reasons->get_keys_requiring_note();

		if ( ! $choices ) {
			return;
		}

		$shown = array_slice( $choices, 0, 3, true );

		foreach ( $shown as $key => $label ) {
			?>
			<div class="hez-locked__row">
				<span class="hez-locked__grip">&#8942;&#8942;</span>
				<span class="hez-locked__input"><?php echo esc_html( $label ); ?></span>
				<span class="hez-locked__check">
					<input type="checkbox" disabled <?php checked( in_array( $key, $notes, true ) ); ?>>
					<?php esc_html_e( 'Açıklama iste', 'hezarfen-for-woocommerce' ); ?>
				</span>
			</div>
			<?php
		}

		$rest = count( $choices ) - count( $shown );

		if ( $rest > 0 ) {
			printf(
				'<p class="hez-locked__more">%s</p>',
				esc_html(
					sprintf(
						/* translators: %d: listede gösterilmeyen sebep sayısı. */
						_n('ve %d sebep daha', 've %d sebep daha', $rest, 'hezarfen-for-woocommerce' ),
						$rest
					)
				)
			);
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

			.hez-locked__more {
				margin: 6px 0 0;
				color: #8c8f94;
				font-size: 11px;
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
