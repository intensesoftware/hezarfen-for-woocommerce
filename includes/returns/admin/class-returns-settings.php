<?php
/**
 * Contains the Returns_Settings screen.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Admin;

use Hezarfen\Inc\Returns\Core\Return_Settings;
use Hezarfen\Inc\Returns\Shipping\Return_Shipping_Registry;

defined( 'ABSPATH' ) || exit();

/**
 * Adds the "İade" section to the Hezarfen settings tab.
 *
 * Registered through WooCommerce's own section filters rather than by
 * editing the settings page class, so the returns module stays removable
 * in one piece.
 */
class Returns_Settings {

	const SECTION = 'returns';

	/**
	 * Constructor.
	 */
	public function __construct() {
		new Returns_Pro_Teasers();

		add_filter( 'woocommerce_get_sections_hezarfen', array( $this, 'add_section' ) );
		add_filter( 'woocommerce_get_settings_hezarfen', array( $this, 'add_settings' ), 10, 2 );
		add_action( 'woocommerce_update_options_hezarfen', array( $this, 'after_save' ), 20 );
		add_action( 'admin_notices', array( $this, 'render_missing_address_notice' ) );
	}

	/**
	 * Warns when returns are live but there is nowhere to send the goods.
	 *
	 * The return address fields are all optional, so a store can switch the
	 * module on with a single checkbox and never fill them in. With the
	 * default "customer ships it" method that leaves every approved customer
	 * looking at a page that has no address on it, and nothing tells the
	 * merchant — the failure happens on a screen they never open.
	 *
	 * @return void
	 */
	public function render_missing_address_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! Return_Settings::is_enabled() || Return_Settings::has_return_address() ) {
			return;
		}

		$registry = new Return_Shipping_Registry();

		// A method that collects the parcel from the customer's door needs
		// no address of ours; only the manual flow does.
		if ( ! $registry->get_active_method()->requires_customer_tracking() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = $screen ? $screen->id : '';

		// Kept to the screens the merchant is already on for this feature,
		// so it does not become wallpaper on every admin page.
		$screens = array( 'woocommerce_page_wc-settings', 'hezarfen_page_hezarfen-returns', 'woocommerce_page_hezarfen-returns' );

		if ( ! in_array( $id, $screens, true ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html__( 'İade modülü açık ve seçili yöntem ürünlerin size gönderilmesini bekliyor, ancak bir iade adresi girilmemiş. Talebi onayladığınız müşteri kargoyu nereye göndereceğini göremeyecek.', 'hezarfen-for-woocommerce' ),
			esc_url( admin_url( 'admin.php?page=wc-settings&tab=hezarfen&section=' . self::SECTION ) ),
			esc_html__( 'İade adresini girin', 'hezarfen-for-woocommerce' )
		);
	}

	/**
	 * Registers the section tab.
	 *
	 * @param array<string, string> $sections Existing sections.
	 *
	 * @return array<string, string>
	 */
	public function add_section( $sections ) {
		$sections[ self::SECTION ] = __( 'İade Yönetimi', 'hezarfen-for-woocommerce' );

		return $sections;
	}

	/**
	 * Supplies the fields of the section.
	 *
	 * @param array<int, array<string, mixed>> $settings   Fields of the current section.
	 * @param string                           $section_id Section being rendered.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function add_settings( $settings, $section_id ) {
		if ( self::SECTION !== $section_id ) {
			return $settings;
		}

		return array_merge( $this->get_general_fields(), $this->get_address_fields() );
	}

	/**
	 * The general fields of the section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_general_fields() {
		$registry = new Return_Shipping_Registry();

		$fields = array(
			array(
				'title' => __( 'İade Yönetimi', 'hezarfen-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Müşterileriniz Hesabım &rarr; Siparişler &rarr; sipariş detayından iade talebi oluşturabilir, siz de talepleri Hezarfen &rarr; İadeler ekranından yönetebilirsiniz.', 'hezarfen-for-woocommerce' ),
				'id'    => 'hezarfen_returns_settings_title',
			),
			array(
				'title'   => __( 'İade talebi özelliğini aç', 'hezarfen-for-woocommerce' ),
				'desc'    => __( 'Açıldığında müşteriler Hesabım &rarr; Siparişler &rarr; sipariş detay sayfasından iade talebi oluşturabilir.', 'hezarfen-for-woocommerce' ),
				'type'    => 'checkbox',
				'id'      => Return_Settings::OPTION_ENABLED,
				'default' => 'no',
			),
			array(
				'title'             => __( 'İade süresi (gün)', 'hezarfen-for-woocommerce' ),
				'desc_tip'          => __( 'Müşterinin iade talebi oluşturabileceği süre. 0 yazarsanız süre sınırı uygulanmaz.', 'hezarfen-for-woocommerce' ),
				'type'              => 'number',
				'id'                => Return_Settings::OPTION_WINDOW_DAYS,
				'default'           => 14,
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
			),
			array(
				'title'    => __( 'Süre başlangıcı', 'hezarfen-for-woocommerce' ),
				'desc_tip' => __( 'İade süresinin hangi tarihten itibaren sayılacağı.', 'hezarfen-for-woocommerce' ),
				'type'     => 'select',
				'id'       => Return_Settings::OPTION_WINDOW_REFERENCE,
				'default'  => Return_Settings::REFERENCE_COMPLETED,
				'options'  => array(
					Return_Settings::REFERENCE_COMPLETED => __( 'Sipariş tamamlandı tarihi', 'hezarfen-for-woocommerce' ),
					Return_Settings::REFERENCE_PAID      => __( 'Ödeme tarihi', 'hezarfen-for-woocommerce' ),
					Return_Settings::REFERENCE_CREATED   => __( 'Sipariş tarihi', 'hezarfen-for-woocommerce' ),
				),
			),
			array(
				'title'    => __( 'İade gönderim yöntemi', 'hezarfen-for-woocommerce' ),
				'desc_tip' => __( 'Onaylanan taleplerde ürünlerin size nasıl ulaşacağı.', 'hezarfen-for-woocommerce' ),
				'type'     => 'select',
				'id'       => Return_Settings::OPTION_SHIPPING_METHOD,
				'default'  => 'customer-ships',
				'options'  => $registry->get_choices(),
			),
			array(
				'title'    => __( 'Tamamlanan iadede WooCommerce iade kaydı oluştur', 'hezarfen-for-woocommerce' ),
				'desc'     => __( 'Talebi "Tamamlandı" işaretlediğinizde iade edilen ürünler için siparişe manuel iade kaydı düşülür.', 'hezarfen-for-woocommerce' ),
				'desc_tip' => __( 'Kayıt yalnızca WooCommerce içindir; para transferi ödeme altyapısına gönderilmez, iadeyi kendiniz yaparsınız. Sipariş toplamının tamamı iade edildiğinde WooCommerce siparişi kendiliğinden "İade edildi" durumuna çeker. Zaten elle iade işliyorsanız kapalı bırakın.', 'hezarfen-for-woocommerce' ),
				'type'     => 'checkbox',
				'id'       => Return_Settings::OPTION_AUTO_REFUND,
				'default'  => 'no',
			),
			array(
				'title'    => __( 'İade kaydında stoğu geri ekle', 'hezarfen-for-woocommerce' ),
				'desc'     => __( 'İade edilen adetler ürün stoğuna geri yazılır.', 'hezarfen-for-woocommerce' ),
				'desc_tip' => __( 'Yalnızca yukarıdaki iade kaydı açıkken çalışır. Hasarlı ürünleri tekrar satışa çıkarmıyorsanız kapalı bırakın.', 'hezarfen-for-woocommerce' ),
				'type'     => 'checkbox',
				'id'       => Return_Settings::OPTION_RESTOCK,
				'default'  => 'no',
			),
			array(
				'title'    => __( 'Müşteriye gösterilecek yönerge', 'hezarfen-for-woocommerce' ),
				'desc_tip' => __( 'Talep onaylandığında iade sayfasında gösterilir. Paketleme, fatura, etiket gibi konuları burada anlatabilirsiniz.', 'hezarfen-for-woocommerce' ),
				'type'     => 'textarea',
				'css'      => 'width:100%;height:90px;',
				'id'       => Return_Settings::OPTION_INSTRUCTIONS,
				'default'  => '',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'hezarfen_returns_settings_end',
			),
		);

		return $this->splice_locked_fields( $fields );
	}

	/**
	 * Drops the Pro-backed placeholders in beside the settings they belong to.
	 *
	 * @param array<int, array<string, mixed>> $fields Section fields.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function splice_locked_fields( $fields ) {
		// Anchored on the setting each placeholder sits beside, not on an
		// index, so reordering the section above cannot silently move one
		// somewhere it makes no sense. The eligibility trio goes together:
		// which orders, which products, and why.
		$after = array(
			Return_Settings::OPTION_WINDOW_REFERENCE => array( 'statuses', 'products', 'reasons' ),
			Return_Settings::OPTION_INSTRUCTIONS     => array( 'photos' ),
		);

		$merged = array();

		foreach ( $fields as $field ) {
			$merged[] = $field;

			$id = isset( $field['id'] ) ? $field['id'] : '';

			if ( ! isset( $after[ $id ] ) ) {
				continue;
			}

			foreach ( $after[ $id ] as $placement ) {
				/**
				 * Filters the settings fields rendered for one Pro-backed
				 * placement.
				 *
				 * The free plugin renders a single disabled preview row per
				 * placement (`statuses`, `products`, `reasons`, `photos`). Pro
				 * replaces a placement it implements by returning its own real
				 * setting fields here — or an empty array to drop the row
				 * entirely — so the swap needs no change to the free plugin.
				 *
				 * @param array<int, array<string, mixed>> $fields    Default locked preview row.
				 * @param string                           $placement Placement key.
				 */
				$placement_fields = apply_filters(
					'hezarfen_returns_setting_fields',
					Returns_Pro_Teasers::get_fields( $placement ),
					$placement
				);

				$merged = array_merge( $merged, (array) $placement_fields );
			}
		}

		return $merged;
	}

	/**
	 * The return address fields.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_address_fields() {
		return array(
			array(
				'title' => __( 'İade Adresi', 'hezarfen-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Müşterilerin ürünleri göndereceği adres. İade talebi sayfasında ve e-postalarda gösterilir.', 'hezarfen-for-woocommerce' ),
				'id'    => 'hezarfen_returns_address_title_section',
			),
			array(
				'title'    => __( 'Adres başlığı', 'hezarfen-for-woocommerce' ),
				'desc_tip' => __( 'Örn. Merkez Depo.', 'hezarfen-for-woocommerce' ),
				'type'     => 'text',
				'id'       => Return_Settings::OPTION_ADDRESS_LABEL,
				'default'  => '',
			),
			array(
				'title'   => __( 'Yetkili / firma adı', 'hezarfen-for-woocommerce' ),
				'type'    => 'text',
				'id'      => Return_Settings::OPTION_ADDRESS_CONTACT,
				'default' => '',
			),
			array(
				'title'   => __( 'Telefon', 'hezarfen-for-woocommerce' ),
				'type'    => 'text',
				'id'      => Return_Settings::OPTION_ADDRESS_PHONE,
				'default' => '',
			),
			array(
				'title'   => __( 'Açık adres', 'hezarfen-for-woocommerce' ),
				'type'    => 'textarea',
				'css'     => 'width:100%;height:70px;',
				'id'      => Return_Settings::OPTION_ADDRESS_LINE,
				'default' => '',
			),
			array(
				'title'   => __( 'Mahalle', 'hezarfen-for-woocommerce' ),
				'type'    => 'text',
				'id'      => Return_Settings::OPTION_ADDRESS_NEIGHBORHOOD,
				'default' => '',
			),
			array(
				'title'   => __( 'İlçe', 'hezarfen-for-woocommerce' ),
				'type'    => 'text',
				'id'      => Return_Settings::OPTION_ADDRESS_DISTRICT,
				'default' => '',
			),
			array(
				'title'   => __( 'İl', 'hezarfen-for-woocommerce' ),
				'type'    => 'text',
				'id'      => Return_Settings::OPTION_ADDRESS_CITY,
				'default' => '',
			),
			array(
				'title'   => __( 'Posta kodu', 'hezarfen-for-woocommerce' ),
				'type'    => 'text',
				'id'      => Return_Settings::OPTION_ADDRESS_POSTCODE,
				'default' => '',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'hezarfen_returns_address_end',
			),
		);
	}

	/**
	 * Rebuilds the account endpoints the first time the feature is switched
	 * on, so the return pages resolve without a manual permalink flush.
	 *
	 * @return void
	 */
	public function after_save() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WooCommerce verified its own settings nonce before firing this action.
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';

		if ( self::SECTION !== $section || ! Return_Settings::is_enabled() ) {
			return;
		}

		// The endpoints are registered on `init`, which already ran for this
		// request, so the rules have to be rebuilt on the next one.
		delete_option( \Hezarfen\Inc\Returns\Frontend\My_Account_Returns::ENDPOINT_VERSION_OPTION );
	}
}
