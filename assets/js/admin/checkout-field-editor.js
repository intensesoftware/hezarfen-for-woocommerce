jQuery(document).ready(function($) {
    'use strict';

    var CheckoutFieldEditor = {
        init: function() {
            this.bindEvents();
            this.initSortable();
        },

        bindEvents: function() {
            
            // Tab switching
            $('.hezarfen-tab-button').on('click', this.switchTab);

            // Add new field button
            $('#add-new-field').on('click', this.openModal);

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

            // Close modal on outside click
            $(document).on('click', '.hezarfen-modal', function(e) {
                if (e.target === this) {
                    CheckoutFieldEditor.closeModal();
                }
            });
        },

        initSortable: function() {
            // Only custom fields can be reordered
            $('#sortable-custom-fields').sortable({
                handle: '.hezarfen-field-handle',
                placeholder: 'hezarfen-field-placeholder',
                items: '.hezarfen-field-item.is-custom',
                tolerance: 'pointer',
                cursor: 'move',
                opacity: 0.8,
                update: function(event, ui) {
                    CheckoutFieldEditor.reorderFields();
                },
                start: function(event, ui) {
                    ui.placeholder.height(ui.item.outerHeight());
                }
            });
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
            var $optionsRow = $('#field-options-row');
            
            if (fieldType === 'select' || fieldType === 'radio') {
                $optionsRow.show();
            } else {
                $optionsRow.hide();
            }
        },

        saveField: function(e) {
            e.preventDefault();
            
            if (!CheckoutFieldEditor.validateForm()) {
                return;
            }

            var fieldId = $('#field-id').val();
            var $fieldItem = $('.hezarfen-field-item[data-field-id="' + fieldId + '"]');
            var isDefault = $fieldItem.length ? $fieldItem.data('is-default') === 1 : false;

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
                    $('#save-field').prop('disabled', true).text('Saving...');
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
                error: function() {
                    CheckoutFieldEditor.showNotice('error', 'An error occurred while saving the field.');
                },
                complete: function() {
                    $('#save-field').prop('disabled', false).text('Save Field');
                }
            });
        },

        validateForm: function() {
            var isValid = true;
            var requiredFields = ['field-name', 'field-label', 'field-type', 'field-section'];
            
            requiredFields.forEach(function(fieldId) {
                var $field = $('#' + fieldId);
                // Skip validation for disabled fields (default field editing)
                if ($field.prop('disabled')) {
                    $field.removeClass('error');
                    return;
                }
                
                if (!$field.val().trim()) {
                    $field.addClass('error');
                    isValid = false;
                } else {
                    $field.removeClass('error');
                }
            });

            if (!isValid) {
                CheckoutFieldEditor.showNotice('error', 'Please fill in all required fields.');
            }

            return isValid;
        },

        deleteField: function(e) {
            e.preventDefault();
            
            if (!confirm(hezarfen_checkout_field_editor.confirm_delete)) {
                return;
            }

            var fieldId = $(this).data('field-id');
            var $fieldItem = $('.hezarfen-field-item[data-field-id="' + fieldId + '"]');

            $.ajax({
                url: hezarfen_checkout_field_editor.ajax_url,
                type: 'POST',
                data: {
                    action: 'hezarfen_delete_checkout_field',
                    nonce: hezarfen_checkout_field_editor.nonce,
                    field_id: fieldId
                },
                beforeSend: function() {
                    $fieldItem.addClass('deleting');
                },
                success: function(response) {
                    if (response.success) {
                        $fieldItem.fadeOut(300, function() {
                            $(this).remove();
                        });
                        CheckoutFieldEditor.showNotice('success', response.data.message);
                    } else {
                        CheckoutFieldEditor.showNotice('error', response.data.message || 'An error occurred.');
                        $fieldItem.removeClass('deleting');
                    }
                },
                error: function() {
                    CheckoutFieldEditor.showNotice('error', 'An error occurred while deleting the field.');
                    $fieldItem.removeClass('deleting');
                }
            });
        },

        resetField: function(e) {
            e.preventDefault();
            
            if (!confirm(hezarfen_checkout_field_editor.confirm_reset)) {
                return;
            }

            var fieldId = $(this).data('field-id');
            var $fieldItem = $('.hezarfen-field-item[data-field-id="' + fieldId + '"]');

            $.ajax({
                url: hezarfen_checkout_field_editor.ajax_url,
                type: 'POST',
                data: {
                    action: 'hezarfen_reset_checkout_field',
                    nonce: hezarfen_checkout_field_editor.nonce,
                    field_id: fieldId
                },
                beforeSend: function() {
                    $fieldItem.addClass('resetting');
                },
                success: function(response) {
                    if (response.success) {
                        CheckoutFieldEditor.showNotice('success', response.data.message);
                        location.reload(); // Reload to show reset field
                    } else {
                        CheckoutFieldEditor.showNotice('error', response.data.message || 'An error occurred.');
                        $fieldItem.removeClass('resetting');
                    }
                },
                error: function() {
                    CheckoutFieldEditor.showNotice('error', 'An error occurred while resetting the field.');
                    $fieldItem.removeClass('resetting');
                }
            });
        },

        reorderFields: function() {
            var fieldOrder = [];
            // Only reorder custom fields
            $('#sortable-custom-fields .hezarfen-field-item.is-custom').each(function() {
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
            var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.hezarfen-field-editor-header').after($notice);
            
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
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