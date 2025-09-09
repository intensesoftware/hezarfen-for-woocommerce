<?php
/**
 * Contract Renderer
 *
 * @package Hezarfen\Contracts
 */

namespace Hezarfen\Inc\Contracts\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract_Renderer class
 */
class Contract_Renderer {

	// Constants for Hezarfen support
	const HEZ_FAT_CONDITIONAL_DIV_WRAPPER_CLASS = 'in-mss-hez-fat-blok';
	const HEZ_FAT_BIREYSEL_DIV_WRAPPER_CLASS = 'in-mss-hez-fat-bireysel';
	const HEZ_FAT_KURUMSAL_DIV_WRAPPER_CLASS = 'in-mss-hez-fat-kurumsal';

	/**
	 * Initialize checkout integration hooks
	 */
	public static function init_checkout_hooks() {
		add_action( 'woocommerce_checkout_before_terms_and_conditions', array( __CLASS__, 'render_checkout_contracts' ), 10 );
		add_action( 'woocommerce_checkout_after_terms_and_conditions', array( __CLASS__, 'render_contract_checkboxes' ) );
		add_action( 'woocommerce_checkout_process', array( '\Hezarfen\Inc\Contracts\Core\Contract_Validator', 'validate_checkout_contracts' ) );
		add_action( 'wp_footer', array( __CLASS__, 'add_contract_modal_script' ) );
		add_filter( 'woocommerce_update_order_review_fragments', array( __CLASS__, 'get_contract_fragments' ) );
	}

	/**
	 * Render contracts on checkout page
	 */
	public static function render_checkout_contracts() {
		$settings = get_option( 'hezarfen_mss_settings', array() );
		$display_type = isset($settings['odeme_sayfasinda_sozlesme_gosterim_tipi']) ? $settings['odeme_sayfasinda_sozlesme_gosterim_tipi'] : 'inline';
		
		self::render_contracts( $display_type );
	}

	/**
	 * Render contracts on checkout page using dynamic contracts from settings
	 *
	 * @param string $display_type Display type (inline|modal).
	 * @return void
	 */
	public static function render_contracts( $display_type = 'inline' ) {
		$settings = get_option( 'hezarfen_mss_settings', array() );
		$contracts = isset( $settings['contracts'] ) ? $settings['contracts'] : array();
		
		if ( empty( $contracts ) ) {
			return;
		}
		
		$contract_contents = array();
		
		foreach ( $contracts as $contract ) {
			// Skip disabled contracts
			if ( empty( $contract['enabled'] ) ) {
				continue;
			}
			
			// Skip contracts without templates
			if ( empty( $contract['template_id'] ) ) {
				continue;
			}
			
			$content = self::get_contract_content_from_template( $contract['template_id'] );
			if ( $content ) {
				$contract_contents[] = array(
					'contract' => array(
						'id' => $contract['id'],
						'name' => $contract['name'],
						'type' => $contract['id'],
						'enabled' => true,
						'required' => true,
					),
					'content' => $content,
				);
			}
		}

		if ( empty( $contract_contents ) ) {
			return;
		}

		// Render based on display type
		if ( 'modal' === $display_type ) {
			self::render_modal_view( $contract_contents );
		} else {
			self::render_inline_view( $contract_contents );
		}
	}

	/**
	 * Get contract content from WordPress page template ID
	 *
	 * @param int   $template_id WordPress page ID.
	 * @param int   $order_id Optional order ID for order-specific variables.
	 * @param array $form_data Optional form data for real-time processing.
	 * @return string|false
	 */
	public static function get_contract_content_from_template( $template_id, $order_id = null, $form_data = array() ) {
		if ( empty( $template_id ) ) {
			return false;
		}
		
		$template_post = get_post( intval( $template_id ) );
		
		// Only allow WordPress pages
		if ( ! $template_post || $template_post->post_type !== 'page' || $template_post->post_status !== 'publish' ) {
			return false;
		}
		
		$raw_content = $template_post->post_content;
		
		if ( empty( $raw_content ) ) {
			return false;
		}

		$processed_content = wpautop( $raw_content );
		
		// Process template variables using the dedicated processor
		$use_cart_data = is_checkout() && ! $order_id;
		$processed_content = Template_Processor::process_variables( $processed_content, $order_id, $use_cart_data, $form_data );
		
		return $processed_content;
	}




