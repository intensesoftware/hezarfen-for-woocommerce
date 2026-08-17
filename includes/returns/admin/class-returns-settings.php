<?php
/**
 * Contains the Returns_Settings screen.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Admin;

use Hezarfen\Inc\Returns\Core\Return_Settings;
use Hezarfen\Inc\Returns\Frontend\Guest_Returns;
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
		add_filter( 'woocommerce_get_sections_hezarfen', array( $this, 'add_section' ) );
		add_filter( 'woocommerce_get_settings_hezarfen', array( $this, 'add_settings' ), 10, 2 );
		add_action( 'woocommerce_update_options_hezarfen', array( $this, 'after_save' ), 20 );
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

		return array(
			array(
				'title' => __( 'İade Yönetimi', 'hezarfen-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Müşterileriniz hesabım sayfasından iade talebi oluşturabilir, siz de talepleri Hezarfen &rarr; İadeler ekranından yönetebilirsiniz.', 'hezarfen-for-woocommerce' ),
				'id'    => 'hezarfen_returns_settings_title',
			),
			array(
				'title'   => __( 'İade talebi özelliğini aç', 'hezarfen-for-woocommerce' ),
				'desc'    => __( 'Açıldığında "İade Talebi" sayfası otomatik oluşturulur ve hesabım menüsüne "İadelerim" eklenir.', 'hezarfen-for-woocommerce' ),
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
				'title'    => __( 'İade edilebilir sipariş durumları', 'hezarfen-for-woocommerce' ),
				'desc_tip' => __( 'Yalnızca bu durumlardaki siparişler için iade talebi oluşturulabilir.', 'hezarfen-for-woocommerce' ),
				'type'     => 'multiselect',
				'class'    => 'wc-enhanced-select',
				'id'       => Return_Settings::OPTION_ELIGIBLE_STATUSES,
				'default'  => array( 'wc-completed' ),
				'options'  => wc_get_order_statuses(),
			),
			array(
				'title'   => __( 'Üyeliksiz iade talebi', 'hezarfen-for-woocommerce' ),
				'desc'    => __( 'Hesabı olmayan müşteriler sipariş numarası ve e-posta ile iade talebi oluşturabilsin.', 'hezarfen-for-woocommerce' ),
				'type'    => 'checkbox',
				'id'      => Return_Settings::OPTION_GUEST_ENABLED,
				'default' => 'yes',
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
	 * Creates the public page and refreshes the rewrite rules the first
	 * time the feature is switched on.
	 *
	 * @return void
	 */
	public function after_save() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WooCommerce verified its own settings nonce before firing this action.
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';

		if ( self::SECTION !== $section || ! Return_Settings::is_enabled() ) {
			return;
		}

		Guest_Returns::ensure_page();

		// The account endpoints are registered on `init`, which already ran
		// for this request, so the rules have to be rebuilt explicitly.
		delete_option( \Hezarfen\Inc\Returns\Frontend\My_Account_Returns::ENDPOINT_VERSION_OPTION );
	}
}
