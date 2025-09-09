<?php
/**
 * Ödeme sayfasındaki işlemleri yürütür (sözleşmelerin SSR gösterilmesi, Ajax endpointler vs.)
 *
 * @package Intense\MSS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IN_MSS_OdemeSayfasi_Sozlesmeler
 */
class IN_MSS_OdemeSayfasi_Sozlesmeler {
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

		add_action( 'woocommerce_checkout_after_terms_and_conditions', array( $this, 'intense_mss_onay_checkbox' ) );

		add_action( 'woocommerce_checkout_process', array( $this, 'intense_mss_checkout_process' ) );

		add_action( 'wp_footer', array( $this, 'add_contract_modal_script' ) );
		
		// Hook into WooCommerce's fragments to add contract data
		add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'add_contract_fragments' ) );
	}

	/**
	 * Hezarfen Desteği
	 *
	 * @param  string $form_icerik
	 * @return string
	 */
	public function form_hezarfen_destegi( $form_icerik ) {
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
			// Add hidden element for contract data fragments
			if (!document.querySelector('.hezarfen-contract-data')) {
				var contractDataDiv = document.createElement('div');
				contractDataDiv.className = 'hezarfen-contract-data';
				contractDataDiv.style.display = 'none';
				document.body.appendChild(contractDataDiv);
			}
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
				
				// Header with close button only
				var modalHeader = document.createElement('div');
				modalHeader.style.cssText = 'display: flex; justify-content: flex-end; align-items: center; padding: 15px 20px 0 20px;';
				
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
				tabNav.style.cssText = 'display: flex; border-bottom: 1px solid #eee; background: #f9f9f9; padding: 0 20px;';
				
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
					
					// Load actual contract content with real-time rendering
					loadContractContent(contract.id, tabPane);
					
					tabContent.appendChild(tabPane);
				});
				
				// Click overlay to close
				modalOverlay.onclick = function(e) {
					if (e.target === modalOverlay) {
						document.body.removeChild(modalOverlay);
						document.body.style.overflow = '';
					}
				};
				
				modalHeader.appendChild(closeButton);
				modalContent.appendChild(modalHeader);
				modalContent.appendChild(tabNav);
				modalContent.appendChild(tabContent);
				modalOverlay.appendChild(modalContent);
				
				document.body.appendChild(modalOverlay);
				document.body.style.overflow = 'hidden';
			}
			
			function loadContractContent(contractId, tabPane) {
				// Add loading message first
				tabPane.innerHTML = `
					<div style="color: #666; line-height: 1.6; text-align: center; padding: 40px;">
						<p>Sözleşme içeriği yükleniyor...</p>
					</div>
				`;
				
				// Load initial contract content
				updateContractContent(contractId, tabPane);
				
				// Set up listener for WooCommerce checkout updates
				setupCheckoutUpdateListener(contractId, tabPane);
			}
			
			function updateContractContent(contractId, tabPane) {
				console.log('Updating contract content for:', contractId);
				
				// Get contract data from fragment element
				var contractDataElement = document.querySelector('.hezarfen-contract-data');
				console.log('Contract data element found:', !!contractDataElement);
				
				if (contractDataElement && contractDataElement.dataset.contracts) {
					console.log('Contract data available:', contractDataElement.dataset.contracts.substring(0, 100) + '...');
					try {
						var contractFragments = JSON.parse(contractDataElement.dataset.contracts);
						console.log('Parsed contract fragments:', Object.keys(contractFragments));
						if (contractFragments[contractId]) {
							console.log('Updating tab content with new data');
							tabPane.innerHTML = contractFragments[contractId];
							return;
						} else {
							console.log('Contract ID not found in fragments:', contractId);
						}
					} catch (e) {
						console.error('Error parsing contract data:', e);
					}
				} else {
					console.log('No contract data element or dataset found');
				}
				
				// Fallback to existing modal content if fragments not available
				var existingModal = document.getElementById('hezarfen-modal-' + contractId);
				if (existingModal) {
					var modalContent = existingModal.querySelector('.hezarfen-modal-content');
					if (modalContent) {
						tabPane.innerHTML = modalContent.innerHTML;
						return;
					}
				}
				
				// If no content found, show loading message
				tabPane.innerHTML = `
					<div style="color: #666; line-height: 1.6; text-align: center; padding: 40px;">
						<p>Sözleşme içeriği yükleniyor...</p>
					</div>
				`;
			}
			
			function setupCheckoutUpdateListener(contractId, tabPane) {
				// Listen for WooCommerce's updated_checkout event
				jQuery(document.body).on('updated_checkout', function() {
					console.log('Checkout updated, refreshing contract content');
					// Only update if this tab is currently visible
					if (tabPane.style.display !== 'none') {
						// Small delay to ensure fragments are processed
						setTimeout(function() {
							updateContractContent(contractId, tabPane);
						}, 200);
					}
				});
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

	/**
	 * Add contract fragments to WooCommerce's update_order_review response
	 */
	public function add_contract_fragments( $fragments ) {
		// Parse form data from post_data parameter
		$form_data = array();
		if ( isset( $_POST['post_data'] ) ) {
			parse_str( $_POST['post_data'], $form_data );
		}
		
		// Debug: Add form data to see what's available
		error_log( 'Form data available: ' . print_r( array_keys( $form_data ), true ) );
		error_log( 'Sample form values: billing_first_name=' . ( $form_data['billing_first_name'] ?? 'empty' ) . ', billing_city=' . ( $form_data['billing_city'] ?? 'empty' ) );
		
		// Get all contracts
		$settings = get_option( 'hezarfen_mss_settings', array() );
		$contracts = isset( $settings['contracts'] ) ? $settings['contracts'] : array();
		
		$contract_data = array();
		
		foreach ( $contracts as $contract ) {
			if ( ! empty( $contract['template_id'] ) && ! empty( $contract['enabled'] ) ) {
				$template_post = get_post( $contract['template_id'] );
				if ( $template_post && $template_post->post_status === 'publish' ) {
					$contract_content = wpautop( $template_post->post_content );
					// First process with Template_Processor for cart data
					$processed_content = \Hezarfen\Inc\MSS\Core\Template_Processor::process_variables( $contract_content, null, true );
					// Then process real-time form variables
					$processed_content = $this->process_realtime_variables( $processed_content, $form_data );
					$contract_data[ $contract['id'] ] = $processed_content;
				}
			}
		}
		
		// Add contract data as a special fragment using data attributes
		if ( ! empty( $contract_data ) ) {
			$fragments['.hezarfen-contract-data'] = '<div class="hezarfen-contract-data" data-contracts="' . esc_attr( wp_json_encode( $contract_data ) ) . '" style="display:none;"></div>';
			
			// Also update inline contracts if they exist
			ob_start();
			?>
			<div id="checkout-sozlesmeler" class="hezarfen-inline-contracts">
				<h3><?php esc_html_e( 'Contracts and Forms', 'hezarfen-for-woocommerce' ); ?></h3>
				
				<?php foreach ( $contract_data as $contract_id => $content ) : ?>
					<div class="sozlesme-container contract-<?php echo esc_attr( $contract_id ); ?>" data-contract-id="<?php echo esc_attr( $contract_id ); ?>">
						<?php echo wp_kses_post( $content ); ?>
					</div>
				<?php endforeach; ?>
			</div>
			<?php
			$fragments['#checkout-sozlesmeler'] = ob_get_clean();
		}
		
		return $fragments;
	}
	
	/**
	 * Process variables with real-time form data
	 */
	private function process_realtime_variables( $content, $form_data ) {
		error_log( 'Processing variables with form data. Content length: ' . strlen( $content ) );
		error_log( 'Form data keys: ' . print_r( array_keys( $form_data ), true ) );
		$replacements = array(
			// Site Variables
			'{{site_adi}}' => get_bloginfo( 'name' ),
			'{{site_url}}' => home_url(),
			
			// Date Variables
			'{{bugunun_tarihi}}' => date_i18n( 'd/m/Y' ),
			'{{su_an}}' => date_i18n( 'd/m/Y H:i:s' ),
			
			// Form data variables (from checkout form)
			'{{fatura_adi}}' => isset( $form_data['billing_first_name'] ) ? sanitize_text_field( $form_data['billing_first_name'] ) : '',
			'{{fatura_soyadi}}' => isset( $form_data['billing_last_name'] ) ? sanitize_text_field( $form_data['billing_last_name'] ) : '',
			'{{fatura_sirket}}' => isset( $form_data['billing_company'] ) ? sanitize_text_field( $form_data['billing_company'] ) : '',
			'{{fatura_adres_1}}' => isset( $form_data['billing_address_1'] ) ? sanitize_text_field( $form_data['billing_address_1'] ) : '',
			'{{fatura_adres_2}}' => isset( $form_data['billing_address_2'] ) ? sanitize_text_field( $form_data['billing_address_2'] ) : '',
			'{{fatura_sehir}}' => isset( $form_data['billing_city'] ) ? sanitize_text_field( $form_data['billing_city'] ) : '',
			'{{fatura_posta_kodu}}' => isset( $form_data['billing_postcode'] ) ? sanitize_text_field( $form_data['billing_postcode'] ) : '',
			'{{fatura_ulke}}' => isset( $form_data['billing_country'] ) ? $this->get_country_name( sanitize_text_field( $form_data['billing_country'] ) ) : '',
			
			'{{teslimat_adi}}' => isset( $form_data['shipping_first_name'] ) ? sanitize_text_field( $form_data['shipping_first_name'] ) : ( isset( $form_data['billing_first_name'] ) ? sanitize_text_field( $form_data['billing_first_name'] ) : '' ),
			'{{teslimat_soyadi}}' => isset( $form_data['shipping_last_name'] ) ? sanitize_text_field( $form_data['shipping_last_name'] ) : ( isset( $form_data['billing_last_name'] ) ? sanitize_text_field( $form_data['billing_last_name'] ) : '' ),
			'{{teslimat_sirket}}' => isset( $form_data['shipping_company'] ) ? sanitize_text_field( $form_data['shipping_company'] ) : ( isset( $form_data['billing_company'] ) ? sanitize_text_field( $form_data['billing_company'] ) : '' ),
			'{{teslimat_adres_1}}' => isset( $form_data['shipping_address_1'] ) ? sanitize_text_field( $form_data['shipping_address_1'] ) : ( isset( $form_data['billing_address_1'] ) ? sanitize_text_field( $form_data['billing_address_1'] ) : '' ),
			'{{teslimat_adres_2}}' => isset( $form_data['shipping_address_2'] ) ? sanitize_text_field( $form_data['shipping_address_2'] ) : ( isset( $form_data['billing_address_2'] ) ? sanitize_text_field( $form_data['billing_address_2'] ) : '' ),
			'{{teslimat_sehir}}' => isset( $form_data['shipping_city'] ) ? sanitize_text_field( $form_data['shipping_city'] ) : ( isset( $form_data['billing_city'] ) ? sanitize_text_field( $form_data['billing_city'] ) : '' ),
			'{{teslimat_posta_kodu}}' => isset( $form_data['shipping_postcode'] ) ? sanitize_text_field( $form_data['shipping_postcode'] ) : ( isset( $form_data['billing_postcode'] ) ? sanitize_text_field( $form_data['billing_postcode'] ) : '' ),
			'{{teslimat_ulke}}' => isset( $form_data['shipping_country'] ) ? $this->get_country_name( sanitize_text_field( $form_data['shipping_country'] ) ) : ( isset( $form_data['billing_country'] ) ? $this->get_country_name( sanitize_text_field( $form_data['billing_country'] ) ) : '' ),
		);
		
		return str_replace( array_keys( $replacements ), array_values( $replacements ), $content );
	}
	
	/**
	 * Get country name from country code
	 */
	private function get_country_name( $country_code ) {
		$countries = WC()->countries->get_countries();
		return isset( $countries[ $country_code ] ) ? $countries[ $country_code ] : $country_code;
	}
}

new IN_MSS_OdemeSayfasi_Sozlesmeler();
