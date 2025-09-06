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
		$order = wc_get_order( $order_id );
		global $wpdb;

		// Check if contracts already exist for this order
		$existing_contracts = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}hezarfen_contracts WHERE order_id=%s", $order_id ) );

		/** If contracts already saved, don't process again */
		if ( ! empty( $existing_contracts ) ) {
			return;
		}

		$ip_address = $this->get_client_ip();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';

		// Get rendered contract contents from the contract renderer
		$settings = get_option( 'hezarfen_mss_settings', array() );
		$active_contracts = isset( $settings['contracts'] ) ? $settings['contracts'] : array();
		
		$contracts_to_save = array();
		
		// Process each active contract
		foreach ( $active_contracts as $contract_config ) {
			if ( empty( $contract_config['enabled'] ) || empty( $contract_config['template_id'] ) ) {
				continue;
			}
			
			// Get the contract content from the template
			$contract_content = \Hezarfen\Inc\MSS\Core\Contract_Renderer::get_contract_content_from_template( 
				$contract_config['template_id'], 
				$order_id 
			);
			
			// Only save if there's actual content
			if ( ! empty( $contract_content ) ) {
				$contracts_to_save[] = array(
					'name' => $contract_config['name'],
					'content' => $contract_content,
				);
			}
		}
		
		// Save each contract as a separate record
		foreach ( $contracts_to_save as $contract ) {
			$wpdb->insert(
				$wpdb->prefix . 'hezarfen_contracts',
				array(
					'order_id'        => $order_id,
					'contract_name'   => $contract['name'],
					'contract_content' => $contract['content'],
					'ip_address'      => $ip_address,
					'user_agent'      => $user_agent,
				),
				array( '%d', '%s', '%s', '%s', '%s' )
			);
		}

		/**
		 *
		 * E-Posta Gönderimi
		 */
		$uygulama_ayarlar = get_option( 'hezarfen_mss_settings' );

		$yonetici_sozlesme_saklama_eposta_adresi = $uygulama_ayarlar['yonetici_sozlesme_saklama_eposta_adresi'];

		if ( $yonetici_sozlesme_saklama_eposta_adresi && ! empty( $contracts_to_save ) ) {
			$subject = sprintf( 'Order #%s - Contracts and Agreements', $order_id );

			$message = '<p><strong>' . esc_html__( 'Date:', 'hezarfen-for-woocommerce' ) . '</strong> ' . esc_html( date_i18n( 'd/m/Y' ) ) . '</p>';
			$message .= '<p><strong>' . esc_html__( 'Time:', 'hezarfen-for-woocommerce' ) . '</strong> ' . esc_html( date_i18n( 'H:i:s' ) ) . '</p>';
			$message .= '<p><strong>' . esc_html__( 'IP Address:', 'hezarfen-for-woocommerce' ) . '</strong> ' . esc_html( $ip_address ) . '</p>';
			$message .= '<p><strong>' . esc_html__( 'Order Number:', 'hezarfen-for-woocommerce' ) . '</strong> ' . $order->get_order_number() . '</p>';
			$message .= '<hr>';

			// Add each contract to the email
			foreach ( $contracts_to_save as $contract ) {
				$message .= '<h2>' . esc_html( $contract['name'] ) . '</h2>';
				$message .= '<div>' . $contract['content'] . '</div>';
				$message .= '<hr>';
			}

			add_filter( 'wp_mail_content_type', array( $this, 'intense_wp_mail_formati_html_yap' ) );
			wp_mail( $yonetici_sozlesme_saklama_eposta_adresi, $subject, $message );
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

		// Check if email was already sent for this order
		$eposta_gonderildi_mi = $order->get_meta( '_in_mss_eposta_gonderildi_mi', true );

		// Don't send contracts again if already sent
		if ( 1 === $eposta_gonderildi_mi ) {
			return;
		}

		update_post_meta( $order_id, '_in_mss_eposta_gonderildi_mi', 1 );

		// Get contracts from database (they should already be saved)
		global $wpdb;
		$contracts = $wpdb->get_results( $wpdb->prepare( 
			"SELECT * FROM {$wpdb->prefix}hezarfen_contracts WHERE order_id=%d ORDER BY created_at ASC", 
			$order_id 
		) );

		if ( empty( $contracts ) ) {
			return;
		}

		// Display each contract dynamically
		foreach ( $contracts as $contract ) {
			?>
			<h3><?php echo esc_html( $contract->contract_name ); ?></h3>
			<div style="height:300px;overflow:scroll;margin-bottom:15px;border:1px solid #dddddd;padding:15px">
				<?php echo wp_kses_post( $contract->contract_content ); ?>
			</div>
			<?php
		}
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

}

new IN_MSS_SiparisSonrasi();
