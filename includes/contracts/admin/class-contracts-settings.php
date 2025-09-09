<?php
/**
 * Contracts Settings integration with Hezarfen
 * 
 * @package Hezarfen\Inc\Contracts
 */

namespace Hezarfen\Inc\Contracts;

defined( 'ABSPATH' ) || exit();

/**
 * Contracts Settings class
 */
class Contracts_Settings {
	
	const SECTION = 'contracts_settings';
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'woocommerce_get_sections_hezarfen', array( $this, 'add_section' ) );
		add_filter( 'woocommerce_get_settings_hezarfen', array( $this, 'add_settings' ), 10, 2 );
		add_action( 'woocommerce_settings_save_hezarfen', array( $this, 'save_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		
		// Initialize the original MSS admin class for post types and other functionality
		$this->init_mss_admin();
	}
	
	/**
	 * Add MSS section to Hezarfen settings
	 */
	public function add_section( $sections ) {
		$sections[ self::SECTION ] = __( 'Contracts & Agreements', 'hezarfen-for-woocommerce' );
		return $sections;
	}
	
	/**
	 * Add MSS settings to the section
	 */
	public function add_settings( $settings, $current_section ) {
		if ( self::SECTION !== $current_section ) {
			return $settings;
		}
		
		$mss_settings = array(
			array(
				'title' => __( 'Mesafeli Satış Sözleşmesi Ayarları', 'hezarfen-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Web sitenize mesafeli satış sözleşmesi ve ön bilgilendirme form desteği ekleyin.', 'hezarfen-for-woocommerce' ),
				'id'    => 'hezarfen_mss_title'
			),
			
			array(
				'title'   => __( 'MSS Etkin', 'hezarfen-for-woocommerce' ),
				'desc'    => __( 'Mesafeli Satış Sözleşmesi özelliğini etkinleştir', 'hezarfen-for-woocommerce' ),
				'id'      => 'hezarfen_contracts_enabled',
				'default' => 'no',
				'type'    => 'checkbox',
			),
			

			
			array(
				'title'    => __( 'Sözleşme Ayarları', 'hezarfen-for-woocommerce' ),
				'desc'     => __( 'Sözleşmenin ödeme sayfasında nasıl gösterileceğini seçin. Sözleşmeler sipariş durumu "Hazırlanıyor" olduğunda otomatik olarak oluşturulur.', 'hezarfen-for-woocommerce' ),
				'id'       => 'hezarfen_mss_settings[odeme_sayfasinda_sozlesme_gosterim_tipi]',
				'type'     => 'select',
				'options'  => array(
					'inline' => __( 'Sayfa İçi', 'hezarfen-for-woocommerce' ),
					'modal'  => __( 'Modal', 'hezarfen-for-woocommerce' ),
				),
				'default'  => 'inline',
			),
			
			array(
				'type' => 'sectionend',
				'id'   => 'hezarfen_mss_title'
			),
			
			array(
				'title' => __( 'Sözleşme Şablonları', 'hezarfen-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'WordPress sayfalarını sözleşme şablonu olarak kullanın. + butonu ile yeni sözleşme ekleyebilir, X butonu ile silebilirsiniz.', 'hezarfen-for-woocommerce' ),
				'id'    => 'hezarfen_mss_templates_title'
			),
			
			array(
				'title' => __( 'Aktif Sözleşmeler', 'hezarfen-for-woocommerce' ),
				'type'  => 'mss_dynamic_contracts',
				'id'    => 'hezarfen_mss_dynamic_contracts',
			),
			
			array(
				'type' => 'sectionend',
				'id'   => 'hezarfen_mss_templates_title'
			),
			
			array(
				'title' => __( 'Kullanılabilir Değişkenler', 'hezarfen-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Sözleşme şablonlarınızda aşağıdaki değişkenleri kullanabilirsiniz. Bu değişkenler sipariş verildiğinde otomatik olarak gerçek verilerle değiştirilir.', 'hezarfen-for-woocommerce' ),
				'id'    => 'hezarfen_mss_variables_title'
			),
			
			array(
				'title' => __( 'Mevcut Değişkenler', 'hezarfen-for-woocommerce' ),
				'type'  => 'mss_available_variables',
				'id'    => 'hezarfen_mss_available_variables',
			),
			
			array(
				'type' => 'sectionend',
				'id'   => 'hezarfen_mss_variables_title'
			),
		);
		
		return $mss_settings;
	}
	
	/**
	 * Get template options for dropdown (WordPress pages only)
	 */
	private function get_template_options() {
		$templates = array( '' => __( 'Sayfa seçin...', 'hezarfen-for-woocommerce' ) );
		
		// Get WordPress pages
		$pages = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		
		// Add pages
		foreach ( $pages as $page ) {
			$templates[ $page->ID ] = $page->post_title;
		}
		
		return $templates;
	}
	
	/**
	 * Save settings
	 */
	public function save_settings() {
		global $current_section;
		
		if ( self::SECTION !== $current_section ) {
			return;
		}
		
		// Handle MSS specific settings save
		if ( isset( $_POST['hezarfen_mss_settings'] ) && is_array( $_POST['hezarfen_mss_settings'] ) ) {
			$mss_settings = $this->sanitize_settings_recursively( $_POST['hezarfen_mss_settings'] );
			update_option( 'hezarfen_mss_settings', $mss_settings );
		}
	}
	
	/**
	 * Recursively sanitize settings array
	 */
	private function sanitize_settings_recursively( $data ) {
		if ( is_array( $data ) ) {
			return array_map( array( $this, 'sanitize_settings_recursively' ), $data );
		} else {
			return sanitize_text_field( $data );
		}
	}
	
	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}
		
		global $current_section;
		if ( self::SECTION !== $current_section ) {
			return;
		}
		
		wp_enqueue_script(
			'hezarfen-contracts-admin',
			HEZARFEN_CONTRACTS_URL . 'js/admin.js',
			array( 'jquery' ),
			WC_HEZARFEN_VERSION,
			true
		);
		
		wp_enqueue_style(
			'hezarfen-contracts-admin',
			HEZARFEN_CONTRACTS_URL . 'css/admin.css',
			array(),
			WC_HEZARFEN_VERSION
		);
	}
	
	/**
	 * Initialize MSS admin functionality
	 */
	private function init_mss_admin() {
		// Add custom field type for dynamic contract management (WordPress pages only)
		add_action( 'woocommerce_admin_field_mss_dynamic_contracts', array( $this, 'output_dynamic_contracts' ) );
		add_action( 'woocommerce_admin_field_mss_available_variables', array( $this, 'output_available_variables' ) );
	}

	/**
	 * Output dynamic contracts management field
	 */
	public function output_dynamic_contracts( $value ) {
		$settings = get_option( 'hezarfen_mss_settings', array() );
		$contracts = isset( $settings['contracts'] ) ? $settings['contracts'] : array();
		
		// Set default contracts if empty
		if ( empty( $contracts ) ) {
			$contracts = array(
				array(
					'id' => 'mss',
					'name' => 'Mesafeli Satış Sözleşmesi',
					'template_id' => '',
					'enabled' => true,
				),
				array(
					'id' => 'obf',
					'name' => 'Ön Bilgilendirme Formu',
					'template_id' => '',
					'enabled' => true,
				),
			);
		}
		
		$template_options = $this->get_template_options();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
			</th>
			<td class="forminp">
				<div id="mss-dynamic-contracts">
					<div id="contracts-list">
						<?php foreach ( $contracts as $index => $contract ): ?>
							<div class="contract-item" data-index="<?php echo esc_attr( $index ); ?>">
								<div class="contract-fields">
									<div class="contract-field">
										<label><?php esc_html_e( 'Sözleşme Adı:', 'hezarfen-for-woocommerce' ); ?></label>
										<input type="text" 
											   name="hezarfen_mss_settings[contracts][<?php echo esc_attr( $index ); ?>][name]" 
											   value="<?php echo esc_attr( $contract['name'] ); ?>" 
											   class="regular-text" />
									</div>
									<div class="contract-field">
										<label><?php esc_html_e( 'WordPress Sayfası:', 'hezarfen-for-woocommerce' ); ?></label>
										<div class="page-selection-wrapper">
											<select name="hezarfen_mss_settings[contracts][<?php echo esc_attr( $index ); ?>][template_id]" class="regular-text page-selector">
												<?php foreach ( $template_options as $option_value => $option_label ): ?>
													<option value="<?php echo esc_attr( $option_value ); ?>" 
														<?php selected( $contract['template_id'], $option_value ); ?>>
														<?php echo esc_html( $option_label ); ?>
													</option>
												<?php endforeach; ?>
											</select>
											<a href="#" class="page-link" style="display: none; margin-left: 10px; color: #0073aa; text-decoration: none;" target="_blank">
												<span class="dashicons dashicons-edit" style="font-size: 16px; width: 16px; height: 16px; line-height: 1;"></span>
												<?php esc_html_e( 'Sayfayı Düzenle', 'hezarfen-for-woocommerce' ); ?>
											</a>
										</div>
									</div>
									<div class="contract-field">
										<label>
											<input type="checkbox" 
												   name="hezarfen_mss_settings[contracts][<?php echo esc_attr( $index ); ?>][enabled]" 
												   value="1" 
												   <?php checked( !empty( $contract['enabled'] ) ); ?> />
											<?php esc_html_e( 'Etkin', 'hezarfen-for-woocommerce' ); ?>
										</label>
									</div>
									<div class="contract-actions">
										<button type="button" class="button remove-contract" title="<?php esc_attr_e( 'Sözleşmeyi Sil', 'hezarfen-for-woocommerce' ); ?>">×</button>
									</div>
								</div>
								<input type="hidden" name="hezarfen_mss_settings[contracts][<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $contract['id'] ); ?>" />
							</div>
						<?php endforeach; ?>
					</div>
					<div class="add-contract-section">
						<button type="button" id="add-new-contract" class="button button-secondary">
							+ <?php esc_html_e( 'Yeni Sözleşme Ekle', 'hezarfen-for-woocommerce' ); ?>
						</button>
					</div>
				</div>
				
				<style>
				#mss-dynamic-contracts {
					max-width: 800px;
				}
				.contract-item {
					background: #f9f9f9;
					border: 1px solid #ddd;
					padding: 15px;
					margin-bottom: 10px;
					border-radius: 4px;
				}
				.contract-fields {
					display: flex;
					align-items: center;
					gap: 15px;
					flex-wrap: wrap;
				}
				.contract-field {
					display: flex;
					flex-direction: column;
					min-width: 150px;
				}
				.contract-field label {
					font-weight: 600;
					margin-bottom: 5px;
				}
				.contract-actions {
					margin-left: auto;
				}
				.remove-contract {
					background: #dc3232;
					color: white;
					border: none;
					width: 30px;
					height: 30px;
					border-radius: 50%;
					font-size: 18px;
					line-height: 1;
					cursor: pointer;
				}
				.remove-contract:hover {
					background: #a00;
				}
				.add-contract-section {
					margin-top: 15px;
				}
				</style>
				
				<script>
				jQuery(document).ready(function($) {
					let contractIndex = <?php echo count( $contracts ); ?>;
					
					// Add new contract
					$('#add-new-contract').on('click', function() {
						const templateOptions = <?php echo json_encode( $template_options ); ?>;
						let optionsHtml = '';
						
						for (const [value, label] of Object.entries(templateOptions)) {
							optionsHtml += `<option value="${value}">${label}</option>`;
						}
						
						const contractHtml = `
							<div class="contract-item" data-index="${contractIndex}">
								<div class="contract-fields">
									<div class="contract-field">
										<label><?php esc_html_e( 'Sözleşme Adı:', 'hezarfen-for-woocommerce' ); ?></label>
										<input type="text" 
											   name="hezarfen_mss_settings[contracts][${contractIndex}][name]" 
											   value="" 
											   class="regular-text" 
											   placeholder="Sözleşme adını girin" />
									</div>
									<div class="contract-field">
										<label><?php esc_html_e( 'WordPress Sayfası:', 'hezarfen-for-woocommerce' ); ?></label>
										<div class="page-selection-wrapper">
											<select name="hezarfen_mss_settings[contracts][${contractIndex}][template_id]" class="regular-text page-selector">
												${optionsHtml}
											</select>
											<a href="#" class="page-link" style="display: none; margin-left: 10px; color: #0073aa; text-decoration: none;" target="_blank">
												<span class="dashicons dashicons-edit" style="font-size: 16px; width: 16px; height: 16px; line-height: 1;"></span>
												<?php esc_html_e( 'Sayfayı Düzenle', 'hezarfen-for-woocommerce' ); ?>
											</a>
										</div>
									</div>
									<div class="contract-field">
										<label>
											<input type="checkbox" 
												   name="hezarfen_mss_settings[contracts][${contractIndex}][enabled]" 
												   value="1" 
												   checked />
											<?php esc_html_e( 'Etkin', 'hezarfen-for-woocommerce' ); ?>
										</label>
									</div>
									<div class="contract-actions">
										<button type="button" class="button remove-contract" title="<?php esc_attr_e( 'Sözleşmeyi Sil', 'hezarfen-for-woocommerce' ); ?>">×</button>
									</div>
								</div>
								<input type="hidden" name="hezarfen_mss_settings[contracts][${contractIndex}][id]" value="contract_${contractIndex}" />
							</div>
						`;
						
						$('#contracts-list').append(contractHtml);
						contractIndex++;
					});
					
					// Remove contract
					$(document).on('click', '.remove-contract', function() {
						if (confirm('<?php esc_js( __( 'Bu sözleşmeyi silmek istediğinizden emin misiniz?', 'hezarfen-for-woocommerce' ) ); ?>')) {
							$(this).closest('.contract-item').remove();
						}
					});
					
					// Handle real-time page link updates
					function updatePageLink($select) {
						const $wrapper = $select.closest('.page-selection-wrapper');
						const $link = $wrapper.find('.page-link');
						const selectedPageId = $select.val();
						
						if (selectedPageId && selectedPageId !== '') {
							// Get the WordPress admin edit URL for the page
							const editUrl = '<?php echo admin_url("post.php?action=edit&post="); ?>' + selectedPageId;
							$link.attr('href', editUrl).show();
						} else {
							$link.hide();
						}
					}
					
					// Update links on page selector change
					$(document).on('change', '.page-selector', function() {
						updatePageLink($(this));
					});
					
					// Initialize links on page load
					$('.page-selector').each(function() {
						updatePageLink($(this));
					});
				});
		</script>
		</td>
	</tr>
	<?php
}

