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
            $(document).on('click', '.delete-contract', this.deleteContract);
            $(document).on('click', '.duplicate-contract', this.duplicateContract);
            $(document).on('click', '#save-contract', this.saveContract);
            $(document).on('click', '#cancel-contract, .hezarfen-modal-close', this.hideModal);
            $(document).on('click', '.hezarfen-modal', this.handleModalClick);
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
            var $row = $('tr[data-contract-id="' + contractId + '"]');
            
            // Populate form with existing data
            ContractManager.populateForm($row, contractId);
            $('#modal-title').text(hezarfen_contract_ajax.strings.edit_contract || 'Edit Contract');
            $('#contract-modal').show();
        },

        populateForm: function($row, contractId) {
            // This is a simplified version - in a real implementation,
            // you'd make an AJAX call to get the full contract data
            $('#contract-id').val(contractId);
            
            // Extract data from the row (this is basic - you might want to store data attributes)
            var name = $row.find('td:first strong').text();
            $('#contract-name').val(name);
            
            // You would populate other fields based on stored data or AJAX response
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
                contract_template: $('#contract-template').val(),
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