	/**
	 * Render inline view
	 *
	 * @param array $contract_contents Contract contents array.
	 * @return void
	 */
	private static function render_inline_view( $contract_contents ) {
		?>
		<div id="checkout-sozlesmeler" class="hezarfen-inline-contracts">
			<h3><?php esc_html_e( 'Contracts and Forms', 'hezarfen-for-woocommerce' ); ?></h3>
			
			<?php foreach ( $contract_contents as $item ) : ?>
				<div class="sozlesme-container contract-<?php echo esc_attr( $item['contract']['type'] ); ?>" data-contract-id="<?php echo esc_attr( $item['contract']['id'] ); ?>">
					<?php echo wp_kses_post( $item['content'] ); ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render modal view
	 *
	 * @param array $contract_contents Contract contents array.
	 * @return void
	 */
	private static function render_modal_view( $contract_contents ) {
		?>
		<!-- Contract Modals -->
		<?php foreach ( $contract_contents as $item ) : ?>
			<div class="hezarfen-modal" id="hezarfen-modal-<?php echo esc_attr( $item['contract']['id'] ); ?>">
				<div class="hezarfen-modal-overlay"></div>
				<div class="hezarfen-modal-container">
					<div class="hezarfen-modal-header">
						<h3><?php echo esc_html( $item['contract']['name'] ); ?></h3>
						<button type="button" class="hezarfen-modal-close">&times;</button>
					</div>
					<div class="hezarfen-modal-content">
						<?php echo wp_kses_post( $item['content'] ); ?>
					</div>
					<div class="hezarfen-modal-footer">
						<button type="button" class="hezarfen-modal-close button"><?php esc_html_e( 'Close', 'hezarfen-for-woocommerce' ); ?></button>
					</div>
				</div>
			</div>
		<?php endforeach; ?>


		<style>
		.contract-modal-link {
			text-decoration: none;
			color: #0073aa;
			cursor: pointer;
		}
		.contract-modal-link:hover {
			text-decoration: underline;
		}
		.hezarfen-modal {
			display: none;
			position: fixed;
			z-index: 1000;
			left: 0;
			top: 0;
			width: 100%;
			height: 100%;
		}
		.hezarfen-modal-overlay {
			position: absolute;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background-color: rgba(0, 0, 0, 0.5);
		}
		.hezarfen-modal-container {
			position: relative;
			background-color: #fff;
			margin: 5% auto;
			padding: 0;
			border: 1px solid #888;
			width: 80%;
			max-width: 800px;
			max-height: 80vh;
			overflow-y: auto;
			border-radius: 4px;
			box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
		}
		.hezarfen-modal-header {
			padding: 20px;
			border-bottom: 1px solid #eee;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		.hezarfen-modal-header h3 {
			margin: 0;
		}
		.hezarfen-modal-close {
			background: none;
			border: none;
			font-size: 24px;
			cursor: pointer;
			color: #666;
		}
		.hezarfen-modal-close:hover {
			color: #000;
		}
		.hezarfen-modal-content {
			padding: 20px;
		}
		.hezarfen-modal-footer {
			padding: 20px;
			border-top: 1px solid #eee;
			text-align: right;
		}
		</style>
		<?php
	}

	/**
	 * Render contract checkboxes
	 *
	 * @return void
	 */
	public static function render_contract_checkboxes() {
		$settings = get_option( 'hezarfen_mss_settings', array() );
		$contracts = isset( $settings['contracts'] ) ? $settings['contracts'] : array();
		
		
		if ( empty( $contracts ) ) {
			return;
		}
		
		$hidden_contracts = isset( $settings['gosterilmeyecek_sozlesmeler'] ) 
			? $settings['gosterilmeyecek_sozlesmeler'] 
			: array();
		
		$default_checked = isset( $settings['sozlesme_onay_checkbox_varsayilan_durum'] ) 
			? (int) $settings['sozlesme_onay_checkbox_varsayilan_durum'] 
			: 0;

		?>
		<div class="in-sozlesme-onay-checkboxes">
			<?php foreach ( $contracts as $contract ) : ?>
				<?php 
				// Skip disabled contracts
				if ( empty( $contract['enabled'] ) ) {
					continue;
				}
				
				// Skip contracts without templates
				if ( empty( $contract['template_id'] ) ) {
					continue;
				}
				
				// Skip validation for hidden contracts (by contract ID)
				if ( in_array( $contract['id'], $hidden_contracts, true ) ) {
					continue;
				}
				?>
				<p class="form-row in-sozlesme-onay-checkbox validate-required">
					<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
						<input 
							type="checkbox" 
							class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" 
							name="contract_<?php echo esc_attr( $contract['id'] ); ?>_checkbox"
							<?php checked( $default_checked, 1 ); ?>
							required
						/>
						<span><?php 
							printf( 
								__( '%s sözleşmesini okudum ve kabul ediyorum.', 'hezarfen-for-woocommerce' ), 
								'<a href="#" class="contract-modal-link" data-contract-id="' . esc_attr( $contract['id'] ) . '">' . esc_html( $contract['name'] ) . '</a>'
							);
						?></span>
					</label>
				</p>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Get contract fragments for WooCommerce's update_order_review response
	 *
	 * @param array $fragments Existing fragments array.
	 * @return array
	 */
	public static function get_contract_fragments( $fragments ) {
		// Parse form data from post_data parameter
		$form_data = array();
		if ( isset( $_POST['post_data'] ) ) {
			parse_str( $_POST['post_data'], $form_data );
		}
		
		// Get all contracts
		$settings = get_option( 'hezarfen_mss_settings', array() );
		$contracts = isset( $settings['contracts'] ) ? $settings['contracts'] : array();
		
		$contract_data = array();
		
		foreach ( $contracts as $contract ) {
			if ( ! empty( $contract['template_id'] ) && ! empty( $contract['enabled'] ) ) {
				$processed_content = self::get_contract_content_from_template( $contract['template_id'], null, $form_data );
				if ( $processed_content ) {
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
	 * Add contract modal JavaScript to footer
	 */
	public static function add_contract_modal_script() {
		// Only add on checkout page
		if ( ! is_checkout() ) {
			return;
		}
		?>
		<script>
		
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
				
				// Get contract data from fragment element
				var contractDataElement = document.querySelector('.hezarfen-contract-data');
				
				if (contractDataElement && contractDataElement.dataset.contracts) {
					try {
						var contractFragments = JSON.parse(contractDataElement.dataset.contracts);
						if (contractFragments[contractId]) {
							tabPane.innerHTML = contractFragments[contractId];
							return;
						} else {
						}
					} catch (e) {
						console.error('Error parsing contract data:', e);
					}
				} else {
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

		}
		
		// Global ESC key handler for all Hezarfen modals (outside of init function)
		if (!window.hezarfenEscListenerAdded) {
			document.addEventListener('keydown', function(e) {
				if (e.key === 'Escape' || e.keyCode === 27) {
					// Try multiple selectors to find visible modals
					var allModals = document.querySelectorAll('.hezarfen-unified-modal, .hezarfen-modal');
					var visibleModals = [];
					
					allModals.forEach(function(modal) {
						var style = window.getComputedStyle(modal);
						var display = style.display;
						var visibility = style.visibility;
						var opacity = style.opacity;
						
						// Check if modal is visible (not none, not hidden, opacity > 0)
						if (display !== 'none' && visibility !== 'hidden' && opacity !== '0') {
							visibleModals.push(modal);
						}
					});
					
					if (visibleModals.length > 0) {
						visibleModals.forEach(function(modal) {
							// Remove modal from DOM (same as close button)
							if (modal.parentNode) {
								modal.parentNode.removeChild(modal);
							}
						});
						document.body.style.overflow = '';
						// Prevent default behavior
						e.preventDefault();
						e.stopPropagation();
					}
				}
			});
			window.hezarfenEscListenerAdded = true;
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