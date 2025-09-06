<?php
/**
 * MSS Settings integration with Hezarfen
 * 
 * @package Hezarfen\Inc\MSS
 */

namespace Hezarfen\Inc\MSS;

defined( 'ABSPATH' ) || exit();

/**
 * MSS Settings class
 */
class MSS_Settings {
	
	const SECTION = 'mss_settings';
	
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
		$sections[ self::SECTION ] = __( 'Mesafeli Satış Sözleşmesi', 'hezarfen-for-woocommerce' );
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
				'id'      => 'hezarfen_mss_enabled',
				'default' => 'no',
				'type'    => 'checkbox',
			),
			

			
			array(
				'title'    => __( 'Sözleşme Gösterim Tipi', 'hezarfen-for-woocommerce' ),
				'desc'     => __( 'Sözleşmenin ödeme sayfasında nasıl gösterileceğini seçin.', 'hezarfen-for-woocommerce' ),
				'id'       => 'hezarfen_mss_settings[odeme_sayfasinda_sozlesme_gosterim_tipi]',
				'type'     => 'select',
				'options'  => array(
					'inline' => __( 'Sayfa İçi', 'hezarfen-for-woocommerce' ),
					'modal'  => __( 'Modal', 'hezarfen-for-woocommerce' ),
				),
				'default'  => 'inline',
			),
			
			array(
				'title'    => __( 'Sözleşme Oluşturma Tipi', 'hezarfen-for-woocommerce' ),
				'desc'     => __( 'Sözleşmenin ne zaman oluşturulacağını seçin.', 'hezarfen-for-woocommerce' ),
				'id'       => 'hezarfen_mss_settings[sozlesme_olusturma_tipi]',
				'type'     => 'select',
				'options'  => array(
					'yeni_siparis' => __( 'Sipariş Alındığında', 'hezarfen-for-woocommerce' ),
					'isleniyor'    => __( 'Sipariş Hazırlanıyor Durumuna Geldiğinde', 'hezarfen-for-woocommerce' ),
				),
				'default'  => 'yeni_siparis',
			),
			
			array(
				'type' => 'sectionend',
				'id'   => 'hezarfen_mss_title'
			),
			
			array(
				'title' => __( 'Sözleşme Şablonları', 'hezarfen-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Sözleşme şablonlarını yönetin. + butonu ile yeni sözleşme ekleyebilir, X butonu ile silebilirsiniz.', 'hezarfen-for-woocommerce' ),
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
		);
		
		return $mss_settings;
	}
	
	/**
	 * Get template options for dropdown (MSS forms + WordPress pages)
	 */
	private function get_template_options() {
		$templates = array( '' => __( 'Şablon seçin...', 'hezarfen-for-woocommerce' ) );
		
		// Get MSS form templates
		$mss_templates = get_posts( array(
			'post_type'      => 'intense_mss_form',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		
		// Get WordPress pages
		$pages = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		
		// Add MSS templates with prefix
		foreach ( $mss_templates as $template ) {
			$templates[ $template->ID ] = '[MSS] ' . $template->post_title;
		}
		
		// Add pages with prefix
		foreach ( $pages as $page ) {
			$templates[ $page->ID ] = '[Sayfa] ' . $page->post_title;
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
			$mss_settings = array_map( 'sanitize_text_field', $_POST['hezarfen_mss_settings'] );
			update_option( 'hezarfen_mss_settings', $mss_settings );
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
			'hezarfen-mss-admin',
			HEZARFEN_MSS_URL . 'js/admin.js',
			array( 'jquery' ),
			HEZARFEN_MSS_VERSION,
			true
		);
		
		wp_enqueue_style(
			'hezarfen-mss-admin',
			HEZARFEN_MSS_URL . 'css/admin.css',
			array(),
			HEZARFEN_MSS_VERSION
		);
	}
	
	/**
	 * Initialize MSS admin functionality
	 */
	private function init_mss_admin() {
		// Post types are handled by the original MSS admin class
		// Add custom field type for dynamic contract management
		add_action( 'woocommerce_admin_field_mss_dynamic_contracts', array( $this, 'output_dynamic_contracts' ) );
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
										<label><?php esc_html_e( 'Şablon:', 'hezarfen-for-woocommerce' ); ?></label>
										<select name="hezarfen_mss_settings[contracts][<?php echo esc_attr( $index ); ?>][template_id]" class="regular-text">
											<?php foreach ( $template_options as $option_value => $option_label ): ?>
												<option value="<?php echo esc_attr( $option_value ); ?>" 
													<?php selected( $contract['template_id'], $option_value ); ?>>
													<?php echo esc_html( $option_label ); ?>
												</option>
											<?php endforeach; ?>
										</select>
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
										<label><?php esc_html_e( 'Şablon:', 'hezarfen-for-woocommerce' ); ?></label>
										<select name="hezarfen_mss_settings[contracts][${contractIndex}][template_id]" class="regular-text">
											${optionsHtml}
										</select>
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
				});
				</script>
			</td>
		</tr>
		<?php
	}
}

// Initialize MSS Settings
new MSS_Settings();