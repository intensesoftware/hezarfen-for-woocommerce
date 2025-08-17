jQuery(document).ready(function($) {
    'use strict';

    // SMS Rules Management
    let currentRuleIndex = null;
    let smsRules = [];

    // Load existing rules from server
    loadSmsRules();

    // Add SMS Rule button click
    $('#hezarfen-add-sms-rule').on('click', function() {
        openSmsRuleModal();
    });

    // Edit SMS Rule button click
    $(document).on('click', '.edit-sms-rule', function() {
        const ruleIndex = $(this).data('rule-index');
        editSmsRule(ruleIndex);
    });

    // Delete SMS Rule button click
    $(document).on('click', '.delete-sms-rule', function() {
        const ruleIndex = $(this).data('rule-index');
        if (confirm(hezarfen_sms_settings.strings.delete_rule + '?')) {
            deleteSmsRule(ruleIndex);
        }
    });

    // Modal close buttons
    $('.sms-rule-modal-close').on('click', function() {
        closeSmsRuleModal();
    });

    // Modal overlay click to close
    $('.sms-rule-modal-overlay').on('click', function(e) {
        if (e.target === this) {
            closeSmsRuleModal();
        }
    });

    // Save SMS Rule button click
    $('#save-sms-rule').on('click', function() {
        saveSmsRule();
    });

    // Action type change handler
    $(document).on('change', '#action-type', function() {
        const actionType = $(this).val();
        
        if (actionType === 'netgsm') {
            $('#netgsm-settings').show();
            $('#sms-content-settings').show();
            
            // Make NetGSM fields required
            $('#netgsm-username, #netgsm-password, #netgsm-msgheader').attr('required', true);
            $('#phone-type, #message-template').attr('required', true);
        } else {
            $('#netgsm-settings').hide();
            $('#sms-content-settings').hide();
            
            // Remove required attribute
            $('#netgsm-username, #netgsm-password, #netgsm-msgheader').removeAttr('required');
            $('#phone-type, #message-template').removeAttr('required');
        }
    });

    // Functions
    function loadSmsRules() {
        $.ajax({
            url: hezarfen_sms_settings.ajax_url,
            type: 'POST',
            data: {
                action: 'hezarfen_get_sms_rules',
                nonce: hezarfen_sms_settings.nonce
            },
            success: function(response) {
                if (response.success) {
                    smsRules = response.data || [];
                    renderSmsRulesList();
                }
            }
        });
    }

    function openSmsRuleModal(ruleData = null) {
        currentRuleIndex = null;
        
        if (ruleData) {
            // Edit mode
            $('#sms-rule-modal-title').text(hezarfen_sms_settings.strings.edit_rule);
            $('#condition-status').val(ruleData.condition_status || '');
            $('#action-type').val(ruleData.action_type || '');
            
            // Trigger action type change to show/hide relevant fields
            $('#action-type').trigger('change');
            
            // Fill NetGSM fields if available
            $('#netgsm-username').val(ruleData.netgsm_username || '');
            $('#netgsm-password').val(ruleData.netgsm_password || '');
            $('#netgsm-msgheader').val(ruleData.netgsm_msgheader || '');
            
            // Fill SMS content fields
            $('#phone-type').val(ruleData.phone_type || '');
            $('#message-template').val(ruleData.message_template || '');
            $('input[name="iys_status"][value="' + (ruleData.iys_status || '0') + '"]').prop('checked', true);
        } else {
            // Add mode
            $('#sms-rule-modal-title').text(hezarfen_sms_settings.strings.add_rule);
            $('#sms-rule-form')[0].reset();
            $('input[name="iys_status"][value="0"]').prop('checked', true);
            
            // Hide settings sections initially
            $('#netgsm-settings').hide();
            $('#sms-content-settings').hide();
        }

        $('#hezarfen-sms-rule-modal').show();
    }

    function closeSmsRuleModal() {
        $('#hezarfen-sms-rule-modal').hide();
        $('#sms-rule-form')[0].reset();
        currentRuleIndex = null;
    }

    function editSmsRule(ruleIndex) {
        if (smsRules[ruleIndex]) {
            currentRuleIndex = ruleIndex;
            openSmsRuleModal(smsRules[ruleIndex]);
        }
    }

    function deleteSmsRule(ruleIndex) {
        smsRules.splice(ruleIndex, 1);
        saveSmsRulesToServer();
    }

    function saveSmsRule() {
        const form = $('#sms-rule-form')[0];
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const ruleData = {
            condition_status: $('#condition-status').val(),
            action_type: $('#action-type').val(),
            phone_type: $('#phone-type').val(),
            message_template: $('#message-template').val(),
            iys_status: $('input[name="iys_status"]:checked').val()
        };

        // Add NetGSM specific fields if NetGSM is selected
        if ($('#action-type').val() === 'netgsm') {
            ruleData.netgsm_username = $('#netgsm-username').val();
            ruleData.netgsm_password = $('#netgsm-password').val();
            ruleData.netgsm_msgheader = $('#netgsm-msgheader').val();
        }

        if (currentRuleIndex !== null) {
            // Edit existing rule
            smsRules[currentRuleIndex] = ruleData;
        } else {
            // Add new rule
            smsRules.push(ruleData);
        }

        saveSmsRulesToServer();
        closeSmsRuleModal();
    }

    function saveSmsRulesToServer() {
        $.ajax({
            url: hezarfen_sms_settings.ajax_url,
            type: 'POST',
            data: {
                action: 'hezarfen_save_sms_rules',
                nonce: hezarfen_sms_settings.nonce,
                rules: JSON.stringify(smsRules)
            },
            success: function(response) {
                if (response.success) {
                    renderSmsRulesList();
                } else {
                    alert('Error saving SMS rules: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                alert('Error saving SMS rules. Please try again.');
            }
        });
    }

    function renderSmsRulesList() {
        const $rulesList = $('#hezarfen-sms-rules-list');
        
        if (smsRules.length === 0) {
            $rulesList.html('<p>' + 'No SMS rules configured yet.' + '</p>');
            return;
        }

        let html = '';
        smsRules.forEach(function(rule, index) {
            const statusLabel = hezarfen_sms_settings.order_statuses[rule.condition_status] || rule.condition_status;
            const phoneTypeLabel = rule.phone_type === 'billing' ? 
                hezarfen_sms_settings.strings.billing_phone : 
                hezarfen_sms_settings.strings.shipping_phone;
            
            const actionTypeLabel = rule.action_type === 'netgsm' ? 'NetGSM' : rule.action_type;

            html += `
                <div class="sms-rule-item" data-rule-index="${index}" style="border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; border-radius: 4px;">
                    <strong>Rule #${index + 1}:</strong>
                    When order status changes to <strong>${statusLabel}</strong>,
                    send SMS via <strong>${actionTypeLabel}</strong> to <strong>${phoneTypeLabel}</strong>
                    <div style="margin-top: 5px;">
                        <button type="button" class="button button-small edit-sms-rule" data-rule-index="${index}">
                            Edit
                        </button>
                        <button type="button" class="button button-small delete-sms-rule" data-rule-index="${index}">
                            Delete
                        </button>
                    </div>
                </div>
            `;
        });

        $rulesList.html(html);
    }
});