/**
 * Output available variables field
 */
public function output_available_variables( $value ) {
	$variables = array(
		// Order Variables
		'Sipariş Bilgileri' => array(
			'{{siparis_no}}' => 'Sipariş numarası',
			'{{siparis_tarihi}}' => 'Sipariş tarihi',
			'{{siparis_saati}}' => 'Sipariş saati',
			'{{toplam_tutar}}' => 'Sipariş toplamı (KDV dahil)',
			'{{ara_toplam}}' => 'Sipariş ara toplamı (KDV hariç)',
			'{{toplam_vergi_tutar}}' => 'KDV tutarı',
			'{{kargo_ucreti}}' => 'Kargo ücreti',
			'{{urunler}}' => 'Sipariş edilen ürünler listesi',
			'{{odeme_yontemi}}' => 'Ödeme yöntemi',
			'{{indirim_toplami}}' => 'İndirim tutarı',
		),
		
		
		// Billing Address Variables
		'Fatura Adresi' => array(
			'{{fatura_adi}}' => 'Fatura adı',
			'{{fatura_soyadi}}' => 'Fatura soyadı',
			'{{fatura_sirket}}' => 'Fatura şirket adı',
			'{{fatura_adres_1}}' => 'Fatura adresi satır 1',
			'{{fatura_adres_2}}' => 'Fatura adresi satır 2',
			'{{fatura_sehir}}' => 'Fatura şehir',
			'{{fatura_posta_kodu}}' => 'Fatura posta kodu',
			'{{fatura_ulke}}' => 'Fatura ülke',
		),
		
		// Shipping Address Variables
		'Teslimat Adresi' => array(
			'{{teslimat_adi}}' => 'Teslimat adı',
			'{{teslimat_soyadi}}' => 'Teslimat soyadı',
			'{{teslimat_sirket}}' => 'Teslimat şirket adı',
			'{{teslimat_adres_1}}' => 'Teslimat adresi satır 1',
			'{{teslimat_adres_2}}' => 'Teslimat adresi satır 2',
			'{{teslimat_sehir}}' => 'Teslimat şehir',
			'{{teslimat_posta_kodu}}' => 'Teslimat posta kodu',
			'{{teslimat_ulke}}' => 'Teslimat ülke',
		),
		
		// Site Variables
		'Site Bilgileri' => array(
			'{{site_adi}}' => 'Site adı',
			'{{site_url}}' => 'Site URL',
		),
		
		// Date Variables
		'Tarih Bilgileri' => array(
			'{{bugunun_tarihi}}' => 'Bugünün tarihi',
			'{{su_an}}' => 'Şu anki tarih ve saat',
		),
		
		// Hezarfen Invoice Fields
		'Hezarfen Fatura Alanları' => array(
			'{{hezarfen_kurumsal_vergi_daire}}' => 'Kurumsal vergi dairesi',
			'{{hezarfen_kurumsal_vergi_no}}' => 'Kurumsal vergi numarası',
			'{{hezarfen_bireysel_tc}}' => 'Bireysel TC kimlik numarası',
		),
	);
	
	?>
	<tr valign="top">
		<th scope="row" class="titledesc">
			<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
		</th>
		<td class="forminp">
			<div id="mss-available-variables">
				<?php foreach ( $variables as $category => $vars ): ?>
					<div class="variable-category">
						<h4 style="margin-top: 20px; margin-bottom: 10px; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 5px;">
							<?php echo esc_html( $category ); ?>
						</h4>
						<div class="variables-grid" style="display: grid; grid-template-columns: 1fr 2fr; gap: 10px; margin-bottom: 15px;">
							<?php foreach ( $vars as $variable => $description ): ?>
								<div style="background: #f9f9f9; padding: 8px 12px; border-radius: 3px; font-family: monospace; font-size: 13px; color: #d63384; cursor: pointer;" 
									 onclick="copyToClipboard('<?php echo esc_js( $variable ); ?>')" 
									 title="Kopyalamak için tıklayın">
									<?php echo esc_html( $variable ); ?>
								</div>
								<div style="padding: 8px 12px; color: #666; font-size: 13px;">
									<?php echo esc_html( $description ); ?>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
				
				<div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin-top: 20px; border-left: 4px solid #0073aa;">
					<strong>💡 Kullanım:</strong><br>
					Bu değişkenleri WordPress sayfa editöründe sözleşme metninizin içine yazın. 
					Sipariş verildiğinde bu değişkenler otomatik olarak gerçek verilerle değiştirilecektir.
				</div>
			</div>
			
			<script>
			function copyToClipboard(text) {
				// Try modern clipboard API first
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(function() {
						showCopyNotification(text);
					}).catch(function() {
						// Fallback to older method
						fallbackCopyToClipboard(text);
					});
				} else {
					// Fallback for older browsers or HTTP
					fallbackCopyToClipboard(text);
				}
			}
			
			function fallbackCopyToClipboard(text) {
				// Create temporary textarea
				var textArea = document.createElement('textarea');
				textArea.value = text;
				textArea.style.position = 'fixed';
				textArea.style.left = '-999999px';
				textArea.style.top = '-999999px';
				document.body.appendChild(textArea);
				textArea.focus();
				textArea.select();
				
				try {
					var successful = document.execCommand('copy');
					if (successful) {
						showCopyNotification(text);
					} else {
						showCopyError();
					}
				} catch (err) {
					showCopyError();
				}
				
				document.body.removeChild(textArea);
			}
			
			function showCopyNotification(text) {
				var notification = document.createElement('div');
				notification.innerHTML = '✓ Kopyalandı: ' + text;
				notification.style.cssText = 'position: fixed; top: 60px; right: 20px; background: #00a32a; color: white; padding: 10px 15px; border-radius: 3px; z-index: 9999; font-size: 14px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);';
				document.body.appendChild(notification);
				
				setTimeout(function() {
					if (document.body.contains(notification)) {
						document.body.removeChild(notification);
					}
				}, 2000);
			}
			
			function showCopyError() {
				var notification = document.createElement('div');
				notification.innerHTML = '❌ Kopyalama başarısız. Lütfen manuel olarak seçip kopyalayın.';
				notification.style.cssText = 'position: fixed; top: 60px; right: 20px; background: #dc3545; color: white; padding: 10px 15px; border-radius: 3px; z-index: 9999; font-size: 14px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);';
				document.body.appendChild(notification);
				
				setTimeout(function() {
					if (document.body.contains(notification)) {
						document.body.removeChild(notification);
					}
				}, 3000);
			}
			</script>
		</td>
	</tr>
	<?php
}
}