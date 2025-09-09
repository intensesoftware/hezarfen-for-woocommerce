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
		add_action( 'wp_footer', array( $this, 'add_contract_modal_script' ) );

		add_action( 'wp_ajax_adres_bilgilerini_guncelle_callback', array( $this, 'adres_bilgilerini_guncelle_callback' ) );

		add_action( 'wp_ajax_nopriv_adres_bilgilerini_guncelle_callback', array( $this, 'adres_bilgilerini_guncelle_callback' ) );
	}

	/**
	 * Sözleşme taslağına değişkenleri yerleştirir
	 *
	 * @param  string $statik_form sözleşme taslağı.
	 * @return string
	 */
	public function html_forma_degiskenleri_bas( $statik_form ) {
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
	public function forma_ozel_alan_tutuculari_yerlestir( $form_icerik ) {
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
	public function form_hezarfen_destegi( $form_icerik ) {
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
		// Use the new dynamic contract renderer
		$settings = get_option( 'hezarfen_mss_settings', array() );
		$display_type = isset($settings['odeme_sayfasinda_sozlesme_gosterim_tipi']) ? $settings['odeme_sayfasinda_sozlesme_gosterim_tipi'] : 'inline';
		
		echo '<script>console.log("Display type: ' . $display_type . '");</script>';
		
		\Hezarfen\Inc\MSS\Core\Contract_Renderer::render_contracts( $display_type );
	}

	/**
	 * Ödeme ekranında sözleşme onay kutucukları ve sözleşmelerin modal veya inline olarak gösterilmesi
	 *
	 * @return void
	 */
	public function intense_mss_onay_checkbox() {
		// Use the new dynamic contract renderer for checkboxes
		\Hezarfen\Inc\MSS\Core\Contract_Renderer::render_contract_checkboxes();
	}

	/**
	 * Sözleşme onay kutuları için validation
	 *
	 * @return void
	 */
	public function intense_mss_checkout_process() {
		// Use the new dynamic contract validator
		\Hezarfen\Inc\MSS\Core\Contract_Validator::validate_checkout_contracts();
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

	/**
	 * Add contract modal JavaScript to footer
	 *
	 * @return void
	 */
	public function add_contract_modal_script() {
		// Only add on checkout page
		if ( ! is_checkout() ) {
			return;
		}
		?>
		<script>
		console.log('Checkout contract modal script loaded');
		
		function initCheckoutContractModals() {
			// Add modal styles to ensure visibility
			var style = document.createElement('style');
			style.textContent = `
				.hezarfen-modal {
					display: none !important;
					position: fixed !important;
					z-index: 999999 !important;
					left: 0 !important;
					top: 0 !important;
					width: 100% !important;
					height: 100% !important;
					background-color: rgba(0,0,0,0.5) !important;
				}
				.hezarfen-modal-container {
					position: relative !important;
					background-color: #fff !important;
					margin: 5% auto !important;
					padding: 20px !important;
					border: 1px solid #888 !important;
					width: 80% !important;
					max-width: 800px !important;
					max-height: 80vh !important;
					overflow-y: auto !important;
					border-radius: 4px !important;
					box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
				}
				.hezarfen-modal-header {
					display: flex !important;
					justify-content: space-between !important;
					align-items: center !important;
					padding-bottom: 10px !important;
					border-bottom: 1px solid #eee !important;
					margin-bottom: 15px !important;
				}
				.hezarfen-modal-close {
					background: none !important;
					border: none !important;
					font-size: 24px !important;
					cursor: pointer !important;
					color: #666 !important;
				}
			`;
			document.head.appendChild(style);
			
			// Use event delegation for contract links (works even if elements are added dynamically)
			document.addEventListener('click', function(e) {
				if (e.target && e.target.classList.contains('contract-modal-link')) {
					e.preventDefault();
					e.stopPropagation();
					
					var clickedContractId = e.target.getAttribute('data-contract-id');
					
					// Check if modal already exists
					var existingModal = document.querySelector('.hezarfen-unified-modal');
					if (existingModal) {
						// Modal exists, just switch to the clicked tab
						switchToTab(clickedContractId);
						return false;
					}
					
					// Get all contracts from the page
					var allContractLinks = document.querySelectorAll('.contract-modal-link');
					var contracts = [];
					allContractLinks.forEach(function(link) {
						contracts.push({
							id: link.getAttribute('data-contract-id'),
							name: link.textContent.trim()
						});
					});
					
					// Create unified modal with tabs
					createUnifiedModal(contracts, clickedContractId);
					
					return false;
				}
			});
			
			function createUnifiedModal(contracts, activeContractId) {
				var modalOverlay = document.createElement('div');
				modalOverlay.className = 'hezarfen-unified-modal';
				modalOverlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 999999; display: flex; align-items: center; justify-content: center;';
				
				var modalContent = document.createElement('div');
				modalContent.style.cssText = 'background: white; border-radius: 8px; max-width: 800px; width: 95%; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column;';
				
				// Header with close button
				var modalHeader = document.createElement('div');
				modalHeader.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #eee;';
				
				var modalTitle = document.createElement('h3');
				modalTitle.style.cssText = 'margin: 0; color: #333;';
				modalTitle.textContent = 'Sözleşmeler';
				
				var closeButton = document.createElement('button');
				closeButton.style.cssText = 'background: none; border: none; font-size: 24px; cursor: pointer; color: #666;';
				closeButton.innerHTML = '&times;';
				closeButton.onclick = function() {
					document.body.removeChild(modalOverlay);
					document.body.style.overflow = '';
				};
				
				// Tab navigation
				var tabNav = document.createElement('div');
				tabNav.className = 'tab-navigation';
				tabNav.style.cssText = 'display: flex; border-bottom: 1px solid #eee; background: #f9f9f9;';
				
				// Tab content container
				var tabContent = document.createElement('div');
				tabContent.className = 'tab-content';
				tabContent.style.cssText = 'flex: 1; overflow-y: auto; padding: 20px;';
				
				// Create tabs and content
				contracts.forEach(function(contract, index) {
					// Create tab button
					var tabButton = document.createElement('button');
					tabButton.className = 'tab-button';
					tabButton.dataset.contractId = contract.id;
					tabButton.style.cssText = 'padding: 15px 20px; border: none; background: none; cursor: pointer; border-bottom: 3px solid transparent; white-space: nowrap; color: #666;';
					tabButton.textContent = contract.name;
					
					if (contract.id === activeContractId) {
						tabButton.style.cssText += 'border-bottom-color: #0073aa; color: #0073aa; font-weight: bold;';
					}
					
					tabButton.onclick = function() {
						switchToTab(contract.id);
					};
					
					tabNav.appendChild(tabButton);
					
					// Create tab content
					var tabPane = document.createElement('div');
					tabPane.className = 'tab-pane';
					tabPane.dataset.contractId = contract.id;
					tabPane.style.cssText = contract.id === activeContractId ? 'display: block;' : 'display: none;';
					tabPane.innerHTML = `
						<h4 style="margin-top: 0; color: #333;">${contract.name}</h4>
						<div style="color: #666; line-height: 1.6;">
							<p>Bu sözleşme içeriği burada görüntülenecek.</p>
							<p>Contract ID: ${contract.id}</p>
							<p>Sözleşme detayları yükleniyor...</p>
						</div>
					`;
					
					tabContent.appendChild(tabPane);
				});
				
				// Click overlay to close
				modalOverlay.onclick = function(e) {
					if (e.target === modalOverlay) {
						document.body.removeChild(modalOverlay);
						document.body.style.overflow = '';
					}
				};
				
				modalHeader.appendChild(modalTitle);
				modalHeader.appendChild(closeButton);
				modalContent.appendChild(modalHeader);
				modalContent.appendChild(tabNav);
				modalContent.appendChild(tabContent);
				modalOverlay.appendChild(modalContent);
				
				document.body.appendChild(modalOverlay);
				document.body.style.overflow = 'hidden';
			}
			
			function switchToTab(contractId) {
				// Update tab buttons
				var tabButtons = document.querySelectorAll('.tab-button');
				tabButtons.forEach(function(button) {
					if (button.dataset.contractId === contractId) {
						button.style.cssText = 'padding: 15px 20px; border: none; background: none; cursor: pointer; border-bottom: 3px solid #0073aa; white-space: nowrap; color: #0073aa; font-weight: bold;';
					} else {
						button.style.cssText = 'padding: 15px 20px; border: none; background: none; cursor: pointer; border-bottom: 3px solid transparent; white-space: nowrap; color: #666;';
					}
				});
				
				// Update tab content
				var tabPanes = document.querySelectorAll('.tab-pane');
				tabPanes.forEach(function(pane) {
					if (pane.dataset.contractId === contractId) {
						pane.style.display = 'block';
					} else {
						pane.style.display = 'none';
					}
				});
			}

			// Handle modal close buttons
			document.addEventListener('click', function(e) {
				if (e.target.classList.contains('hezarfen-modal-close') || 
					e.target.classList.contains('hezarfen-modal-overlay')) {
					var modal = e.target.closest('.hezarfen-modal');
					if (modal) {
						modal.style.display = 'none';
						document.body.style.overflow = '';
					}
				}
			});

			// Handle ESC key to close modals
			document.addEventListener('keydown', function(e) {
				if (e.key === 'Escape') {
					document.querySelectorAll('.hezarfen-modal').forEach(function(modal) {
						modal.style.display = 'none';
					});
					document.body.style.overflow = '';
				}
			});
		}
		
		// Try multiple ways to initialize
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', initCheckoutContractModals);
		} else {
			initCheckoutContractModals();
		}
		
		// Also try with a small delay
		setTimeout(initCheckoutContractModals, 500);
		</script>
		<?php
	}
}

new IN_MSS_OdemeSayfasi_Sozlesmeler();
