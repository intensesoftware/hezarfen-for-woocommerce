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
				'title' => __( 'Distance Sales Contract Settings', 'hezarfen-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Add distance sales contract and pre-information form support to your website.', 'hezarfen-for-woocommerce' ),
				'id'    => 'hezarfen_mss_title'
			),
			
			array(
				'title'   => __( 'DSC Enabled', 'hezarfen-for-woocommerce' ),
				'desc'    => __( 'Enable Distance Sales Contract feature', 'hezarfen-for-woocommerce' ),
				'id'      => 'hezarfen_contracts_enabled',
				'default' => 'no',
				'type'    => 'checkbox',
			),
			

			
			array(
				'title'    => __( 'Contract Settings', 'hezarfen-for-woocommerce' ),
				'desc'     => __( 'Choose how the contract will be displayed on the payment page. Contracts are automatically created when order status is "Processing".', 'hezarfen-for-woocommerce' ),
				'id'       => 'hezarfen_mss_settings[odeme_sayfasinda_sozlesme_gosterim_tipi]',
				'type'     => 'select',
				'options'  => array(
					'inline' => __( 'Inline', 'hezarfen-for-woocommerce' ),
					'modal'  => __( 'Modal', 'hezarfen-for-woocommerce' ),
				),
				'default'  => 'inline',
			),
			
			array(
				'type' => 'sectionend',
				'id'   => 'hezarfen_mss_title'
			),
			
			array(
				'title' => __( 'Contract Templates', 'hezarfen-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Use WordPress pages as contract templates. You can add new contracts with the + button and delete them with the X button.', 'hezarfen-for-woocommerce' ),
				'id'    => 'hezarfen_mss_templates_title'
			),
			
			array(
				'title' => __( 'Active Contracts', 'hezarfen-for-woocommerce' ),
				'type'  => 'mss_dynamic_contracts',
				'id'    => 'hezarfen_mss_dynamic_contracts',
			),
			
			array(
				'type' => 'sectionend',
				'id'   => 'hezarfen_mss_templates_title'
			),
			
			array(
				'title' => __( 'Available Variables', 'hezarfen-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'You can use the following variables in your contract templates. These variables are automatically replaced with real data when an order is placed.', 'hezarfen-for-woocommerce' ),
				'id'    => 'hezarfen_mss_variables_title'
			),
			
			array(
				'title' => __( 'Current Variables', 'hezarfen-for-woocommerce' ),
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
		$templates = array( '' => __( 'Select page...', 'hezarfen-for-woocommerce' ) );
		
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
					'name' => __( 'Distance Sales Contract', 'hezarfen-for-woocommerce' ),
					'template_id' => '',
					'enabled' => true,
					'show_in_checkbox' => true,
				),
				array(
					'id' => 'obf',
					'name' => __( 'Pre-Information Form', 'hezarfen-for-woocommerce' ),
					'template_id' => '',
					'enabled' => true,
					'show_in_checkbox' => true,
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
										<label><?php esc_html_e( 'Contract Name:', 'hezarfen-for-woocommerce' ); ?></label>
										<input type="text" 
											   name="hezarfen_mss_settings[contracts][<?php echo esc_attr( $index ); ?>][name]" 
											   value="<?php echo esc_attr( $contract['name'] ); ?>" 
											   class="regular-text" />
									</div>
									<div class="contract-field">
										<label><?php esc_html_e( 'WordPress Page:', 'hezarfen-for-woocommerce' ); ?></label>
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
												<?php esc_html_e( 'Edit Page', 'hezarfen-for-woocommerce' ); ?>
											</a>
										</div>
									</div>
									<div class="contract-field">
										<label>
											<input type="checkbox" 
												   name="hezarfen_mss_settings[contracts][<?php echo esc_attr( $index ); ?>][enabled]" 
												   value="1" 
												   <?php checked( !empty( $contract['enabled'] ) ); ?> />
											<?php esc_html_e( 'Enabled', 'hezarfen-for-woocommerce' ); ?>
										</label>
									</div>
									<div class="contract-field">
										<label>
											<input type="checkbox" 
												   name="hezarfen_mss_settings[contracts][<?php echo esc_attr( $index ); ?>][show_in_checkbox]" 
												   value="1" 
												   <?php checked( !empty( $contract['show_in_checkbox'] ) ); ?> />
											<?php esc_html_e( 'Require User Agreement', 'hezarfen-for-woocommerce' ); ?>
										</label>
									</div>
									<div class="contract-actions">
										<button type="button" class="button remove-contract" title="<?php esc_attr_e( 'Delete Contract', 'hezarfen-for-woocommerce' ); ?>">×</button>
									</div>
								</div>
								<input type="hidden" name="hezarfen_mss_settings[contracts][<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $contract['id'] ); ?>" />
							</div>
						<?php endforeach; ?>
					</div>
					<div class="add-contract-section">
						<button type="button" id="add-new-contract" class="button button-secondary">
							+ <?php esc_html_e( 'Add New Contract', 'hezarfen-for-woocommerce' ); ?>
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
					
					// Localized strings
					const localizedStrings = {
						contractName: '<?php echo esc_js( __( 'Contract Name:', 'hezarfen-for-woocommerce' ) ); ?>',
						wordPressPage: '<?php echo esc_js( __( 'WordPress Page:', 'hezarfen-for-woocommerce' ) ); ?>',
						editPage: '<?php echo esc_js( __( 'Edit Page', 'hezarfen-for-woocommerce' ) ); ?>',
						enabled: '<?php echo esc_js( __( 'Enabled', 'hezarfen-for-woocommerce' ) ); ?>',
						requireUserAgreement: '<?php echo esc_js( __( 'Require User Agreement', 'hezarfen-for-woocommerce' ) ); ?>',
						deleteContract: '<?php echo esc_js( __( 'Delete Contract', 'hezarfen-for-woocommerce' ) ); ?>',
						enterContractName: '<?php echo esc_js( __( 'Enter contract name', 'hezarfen-for-woocommerce' ) ); ?>',
						confirmDelete: '<?php echo esc_js( __( 'Are you sure you want to delete this contract?', 'hezarfen-for-woocommerce' ) ); ?>'
					};
					
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
										<label>${localizedStrings.contractName}</label>
										<input type="text" 
											   name="hezarfen_mss_settings[contracts][${contractIndex}][name]" 
											   value="" 
											   class="regular-text" 
											   placeholder="${localizedStrings.enterContractName}" />
									</div>
									<div class="contract-field">
										<label>${localizedStrings.wordPressPage}</label>
										<div class="page-selection-wrapper">
											<select name="hezarfen_mss_settings[contracts][${contractIndex}][template_id]" class="regular-text page-selector">
												${optionsHtml}
											</select>
											<a href="#" class="page-link" style="display: none; margin-left: 10px; color: #0073aa; text-decoration: none;" target="_blank">
												<span class="dashicons dashicons-edit" style="font-size: 16px; width: 16px; height: 16px; line-height: 1;"></span>
												${localizedStrings.editPage}
											</a>
										</div>
									</div>
									<div class="contract-field">
										<label>
											<input type="checkbox" 
												   name="hezarfen_mss_settings[contracts][${contractIndex}][enabled]" 
												   value="1" 
												   checked />
											${localizedStrings.enabled}
										</label>
									</div>
									<div class="contract-field">
										<label>
											<input type="checkbox" 
												   name="hezarfen_mss_settings[contracts][${contractIndex}][show_in_checkbox]" 
												   value="1" 
												   checked />
											${localizedStrings.requireUserAgreement}
										</label>
									</div>
									<div class="contract-actions">
										<button type="button" class="button remove-contract" title="${localizedStrings.deleteContract}">×</button>
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
						if (confirm(localizedStrings.confirmDelete)) {
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
		__( 'Order Information', 'hezarfen-for-woocommerce' ) => array(
			'{{siparis_no}}' => __( 'Order number', 'hezarfen-for-woocommerce' ),
			'{{siparis_tarihi}}' => __( 'Order date', 'hezarfen-for-woocommerce' ),
			'{{siparis_saati}}' => __( 'Order time', 'hezarfen-for-woocommerce' ),
			'{{toplam_tutar}}' => __( 'Order total (including tax)', 'hezarfen-for-woocommerce' ),
			'{{ara_toplam}}' => __( 'Order subtotal (excluding tax)', 'hezarfen-for-woocommerce' ),
			'{{toplam_vergi_tutar}}' => __( 'Tax amount', 'hezarfen-for-woocommerce' ),
			'{{kargo_ucreti}}' => __( 'Shipping cost', 'hezarfen-for-woocommerce' ),
			'{{urunler}}' => __( 'List of ordered products', 'hezarfen-for-woocommerce' ),
			'{{odeme_yontemi}}' => __( 'Payment method', 'hezarfen-for-woocommerce' ),
			'{{indirim_toplami}}' => __( 'Discount amount', 'hezarfen-for-woocommerce' ),
		),
		
		
		// Billing Address Variables
		__( 'Billing Address', 'hezarfen-for-woocommerce' ) => array(
			'{{fatura_adi}}' => __( 'Billing first name', 'hezarfen-for-woocommerce' ),
			'{{fatura_soyadi}}' => __( 'Billing last name', 'hezarfen-for-woocommerce' ),
			'{{fatura_sirket}}' => __( 'Billing company name', 'hezarfen-for-woocommerce' ),
			'{{fatura_adres_1}}' => __( 'Billing address line 1', 'hezarfen-for-woocommerce' ),
			'{{fatura_adres_2}}' => __( 'Billing address line 2', 'hezarfen-for-woocommerce' ),
			'{{fatura_sehir}}' => __( 'Billing city', 'hezarfen-for-woocommerce' ),
			'{{fatura_posta_kodu}}' => __( 'Billing postal code', 'hezarfen-for-woocommerce' ),
			'{{fatura_ulke}}' => __( 'Billing country', 'hezarfen-for-woocommerce' ),
		),
		
		// Shipping Address Variables
		__( 'Shipping Address', 'hezarfen-for-woocommerce' ) => array(
			'{{teslimat_adi}}' => __( 'Shipping first name', 'hezarfen-for-woocommerce' ),
			'{{teslimat_soyadi}}' => __( 'Shipping last name', 'hezarfen-for-woocommerce' ),
			'{{teslimat_sirket}}' => __( 'Shipping company name', 'hezarfen-for-woocommerce' ),
			'{{teslimat_adres_1}}' => __( 'Shipping address line 1', 'hezarfen-for-woocommerce' ),
			'{{teslimat_adres_2}}' => __( 'Shipping address line 2', 'hezarfen-for-woocommerce' ),
			'{{teslimat_sehir}}' => __( 'Shipping city', 'hezarfen-for-woocommerce' ),
			'{{teslimat_posta_kodu}}' => __( 'Shipping postal code', 'hezarfen-for-woocommerce' ),
			'{{teslimat_ulke}}' => __( 'Shipping country', 'hezarfen-for-woocommerce' ),
		),
		
		// Site Variables
		__( 'Site Information', 'hezarfen-for-woocommerce' ) => array(
			'{{site_adi}}' => __( 'Site name', 'hezarfen-for-woocommerce' ),
			'{{site_url}}' => __( 'Site URL', 'hezarfen-for-woocommerce' ),
		),
		
		// Date Variables
		__( 'Date Information', 'hezarfen-for-woocommerce' ) => array(
			'{{bugunun_tarihi}}' => __( 'Today\'s date', 'hezarfen-for-woocommerce' ),
			'{{su_an}}' => __( 'Current date and time', 'hezarfen-for-woocommerce' ),
		),
		
		// Hezarfen Invoice Fields
		__( 'Hezarfen Invoice Fields', 'hezarfen-for-woocommerce' ) => array(
			'{{hezarfen_kurumsal_vergi_daire}}' => __( 'Corporate tax office', 'hezarfen-for-woocommerce' ),
			'{{hezarfen_kurumsal_vergi_no}}' => __( 'Corporate tax number', 'hezarfen-for-woocommerce' ),
			'{{hezarfen_bireysel_tc}}' => __( 'Individual ID number', 'hezarfen-for-woocommerce' ),
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
									 title="Click to copy">
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
					<strong>💡 <?php esc_html_e( 'Usage:', 'hezarfen-for-woocommerce' ); ?></strong><br>
					<?php esc_html_e( 'Write these variables into your contract text in the WordPress page editor. When an order is placed, these variables will be automatically replaced with real data.', 'hezarfen-for-woocommerce' ); ?>
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
				notification.innerHTML = '✓ Copied: ' + text;
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
				notification.innerHTML = '❌ Copy failed. Please select and copy manually.';
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