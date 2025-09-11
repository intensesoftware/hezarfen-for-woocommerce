jQuery(document).ready(function($) {
    'use strict';

    var CheckoutFieldEditor = {
        init: function() {
            this.bindEvents();
            this.initSortable();
            this.initFilters();
            this.initSearch();
        },

        bindEvents: function() {
            // Add new field buttons
            $('#add-new-field, #add-first-field').on('click', this.openModal);

            // Edit field button
            $(document).on('click', '.edit-field', this.editField);

            // Delete field button
            $(document).on('click', '.delete-field', this.deleteField);

            // Reset field button
            $(document).on('click', '.reset-field', this.resetField);

            // Export/Import buttons
            $('#export-fields').on('click', this.exportFields);
            $('#import-fields').on('click', this.openImportModal);
            $('#process-import').on('click', this.importFields);

            // Modal events
            $('.hezarfen-modal-close, #cancel-field, #cancel-import').on('click', this.closeModal);
            $('#save-field').on('click', this.saveField);

            // Field type change
            $('#field-type').on('change', this.handleFieldTypeChange);

            // Section filters
            $('.hezarfen-section-filter').on('click', this.filterBySection);
            $('.hezarfen-type-filter').on('click', this.filterByType);

            // View toggle
            $('.hezarfen-view-btn').on('click', this.toggleView);

            // Section toggle
            $(document).on('click', '.hezarfen-section-toggle', this.toggleSection);

            // Advanced settings toggle
            $(document).on('click', '.hezarfen-form-section-toggle', this.toggleFormSection);

            // Search functionality
            $('#hezarfen-field-search').on('input', this.handleSearch);

            // Close modal on outside click
            $(document).on('click', '.hezarfen-modal', function(e) {
                if (e.target === this) {
                    CheckoutFieldEditor.closeModal();
                }
            });
        },

        initSortable: function() {
            // Initialize sortable for each section
            $('.hezarfen-section-fields').each(function() {
                var $section = $(this);
                $section.sortable({
                    handle: '.hezarfen-field-drag-handle',
                    placeholder: 'hezarfen-field-placeholder',
                    items: '.hezarfen-field-card.is-custom',
                    tolerance: 'pointer',
                    cursor: 'move',
                    opacity: 0.8,
                    update: function(event, ui) {
                        CheckoutFieldEditor.reorderFields();
                    },
                    start: function(event, ui) {
                        ui.placeholder.height(ui.item.outerHeight());
                        ui.item.addClass('dragging');
                    },
                    stop: function(event, ui) {
                        ui.item.removeClass('dragging');
                    }
                });
            });
        },

        initFilters: function() {
            this.currentFilters = {
                section: 'all',
                type: 'all'
            };
        },

        initSearch: function() {
            this.searchTerm = '';
        },

        filterBySection: function(e) {
            e.preventDefault();
            var section = $(this).data('section');
            
            $('.hezarfen-section-filter').removeClass('active');
            $(this).addClass('active');
            
            CheckoutFieldEditor.currentFilters.section = section;
            CheckoutFieldEditor.applyFilters();
        },

        filterByType: function(e) {
            e.preventDefault();
            var type = $(this).data('type');
            
            $('.hezarfen-type-filter').removeClass('active');
            $(this).addClass('active');
            
            CheckoutFieldEditor.currentFilters.type = type;
            CheckoutFieldEditor.applyFilters();
        },

        applyFilters: function() {
            var section = this.currentFilters.section;
            var type = this.currentFilters.type;
            var search = this.searchTerm.toLowerCase();

            $('.hezarfen-section-group').each(function() {
                var $group = $(this);
                var groupSection = $group.data('section');
                var hasVisibleCards = false;

                $group.find('.hezarfen-field-card').each(function() {
                    var $card = $(this);
                    var cardSection = $card.data('section');
                    var cardType = $card.data('type');
                    var cardText = $card.text().toLowerCase();

                    var sectionMatch = section === 'all' || cardSection === section;
                    var typeMatch = type === 'all' || cardType === type;
                    var searchMatch = search === '' || cardText.includes(search);

                    if (sectionMatch && typeMatch && searchMatch) {
                        $card.show();
                        hasVisibleCards = true;
                    } else {
                        $card.hide();
                    }
                });

                if (hasVisibleCards) {
                    $group.show();
                } else {
                    $group.hide();
                }
            });
        },

        handleSearch: function(e) {
            CheckoutFieldEditor.searchTerm = $(this).val();
            CheckoutFieldEditor.applyFilters();
        },

        toggleView: function(e) {
            e.preventDefault();
            var view = $(this).data('view');
            
            $('.hezarfen-view-btn').removeClass('active');
            $(this).addClass('active');
            
            var $fieldsList = $('.hezarfen-fields-list');
            $fieldsList.removeClass('hezarfen-grid-view hezarfen-list-view');
            $fieldsList.addClass('hezarfen-' + view + '-view');
        },

        toggleSection: function(e) {
            e.preventDefault();
            var $button = $(this);
            var $section = $button.closest('.hezarfen-section-group');
            var $fields = $section.find('.hezarfen-section-fields');
            
            $fields.slideToggle(300);
            $button.find('svg').toggleClass('rotated');
        },

        toggleFormSection: function(e) {
            e.preventDefault();
            var $section = $(this).closest('.hezarfen-form-section-collapsible');
            var isCollapsed = $section.attr('data-collapsed') === 'true';
            
            $section.attr('data-collapsed', !isCollapsed);
            $section.find('.hezarfen-form-section-content').slideToggle(200);
        },

        switchTab: function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');
            
            // Update tab buttons
            $('.hezarfen-tab-button').removeClass('active');
            $(this).addClass('active');
            
            // Update tab content
            $('.hezarfen-tab-content').removeClass('active');
            $('#' + tab + '-fields-tab').addClass('active');
        },

        openModal: function(e) {
            e.preventDefault();
            CheckoutFieldEditor.resetForm();
            $('#modal-title').text(hezarfen_checkout_field_editor.add_field_title || 'Add New Field');
            $('#field-editor-modal').show();
        },

        closeModal: function() {
            $('#field-editor-modal').hide();
            CheckoutFieldEditor.resetForm();
        },

        resetForm: function() {
            var $form = $('#field-editor-form');
            if ($form.length && $form[0].reset) {
                $form[0].reset();
            }
            
            $('#field-id').val('');
            $('#is-default-field').val('0');
            $('#field-enabled').prop('checked', true);
            $('#field-priority').val('10');
            $('#field-options-row').hide();
            
            // Re-enable all fields (in case they were disabled for default field editing)
            $('#field-name').prop('disabled', false);
            $('#field-type').prop('disabled', false);
            $('#field-section').prop('disabled', false);
        },

        editField: function(e) {
            e.preventDefault();
            var fieldId = $(this).data('field-id');
            var fieldData = CheckoutFieldEditor.getFieldData(fieldId);
            
            if (fieldData) {
                CheckoutFieldEditor.populateForm(fieldData);
                $('#modal-title').text(hezarfen_checkout_field_editor.edit_field_title || 'Edit Field');
                $('#field-editor-modal').show();
            }
        },

        getFieldData: function(fieldId) {
            // Check if it's a default field first
            if (hezarfen_checkout_field_editor.default_fields_data && hezarfen_checkout_field_editor.default_fields_data[fieldId]) {
                var fieldData = hezarfen_checkout_field_editor.default_fields_data[fieldId];
                return {
                    id: fieldId,
                    name: fieldData.name || '',
                    label: fieldData.label || '',
                    type: fieldData.type || '',
                    section: fieldData.section || '',
                    placeholder: fieldData.placeholder || '',
                    options: fieldData.options || '',
                    required: fieldData.required || false,
                    enabled: fieldData.enabled !== false,
                    priority: fieldData.priority || 10,
                    show_for_countries: fieldData.show_for_countries || [],
                    is_default: true
                };
            }
            
            // Check custom fields
            if (hezarfen_checkout_field_editor.custom_fields_data && hezarfen_checkout_field_editor.custom_fields_data[fieldId]) {
                var fieldData = hezarfen_checkout_field_editor.custom_fields_data[fieldId];
                return {
                    id: fieldId,
                    name: fieldData.name || '',
                    label: fieldData.label || '',
                    type: fieldData.type || '',
                    section: fieldData.section || '',
                    placeholder: fieldData.placeholder || '',
                    options: fieldData.options || '',
                    required: fieldData.required || false,
                    enabled: fieldData.enabled !== false,
                    priority: fieldData.priority || 10,
                    show_for_countries: fieldData.show_for_countries || [],
                    is_default: false
                };
            }
            return null;
        },

        populateForm: function(fieldData) {
            $('#field-id').val(fieldData.id || '');
            $('#field-name').val(fieldData.name || '');
            $('#field-label').val(fieldData.label || '');
            $('#field-type').val(fieldData.type || '');
            $('#field-section').val(fieldData.section || '');
            $('#field-placeholder').val(fieldData.placeholder || '');
            $('#field-options').val(fieldData.options || '');
            $('#field-required').prop('checked', fieldData.required || false);
            $('#field-enabled').prop('checked', fieldData.enabled !== false);
            $('#field-priority').val(fieldData.priority || '10');
            $('#is-default-field').val(fieldData.is_default ? '1' : '0');
            
            // Handle countries selection
            $('#field-show-for-countries').val(fieldData.show_for_countries || []);
            
            // Disable certain fields for default fields
            if (fieldData.is_default) {
                $('#field-name').prop('disabled', true);
                $('#field-type').prop('disabled', true);
                $('#field-section').prop('disabled', true);
            } else {
                $('#field-name').prop('disabled', false);
                $('#field-type').prop('disabled', false);
                $('#field-section').prop('disabled', false);
            }
            
            CheckoutFieldEditor.handleFieldTypeChange();
        },

        handleFieldTypeChange: function() {
            var fieldType = $('#field-type').val();
            var $optionsSection = $('#field-options-section');
            
            if (fieldType === 'select' || fieldType === 'radio') {
                $optionsSection.show();
            } else {
                $optionsSection.hide();
            }
        },

        saveField: function(e) {
            e.preventDefault();
            
            if (!CheckoutFieldEditor.validateForm()) {
                return;
            }

            var fieldId = $('#field-id').val();
            var $fieldCard = $('.hezarfen-field-card[data-field-id="' + fieldId + '"]');
            var isDefault = $fieldCard.length ? $fieldCard.data('is-default') === 1 : false;

            var formData = {
                action: 'hezarfen_save_checkout_field',
                nonce: hezarfen_checkout_field_editor.nonce,
                field_id: fieldId,
                field_name: $('#field-name').val(),
                field_label: $('#field-label').val(),
                field_type: $('#field-type').val(),
                field_section: $('#field-section').val(),
                field_placeholder: $('#field-placeholder').val(),
                field_options: $('#field-options').val(),
                field_required: $('#field-required').is(':checked') ? 1 : 0,
                field_enabled: $('#field-enabled').is(':checked') ? 1 : 0,
                field_priority: $('#field-priority').val(),
                field_show_for_countries: $('#field-show-for-countries').val() || [],
                is_default: isDefault ? 1 : 0
            };

            $.ajax({
                url: hezarfen_checkout_field_editor.ajax_url,
                type: 'POST',
                data: formData,
                beforeSend: function() {
                    var $saveBtn = $('#save-field');
                    $saveBtn.prop('disabled', true).addClass('loading');
                    $saveBtn.find('svg').hide();
                    $saveBtn.append('<span class="loading-text">Saving...</span>');
                },
                success: function(response) {
                    if (response.success) {
                        CheckoutFieldEditor.showNotice('success', response.data.message);
                        CheckoutFieldEditor.closeModal();
                        location.reload(); // Reload to show updated fields
                    } else {
                        CheckoutFieldEditor.showNotice('error', response.data.message || 'An error occurred.');
                    }
                },
                error: function(xhr, status, error) {
                    CheckoutFieldEditor.showNotice('error', 'An error occurred while saving the field.');
                },
                complete: function() {
                    var $saveBtn = $('#save-field');
                    $saveBtn.prop('disabled', false).removeClass('loading');
                    $saveBtn.find('svg').show();
                    $saveBtn.find('.loading-text').remove();
                }
            });
        },

        validateForm: function() {
            var isValid = true;
            var requiredFields = ['field-name', 'field-label', 'field-type', 'field-section'];
            var firstErrorField = null;
            
            // Clear previous errors
            $('.error').removeClass('error');
            
            requiredFields.forEach(function(fieldId) {
                var $field = $('#' + fieldId);
                // Skip validation for disabled fields (default field editing)
                if ($field.prop('disabled')) {
                    $field.removeClass('error');
                    return;
                }
                
                if (!$field.val().trim()) {
                    $field.addClass('error');
                    if (!firstErrorField) {
                        firstErrorField = $field;
                    }
                    isValid = false;
                } else {
                    $field.removeClass('error');
                }
            });

            // Additional validation for field name format
            var fieldName = $('#field-name').val().trim();
            if (fieldName && !/^[a-zA-Z0-9_]+$/.test(fieldName)) {
                $('#field-name').addClass('error');
                CheckoutFieldEditor.showNotice('error', 'Field name can only contain letters, numbers, and underscores.');
                if (!firstErrorField) {
                    firstErrorField = $('#field-name');
                }
                isValid = false;
            }

            if (!isValid) {
                if (firstErrorField) {
                    // Scroll to first error and focus
                    firstErrorField[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstErrorField.focus();
                }
                
                if (!fieldName || !/^[a-zA-Z0-9_]+$/.test(fieldName)) {
                    // Already showed specific message above
                } else {
                    CheckoutFieldEditor.showNotice('error', 'Please fill in all required fields.');
                }
            }

            return isValid;
        },

        deleteField: function(e) {
            e.preventDefault();
            
            if (!confirm(hezarfen_checkout_field_editor.confirm_delete)) {
                return;
            }

            var fieldId = $(this).data('field-id');
            var $fieldCard = $('.hezarfen-field-card[data-field-id="' + fieldId + '"]');

            $.ajax({
                url: hezarfen_checkout_field_editor.ajax_url,
                type: 'POST',
                data: {
                    action: 'hezarfen_delete_checkout_field',
                    nonce: hezarfen_checkout_field_editor.nonce,
                    field_id: fieldId
                },
                beforeSend: function() {
                    $fieldCard.addClass('deleting');
                },
                success: function(response) {
                    if (response.success) {
                        $fieldCard.fadeOut(300, function() {
                            $(this).remove();
                        });
                        CheckoutFieldEditor.showNotice('success', response.data.message);
                    } else {
                        CheckoutFieldEditor.showNotice('error', response.data.message || 'An error occurred.');
                        $fieldCard.removeClass('deleting');
                    }
                },
                error: function() {
                    CheckoutFieldEditor.showNotice('error', 'An error occurred while deleting the field.');
                    $fieldCard.removeClass('deleting');
                }
            });
        },

        resetField: function(e) {
            e.preventDefault();
            
            if (!confirm(hezarfen_checkout_field_editor.confirm_reset)) {
                return;
            }

            var fieldId = $(this).data('field-id');
            var $fieldCard = $('.hezarfen-field-card[data-field-id="' + fieldId + '"]');

            $.ajax({
                url: hezarfen_checkout_field_editor.ajax_url,
                type: 'POST',
                data: {
                    action: 'hezarfen_reset_checkout_field',
                    nonce: hezarfen_checkout_field_editor.nonce,
                    field_id: fieldId
                },
                beforeSend: function() {
                    $fieldCard.addClass('resetting');
                },
                success: function(response) {
                    if (response.success) {
                        CheckoutFieldEditor.showNotice('success', response.data.message);
                        location.reload(); // Reload to show reset field
                    } else {
                        CheckoutFieldEditor.showNotice('error', response.data.message || 'An error occurred.');
                        $fieldCard.removeClass('resetting');
                    }
                },
                error: function() {
                    CheckoutFieldEditor.showNotice('error', 'An error occurred while resetting the field.');
                    $fieldCard.removeClass('resetting');
                }
            });
        },

        reorderFields: function() {
            var fieldOrder = [];
            // Only reorder custom fields across all sections
            $('.hezarfen-field-card.is-custom').each(function() {
                fieldOrder.push($(this).data('field-id'));
            });

            $.ajax({
                url: hezarfen_checkout_field_editor.ajax_url,
                type: 'POST',
                data: {
                    action: 'hezarfen_reorder_checkout_fields',
                    nonce: hezarfen_checkout_field_editor.nonce,
                    field_order: fieldOrder
                },
                success: function(response) {
                    if (response.success) {
                        CheckoutFieldEditor.showNotice('success', 'Field order updated successfully.');
                    }
                }
            });
        },

        showNotice: function(type, message) {
            // Remove existing notifications
            $('.hezarfen-notification').remove();
            
            var noticeClass = type === 'success' ? '' : type;
            var icon = type === 'success' ? 
                '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' :
                type === 'warning' ?
                '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 9V13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 17H12.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10.29 3.86L1.82 18A2 2 0 0 0 3.68 21H20.32A2 2 0 0 0 22.18 18L13.71 3.86A2 2 0 0 0 10.29 3.86Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' :
                '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><line x1="15" y1="9" x2="9" y2="15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="9" y1="9" x2="15" y2="15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            
            var $notification = $('<div class="hezarfen-notification ' + noticeClass + '" style="display: flex; align-items: center; gap: 12px;"><div style="color: currentColor;">' + icon + '</div><div style="flex: 1;">' + message + '</div></div>');
            
            $('body').append($notification);
            
            setTimeout(function() {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 4000);
        },

        exportFields: function(e) {
            e.preventDefault();
            
            var url = hezarfen_checkout_field_editor.ajax_url + '?action=hezarfen_export_checkout_fields&nonce=' + hezarfen_checkout_field_editor.nonce;
            window.open(url, '_blank');
        },

        openImportModal: function(e) {
            e.preventDefault();
            $('#import-modal').show();
        },

        importFields: function(e) {
            e.preventDefault();
            
            var fileInput = $('#import-file')[0];
            if (!fileInput.files.length) {
                CheckoutFieldEditor.showNotice('error', 'Please select a file to import.');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'hezarfen_import_checkout_fields');
            formData.append('nonce', hezarfen_checkout_field_editor.nonce);
            formData.append('import_file', fileInput.files[0]);
            formData.append('replace_mode', $('#import-mode').is(':checked') ? 'true' : 'false');

            $.ajax({
                url: hezarfen_checkout_field_editor.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    $('#process-import').prop('disabled', true).text('Importing...');
                },
                success: function(response) {
                    if (response.success) {
                        CheckoutFieldEditor.showNotice('success', response.data.message);
                        CheckoutFieldEditor.closeModal();
                        location.reload(); // Reload to show imported fields
                    } else {
                        CheckoutFieldEditor.showNotice('error', response.data.message || 'Import failed.');
                    }
                },
                error: function() {
                    CheckoutFieldEditor.showNotice('error', 'An error occurred during import.');
                },
                complete: function() {
                    $('#process-import').prop('disabled', false).text('Import');
                }
            });
        }
    };

    // Initialize the field editor
    CheckoutFieldEditor.init();
});