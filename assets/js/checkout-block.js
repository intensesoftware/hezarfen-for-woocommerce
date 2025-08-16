/**
 * Hezarfen Checkout Block Integration
 * 
 * This script handles the dynamic loading of districts and neighborhoods
 * for Turkish addresses in the WooCommerce Checkout Block.
 */

(function($) {
    'use strict';

    // Wait for the checkout block to be fully loaded
    $(document).ready(function() {
        initializeHezarfenCheckoutBlock();
    });

    /**
     * Initialize the Hezarfen checkout block integration
     */
    function initializeHezarfenCheckoutBlock() {
        // Monitor for changes in the state/province field
        $(document).on('change', 'select[name*="state"], select[name*="shipping_state"], select[name*="billing_state"]', function() {
            const stateValue = $(this).val();
            const addressType = getAddressTypeFromFieldName($(this).attr('name'));
            
            if (stateValue && stateValue.startsWith('TR')) {
                updateDistrictOptions(stateValue, addressType);
            }
        });

        // Monitor for changes in the district field
        $(document).on('change', 'select[name*="hezarfen/district"], [data-field-id*="hezarfen/district"] select', function() {
            const districtValue = $(this).val();
            const addressType = getAddressTypeFromFieldName($(this).attr('name'));
            const stateField = $(`select[name*="${addressType}_state"]`);
            const stateValue = stateField.val();
            
            if (stateValue && districtValue) {
                updateNeighborhoodOptions(stateValue, districtValue, addressType);
            }
        });

        // Monitor for country changes to show/hide Turkish fields
        $(document).on('change', 'select[name*="country"], select[name*="shipping_country"], select[name*="billing_country"]', function() {
            const countryValue = $(this).val();
            const addressType = getAddressTypeFromFieldName($(this).attr('name'));
            
            toggleTurkishFields(countryValue === 'TR', addressType);
        });
    }

    /**
     * Extract address type (billing/shipping) from field name
     */
    function getAddressTypeFromFieldName(fieldName) {
        if (fieldName.includes('billing')) {
            return 'billing';
        } else if (fieldName.includes('shipping')) {
            return 'shipping';
        }
        return 'billing'; // default
    }

    /**
     * Update district options based on selected state
     */
    function updateDistrictOptions(stateValue, addressType) {
        // Try multiple selectors to find the district field
        let districtField = $(`select[name*="${addressType}"][name*="hezarfen/district"]`);
        if (districtField.length === 0) {
            districtField = $(`select[name*="hezarfen/district"]`);
        }
        if (districtField.length === 0) {
            districtField = $(`[data-field-id*="hezarfen/district"]`).find('select');
        }
        
        // Try multiple selectors to find the neighborhood field
        let neighborhoodField = $(`select[name*="${addressType}"][name*="hezarfen/neighborhood"]`);
        if (neighborhoodField.length === 0) {
            neighborhoodField = $(`select[name*="hezarfen/neighborhood"]`);
        }
        if (neighborhoodField.length === 0) {
            neighborhoodField = $(`[data-field-id*="hezarfen/neighborhood"]`).find('select');
        }
        
        if (districtField.length === 0) return;

        // Show loading state
        districtField.prop('disabled', true);
        neighborhoodField.prop('disabled', true);
        
        // Clear existing options
        districtField.empty().append('<option value="">' + 'Loading districts...' + '</option>');
        neighborhoodField.empty().append('<option value="">' + 'Select district first' + '</option>');

        // Make AJAX request to get districts
        $.ajax({
            url: hezarfen_checkout_block.ajax_url,
            type: 'POST',
            data: {
                action: 'hezarfen_get_districts_for_checkout_block',
                city_plate_number: stateValue,
                nonce: hezarfen_checkout_block.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    districtField.empty().append('<option value="">' + 'Select a district' + '</option>');
                    
                    response.data.forEach(function(option) {
                        districtField.append(`<option value="${option.value}">${option.label}</option>`);
                    });
                    
                    districtField.prop('disabled', false);
                } else {
                    console.error('Failed to load districts:', response.data);
                    districtField.empty().append('<option value="">' + 'Error loading districts' + '</option>');
                }
            },
            error: function() {
                console.error('AJAX error while loading districts');
                districtField.empty().append('<option value="">' + 'Error loading districts' + '</option>');
            }
        });
    }

    /**
     * Update neighborhood options based on selected state and district
     */
    function updateNeighborhoodOptions(stateValue, districtValue, addressType) {
        // Try multiple selectors to find the neighborhood field
        let neighborhoodField = $(`select[name*="${addressType}"][name*="hezarfen/neighborhood"]`);
        if (neighborhoodField.length === 0) {
            neighborhoodField = $(`select[name*="hezarfen/neighborhood"]`);
        }
        if (neighborhoodField.length === 0) {
            neighborhoodField = $(`[data-field-id*="hezarfen/neighborhood"]`).find('select');
        }
        
        if (neighborhoodField.length === 0) return;

        // Show loading state
        neighborhoodField.prop('disabled', true);
        neighborhoodField.empty().append('<option value="">' + 'Loading neighborhoods...' + '</option>');

        // Make AJAX request to get neighborhoods
        $.ajax({
            url: hezarfen_checkout_block.ajax_url,
            type: 'POST',
            data: {
                action: 'hezarfen_get_neighborhoods_for_checkout_block',
                city_plate_number: stateValue,
                district: districtValue,
                nonce: hezarfen_checkout_block.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    neighborhoodField.empty().append('<option value="">' + 'Select a neighborhood' + '</option>');
                    
                    response.data.forEach(function(option) {
                        neighborhoodField.append(`<option value="${option.value}">${option.label}</option>`);
                    });
                    
                    neighborhoodField.prop('disabled', false);
                } else {
                    console.error('Failed to load neighborhoods:', response.data);
                    neighborhoodField.empty().append('<option value="">' + 'Error loading neighborhoods' + '</option>');
                }
            },
            error: function() {
                console.error('AJAX error while loading neighborhoods');
                neighborhoodField.empty().append('<option value="">' + 'Error loading neighborhoods' + '</option>');
            }
        });
    }

    /**
     * Toggle visibility of Turkish-specific fields
     */
    function toggleTurkishFields(isTurkey, addressType) {
        // Find district field container
        let districtField = $(`select[name*="${addressType}"][name*="hezarfen/district"]`).closest('.wc-block-components-text-input, .wc-block-components-select-input');
        if (districtField.length === 0) {
            districtField = $(`select[name*="hezarfen/district"]`).closest('.wc-block-components-text-input, .wc-block-components-select-input');
        }
        if (districtField.length === 0) {
            districtField = $(`[data-field-id*="hezarfen/district"]`);
        }
        
        // Find neighborhood field container
        let neighborhoodField = $(`select[name*="${addressType}"][name*="hezarfen/neighborhood"]`).closest('.wc-block-components-text-input, .wc-block-components-select-input');
        if (neighborhoodField.length === 0) {
            neighborhoodField = $(`select[name*="hezarfen/neighborhood"]`).closest('.wc-block-components-text-input, .wc-block-components-select-input');
        }
        if (neighborhoodField.length === 0) {
            neighborhoodField = $(`[data-field-id*="hezarfen/neighborhood"]`);
        }
        
        if (isTurkey) {
            districtField.show();
            neighborhoodField.show();
        } else {
            districtField.hide();
            neighborhoodField.hide();
        }
    }

    // Also listen for checkout block specific events if available
    if (window.wp && window.wp.data) {
        const { subscribe, select } = window.wp.data;
        
        // Subscribe to checkout store changes
        let previousCountry = '';
        let previousState = '';
        
        subscribe(() => {
            try {
                const checkoutStore = select('wc/store/checkout');
                if (!checkoutStore) return;
                
                // Try different methods to get billing data
                let billingData = null;
                
                if (typeof checkoutStore.getBillingAddress === 'function') {
                    billingData = checkoutStore.getBillingAddress();
                } else if (typeof checkoutStore.getCustomerData === 'function') {
                    const customerData = checkoutStore.getCustomerData();
                    billingData = customerData ? customerData.billingAddress : null;
                } else if (typeof checkoutStore.getCheckoutData === 'function') {
                    const checkoutData = checkoutStore.getCheckoutData();
                    billingData = checkoutData ? checkoutData.billingAddress : null;
                }
                
                if (!billingData) return;
                
                // Check for country changes
                if (billingData.country !== previousCountry) {
                    previousCountry = billingData.country;
                    toggleTurkishFields(billingData.country === 'TR', 'billing');
                }
                
                // Check for state changes
                if (billingData.state !== previousState) {
                    previousState = billingData.state;
                    if (billingData.country === 'TR' && billingData.state) {
                        updateDistrictOptions(billingData.state, 'billing');
                    }
                }
            } catch (error) {
                console.log('Hezarfen: Error accessing checkout store:', error);
            }
        });
    }

})(jQuery);