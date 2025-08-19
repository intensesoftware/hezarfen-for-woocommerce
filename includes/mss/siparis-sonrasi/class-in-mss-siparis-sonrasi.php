<?php
/**
 * IN_MSS_SiparisSonrasi
 *
 * @package Intense\MSS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use In_MSS_Utility as Utility;

/**
 * Siparis sonrasi islemlerini yurutur (siparis verilerine gore sozlesme olusturma, e-posta vs.)
 */
class IN_MSS_SiparisSonrasi {
	use Utility;

	const REGEX_PATTERN_HEZARFEN_FATURA_BIREYSEL = '/@IF_HEZARFEN_FAT_BIREYSEL\s*([\s\S]*?)\s*@END(?:IF)?_HEZARFEN_FAT_BIREYSEL/';
	const REGEX_PATTERN_HEZARFEN_FATURA_KURUMSAL = '/@IF_HEZARFEN_FAT_KURUMSAL\s*([\s\S]*?)\s*@END(?:IF)?_HEZARFEN_FAT_KURUMSAL/';

	/**
	 * Ayarlar
	 *
	 * @var array
	 */
	private $ayarlar;

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {

		$this->ayarlar = get_option( 'hezarfen_mss_settings' );

		$sozlesme_olusturma_tipi = isset($this->ayarlar['sozlesme_olusturma_tipi']) ? $this->ayarlar['sozlesme_olusturma_tipi'] : 'yeni_siparis';

		// Use the variable instead of directly accessing the array
		if ( 'isleniyor' === $sozlesme_olusturma_tipi ) {
			add_action( 'woocommerce_order_status_processing', array( $this, 'sozlesmeleri_isleme_al' ) );
		} else {
			add_action( 'woocommerce_thankyou', array( $this, 'sozlesmeleri_isleme_al' ) );
		}

		add_action( 'woocommerce_email_customer_details', array( $this, 'epostaya_sozlesmeleri_dahil_et' ), 100, 4 );
	}

	/**
	 * Musteri IP Adres
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$ipaddress = '';
		if ( getenv( 'HTTP_CLIENT_IP' ) ) {
			$ipaddress = getenv( 'HTTP_CLIENT_IP' );
		} elseif ( getenv( 'HTTP_X_FORWARDED_FOR' ) ) {
			$ipaddress = getenv( 'HTTP_X_FORWARDED_FOR' );
		} elseif ( getenv( 'HTTP_X_FORWARDED' ) ) {
			$ipaddress = getenv( 'HTTP_X_FORWARDED' );
		} elseif ( getenv( 'HTTP_FORWARDED_FOR' ) ) {
			$ipaddress = getenv( 'HTTP_FORWARDED_FOR' );
		} elseif ( getenv( 'HTTP_FORWARDED' ) ) {
			$ipaddress = getenv( 'HTTP_FORWARDED' );
		} elseif ( getenv( 'REMOTE_ADDR' ) ) {
			$ipaddress = getenv( 'REMOTE_ADDR' );
		} else {
			$ipaddress = 'UNKNOWN';
		}

		if ( strpos( $ipaddress, ',' ) !== false ) {
			return trim( explode( ',', $ipaddress )[0] );
		}

		return $ipaddress;
	}

	/**
	 * Sozlesmeleri olustur ve mail gonder.
	 *
	 * @param  int $order_id WC Order ID.
	 * @return void
	 */
	public function sozlesmeleri_isleme_al( $order_id ) {
		$render = $this->render_forms( $order_id );

		$order = wc_get_order( $order_id );

		$intense_obf = $render['obf'];
		$intense_mss = $render['mss'];
		$ozel_sozlesme_1_content = $render['ozel_sozlesme_1_content'];
		$ozel_sozlesme_2_content = $render['ozel_sozlesme_2_content'];

		// Get contract titles from the new dynamic system
		$active_contracts = \Hezarfen\Inc\MSS\Core\Contract_Manager::get_active_contracts();
		$contract_titles = array();
		
		foreach ( $active_contracts as $contract ) {
			$contract_titles[ $contract['type'] ] = $contract['name'];
		}
		
		// Set titles for backward compatibility (these are used in database storage)
		$ozel_sozlesme_1_baslik = isset( $contract_titles['cayma_hakki'] ) ? $contract_titles['cayma_hakki'] : null;
		$ozel_sozlesme_2_baslik = isset( $contract_titles['custom'] ) ? $contract_titles['custom'] : null;

		global $wpdb;

		$kayitli_sozlesme_query  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}intense_sozlesmeler WHERE order_id=%s", $order_id ) );
		$toplam_kayitli_sozlesme = count( $kayitli_sozlesme_query );

