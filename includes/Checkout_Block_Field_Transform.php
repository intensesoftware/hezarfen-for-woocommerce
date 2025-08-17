<?php
/**
 * Checkout Block Field Transform
 *
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

use Hezarfen\Inc\Mahalle_Local;
use Hezarfen\Inc\Helper;

/**
 * Checkout Block Field Transform
 * 
 * This transforms the existing WooCommerce checkout block fields:
 * - billing_city -> District dropdown for Turkey
 * - billing_address_1 -> Neighborhood dropdown for Turkey
 */
class Checkout_Block_Field_Transform {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Hook into checkout block field rendering
		add_action( 'woocommerce_blocks_loaded', array( $this, 'init_field_transforms' ) );
		add_action( 'init', array( $this, 'init_field_transforms_fallback' ), 25 );
		
		// Add scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		
		// Add AJAX handlers
		add_action( 'wp_ajax_hezarfen_transform_get_districts', array( $this, 'ajax_get_districts' ) );
		add_action( 'wp_ajax_nopriv_hezarfen_transform_get_districts', array( $this, 'ajax_get_districts' ) );
		add_action( 'wp_ajax_hezarfen_transform_get_neighborhoods', array( $this, 'ajax_get_neighborhoods' ) );
		add_action( 'wp_ajax_nopriv_hezarfen_transform_get_neighborhoods', array( $this, 'ajax_get_neighborhoods' ) );
	}

	/**
	 * Initialize field transforms
	 */
	public function init_field_transforms() {
		static $initialized = false;
		if ( $initialized ) {
			return;
		}

		error_log( 'Hezarfen Transform: Initializing field transforms' );
		$initialized = true;

		// Hook into checkout block rendering
		add_filter( 'render_block', array( $this, 'transform_checkout_block' ), 10, 2 );
		
		// Add inline script to transform fields after block renders
		add_action( 'wp_footer', array( $this, 'add_field_transform_script' ) );
	}

	/**
	 * Fallback initialization
	 */
	public function init_field_transforms_fallback() {
		$this->init_field_transforms();
	}

	/**
	 * Transform checkout block content
	 */
	public function transform_checkout_block( $block_content, $block ) {
		// Only process checkout block
		if ( ! isset( $block['blockName'] ) || $block['blockName'] !== 'woocommerce/checkout' ) {
			return $block_content;
		}

		error_log( 'Hezarfen Transform: Processing checkout block' );

		// Add data attributes to help with field transformation
		$block_content = str_replace(
			'class="wp-block-woocommerce-checkout"',
			'class="wp-block-woocommerce-checkout" data-hezarfen-transform="true"',
			$block_content
		);

		return $block_content;
	}

	/**
	 * Add field transform script to footer
	 */
	public function add_field_transform_script() {
		if ( ! is_checkout() || ! has_block( 'woocommerce/checkout' ) ) {
			return;
		}

		?>
		<script type="text/javascript">
		(function() {
			// Prevent multiple simultaneous AJAX calls
			let isLoadingDistricts = false;
			let isLoadingNeighborhoods = false;
			console.log('Hezarfen Transform: Initializing field transformation');
			
			// Wait for checkout block to be fully rendered
			function initFieldTransform() {
				const checkoutBlock = document.querySelector('.wp-block-woocommerce-checkout');
				if (!checkoutBlock) {
					console.log('Hezarfen Transform: Checkout block not found, retrying...');
					setTimeout(initFieldTransform, 500);
					return;
				}

				console.log('Hezarfen Transform: Checkout block found, checking country and transforming fields');
				
				// Debug: Check all country-related fields
				const allCountryFields = document.querySelectorAll('select[name*="country"], input[name*="country"]');
				console.log('Hezarfen Transform: All country fields found:', allCountryFields);
				
				// Check if Turkey is already selected - try multiple selectors
				const countrySelectors = [
					'select[id="billing-country"]',
					'select[name="billing_country"]',
					'select[id*="billing"][id*="country"]'
				];
				
				let countryField = null;
				for (const selector of countrySelectors) {
					countryField = document.querySelector(selector);
					if (countryField) {
						console.log('Hezarfen Transform: Found country field with selector:', selector);
						break;
					}
				}
				console.log('Hezarfen Transform: Country field:', countryField);
				if (countryField) {
					console.log('Hezarfen Transform: Country field value:', countryField.value);
					if (countryField.value === 'TR') {
						console.log('Hezarfen Transform: Turkey already selected, transforming fields immediately');
						transformFields();
					} else {
						console.log('Hezarfen Transform: Country is not Turkey, current value:', countryField.value);
					}
				} else {
					console.log('Hezarfen Transform: No country field found, will try to transform fields anyway');
					// Try to transform anyway in case Turkey is pre-selected in a different way
					transformFields();
				}
				
				// Monitor for country changes
				monitorCountryChanges();
			}

			function transformFields() {
				// Transform city field to district dropdown for Turkey
				transformCityField();
				
				// Transform address_1 field to neighborhood dropdown for Turkey
				transformAddress1Field();
			}

			function transformCityField() {
				// Debug: List all input and select fields
				const allInputs = document.querySelectorAll('input');
				const allSelects = document.querySelectorAll('select');
				console.log('Hezarfen Transform: All input fields:', allInputs);
				console.log('Hezarfen Transform: All select fields:', allSelects);
				
				// Find billing city field - prioritize billing over shipping
				const citySelectors = [
					'input[id="billing-city"]',           // Exact billing city field
					'input[name="billing_city"]',
					'input[id*="billing_city"]',
					'input[id*="billing"][id*="city"]',   // Must contain both billing and city
					'select[name="billing_city"]',
					'input[name*="city"]',
					'input[id*="İlçe"]',
					'input[placeholder*="city"]',
					'input[placeholder*="İlçe"]'
				];
				
				let cityField = null;
				
				console.log('Hezarfen Transform: Trying to find city field...');
				for (const selector of citySelectors) {
					const fields = document.querySelectorAll(selector);
					console.log(`Hezarfen Transform: Selector "${selector}" found ${fields.length} fields:`, fields);
					if (fields.length > 0) {
						cityField = fields[0];
						console.log('Hezarfen Transform: Found city field with selector:', selector, cityField);
						break;
					}
				}
				
				if (!cityField) {
					console.log('Hezarfen Transform: City field not found, trying again in 1 second...');
					setTimeout(transformCityField, 1000);
					return;
				}
				
				// Check if already transformed
				if (cityField.dataset.hezarfenTransformed) {
					return;
				}
				
				// Mark as transformed
				cityField.dataset.hezarfenTransformed = 'true';
					
									// Create district dropdown
				const districtSelect = document.createElement('select');
				districtSelect.name = cityField.name;
				districtSelect.id = cityField.id;
				districtSelect.className = cityField.className;
				districtSelect.required = cityField.required;
				
				// Copy attributes
				Array.from(cityField.attributes).forEach(function(attr) {
					if (!['type', 'value'].includes(attr.name)) {
						districtSelect.setAttribute(attr.name, attr.value);
					}
				});
				
				// Add default option
				const defaultOption = document.createElement('option');
				defaultOption.value = '';
				defaultOption.textContent = '<?php echo esc_js( __( 'Select District', 'hezarfen-for-woocommerce' ) ); ?>';
				districtSelect.appendChild(defaultOption);
				
				// Replace field
				cityField.parentNode.replaceChild(districtSelect, cityField);
				
				// Update label
				const label = document.querySelector(`label[for="${districtSelect.id}"]`);
				if (label) {
					label.textContent = '<?php echo esc_js( __( 'District (İlçe)', 'hezarfen-for-woocommerce' ) ); ?>';
				}
				
				// Load districts based on current state
				const stateField = document.querySelector('select[id="billing-state"]');
				if (stateField && stateField.value) {
					console.log('Hezarfen Transform: Loading districts for state:', stateField.value);
					loadDistricts(stateField.value);
				}
				
				// Add event listener for district selection
				districtSelect.addEventListener('change', function() {
					if (this.value) {
						console.log('Hezarfen Transform: District selected:', this.value);
						// Find and update neighborhood dropdown
						const neighborhoodField = document.querySelector('select[id="billing-address_1"]');
						if (neighborhoodField) {
							loadNeighborhoods(this.value);
						}
					}
					
					// Trigger change event to notify WooCommerce
					const event = new Event('change', { bubbles: true });
					this.dispatchEvent(event);
					
					// Sync value to hidden input
					syncDropdownValuesQuiet();
				});
				
				console.log('Hezarfen Transform: City field transformed to district dropdown');
			}

			function transformAddress1Field() {
				// Find billing address_1 field - prioritize billing over shipping
				const address1Selectors = [
					'input[id="billing-address_1"]',        // Exact billing address_1 field
					'input[name="billing_address_1"]',
					'input[id*="billing_address_1"]',
					'input[id*="billing"][id*="address_1"]', // Must contain both billing and address_1
					'select[name="billing_address_1"]',
					'input[name*="address"]',
					'input[id*="Mahalle"]',
					'input[placeholder*="address"]',
					'input[placeholder*="Mahalle"]'
				];
				
				let address1Field = null;
				
				console.log('Hezarfen Transform: Trying to find address_1 field...');
				for (const selector of address1Selectors) {
					const fields = document.querySelectorAll(selector);
					console.log(`Hezarfen Transform: Selector "${selector}" found ${fields.length} fields:`, fields);
					if (fields.length > 0) {
						address1Field = fields[0];
						console.log('Hezarfen Transform: Found address_1 field with selector:', selector, address1Field);
						break;
					}
				}
				
				if (!address1Field) {
					console.log('Hezarfen Transform: Address_1 field not found, trying again in 1 second...');
					setTimeout(transformAddress1Field, 1000);
					return;
				}
				
				// Check if already transformed
				if (address1Field.dataset.hezarfenTransformed) {
					return;
				}
				
				// Mark as transformed
				address1Field.dataset.hezarfenTransformed = 'true';
					
									// Create neighborhood dropdown
				const neighborhoodSelect = document.createElement('select');
				neighborhoodSelect.name = address1Field.name;
				neighborhoodSelect.id = address1Field.id;
				neighborhoodSelect.className = address1Field.className;
				neighborhoodSelect.required = address1Field.required;
				
				// Copy attributes
				Array.from(address1Field.attributes).forEach(function(attr) {
					if (!['type', 'value'].includes(attr.name)) {
						neighborhoodSelect.setAttribute(attr.name, attr.value);
					}
				});
				
				// Add default option
				const defaultOption = document.createElement('option');
				defaultOption.value = '';
				defaultOption.textContent = '<?php echo esc_js( __( 'Select Neighborhood', 'hezarfen-for-woocommerce' ) ); ?>';
				neighborhoodSelect.appendChild(defaultOption);
				
				// Replace field
				address1Field.parentNode.replaceChild(neighborhoodSelect, address1Field);
				
				// Update label
				const label = document.querySelector(`label[for="${neighborhoodSelect.id}"]`);
				if (label) {
					label.textContent = '<?php echo esc_js( __( 'Neighborhood (Mahalle)', 'hezarfen-for-woocommerce' ) ); ?>';
				}
				
				// Load neighborhoods based on current district
				const districtField = document.querySelector('select[id="billing-city"]');
				if (districtField && districtField.value) {
					console.log('Hezarfen Transform: Loading neighborhoods for district:', districtField.value);
					loadNeighborhoods(districtField.value);
				}
				
				// Add event listener for neighborhood selection
				neighborhoodSelect.addEventListener('change', function() {
					console.log('Hezarfen Transform: Neighborhood selected:', this.value);
					
					// Trigger change event to notify WooCommerce
					const event = new Event('change', { bubbles: true });
					this.dispatchEvent(event);
					
					// Sync value to hidden input
					syncDropdownValuesQuiet();
				});
				
				console.log('Hezarfen Transform: Address_1 field transformed to neighborhood dropdown');
			}

			function monitorCountryChanges() {
				// Monitor country field changes
				document.addEventListener('change', function(e) {
					if (e.target.name === 'billing_country') {
						const isTurkey = e.target.value === 'TR';
						console.log('Hezarfen Transform: Country changed to', e.target.value, 'Turkey:', isTurkey);
						
						if (isTurkey) {
							// Transform fields for Turkey
							setTimeout(transformFields, 100); // Small delay to ensure DOM is ready
							// Load districts when state changes
							monitorStateChanges();
						} else {
							// Could revert transformations here if needed
							console.log('Hezarfen Transform: Country is not Turkey, skipping transformation');
						}
					}
				});

				// Monitor state changes for Turkey
				monitorStateChanges();
				
				// Ensure form data is captured
				ensureFormDataCapture();
			}

			function monitorStateChanges() {
				document.addEventListener('change', function(e) {
					// Monitor billing state changes
					if (e.target.id === 'billing-state' || e.target.name === 'billing_state') {
						console.log('Hezarfen Transform: State changed to', e.target.value);
						
						// Check if it's a Turkish state
						if (e.target.value && e.target.value.startsWith('TR')) {
							// Load districts for the new state
							loadDistricts(e.target.value);
							
							// Clear and disable neighborhoods when state changes
							const neighborhoodField = document.querySelector('select[id="billing-address_1"]');
							if (neighborhoodField) {
								neighborhoodField.innerHTML = '<option value=""><?php echo esc_js( __( 'Select Neighborhood', 'hezarfen-for-woocommerce' ) ); ?></option>';
								neighborhoodField.disabled = true;
								console.log('Hezarfen Transform: Neighborhoods cleared due to state change');
							}
						} else {
							console.log('Hezarfen Transform: Non-Turkish state selected, clearing districts and neighborhoods');
							
							// Clear districts
							const districtField = document.querySelector('select[id="billing-city"]');
							if (districtField) {
								districtField.innerHTML = '<option value=""><?php echo esc_js( __( 'Select District', 'hezarfen-for-woocommerce' ) ); ?></option>';
								districtField.disabled = true;
							}
							
							// Clear neighborhoods
							const neighborhoodField = document.querySelector('select[id="billing-address_1"]');
							if (neighborhoodField) {
								neighborhoodField.innerHTML = '<option value=""><?php echo esc_js( __( 'Select Neighborhood', 'hezarfen-for-woocommerce' ) ); ?></option>';
								neighborhoodField.disabled = true;
							}
						}
					}
				});

				// Monitor district changes
				document.addEventListener('change', function(e) {
					if (e.target.id === 'billing-city' || e.target.name === 'billing_city') {
						const stateField = document.querySelector('select[id="billing-state"]');
						if (stateField && stateField.value && stateField.value.startsWith('TR')) {
							console.log('Hezarfen Transform: District changed to', e.target.value);
							if (e.target.value) {
								loadNeighborhoods(e.target.value);
							} else {
								// Clear neighborhoods if no district selected
								const neighborhoodField = document.querySelector('select[id="billing-address_1"]');
								if (neighborhoodField) {
									neighborhoodField.innerHTML = '<option value=""><?php echo esc_js( __( 'Select Neighborhood', 'hezarfen-for-woocommerce' ) ); ?></option>';
									neighborhoodField.disabled = true;
								}
							}
						}
					}
				});
			}

			function ensureFormDataCapture() {
				// Monitor form submission to ensure dropdown values are captured
				document.addEventListener('submit', function(e) {
					const form = e.target;
					if (form.classList.contains('wc-block-checkout__form') || form.querySelector('.wp-block-woocommerce-checkout')) {
						console.log('Hezarfen Transform: Form submission detected, ensuring dropdown values are captured');
						
						// Ensure district value is captured
						const districtField = document.querySelector('select[id="billing-city"]');
						if (districtField && districtField.value) {
							console.log('Hezarfen Transform: District value to be saved:', districtField.value);
							
							// Create hidden input to ensure value is submitted
							let hiddenDistrict = form.querySelector('input[name="billing_city"]');
							if (!hiddenDistrict) {
								hiddenDistrict = document.createElement('input');
								hiddenDistrict.type = 'hidden';
								hiddenDistrict.name = 'billing_city';
								form.appendChild(hiddenDistrict);
							}
							hiddenDistrict.value = districtField.value;
						}
						
						// Ensure neighborhood value is captured
						const neighborhoodField = document.querySelector('select[id="billing-address_1"]');
						if (neighborhoodField && neighborhoodField.value) {
							console.log('Hezarfen Transform: Neighborhood value to be saved:', neighborhoodField.value);
							
							// Create hidden input to ensure value is submitted
							let hiddenNeighborhood = form.querySelector('input[name="billing_address_1"]');
							if (!hiddenNeighborhood) {
								hiddenNeighborhood = document.createElement('input');
								hiddenNeighborhood.type = 'hidden';
								hiddenNeighborhood.name = 'billing_address_1';
								form.appendChild(hiddenNeighborhood);
							}
							hiddenNeighborhood.value = neighborhoodField.value;
						}
					}
				}, true);
				
				// Also monitor for WooCommerce checkout updates
				document.addEventListener('checkout_updated', function() {
					console.log('Hezarfen Transform: Checkout updated, syncing dropdown values');
					syncDropdownValues();
				});
				
				// Initial sync to create hidden inputs
				setTimeout(function() {
					syncDropdownValuesQuiet();
				}, 1000);
			}

			function syncDropdownValues() {
				// Sync district value
				const districtField = document.querySelector('select[id="billing-city"]');
				if (districtField && districtField.value) {
					// Update any hidden inputs or original fields
					const hiddenDistrict = document.querySelector('input[name="billing_city"]');
					if (hiddenDistrict) {
						hiddenDistrict.value = districtField.value;
					}
				}
				
				// Sync neighborhood value
				const neighborhoodField = document.querySelector('select[id="billing-address_1"]');
				if (neighborhoodField && neighborhoodField.value) {
					// Update any hidden inputs or original fields
					const hiddenNeighborhood = document.querySelector('input[name="billing_address_1"]');
					if (hiddenNeighborhood) {
						hiddenNeighborhood.value = neighborhoodField.value;
					}
				}
			}

			function syncDropdownValuesQuiet() {
				// Quiet sync - only update hidden inputs, don't trigger any loading
				const districtField = document.querySelector('select[id="billing-city"]');
				if (districtField && districtField.value) {
					let hiddenDistrict = document.querySelector('input[name="billing_city"]');
					if (!hiddenDistrict) {
						hiddenDistrict = document.createElement('input');
						hiddenDistrict.type = 'hidden';
						hiddenDistrict.name = 'billing_city';
						const form = document.querySelector('.wc-block-checkout__form') || document.querySelector('form');
						if (form) form.appendChild(hiddenDistrict);
					}
					if (hiddenDistrict) {
						hiddenDistrict.value = districtField.value;
					}
				}
				
				const neighborhoodField = document.querySelector('select[id="billing-address_1"]');
				if (neighborhoodField && neighborhoodField.value) {
					let hiddenNeighborhood = document.querySelector('input[name="billing_address_1"]');
					if (!hiddenNeighborhood) {
						hiddenNeighborhood = document.createElement('input');
						hiddenNeighborhood.type = 'hidden';
						hiddenNeighborhood.name = 'billing_address_1';
						const form = document.querySelector('.wc-block-checkout__form') || document.querySelector('form');
						if (form) form.appendChild(hiddenNeighborhood);
					}
					if (hiddenNeighborhood) {
						hiddenNeighborhood.value = neighborhoodField.value;
					}
				}
			}

			function loadDistricts(stateValue) {
				if (isLoadingDistricts) {
					console.log('Hezarfen Transform: Already loading districts, skipping');
					return;
				}

				const districtField = document.querySelector('select[id="billing-city"]');
				if (!districtField) {
					console.log('Hezarfen Transform: District field not found for loading options');
					return;
				}

				isLoadingDistricts = true;
				console.log('Hezarfen Transform: Loading districts for', stateValue);

				// Show loading
				districtField.disabled = true;
				districtField.innerHTML = '<option value=""><?php echo esc_js( __( 'Loading districts...', 'hezarfen-for-woocommerce' ) ); ?></option>';

				// Make AJAX request
				fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: new URLSearchParams({
						action: 'hezarfen_transform_get_districts',
						city_plate_number: stateValue,
						nonce: '<?php echo wp_create_nonce( 'hezarfen_transform_nonce' ); ?>'
					})
				})
				.then(response => response.json())
				.then(data => {
					console.log('Hezarfen Transform: Districts loaded', data);
					
					if (data.success) {
						districtField.innerHTML = '';
						data.data.forEach(function(option) {
							const optionElement = document.createElement('option');
							optionElement.value = option.value;
							optionElement.textContent = option.label;
							districtField.appendChild(optionElement);
						});
						districtField.disabled = false;
					} else {
						districtField.innerHTML = '<option value=""><?php echo esc_js( __( 'Error loading districts', 'hezarfen-for-woocommerce' ) ); ?></option>';
					}
					isLoadingDistricts = false;
				})
				.catch(error => {
					console.error('Hezarfen Transform: Error loading districts', error);
					districtField.innerHTML = '<option value=""><?php echo esc_js( __( 'Error loading districts', 'hezarfen-for-woocommerce' ) ); ?></option>';
					isLoadingDistricts = false;
				});
			}

			function loadNeighborhoods(districtValue) {
				if (isLoadingNeighborhoods) {
					console.log('Hezarfen Transform: Already loading neighborhoods, skipping');
					return;
				}

				const neighborhoodField = document.querySelector('select[id="billing-address_1"]');
				if (!neighborhoodField) {
					console.log('Hezarfen Transform: Neighborhood field not found for loading options');
					return;
				}

				isLoadingNeighborhoods = true;
				console.log('Hezarfen Transform: Loading neighborhoods for district:', districtValue);

				// Show loading
				neighborhoodField.disabled = true;
				neighborhoodField.innerHTML = '<option value=""><?php echo esc_js( __( 'Loading neighborhoods...', 'hezarfen-for-woocommerce' ) ); ?></option>';

				// Get state value from state field
				const stateField = document.querySelector('select[id="billing-state"]');
				const stateValue = stateField ? stateField.value : '';
				
				// Make AJAX request
				fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: new URLSearchParams({
						action: 'hezarfen_transform_get_neighborhoods',
						city_plate_number: stateValue,
						district: districtValue,
						nonce: '<?php echo wp_create_nonce( 'hezarfen_transform_nonce' ); ?>'
					})
				})
				.then(response => response.json())
				.then(data => {
					console.log('Hezarfen Transform: Neighborhoods loaded', data);
					
					if (data.success) {
						neighborhoodField.innerHTML = '';
						data.data.forEach(function(option) {
							const optionElement = document.createElement('option');
							optionElement.value = option.value;
							optionElement.textContent = option.label;
							neighborhoodField.appendChild(optionElement);
						});
						neighborhoodField.disabled = false;
					} else {
						neighborhoodField.innerHTML = '<option value=""><?php echo esc_js( __( 'Error loading neighborhoods', 'hezarfen-for-woocommerce' ) ); ?></option>';
					}
					isLoadingNeighborhoods = false;
				})
				.catch(error => {
					console.error('Hezarfen Transform: Error loading neighborhoods', error);
					neighborhoodField.innerHTML = '<option value=""><?php echo esc_js( __( 'Error loading neighborhoods', 'hezarfen-for-woocommerce' ) ); ?></option>';
					isLoadingNeighborhoods = false;
				});
			}

			// Start the transformation
			initFieldTransform();
		})();
		</script>
		<?php
	}

	/**
	 * AJAX handler to get districts
	 */
	public function ajax_get_districts() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'hezarfen_transform_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$city_plate_number = sanitize_text_field( $_POST['city_plate_number'] ?? '' );
		
		if ( empty( $city_plate_number ) ) {
			wp_send_json_error( 'City plate number is required' );
		}

		$districts = Mahalle_Local::get_districts( $city_plate_number );
		$options = array(
			array( 'value' => '', 'label' => __( 'Select District', 'hezarfen-for-woocommerce' ) )
		);

		if ( is_array( $districts ) ) {
			foreach ( $districts as $district ) {
				$options[] = array(
					'value' => $district,
					'label' => $district
				);
			}
		}

		wp_send_json_success( $options );
	}

	/**
	 * AJAX handler to get neighborhoods
	 */
	public function ajax_get_neighborhoods() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'hezarfen_transform_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$city_plate_number = sanitize_text_field( $_POST['city_plate_number'] ?? '' );
		$district = sanitize_text_field( $_POST['district'] ?? '' );
		
		if ( empty( $city_plate_number ) || empty( $district ) ) {
			wp_send_json_error( 'City plate number and district are required' );
		}

		$neighborhoods = Mahalle_Local::get_neighborhoods( $city_plate_number, $district, false );
		$options = array(
			array( 'value' => '', 'label' => __( 'Select Neighborhood', 'hezarfen-for-woocommerce' ) )
		);

		if ( is_array( $neighborhoods ) ) {
			foreach ( $neighborhoods as $neighborhood ) {
				$options[] = array(
					'value' => $neighborhood,
					'label' => $neighborhood
				);
			}
		}

		wp_send_json_success( $options );
	}

	/**
	 * Enqueue assets
	 */
	public function enqueue_assets() {
		if ( ! is_checkout() || ! has_block( 'woocommerce/checkout' ) ) {
			return;
		}

		wp_enqueue_style(
			'hezarfen-transform-checkout-block',
			plugins_url( 'assets/css/checkout-block-transform.css', WC_HEZARFEN_FILE ),
			array(),
			WC_HEZARFEN_VERSION
		);
	}
}

new Checkout_Block_Field_Transform();