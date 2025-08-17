jQuery(document).ready(function($) {
    'use strict';

    // Check if we're on the SMS settings page
    if (typeof hezarfen_sms_settings === 'undefined') {
        return;
    }

    // SMS Rules Management
    let currentRuleIndex = null;
    let smsRules = [];

    // Load existing rules from server
    loadSmsRules();

    // Add SMS Rule button click
    $('#hezarfen-add-sms-rule').on('click', function(e) {
        e.preventDefault();
        openSmsRuleForm();
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

    // Cancel button click
    $('#cancel-sms-rule').on('click', function() {
        closeSmsRuleForm();
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

    function openSmsRuleForm(ruleData = null) {
        // Only reset currentRuleIndex if we're adding a new rule (no ruleData)
        if (!ruleData) {
            currentRuleIndex = null;
        }
        
        // Show the inline form first
        $('#hezarfen-sms-rule-form-container').show();
        
        // Wait a moment for the form to be rendered
        setTimeout(function() {
            const form = document.getElementById('sms-rule-form');
            console.log('Form found:', form ? 'Yes' : 'No');
            console.log('All forms on page:', document.querySelectorAll('form'));
            console.log('Looking for form with ID sms-rule-form:', document.querySelector('#sms-rule-form'));
            console.log('Forms in container:', $('#hezarfen-sms-rule-form-container').find('form'));
            
            if (ruleData) {
                // Edit mode
                $('#sms-rule-form-title').text(hezarfen_sms_settings.strings.edit_rule);
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
                $('#sms-rule-form-title').text(hezarfen_sms_settings.strings.add_rule);
                
                // Reset form safely
                if (form) {
                    form.reset();
                }
                
                $('input[name="iys_status"][value="0"]').prop('checked', true);
                
                // Hide settings sections initially
                $('#netgsm-settings').hide();
                $('#sms-content-settings').hide();
            }
            
            // Scroll to the form
            $('html, body').animate({
                scrollTop: $('#hezarfen-sms-rule-form-container').offset().top - 50
            }, 500);
        }, 100);
    }

    function closeSmsRuleForm() {
        $('#hezarfen-sms-rule-form-container').hide();
        
        // Reset form safely
        const form = document.getElementById('sms-rule-form');
        if (form) {
            form.reset();
        }
        
        // Hide settings sections
        $('#netgsm-settings').hide();
        $('#sms-content-settings').hide();
        
        currentRuleIndex = null;
    }

    function editSmsRule(ruleIndex) {
        if (smsRules[ruleIndex]) {
            currentRuleIndex = ruleIndex;
            openSmsRuleForm(smsRules[ruleIndex]);
        }
    }

    function deleteSmsRule(ruleIndex) {
        smsRules.splice(ruleIndex, 1);
        saveSmsRulesToServer();
    }

    function saveSmsRule() {
        // Check if form container is visible first
        if (!$('#hezarfen-sms-rule-form-container').is(':visible')) {
            alert('Form container is not visible. Please try again.');
            return;
        }

        // Simple validation using jQuery (no need to find form element)
        const conditionStatus = $('#condition-status').val();
        const actionType = $('#action-type').val();
        
        console.log('Validation - Status:', conditionStatus, 'Action:', actionType);
        
        if (!conditionStatus) {
            alert('Please select an order status.');
            $('#condition-status').focus();
            return;
        }
        
        if (!actionType) {
            alert('Please select an action type.');
            $('#action-type').focus();
            return;
        }
        
        if (actionType === 'netgsm') {
            const username = $('#netgsm-username').val();
            const password = $('#netgsm-password').val();
            const msgheader = $('#netgsm-msgheader').val();
            const phoneType = $('#phone-type').val();
            const messageTemplate = $('#message-template').val();
            
            console.log('NetGSM validation - Username:', username, 'Password:', password ? 'Set' : 'Empty', 'Header:', msgheader, 'Phone:', phoneType, 'Message:', messageTemplate ? 'Set' : 'Empty');
            
            if (!username) {
                alert('Please enter NetGSM username.');
                $('#netgsm-username').focus();
                return;
            }
            
            if (!password) {
                alert('Please enter NetGSM password.');
                $('#netgsm-password').focus();
                return;
            }
            
            if (!msgheader) {
                alert('Please enter message header.');
                $('#netgsm-msgheader').focus();
                return;
            }
            
            if (!phoneType) {
                alert('Please select phone type.');
                $('#phone-type').focus();
                return;
            }
            
            if (!messageTemplate) {
                alert('Please enter message template.');
                $('#message-template').focus();
                return;
            }
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

        console.log('Current rule index:', currentRuleIndex);
        console.log('Rule data to save:', ruleData);
        
        if (currentRuleIndex !== null && currentRuleIndex >= 0) {
            // Edit existing rule
            console.log('Editing existing rule at index:', currentRuleIndex);
            smsRules[currentRuleIndex] = ruleData;
        } else {
            // Add new rule
            console.log('Adding new rule');
            smsRules.push(ruleData);
        }

        saveSmsRulesToServer();
        closeSmsRuleForm();
    }

    function saveSmsRulesToServer() {
        console.log('Saving rules to server:', smsRules);
        
        const jsonString = JSON.stringify(smsRules);
        console.log('JSON string:', jsonString);
        console.log('JSON string length:', jsonString.length);
        console.log('JSON string first 100 chars:', jsonString.substring(0, 100));
        
        // Validate JSON on client side too
        try {
            const testParse = JSON.parse(jsonString);
            console.log('Client-side JSON validation: OK');
        } catch (e) {
            console.error('Client-side JSON validation failed:', e);
            alert('Error: Invalid JSON data generated. Please try again.');
            return;
        }
        
        $.ajax({
            url: hezarfen_sms_settings.ajax_url,
            type: 'POST',
            data: {
                action: 'hezarfen_save_sms_rules',
                nonce: hezarfen_sms_settings.nonce,
                rules: jsonString
            },
            success: function(response) {
                console.log('Server response:', response);
                if (response.success) {
                    renderSmsRulesList();
                } else {
                    console.error('Server error:', response);
                    alert('Error saving SMS rules: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', xhr, status, error);
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

    // SMS Variable copy functionality
    $(document).on('click', '.sms-variable', function(e) {
        e.preventDefault();
        const variable = $(this).data('variable');
        const textarea = $('#message-template');
        
        if (variable && textarea.length) {
            // Get current cursor position
            const cursorPos = textarea[0].selectionStart;
            const textBefore = textarea.val().substring(0, cursorPos);
            const textAfter = textarea.val().substring(cursorPos);
            
            // Insert variable at cursor position
            textarea.val(textBefore + variable + textAfter);
            
            // Set cursor position after the inserted variable
            const newCursorPos = cursorPos + variable.length;
            textarea[0].setSelectionRange(newCursorPos, newCursorPos);
            
            // Focus the textarea
            textarea.focus();
            
            // Visual feedback
            $(this).addClass('button-primary');
            setTimeout(() => {
                $(this).removeClass('button-primary');
            }, 200);
        }
    });

    // Add hover effect for variables
    $(document).on('mouseenter', '.sms-variable', function() {
        $(this).css('cursor', 'pointer');
    });
});