		/** Eğer sözleşme daha önceden kayıt edildiyse, tekrar işlem yapma */
		if ( $toplam_kayitli_sozlesme > 0 ) {
			return;
		}

		$ip_adresi    = $this->get_client_ip();
		$islem_zamani = current_time( 'mysql' );

		$wpdb->insert(
			$wpdb->prefix . 'intense_sozlesmeler',
			array(
				'order_id'    => $order_id,
				'mss_icerik'  => $intense_mss,
				'obf_icerik'  => $intense_obf,
				'ozel_sozlesme_1_baslik' => $ozel_sozlesme_1_baslik,
				'ozel_sozlesme_1_icerik' => $ozel_sozlesme_1_content,
				'ozel_sozlesme_2_baslik' => $ozel_sozlesme_2_baslik,
				'ozel_sozlesme_2_icerik' => $ozel_sozlesme_2_content,
				'ip_adresi'   => $ip_adresi,
				'islem_zaman' => $islem_zamani,
			)
		);

		/**
		 *
		 * E-Posta Gönderimi
		 */
		$uygulama_ayarlar = get_option( 'hezarfen_mss_settings' );

		$yonetici_sozlesme_saklama_eposta_adresi = $uygulama_ayarlar['yonetici_sozlesme_saklama_eposta_adresi'];

