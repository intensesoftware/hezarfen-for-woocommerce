/**
 * Hezarfen Working Checkout Block Integration
 * 
 * This script handles the dynamic loading of districts and neighborhoods
 * for the working integration that transforms actual WooCommerce fields.
 */

(function($) {
    'use strict';

    // Wait for the checkout block to be fully loaded
    $(document).ready(function() {
        initializeHezarfenWorkingIntegration();
    });

    /**
     * Initialize the working integration
     */
    function initializeHezarfenWorkingIntegration() {
        console.log('Hezarfen Working: Initializing checkout block integration');

        // Monitor for changes in the state/province field
        $(document).on('change', 'select[name*="state"], select[name*="shipping_state"], select[name*="billing_state"]', function() {
            const stateValue = $(this).val();
            const addressType = getAddressTypeFromFieldName($(this).attr('name'));
            
            console.log('Hezarfen Working: State changed to', stateValue, 'for', addressType);
            
            if (stateValue && stateValue.startsWith('TR')) {
                updateDistrictOptions(stateValue, addressType);
            }
        });

        // Monitor for changes in the district field
        $(document).on('change', 'select[name*="hezarfen/district"], [data-field-id*="hezarfen/district"] select', function() {
            const districtValue = $(this).val();
            const addressType = getAddressTypeFromFieldName($(this).attr('name') || $(this).closest('[data-field-id]').attr('data-field-id'));
            
            console.log('Hezarfen Working: District changed to', districtValue, 'for', addressType);
            
            // Find the state field to get the city plate number
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
            
            console.log('Hezarfen Working: Country changed to', countryValue, 'for', addressType);
            
            toggleTurkishFields(countryValue === 'TR', addressType);
        });

        // Initial check for Turkey
        setTimeout(function() {
            checkInitialCountry();
        }, 1000);
    }

    /**
     * Check initial country selection
     */
    function checkInitialCountry() {
        $('select[name*="country"]').each(function() {
            const countryValue = $(this).val();
            const addressType = getAddressTypeFromFieldName($(this).attr('name'));
            
            if (countryValue === 'TR') {
                console.log('Hezarfen Working: Initial country is Turkey for', addressType);
                toggleTurkishFields(true, addressType);
                
                // Also check if state is already selected
                const stateField = $(`select[name*="${addressType}_state"]`);
                const stateValue = stateField.val();
                if (stateValue && stateValue.startsWith('TR')) {
                    updateDistrictOptions(stateValue, addressType);
                }
            }
        });
    }

    /**
     * Extract address type (billing/shipping) from field name
     */
    function getAddressTypeFromFieldName(fieldName) {
        if (!fieldName) return 'billing';
        
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
        console.log('Hezarfen Working: Updating districts for', stateValue, addressType);
        
        // Find district field
        let districtField = findDistrictField(addressType);
        let neighborhoodField = findNeighborhoodField(addressType);
        
        if (districtField.length === 0) {
            console.log('Hezarfen Working: District field not found for', addressType);
            return;
        }

        // Show loading state
        districtField.prop('disabled', true);
        neighborhoodField.prop('disabled', true);
        
        // Clear existing options
        districtField.empty().append('<option value="">' + 'Loading districts...' + '</option>');
        neighborhoodField.empty().append('<option value="">' + 'Select district first' + '</option>');

        // Make AJAX request to get districts
        $.ajax({
            url: hezarfen_working.ajax_url,
            type: 'POST',
            data: {
                action: 'hezarfen_get_districts_working',
                city_plate_number: stateValue,
                nonce: hezarfen_working.nonce
            },
            success: function(response) {
                console.log('Hezarfen Working: Districts response', response);
                
                if (response.success && response.data) {
                    districtField.empty();
                    
                    response.data.forEach(function(option) {
                        districtField.append(`<option value="${option.value}">${option.label}</option>`);
                    });
                    
                    districtField.prop('disabled', false);
                } else {
                    console.error('Hezarfen Working: Failed to load districts:', response.data);
                    districtField.empty().append('<option value="">' + 'Error loading districts' + '</option>');
                }
            },
            error: function() {
                console.error('Hezarfen Working: AJAX error while loading districts');
                districtField.empty().append('<option value="">' + 'Error loading districts' + '</option>');
            }
        });
    }

    /**
     * Update neighborhood options based on selected state and district
     */
    function updateNeighborhoodOptions(stateValue, districtValue, addressType) {
        console.log('Hezarfen Working: Updating neighborhoods for', stateValue, districtValue, addressType);
        
        let neighborhoodField = findNeighborhoodField(addressType);
        
        if (neighborhoodField.length === 0) {
            console.log('Hezarfen Working: Neighborhood field not found for', addressType);
            return;
        }

        // Show loading state
        neighborhoodField.prop('disabled', true);
        neighborhoodField.empty().append('<option value="">' + 'Loading neighborhoods...' + '</option>');

        // Make AJAX request to get neighborhoods
        $.ajax({
            url: hezarfen_working.ajax_url,
            type: 'POST',
            data: {
                action: 'hezarfen_get_neighborhoods_working',
                city_plate_number: stateValue,
                district: districtValue,
                nonce: hezarfen_working.nonce
            },
            success: function(response) {
                console.log('Hezarfen Working: Neighborhoods response', response);
                
                if (response.success && response.data) {
                    neighborhoodField.empty();
                    
                    response.data.forEach(function(option) {
                        neighborhoodField.append(`<option value="${option.value}">${option.label}</option>`);
                    });
                    
                    neighborhoodField.prop('disabled', false);
                } else {
                    console.error('Hezarfen Working: Failed to load neighborhoods:', response.data);
                    neighborhoodField.empty().append('<option value="">' + 'Error loading neighborhoods' + '</option>');
                }
            },
            error: function() {
                console.error('Hezarfen Working: AJAX error while loading neighborhoods');
                neighborhoodField.empty().append('<option value="">' + 'Error loading neighborhoods' + '</option>');
            }
        });
    }

    /**
     * Find district field
     */
    function findDistrictField(addressType) {
        // Try multiple selectors
        let field = $(`select[name*="${addressType}"][name*="hezarfen/district"]`);
        if (field.length === 0) {
            field = $(`select[name*="hezarfen/district"]`);
        }
        if (field.length === 0) {
            field = $(`[data-field-id*="hezarfen/district"]`).find('select');
        }
        return field;
    }

    /**
     * Find neighborhood field
     */
    function findNeighborhoodField(addressType) {
        // Try multiple selectors
        let field = $(`select[name*="${addressType}"][name*="hezarfen/neighborhood"]`);
        if (field.length === 0) {
            field = $(`select[name*="hezarfen/neighborhood"]`);
        }
        if (field.length === 0) {
            field = $(`[data-field-id*="hezarfen/neighborhood"]`).find('select');
        }
        return field;
    }

    /**
     * Toggle visibility of Turkish-specific fields
     */
    function toggleTurkishFields(isTurkey, addressType) {
        console.log('Hezarfen Working: Toggle Turkish fields', isTurkey, 'for', addressType);
        
        // Find field containers
        let districtContainer = findDistrictField(addressType).closest('.wc-block-components-text-input, .wc-block-components-select-input, [data-field-id]');
        let neighborhoodContainer = findNeighborhoodField(addressType).closest('.wc-block-components-text-input, .wc-block-components-select-input, [data-field-id]');
        
        if (isTurkey) {
            districtContainer.show();
            neighborhoodContainer.show();
            console.log('Hezarfen Working: Showing Turkish fields');
        } else {
            districtContainer.hide();
            neighborhoodContainer.hide();
            console.log('Hezarfen Working: Hiding Turkish fields');
        }
    }

})(jQuery);