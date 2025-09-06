/**
 * Contract Management JavaScript
 */
(function($) {
    'use strict';

    var ContractManager = {
        
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '#add-new-contract', this.showAddModal);
            $(document).on('click', '.edit-contract', this.showEditModal);
            $(document).on('click', '.edit-content', this.showContentModal);
            $(document).on('click', '.delete-contract', this.deleteContract);
            $(document).on('click', '.duplicate-contract', this.duplicateContract);
            $(document).on('click', '#save-contract', this.saveContract);
            $(document).on('click', '#save-content', this.saveContent);
            $(document).on('click', '#cancel-contract, #cancel-content, .hezarfen-modal-close', this.hideModal);
            $(document).on('click', '.hezarfen-modal', this.handleModalClick);
            $(document).on('change', '#content-source', this.toggleContentSource);
        },

        showAddModal: function(e) {
            e.preventDefault();
            ContractManager.resetForm();
            $('#modal-title').text(hezarfen_contract_ajax.strings.add_new || 'Add New Contract');
            $('#contract-modal').show();
        },

        showEditModal: function(e) {
            e.preventDefault();
            var contractId = $(this).data('contract-id');
            
            // Load contract data via AJAX
            ContractManager.loadContractData(contractId);
            $('#modal-title').text(hezarfen_contract_ajax.strings.edit_contract || 'Edit Contract');
            $('#contract-modal').show();
        },

        loadContractData: function(contractId) {
            $.ajax({
                url: hezarfen_contract_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'hezarfen_get_contract',
                    nonce: hezarfen_contract_ajax.nonce,
                    contract_id: contractId
                },
                success: function(response) {
                    if (response.success) {
                        ContractManager.populateForm(response.data);
                    } else {
                        ContractManager.showNotice(response.data || 'Failed to load contract data', 'error');
                    }
                },
                error: function() {
                    ContractManager.showNotice('Network error occurred', 'error');
                }
            });
        },

        populateForm: function(contract) {
            $('#contract-id').val(contract.id);
            $('#contract-name').val(contract.name);
            $('#contract-type').val(contract.type);
            $('#custom-label').val(contract.custom_label || '');
            $('#display-order').val(contract.display_order || 999);
            $('#contract-enabled').prop('checked', contract.enabled);
            $('#contract-required').prop('checked', contract.required);
        },

        showContentModal: function(e) {
            e.preventDefault();
            var contractId = $(this).data('contract-id');
            
            // Load contract data and show content editor
            ContractManager.loadContentData(contractId);
            $('#content-modal').show();
        },

        loadContentData: function(contractId) {
            $.ajax({
                url: hezarfen_contract_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'hezarfen_get_contract',
                    nonce: hezarfen_contract_ajax.nonce,
                    contract_id: contractId
                },
                success: function(response) {
                    if (response.success) {
                        $('#content-contract-id').val(response.data.id);
                        $('#content-modal-title').text('Edit Content: ' + response.data.name);
                        
                        // Check if using template or manual content
                        if (response.data.template_id && response.data.template_id > 0) {
                            // Using template
                            $('#content-source').val('template');
                            $('#template-select').val(response.data.template_id);
                            ContractManager.toggleContentSource();
                        } else {
                            // Using manual content
                            $('#content-source').val('manual');
                            ContractManager.toggleContentSource();
                            
                            // Set content in TinyMCE editor
                            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('contract-content-editor')) {
                                tinyMCE.get('contract-content-editor').setContent(response.data.content || '');
                            } else {
                                $('#contract-content-editor').val(response.data.content || '');
                            }
                        }
                    } else {
                        ContractManager.showNotice(response.data || 'Failed to load contract data', 'error');
                    }
                },
                error: function() {
                    ContractManager.showNotice('Network error occurred', 'error');
                }
            });
        },

        resetForm: function() {
            var $form = $('#contract-form');
            if ($form.length && $form[0].reset) {
                $form[0].reset();
            }
            $('#contract-id').val('');
            $('#contract-enabled').prop('checked', true);
            $('#display-order').val('999');
        },

        saveContract: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            // Validate form
            if (!ContractManager.validateForm()) {
                return;
            }

            // Disable button and show loading
            $button.prop('disabled', true).text(hezarfen_contract_ajax.strings.saving);

            var formData = {
                action: 'hezarfen_save_contract',
                nonce: hezarfen_contract_ajax.nonce,
                contract_id: $('#contract-id').val(),
                contract_name: $('#contract-name').val(),
                contract_type: $('#contract-type').val(),
                contract_content: '', // No content in basic form
                custom_label: $('#custom-label').val(),
                display_order: $('#display-order').val(),
                contract_enabled: $('#contract-enabled').is(':checked') ? '1' : '',
                contract_required: $('#contract-required').is(':checked') ? '1' : ''
            };

            $.ajax({
                url: hezarfen_contract_ajax.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        ContractManager.showNotice(response.data.message, 'success');
                        ContractManager.hideModal();
                        // Reload page to show updated list
                        window.location.reload();
                    } else {
                        ContractManager.showNotice(response.data || 'An error occurred', 'error');
                    }
                },
                error: function() {
                    ContractManager.showNotice('Network error occurred', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        saveContent: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            var contentSource = $('#content-source').val();
            var data = {
                action: 'hezarfen_save_content',
                nonce: hezarfen_contract_ajax.nonce,
                contract_id: $('#content-contract-id').val(),
                content_source: contentSource
            };

            if (contentSource === 'template') {
                // Template mode
                var templateId = $('#template-select').val();
                if (!templateId) {
                    ContractManager.showNotice('Please select a template.', 'error');
                    return;
                }
                data.template_id = templateId;
            } else {
                // Manual content mode
                var content = '';
                if (typeof tinyMCE !== 'undefined' && tinyMCE.get('contract-content-editor')) {
                    content = tinyMCE.get('contract-content-editor').getContent();
                } else {
                    content = $('#contract-content-editor').val();
                }
                data.contract_content = content;
            }

            // Disable button and show loading
            $button.prop('disabled', true).text(hezarfen_contract_ajax.strings.saving);

            $.ajax({
                url: hezarfen_contract_ajax.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        ContractManager.showNotice(response.data.message, 'success');
                        ContractManager.hideModal();
                        // Reload page to show updated content preview
                        window.location.reload();
                    } else {
                        ContractManager.showNotice(response.data || 'An error occurred', 'error');
                    }
                },
                error: function() {
                    ContractManager.showNotice('Network error occurred', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        toggleContentSource: function() {
            var contentSource = $('#content-source').val();
            
            if (contentSource === 'template') {
                $('#template-selection-row').show();
                $('#manual-content-row').hide();
            } else {
                $('#template-selection-row').hide();
                $('#manual-content-row').show();
            }
        },

        deleteContract: function(e) {
            e.preventDefault();
            
            if (!confirm(hezarfen_contract_ajax.strings.confirm_delete)) {
                return;
            }

            var $button = $(this);
            var contractId = $button.data('contract-id');
            var originalText = $button.text();

            $button.prop('disabled', true).text(hezarfen_contract_ajax.strings.deleting);

            $.ajax({
                url: hezarfen_contract_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'hezarfen_delete_contract',
                    nonce: hezarfen_contract_ajax.nonce,
                    contract_id: contractId
                },
                success: function(response) {
                    if (response.success) {
                        ContractManager.showNotice(response.data.message, 'success');
                        $button.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                            // Show "no contracts" message if table is empty
                            if ($('.contract-list tbody tr').length === 0) {
                                window.location.reload();
                            }
                        });
                    } else {
                        ContractManager.showNotice(response.data || 'Failed to delete contract', 'error');
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    ContractManager.showNotice('Network error occurred', 'error');
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        duplicateContract: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var contractId = $button.data('contract-id');
            var originalText = $button.text();

            $button.prop('disabled', true).text(hezarfen_contract_ajax.strings.duplicating);

            $.ajax({
                url: hezarfen_contract_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'hezarfen_duplicate_contract',
                    nonce: hezarfen_contract_ajax.nonce,
                    contract_id: contractId
                },
                success: function(response) {
                    if (response.success) {
                        ContractManager.showNotice(response.data.message, 'success');
                        // Reload page to show duplicated contract
                        window.location.reload();
                    } else {
                        ContractManager.showNotice(response.data || 'Failed to duplicate contract', 'error');
                    }
                },
                error: function() {
                    ContractManager.showNotice('Network error occurred', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        validateForm: function() {
            var isValid = true;
            var $form = $('#contract-form');

            // Clear previous errors
            $form.find('.error').removeClass('error');

            // Check required fields
            $form.find('[required]').each(function() {
                if (!$(this).val().trim()) {
                    $(this).addClass('error');
                    isValid = false;
                }
            });

            if (!isValid) {
                ContractManager.showNotice('Please fill in all required fields', 'error');
            }

            return isValid;
        },

        hideModal: function(e) {
            if (e) e.preventDefault();
            $('#contract-modal').hide();
            $('#content-modal').hide();
        },

        handleModalClick: function(e) {
            if (e.target === this) {
                ContractManager.hideModal();
            }
        },

        showNotice: function(message, type) {
            // Remove existing notices
            $('.hezarfen-notice').remove();

            var noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
            var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible hezarfen-notice"><p>' + message + '</p></div>');
            
            $('.hezarfen-contract-management').prepend($notice);

            // Auto-hide success notices
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut();
                }, 3000);
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.hezarfen-contract-management').length) {
            ContractManager.init();
        }
    });

})(jQuery);