		if ( $yonetici_sozlesme_saklama_eposta_adresi ) {
			$konu = sprintf( '%s Nolu Sipariş Mesafeli Satış Sözleşmesi ve Ön Bilgilendirme Formu', $order_id );

			$mesaj = '<p>' . esc_html__( 'Tarih:', 'intense-mss-for-woocommerce' ) . ' ' . esc_html( date_i18n( 'd/m/Y', strtotime( $islem_zamani ) ) ) . '</p>';

			$mesaj .= '<p>' . esc_html__( 'Zaman:', 'intense-mss-for-woocommerce' ) . ' ' . esc_html( date_i18n( 'H:i:s', strtotime( $islem_zamani ) ) ) . '</p>';

			$mesaj .= '<p>' . esc_html__( 'IP Adresi:', 'intense-mss-for-woocommerce' ) . ' ' . esc_html( $ip_adresi ) . '</p>';

			$mesaj .= '<p>' . esc_html__( 'Sipariş No:', 'intense-mss-for-woocommerce' ) . ' ' . $order->get_order_number() . '</p>';

			if( $has_ozel_sozlesme_1 ) {
				$mesaj .= '<h2>' . esc_html( $ozel_sozlesme_1_baslik ) . '</h2>';

				$mesaj .= sprintf( '<div>%s</div>', $ozel_sozlesme_1_content );
			}

			if( $has_ozel_sozlesme_2 ) {
				$mesaj .= '<h2>' . esc_html( $ozel_sozlesme_2_baslik ) . '</h2>';

				$mesaj .= sprintf( '<div>%s</div>', $ozel_sozlesme_2_content );
			}

			$mesaj .= '<h2>' . __( 'Mesafeli Satış Sözleşmesi:', 'intense-mss-for-woocommerce' ) . '</h2>';

			$mesaj .= sprintf( '<div>%s</div>', $intense_mss );

			$mesaj .= '<h2>' . __( 'Ön Bilgilendirme Formu:', 'intense-mss-for-woocommerce' ) . '</h2>';

			$mesaj .= sprintf( '<div>%s</div>', $intense_obf );

			add_filter( 'wp_mail_content_type', array( $this, 'intense_wp_mail_formati_html_yap' ) );

			wp_mail( $yonetici_sozlesme_saklama_eposta_adresi, $konu, $mesaj );

			remove_filter( 'wp_mail_content_type', array( $this, 'intense_wp_mail_formati_html_yap' ) );
		}
	}

	/**
	 * Mail content type degerini belirler
	 *
	 * @return string
	 */
	public function intense_wp_mail_formati_html_yap() {
		return 'text/html';
	}

	/**
	 * Epostaya sozlesmeleri dahil et.
	 *
	 * @param  \WC_Order $order that WC Order.
	 * @param  bool      $sent_to_admin Admin'e gonderilmeli mi?.
	 * @param  bool      $plain_text plain - duz yazi mail mi?.
	 * @param  \WC_Email $email email - email sablon class.
	 * @return void
	 */
	public function epostaya_sozlesmeleri_dahil_et( $order, $sent_to_admin, $plain_text, $email ) {
		if ( ! apply_filters( 'intense_mss_include_agreements_in_customer_email', true ) ) {
			return;
		}

		/**
		 * Yeni Sipariş E-Postası: WC_Email_New_Order
		 * işleniyor: WC_Email_Customer_Processing_Order
		 */

		if ( 'isleniyor' === $this->ayarlar['sozlesme_olusturma_tipi'] ) {
			if ( 'WC_Email_Customer_Processing_Order' === get_class( $email ) ) {
				$this->email_icerik( $order );
			}
		} else {
			if ( 'WC_Email_Customer_On_Hold_Order' === get_class( $email ) ) {
				$this->email_icerik( $order );
			}
		}

	}

	/**
	 * E-mail icerigi
	 *
	 * @param  \WC_Order $order WC Order instance.
	 * @return void
	 */
	private function email_icerik( $order ) {
		$order_id = $order->get_id();

		// Bu siparis icin daha once e-posta gonderimi yapildi mi?
		$eposta_gonderildi_mi = $order->get_meta( '_in_mss_eposta_gonderildi_mi', true );

		// daha önceden e-posta göndeirldiyse, sözleşmeleleri tekrar gönderme.
		if ( 1 === $eposta_gonderildi_mi ) {
			return;
		}

		update_post_meta( $order_id, '_in_mss_eposta_gonderildi_mi', 1 );

		$render = $this->render_forms( $order_id );

		$intense_obf = $render['obf'];
		$intense_mss = $render['mss'];

		$ozel_sozlesme_1_content = $render['ozel_sozlesme_1_content'];
		$ozel_sozlesme_2_content = $render['ozel_sozlesme_2_content'];

		$hezarfen_mss_settings = get_option( 'hezarfen_mss_settings', array() );

		$has_ozel_sozlesme_1 = isset($hezarfen_mss_settings['ozel_sozlesme_1_taslak_id']) && $hezarfen_mss_settings['ozel_sozlesme_1_taslak_id'] > 0;
		$has_ozel_sozlesme_2 = isset($hezarfen_mss_settings['ozel_sozlesme_2_taslak_id']) && $hezarfen_mss_settings['ozel_sozlesme_2_taslak_id'] > 0;

		if ( $has_ozel_sozlesme_1 ) {
			$ozel_sozlesme_1_post = get_post( apply_filters( 'wpml_object_id', $hezarfen_mss_settings['ozel_sozlesme_1_taslak_id'], 'intense_mss_form', true ) );
			$ozel_sozlesme_1_baslik = $ozel_sozlesme_1_post->post_title;
		}else{
			$ozel_sozlesme_1_baslik = null;
		}

		if ( $has_ozel_sozlesme_2 ) {
			$ozel_sozlesme_2_post = get_post( apply_filters( 'wpml_object_id', $hezarfen_mss_settings['ozel_sozlesme_2_taslak_id'], 'intense_mss_form', true ) );
			$ozel_sozlesme_2_baslik = $ozel_sozlesme_2_post->post_title;
		}else{
			$ozel_sozlesme_2_baslik = null;
		}

		if( $has_ozel_sozlesme_1 ):
		?>
		<h3><?php echo esc_html( $ozel_sozlesme_1_baslik ); ?></h3>
		<div style="height:300px;overflow:scroll;margin-bottom:15px;border:1px solid #dddddd;padding:15px">
			<?php echo wp_kses_post( $ozel_sozlesme_1_content ); ?>
		</div>
		<?php
		endif;

		if( $has_ozel_sozlesme_2 ):
			?>
			<h3><?php echo esc_html( $ozel_sozlesme_2_baslik ); ?></h3>
			<div style="height:300px;overflow:scroll;margin-bottom:15px;border:1px solid #dddddd;padding:15px">
				<?php echo wp_kses_post( $ozel_sozlesme_2_content ); ?>
			</div>
			<?php
		endif;
		?>

		<h3><?php esc_html_e( 'Preliminary Information Form', 'intense-mss-for-woocommerce' ); ?></h3>
		<div style="height:300px;overflow:scroll;margin-bottom:15px;border:1px solid #dddddd;padding:15px">
			<?php echo wp_kses_post( $intense_obf ); ?>
		</div>

		<h3><?php esc_html_e( 'Distance Sales Agreement', 'intense-mss-for-woocommerce' ); ?></h3>
		<div style="height:300px;overflow:scroll;margin-bottom:15px;border:1px solid #dddddd;padding:15px">
			<?php echo wp_kses_post( $intense_mss ); ?>
		</div>
		<?php
	}

	/**
	 * HTML forma degiskenlerin yerlestirilmesi
	 *
	 * @param  string $statik_form ham sozlesme taslagi.
	 * @param  int    $order_id WC siparis ID.
	 * @return string
	 */
	private function html_forma_degiskenleri_bas( $statik_form, $order_id ) {

		$siparis_degiskenler = new IN_MSS_SiparisDegiskenler( $order_id );

		$form_degiskenler = array(
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
		);

		$form_degisken_degerler = array(
			$siparis_degiskenler->get_fatura_unvani(),
			$siparis_degiskenler->get_fatura_adres(),
			$siparis_degiskenler->get_musteri_adres(),
			$siparis_degiskenler->get_musteri_telefon(),
			$siparis_degiskenler->get_musteri_email(),
			$siparis_degiskenler->get_musteri_unvani(),
			date_i18n( 'd/m/Y' ),
			$siparis_degiskenler->get_siparis_urun_ozeti(),
			$siparis_degiskenler->get_siparis_odeme_yontemi(),
			wc_price( $siparis_degiskenler->get_siparis_kargo_bedeli() ),
			wc_price( $siparis_degiskenler->get_siparis_kargo_haric_bedel() ),
			wc_price( $siparis_degiskenler->get_siparis_kargo_dahil_bedel() ),
			wc_price( $siparis_degiskenler->get_indirim_toplami() ),
			wc_price( $siparis_degiskenler->get_indirim_toplam_vergisi() ),
			$siparis_degiskenler->get_ek_ucret_detaylari(),
		);

		$order = wc_get_order( $order_id );

		if( $this->hezarfen_aktif() ) {
			$form_degiskenler[] = '{HEZARFEN_BIREYSEL_TC}';
			$form_degiskenler[] = '{HEZARFEN_KURUMSAL_VERGI_DAIRE}';
			$form_degiskenler[] = '{HEZARFEN_KURUMSAL_VERGI_NO}';

			$form_degisken_degerler[] = $siparis_degiskenler->get_hezarfen_fatura_TC();
			$form_degisken_degerler[] = $siparis_degiskenler->get_hezarfen_fatura_vergi_daire();
			$form_degisken_degerler[] = $siparis_degiskenler->get_hezarfen_fatura_vergi_no();

			$fatura_tipi = $order->get_meta( '_billing_hez_invoice_type' );

			if( $fatura_tipi === 'person' ) {
				// Kurumsal blok içeriğini temizle
				$statik_form = preg_replace( self::REGEX_PATTERN_HEZARFEN_FATURA_KURUMSAL, '', $statik_form );

				// Bireysel IF bloklarini temizle, içeriğini tut.
				$statik_form = str_replace(['@IF_HEZARFEN_FAT_BIREYSEL', '@ENDIF_HEZARFEN_FAT_BIREYSEL'], '', $statik_form);
			}else if( $fatura_tipi === 'company' ) {
				// Bireysel blok içeriğini temizle
				$statik_form = preg_replace( self::REGEX_PATTERN_HEZARFEN_FATURA_BIREYSEL, '', $statik_form );

				// Kurumsal IF bloklarini temizle, içeriğini tut.
				$statik_form = str_replace(['@IF_HEZARFEN_FAT_KURUMSAL', '@ENDIF_HEZARFEN_FAT_KURUMSAL'], '', $statik_form);
			}
		}

		$dinamik_form = str_replace(
			$form_degiskenler,
			$form_degisken_degerler,
			$statik_form
		);

		$desen = '/\{OZELALAN_(.*?)\}/';

		// özel alanların tespiti.
		preg_match_all( $desen, $dinamik_form, $ozel_alan_adlari );

		// özel alan değişkenlerinin yerine konulması.
		foreach ( $ozel_alan_adlari[1] as $ozel_alan_name ) {

			$ozel_alan_value = $order->get_meta( $ozel_alan_name, true );

			if ( ! $ozel_alan_value ) {
				$ozel_alan_value = $order->get_meta( sprintf( '_%s', $ozel_alan_name ), true );
			}

			$dinamik_form = str_replace(
				sprintf( '{OZELALAN_%s}', $ozel_alan_name ),
				$ozel_alan_value,
				$dinamik_form
			);

		}

		return $dinamik_form;
	}

	/**
	 * Render form
	 *
	 * @param  int $order_id WC Order ID.
	 * @return array
	 */
	private function render_forms( $order_id ) {
		// Use the new dynamic contract system
		$active_contracts = \Hezarfen\Inc\MSS\Core\Contract_Manager::get_active_contracts();
		
		$contract_contents = array();
		
		// Process each active contract
		foreach ( $active_contracts as $contract ) {
			if ( ! empty( $contract['content'] ) ) {
				$raw_content = wpautop( $contract['content'] );
				$processed_content = $this->html_forma_degiskenleri_bas( $raw_content, $order_id );
				
				// Map contract types to expected array keys for backward compatibility
				switch ( $contract['type'] ) {
					case 'mesafeli_satis_sozlesmesi':
						$contract_contents['mss'] = $processed_content;
						break;
					case 'on_bilgilendirme_formu':
						$contract_contents['obf'] = $processed_content;
						break;
					case 'cayma_hakki':
					case 'custom':
						// For custom contracts, use a dynamic key
						$contract_contents['custom_' . $contract['id']] = $processed_content;
						break;
				}
			}
		}

		// Ensure backward compatibility by providing empty values for missing contracts
		return array_merge( array(
			'mss' => '',
			'obf' => '',
			'ozel_sozlesme_1_content' => '',
			'ozel_sozlesme_2_content' => ''
		), $contract_contents );
	}
}

new IN_MSS_SiparisSonrasi();
