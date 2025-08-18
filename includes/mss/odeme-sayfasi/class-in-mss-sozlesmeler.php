<?php
/**
 * Ödeme sayfasındaki işlemleri yürütür (sözleşmelerin SSR gösterilmesi, Ajax endpointler vs.)
 *
 * @package Intense\MSS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use In_MSS_Utility as Utility;

/**
 * IN_MSS_OdemeSayfasi_Sozlesmeler
 */
class IN_MSS_OdemeSayfasi_Sozlesmeler {
	use Utility;

	const HEZ_FAT_CONDITIONAL_DIV_WRAPPER_CLASS = 'in-mss-hez-fat-blok';
	const HEZ_FAT_BIREYSEL_DIV_WRAPPER_CLASS = 'in-mss-hez-fat-bireysel';
	const HEZ_FAT_KURUMSAL_DIV_WRAPPER_CLASS = 'in-mss-hez-fat-kurumsal';

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'woocommerce_checkout_before_terms_and_conditions', array( $this, 'render_forms' ), 10 );

		/** Şartlar ve Koşullar
		 * Bu alan iyileştirilebilir.  02.04.2019
		 */

		add_action( 'woocommerce_checkout_after_terms_and_conditions', array( $this, 'intense_mss_onay_checkbox' ) );

		add_action( 'woocommerce_checkout_process', array( $this, 'intense_mss_checkout_process' ) );

		add_action( 'wp_footer', array( $this, 'jquery_adres_degisiklikleri_checkout_guncellemesi' ) );

		add_action( 'wp_ajax_adres_bilgilerini_guncelle_callback', array( $this, 'adres_bilgilerini_guncelle_callback' ) );

		add_action( 'wp_ajax_nopriv_adres_bilgilerini_guncelle_callback', array( $this, 'adres_bilgilerini_guncelle_callback' ) );
	}

	/**
	 * Sözleşme taslağına değişkenleri yerleştirir
	 *
	 * @param  string $statik_form sözleşme taslağı.
	 * @return string
	 */
	private function html_forma_degiskenleri_bas( $statik_form ) {
		$dinamik_form = str_replace(
			array(
				'{FATURA_TAM_AD_UNVANI}',
				'{FATURA_ADRESI}',
				'{ALICI_ADRESI}',
				'{ALICI_TELEFONU}',
				'{ALICI_EPOSTA}',
				'{ALICI_TAM_AD_UNVANI}',
				'{GUNCEL_TARIH}',
				'{URUNLER}',
				'{ODEME_YONTEMI}',
				'{KARGO_BEDELI}',
				'{KARGO_HARIC_SIPARIS_TUTARI}',
				'{KARGO_DAHIL_SIPARIS_TUTARI}',
				'{INDIRIM_TOPLAMI}',
				'{INDIRIM_TOPLAM_VERGISI}',
				'{EK_UCRET_DETAYLARI}',
			),
			array(
				IN_MSS_OdemeSayfasi_KullaniciDegiskenler::get_fatura_unvani(),
				IN_MSS_OdemeSayfasi_KullaniciDegiskenler::get_fatura_adres(),
				IN_MSS_OdemeSayfasi_KullaniciDegiskenler::get_musteri_adres(),
				IN_MSS_OdemeSayfasi_KullaniciDegiskenler::get_musteri_telefon(),
				IN_MSS_OdemeSayfasi_KullaniciDegiskenler::get_musteri_email(),
				IN_MSS_OdemeSayfasi_KullaniciDegiskenler::get_musteri_unvani(),
				date_i18n( 'd/m/Y' ),
				IN_MSS_OdemeSayfasi_SepetDegiskenler::get_siparis_urun_ozeti(),
				'<span class="obf_mss_payment_method"></span>',
				IN_MSS_OdemeSayfasi_SepetDegiskenler::get_siparis_kargo_bedeli(),
				IN_MSS_OdemeSayfasi_SepetDegiskenler::get_siparis_kargo_haric_bedel(),
				IN_MSS_OdemeSayfasi_SepetDegiskenler::get_siparis_kargo_dahil_bedel(),
				IN_MSS_OdemeSayfasi_SepetDegiskenler::get_indirim_toplami(),
				IN_MSS_OdemeSayfasi_SepetDegiskenler::get_indirim_toplam_vergisi(),
				IN_MSS_OdemeSayfasi_SepetDegiskenler::get_ek_ucret_detaylari(),
			),
			$statik_form
		);
		return $dinamik_form;

	}

	/**
	 * Sözleşme taslağındaki özel alan tutucuları yerlerine değişkenleri yerleştirir.
	 *
	 * @param  string $form_icerik sözleşme taslağı.
	 * @return string
	 */
	private function forma_ozel_alan_tutuculari_yerlestir( $form_icerik ) {
		// özel alan deseni.
		$desen = '/\{OZELALAN_(.*?)\}/';

		// özel alanların tespiti.
		preg_match_all( $desen, $form_icerik, $ozel_alan_adlari );

		// özel alan değişkenlerinin yerine konulması.
		foreach ( $ozel_alan_adlari[1] as $ozel_alan_name ) {
			$form_icerik = str_replace(
				sprintf( '{OZELALAN_%s}', $ozel_alan_name ),
				sprintf( '<span data-mss-custom-field-id="%s" class="obf_mss_ozelalan obf_mss_ozelalan_%s"></span>', $ozel_alan_name, $ozel_alan_name ),
				$form_icerik
			);
		}

		return $form_icerik;
	}

	/**
	 * Hezarfen Desteği
	 *
	 * @param  string $form_icerik
	 * @return string
	 */
	private function form_hezarfen_destegi( $form_icerik ) {
		if( ! $this->hezarfen_aktif() ) {
			return $form_icerik;
		}

		$form_icerik = preg_replace_callback( IN_MSS_SiparisSonrasi::REGEX_PATTERN_HEZARFEN_FATURA_KURUMSAL, function($matches) {
			return sprintf('<div class="%s %s">%s</div>', self::HEZ_FAT_CONDITIONAL_DIV_WRAPPER_CLASS, self::HEZ_FAT_KURUMSAL_DIV_WRAPPER_CLASS, $matches[1]);
		}, $form_icerik );

		$form_icerik = preg_replace_callback( IN_MSS_SiparisSonrasi::REGEX_PATTERN_HEZARFEN_FATURA_BIREYSEL, function($matches) {
			return sprintf('<div class="%s %s">%s</div>', self::HEZ_FAT_CONDITIONAL_DIV_WRAPPER_CLASS, self::HEZ_FAT_BIREYSEL_DIV_WRAPPER_CLASS, $matches[1]);
		}, $form_icerik );

		$mapper = [
			'/\{HEZARFEN_KURUMSAL_VERGI_DAIRE\}/'=>'billing_hez_tax_office',
			'/\{HEZARFEN_KURUMSAL_VERGI_NO}/'=>'billing_hez_tax_number',
			'/\{HEZARFEN_BIREYSEL_TC\}/'=>'billing_hez_TC_number',
		];

		foreach($mapper as $degisken_callback=>$input_name) {
			$form_icerik = preg_replace_callback($degisken_callback, function($matches) use ($input_name){
				return sprintf('<span data-mss-custom-field-id="%1$s" class="obf_mss_ozelalan obf_mss_ozelalan_%1$s"></span>', $input_name );
			}, $form_icerik);
		}

		return $form_icerik;
	}

	/**
	 * Sözleşmeler için render fonksiyonu
	 *
	 * @return void
	 */
	public function render_forms() {
		$ayarlar = get_option( 'intense_mss_ayarlar' );

		// her iki form da eşleştirilmemişse, sonlandır.
		if ( ! $ayarlar['obf_taslak_id'] > 0 && ! $ayarlar['mss_taslak_id'] > 0 ) {
			return;
		}

		if ( $ayarlar['ozel_sozlesme_1_taslak_id'] > 0 ) {
			$ozel_sozleme_1_post = get_post( apply_filters( 'wpml_object_id', $ayarlar['ozel_sozlesme_1_taslak_id'], 'mss', true ) );

			$raw_content_ozel_sozlesme_1 = wpautop( $ozel_sozleme_1_post->post_content );

			$ozel_sozlesme_1_content = $this->html_forma_degiskenleri_bas( $this->forma_ozel_alan_tutuculari_yerlestir( $this->form_hezarfen_destegi( $raw_content_ozel_sozlesme_1 ) ) );
		}

		if ( $ayarlar['ozel_sozlesme_2_taslak_id'] > 0 ) {
			$ozel_sozleme_2_post = get_post( apply_filters( 'wpml_object_id', $ayarlar['ozel_sozlesme_2_taslak_id'], 'mss', true ) );

			$raw_content_ozel_sozlesme_2 = wpautop( $ozel_sozleme_2_post->post_content );

			$ozel_sozlesme_2_content = $this->html_forma_degiskenleri_bas( $this->forma_ozel_alan_tutuculari_yerlestir( $this->form_hezarfen_destegi( $raw_content_ozel_sozlesme_2 ) ) );
		}

		if ( $ayarlar['obf_taslak_id'] > 0 ) {
			$obf_taslak_post = get_post( apply_filters( 'wpml_object_id', $ayarlar['obf_taslak_id'], 'mss', true ) );

			$raw_content_obf = wpautop( $obf_taslak_post->post_content );

			$obf_content = $this->html_forma_degiskenleri_bas( $this->forma_ozel_alan_tutuculari_yerlestir( $this->form_hezarfen_destegi( $raw_content_obf ) ) );
		}

		if ( $ayarlar['mss_taslak_id'] > 0 ) {
			$mss_taslak_post = get_post( apply_filters( 'wpml_object_id', $ayarlar['mss_taslak_id'], 'mss', true ) );

			$raw_content_mss = wpautop( $mss_taslak_post->post_content );

			$mss_content = $this->html_forma_degiskenleri_bas( $this->forma_ozel_alan_tutuculari_yerlestir( $this->form_hezarfen_destegi( $raw_content_mss ) ) );
		}

		if ( 'inline' === (string) $ayarlar['odeme_sayfasinda_sozlesme_gosterim_tipi'] ) {
			include_once INTENSE_MSS_UYGULAMA_PATH . 'fonksiyonlar/odeme-sayfasi/views/html-odeme-sozlesmeler-inline.php';
		} elseif ( 'modal' === (string) $ayarlar['odeme_sayfasinda_sozlesme_gosterim_tipi'] ) {
			include_once INTENSE_MSS_UYGULAMA_PATH . 'fonksiyonlar/odeme-sayfasi/views/html-odeme-sozlesmeler-modal.php';
		} else {
			include_once INTENSE_MSS_UYGULAMA_PATH . 'fonksiyonlar/odeme-sayfasi/views/html-odeme-sozlesmeler-inline.php';
		}
	}

	/**
	 * Ödeme ekranında sözleşme onay kutucukları ve sözleşmelerin modal veya inline olarak gösterilmesi
	 *
	 * @return void
	 */
	public function intense_mss_onay_checkbox() {
		$ayarlar = get_option( 'intense_mss_ayarlar' );

		if ( 'inline' === $ayarlar['odeme_sayfasinda_sozlesme_gosterim_tipi'] ) {
			include_once INTENSE_MSS_UYGULAMA_PATH . 'fonksiyonlar/odeme-sayfasi/views/html-sozlesme-onay-checkbox-inline.php';
		} elseif ( 'modal' === $ayarlar['odeme_sayfasinda_sozlesme_gosterim_tipi'] ) {
			include_once INTENSE_MSS_UYGULAMA_PATH . 'fonksiyonlar/odeme-sayfasi/views/html-sozlesme-onay-checkbox-modal.php';
		} else {
			include_once INTENSE_MSS_UYGULAMA_PATH . 'fonksiyonlar/odeme-sayfasi/views/html-sozlesme-onay-checkbox-inline.php';
		}
	}

	/**
	 * Sözleşme onay kutuları için validation
	 *
	 * @return void
	 */
	public function intense_mss_checkout_process() {
		$ayarlar = get_option( 'intense_mss_ayarlar' );
		$gosterilmeyecek_sozlesmeler = array_key_exists( 'gosterilmeyecek_sozlesmeler', $ayarlar ) ? $ayarlar['gosterilmeyecek_sozlesmeler'] : array();

		//phpcs:disable
		if ( ! in_array( 'mss', $gosterilmeyecek_sozlesmeler, true ) && empty( $_POST['intense_mss_onay_checkbox'] ) ) {
			wc_add_notice( __( 'You need to read and accept the distance sales contract.', 'intense-mss-for-woocommerce' ), 'error' );
		}

		if ( ! in_array( 'obf', $gosterilmeyecek_sozlesmeler, true ) && empty ( $_POST['intense_obf_onay_checkbox'] ) ) {
			wc_add_notice( __( 'You need to read and accept the preliminary information form.', 'intense-mss-for-woocommerce' ), 'error' );
		}

		if ( ! in_array( 'custom_1', $gosterilmeyecek_sozlesmeler, true ) && $ayarlar['ozel_sozlesme_1_taslak_id'] > 0 && empty ( $_POST['intense_ozel_sozlesme_1_onay_checkbox'] ) ) {
			$ozel_sozleme_1_post = get_post( apply_filters( 'wpml_object_id', $ayarlar['ozel_sozlesme_1_taslak_id'], 'mss', true ) );
			wc_add_notice( sprintf( __( 'You need to read and accept the %s.', 'intense-mss-for-woocommerce' ), $ozel_sozleme_1_post->post_title), 'error' );
		}

		if ( ! in_array( 'custom_2', $gosterilmeyecek_sozlesmeler, true ) && $ayarlar['ozel_sozlesme_2_taslak_id'] > 0 && empty ( $_POST['intense_ozel_sozlesme_2_onay_checkbox'] ) ) {
			$ozel_sozleme_2_post = get_post( apply_filters( 'wpml_object_id', $ayarlar['ozel_sozlesme_2_taslak_id'], 'mss', true ) );
			wc_add_notice( sprintf( __( 'You need to read and accept the %s.', 'intense-mss-for-woocommerce' ), $ozel_sozleme_2_post->post_title), 'error' );
		}
		//phpcs:enable
	}

	/**
	 * Ödeme alanlarında değişiklik olduğunda sözleşmelerin yenilenmesi
	 *
	 * @return void
	 */
	public function jquery_adres_degisiklikleri_checkout_guncellemesi() {
		if ( ! is_checkout() ) {
			return;
		}
		?>

		<script type="text/javascript">
			(function($){
				$('body').on('updated_checkout', function(){
					$('.obf_mss_ozelalan').each(function(){
						var ozel_alan_input_id = $(this).attr('data-mss-custom-field-id');
						var ozel_alan_input_type = $("input[name=" + ozel_alan_input_id +"]").attr('type');

						if(ozel_alan_input_type!='radio')
							var input_value = $("input[name=" + ozel_alan_input_id +"]").val();
						else
							var input_value = $("input[name=" + ozel_alan_input_id +"]:checked").val();

						$("."+"obf_mss_ozelalan_"+ozel_alan_input_id).html(input_value);
					});

					<?php if($this->hezarfen_aktif()){ ?>
						const faturaTipi = $("select[name=billing_hez_invoice_type]").val();

						if( faturaTipi === 'person' ) {
							$('.<?php echo self::HEZ_FAT_KURUMSAL_DIV_WRAPPER_CLASS; ?>').hide();
							$('.<?php echo self::HEZ_FAT_BIREYSEL_DIV_WRAPPER_CLASS; ?>').show();
						}else if (faturaTipi === 'company') {
							$('.<?php echo self::HEZ_FAT_BIREYSEL_DIV_WRAPPER_CLASS; ?>').hide();
							$('.<?php echo self::HEZ_FAT_KURUMSAL_DIV_WRAPPER_CLASS; ?>').show();
						}
					<?php } ?>
				});

				jQuery('body').one('updated_checkout', function(){
					$('form').on( 'change', '.woocommerce-billing-fields, .woocommerce-shipping-fields', function(event){
						if($('#ship-to-different-address input').prop("checked")){
							var shipping_first_name  = $('#shipping_first_name').val();
							var shipping_last_name = $('#shipping_last_name').val();
							var shipping_company = $('#shipping_company').val();
						}else{
							var shipping_first_name  = $('#billing_first_name').val();
							var shipping_last_name = $('#billing_last_name').val();
							var shipping_company = $('#billing_company').val();
						}

						var data = {
							'action':'adres_bilgilerini_guncelle_callback',
							'_wpnonce': '<?php echo esc_js( wp_create_nonce( 'adres-bilgilerini-guncelle' ) ); ?>',
							'billing_first_name': $('#billing_first_name').val(),
							'billing_last_name': $('#billing_last_name').val(),
							'billing_company': $('#billing_company').val(),
							'billing_phone': $('#billing_phone').val(),
							'billing_email': $('#billing_email').val(),
							'shipping_first_name': shipping_first_name,
							'shipping_last_name': shipping_last_name,
							'shipping_company': shipping_company,
						};

						$.post(ajax_object.ajax_url, data, function(response){
							$('body').trigger('update_checkout');
						});
					});
				});
			})(jQuery);
		</script>
		<?php
	}

	/**
	 * Ödeme ekranındaki alanlar güncellendiğinde, ilgili alanları kaydet.
	 *
	 * @return void
	 */
	public function adres_bilgilerini_guncelle_callback() {
		global $woocommerce;

		check_ajax_referer( 'adres-bilgilerini-guncelle' );

		$woocommerce->customer->set_billing_first_name( sanitize_text_field( $_POST['billing_first_name'] ) );
		$woocommerce->customer->set_billing_last_name( sanitize_text_field( $_POST['billing_last_name'] ) );
		$woocommerce->customer->set_billing_company( sanitize_text_field( $_POST['billing_company'] ) );
		$woocommerce->customer->set_billing_phone( wc_sanitize_phone_number( $_POST['billing_phone'] ) );
		$woocommerce->customer->set_billing_email( sanitize_email( $_POST['billing_email'] ) );
		$woocommerce->customer->set_shipping_first_name( sanitize_text_field( $_POST['shipping_first_name'] ) );
		$woocommerce->customer->set_shipping_last_name( sanitize_text_field( $_POST['shipping_last_name'] ) );
		$woocommerce->customer->set_shipping_company( sanitize_text_field( $_POST['shipping_company'] ) );

		wp_die();
	}
}

new IN_MSS_OdemeSayfasi_Sozlesmeler();
