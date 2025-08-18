jQuery(document).ready(function($) {
    'use strict';

    // Check if we're on the SMS settings page
    if (typeof hezarfen_sms_settings === 'undefined') {
        return;
    }

    // SMS Rules Management
    let currentRuleIndex = null;
    let smsRules = [];
    let senderLoadTimeout = null;
    let countdownInterval = null;

    	// Load existing rules from server
	loadSmsRules();
	
	// Load NetGSM connection status
	loadNetGsmConnectionStatus();

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
        
        // Hide all settings first
        $('#netgsm-settings').hide();
        $('#netgsm-legacy-settings').hide();
        $('#pandasms-legacy-settings').hide();
        $('#sms-content-settings').hide();
        
        // Remove all required attributes
        $('#netgsm-legacy-phone-type').removeAttr('required');
        $('#phone-type, #message-template').removeAttr('required');
        
        if (actionType === 'netgsm') {
            $('#netgsm-settings').show();
            $('#sms-content-settings').show();
            
            // Only message template and phone type are required (credentials are global)
            $('#phone-type, #message-template').attr('required', true);
        } else if (actionType === 'netgsm_legacy') {
            $('#netgsm-legacy-settings').show();
            // Don't show sms-content-settings for legacy - it has its own phone type handling
            
            // Make NetGSM legacy fields required (message is synced, so only phone type is required)
            $('#netgsm-legacy-phone-type').attr('required', true);
        } else if (actionType === 'pandasms_legacy') {
            $('#pandasms-legacy-settings').show();
            // Don't show sms-content-settings for legacy - message is configured in PandaSMS plugin
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
                
                // Fill NetGSM Legacy fields if available (message is synced, only set phone type)
                $('#netgsm-legacy-phone-type').val(ruleData.phone_type || '');
                
                // PandaSMS Legacy - message is configured in PandaSMS plugin
                
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
            const phoneType = $('#phone-type').val();
            const messageTemplate = $('#message-template').val();
            
            console.log('NetGSM validation - Phone:', phoneType, 'Message:', messageTemplate ? 'Set' : 'Empty');
            
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
        } else if (actionType === 'netgsm_legacy') {
            const phoneType = $('#netgsm-legacy-phone-type').val();
            
            console.log('NetGSM Legacy validation - Phone:', phoneType);
            
            if (!phoneType) {
                alert('Please select phone type.');
                $('#netgsm-legacy-phone-type').focus();
                return;
            }
            
            // No need to validate message since it's synced from legacy settings
        } else if (actionType === 'pandasms_legacy') {
            // No validation needed - message is configured in PandaSMS plugin
        }

        const ruleData = {
            condition_status: $('#condition-status').val(),
            action_type: actionType,
            phone_type: actionType === 'netgsm_legacy' ? $('#netgsm-legacy-phone-type').val() : 
                        actionType === 'pandasms_legacy' ? 'billing' : 
                        $('#phone-type').val(),
            message_template: $('#message-template').val(),
            iys_status: $('input[name="iys_status"]:checked').val()
        };

        // NetGSM credentials are now stored globally, no need to save with individual rules
        if (actionType === 'netgsm_legacy') {
            // Message is synced from legacy settings, no need to store it in rule data
            ruleData.netgsm_legacy_synced = true;
        } else if (actionType === 'pandasms_legacy') {
            // Message is configured in PandaSMS plugin, no need to store it in rule data
            ruleData.pandasms_legacy_synced = true;
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
            
            let actionTypeLabel = rule.action_type;
        if (rule.action_type === 'netgsm') {
            actionTypeLabel = 'NetGSM';
        } else if (rule.action_type === 'netgsm_legacy') {
            actionTypeLabel = 'NetGSM Legacy';
        } else if (rule.action_type === 'pandasms_legacy') {
            actionTypeLabel = 'PandaSMS Legacy';
        }

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

		// Variable group toggle functionality
		$(document).on('click', '.sms-variable-group-title', function(e) {
			e.preventDefault();
			const $title = $(this);
			const $content = $title.next('.sms-variable-group-content');
			const $icon = $title.find('.dashicons');
			
			// Toggle content visibility
			$content.slideToggle(200);
			
			// Toggle icon
			if ($icon.hasClass('dashicons-arrow-down-alt2')) {
				$icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
			} else {
				$icon.removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
			}
		});

	// NetGSM Credentials Modal Handlers
	$(document).on('click', '#netgsm-connect-btn', function() {
		openNetGsmCredentialsModal();
	});

	$(document).on('click', '.netgsm-modal-close, .netgsm-modal-cancel', function() {
		closeNetGsmCredentialsModal();
	});

	$(document).on('click', '#netgsm-save-credentials', function() {
		saveNetGsmCredentials();
	});

	$(document).on('click', '#netgsm-load-senders', function() {
		loadNetGsmSenders();
	});

	// Auto-load senders when username and password are entered (with debounce)
	$(document).on('input', '#netgsm-modal-username, #netgsm-modal-password', function() {
		// Immediate feedback while typing
		updateSenderSelectForTyping();
		
		// Clear previous timeouts
		if (senderLoadTimeout) {
			clearTimeout(senderLoadTimeout);
		}
		if (countdownInterval) {
			clearInterval(countdownInterval);
		}
		
		const username = $('#netgsm-modal-username').val().trim();
		const password = $('#netgsm-modal-password').val().trim();
		
		// Only start countdown if both fields have values
		if (username && password) {
			startLoadCountdown();
		}
		
		// Set new timeout with 1.5 second delay
		senderLoadTimeout = setTimeout(function() {
			checkCredentialsAndEnableSenderLoad();
		}, 1500);
	});

	// Close modal when clicking outside
	$(document).on('click', '#netgsm-credentials-modal', function(e) {
		if (e.target === this) {
			closeNetGsmCredentialsModal();
		}
	});

	// Functions for NetGSM Connection Management
	function loadNetGsmConnectionStatus() {
		$.ajax({
			url: hezarfen_sms_settings.ajax_url,
			type: 'POST',
			data: {
				action: 'hezarfen_get_netgsm_credentials',
				nonce: hezarfen_sms_settings.nonce
			},
			success: function(response) {
				if (response.success) {
					updateNetGsmConnectionUI(response.data.is_connected, response.data.credentials);
				}
			},
			error: function() {
				console.error('Failed to load NetGSM connection status');
			}
		});
	}

	function updateNetGsmConnectionUI(isConnected, credentials) {
		const $statusContainer = $('#netgsm-connection-status');
		
		if (isConnected) {
			$statusContainer.html(`
				<div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: #d1edff; border: 1px solid #0073aa; border-radius: 4px;">
					<div style="display: flex; align-items: center;">
						<svg style="width: 20px; height: 20px; color: #0073aa; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
						</svg>
						<div>
							<strong style="color: #0073aa;">Connected to NetGSM</strong>
							<p style="margin: 0; font-size: 12px; color: #0073aa;">Username: ${credentials.username} | Sender: ${credentials.msgheader}</p>
						</div>
					</div>
					<button type="button" id="netgsm-connect-btn" class="button button-secondary" style="background: #0073aa; color: white; border-color: #0073aa;">
						Change Credentials
					</button>
				</div>
			`);
		} else {
			$statusContainer.html(`
				<div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: #fff2cd; border: 1px solid #f39c12; border-radius: 4px;">
					<div style="display: flex; align-items: center;">
						<svg style="width: 20px; height: 20px; color: #f39c12; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
						</svg>
						<div>
							<strong style="color: #856404;">NetGSM Not Connected</strong>
							<p style="margin: 0; font-size: 12px; color: #856404;">Connect your NetGSM account to enable SMS functionality</p>
						</div>
					</div>
					<button type="button" id="netgsm-connect-btn" class="button button-primary">
						Connect
					</button>
				</div>
			`);
		}
	}

	function openNetGsmCredentialsModal() {
		const $modal = $('#netgsm-credentials-modal');
		const $content = $modal.find('.hez-modal-content');
		
		$modal.removeClass('hidden').css('display', 'flex');
		
		// Animate modal appearance
		setTimeout(function() {
			$content.css({
				'transform': 'scale(1)',
				'opacity': '1'
			});
		}, 10);
		
		// Clear form
		$('#netgsm-credentials-form')[0].reset();
		
		// Reset sender dropdown
		const $senderSelect = $('#netgsm-modal-msgheader');
		$senderSelect.prop('disabled', true).html('<option value="">First enter username and password above</option>');
		$('#netgsm-load-senders').hide();
		
		// Focus first input
		$('#netgsm-modal-username').focus();
	}

	function closeNetGsmCredentialsModal() {
		const $modal = $('#netgsm-credentials-modal');
		const $content = $modal.find('.hez-modal-content');
		
		// Clear any pending timeouts and intervals
		if (senderLoadTimeout) {
			clearTimeout(senderLoadTimeout);
			senderLoadTimeout = null;
		}
		if (countdownInterval) {
			clearInterval(countdownInterval);
			countdownInterval = null;
		}
		
		// Animate modal disappearance
		$content.css({
			'transform': 'scale(0.95)',
			'opacity': '0'
		});
		
		setTimeout(function() {
			$modal.addClass('hidden').css('display', 'none');
		}, 300);
	}

	function saveNetGsmCredentials() {
		const $form = $('#netgsm-credentials-form');
		const $saveBtn = $('#netgsm-save-credentials');
		const originalText = $saveBtn.text();
		
		// Get form data
		const username = $('#netgsm-modal-username').val().trim();
		const password = $('#netgsm-modal-password').val().trim();
		const msgheader = $('#netgsm-modal-msgheader').val().trim();
		
		// Validate
		if (!username || !password) {
			showInlineAlert(hezarfen_sms_settings.strings.credentials_required, 'error');
			return;
		}
		
		if (!msgheader) {
			showInlineAlert(hezarfen_sms_settings.strings.select_message_header, 'error');
			$('#netgsm-modal-msgheader').focus();
			return;
		}
		
		// Show loading state
		$saveBtn.prop('disabled', true).text('Connecting...');
		
		$.ajax({
			url: hezarfen_sms_settings.ajax_url,
			type: 'POST',
			data: {
				action: 'hezarfen_save_netgsm_credentials',
				nonce: hezarfen_sms_settings.nonce,
				username: username,
				password: password,
				msgheader: msgheader
			},
			success: function(response) {
				if (response.success) {
					// Show success message before closing modal
					showInlineAlert(hezarfen_sms_settings.strings.credentials_saved_successfully, 'success');
					
					// Close modal after a brief delay to show the success message
					setTimeout(function() {
						closeNetGsmCredentialsModal();
						
						// Reload connection status
						loadNetGsmConnectionStatus();
					}, 1500);
				} else {
					showInlineAlert('Error: ' + (response.data || hezarfen_sms_settings.strings.failed_to_save_credentials), 'error');
				}
			},
			error: function() {
				showInlineAlert(hezarfen_sms_settings.strings.network_error_saving_credentials, 'error');
			},
			complete: function() {
				$saveBtn.prop('disabled', false).text(originalText);
			}
		});
	}

	function checkCredentialsAndEnableSenderLoad() {
		const username = $('#netgsm-modal-username').val().trim();
		const password = $('#netgsm-modal-password').val().trim();
		const $senderSelect = $('#netgsm-modal-msgheader');
		const $loadButton = $('#netgsm-load-senders');
		
		if (username && password) {
			$senderSelect.prop('disabled', false).html('<option value="">Loading senders automatically...</option>');
			$loadButton.show();
			// Auto-load senders
			loadNetGsmSenders();
		} else {
			$senderSelect.prop('disabled', true).html('<option value="">First enter username and password above</option>');
			$loadButton.hide();
		}
	}

	function updateSenderSelectForTyping() {
		const username = $('#netgsm-modal-username').val().trim();
		const password = $('#netgsm-modal-password').val().trim();
		const $senderSelect = $('#netgsm-modal-msgheader');
		const $loadButton = $('#netgsm-load-senders');
		
		if (username && password) {
			$senderSelect.prop('disabled', true).html('<option value="">Will load senders in 1.5 seconds...</option>');
			$loadButton.show();
		} else if (username || password) {
			$senderSelect.prop('disabled', true).html('<option value="">Enter both username and password</option>');
			$loadButton.hide();
		} else {
			$senderSelect.prop('disabled', true).html('<option value="">First enter username and password above</option>');
			$loadButton.hide();
		}
	}

	function showInlineAlert(message, type = 'info') {
		// Remove any existing alerts
		$('.netgsm-inline-alert').remove();
		
		// Create alert element
		const alertClass = type === 'error' ? 'error' : type === 'success' ? 'success' : 'info';
		const iconSvg = type === 'error' 
			? '<svg style="width: 16px; height: 16px; flex-shrink: 0;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>'
			: type === 'success'
			? '<svg style="width: 16px; height: 16px; flex-shrink: 0;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>'
			: '<svg style="width: 16px; height: 16px; flex-shrink: 0;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>';
		
		const bgColor = type === 'error' ? '#fee2e2' : type === 'success' ? '#d1fae5' : '#dbeafe';
		const textColor = type === 'error' ? '#dc2626' : type === 'success' ? '#059669' : '#2563eb';
		const borderColor = type === 'error' ? '#fca5a5' : type === 'success' ? '#6ee7b7' : '#93c5fd';
		
		const $alert = $(`
			<div class="netgsm-inline-alert" style="
				display: none;
				margin: 10px 0;
				padding: 12px 16px;
				background-color: ${bgColor};
				border: 1px solid ${borderColor};
				border-radius: 6px;
				color: ${textColor};
				font-size: 14px;
				display: flex;
				align-items: center;
				gap: 8px;
				opacity: 0;
				transform: translateY(-10px);
				transition: all 0.3s ease;
			">
				${iconSvg}
				<span>${message}</span>
			</div>
		`);
		
		// Insert alert after the message header field
		$('#netgsm-modal-msgheader').closest('.mb-4').after($alert);
		
		// Animate in
		$alert.show();
		setTimeout(function() {
			$alert.css({
				'opacity': '1',
				'transform': 'translateY(0)'
			});
		}, 10);
		
		// Auto-remove after 5 seconds
		setTimeout(function() {
			$alert.css({
				'opacity': '0',
				'transform': 'translateY(-10px)'
			});
			setTimeout(function() {
				$alert.remove();
			}, 300);
		}, 5000);
	}

	function startLoadCountdown() {
		let countdown = 1.5; // 1.5 seconds
		const $senderSelect = $('#netgsm-modal-msgheader');
		
		// Update immediately
		$senderSelect.html(`<option value="">Loading senders in ${countdown.toFixed(1)}s...</option>`);
		
		countdownInterval = setInterval(function() {
			countdown -= 0.1;
			if (countdown > 0) {
				$senderSelect.html(`<option value="">Loading senders in ${countdown.toFixed(1)}s...</option>`);
			} else {
				clearInterval(countdownInterval);
				countdownInterval = null;
			}
		}, 100); // Update every 100ms for smooth countdown
	}

	function loadNetGsmSenders() {
		const username = $('#netgsm-modal-username').val().trim();
		const password = $('#netgsm-modal-password').val().trim();
		const $senderSelect = $('#netgsm-modal-msgheader');
		const $loadButton = $('#netgsm-load-senders');
		
		if (!username || !password) {
			showInlineAlert(hezarfen_sms_settings.strings.please_enter_credentials, 'error');
			return;
		}
		
		// Show loading state
		$senderSelect.prop('disabled', true).html('<option value="">Loading senders...</option>');
		$loadButton.find('svg').addClass('animate-spin');
		
		$.ajax({
			url: hezarfen_sms_settings.ajax_url,
			type: 'POST',
			data: {
				action: 'hezarfen_get_netgsm_senders',
				nonce: hezarfen_sms_settings.nonce,
				username: username,
				password: password
			},
			success: function(response) {
				if (response.success && response.data.senders) {
					const senders = response.data.senders;
					let options = '<option value="">Select a sender</option>';
					
					senders.forEach(function(sender) {
						options += `<option value="${sender}">${sender}</option>`;
					});
					
					$senderSelect.prop('disabled', false).html(options);
					
					// If there's only one sender, select it automatically
					if (senders.length === 1) {
						$senderSelect.val(senders[0]);
						showInlineAlert(hezarfen_sms_settings.strings.found_sender_single.replace('%s', senders[0]), 'success');
					} else {
						showInlineAlert(hezarfen_sms_settings.strings.found_senders_multiple.replace('%d', senders.length), 'success');
					}
				} else {
					$senderSelect.html('<option value="">Error loading senders</option>');
					showInlineAlert('Error: ' + (response.data || hezarfen_sms_settings.strings.failed_to_load_senders), 'error');
				}
			},
			error: function() {
				$senderSelect.html('<option value="">Error loading senders</option>');
				showInlineAlert(hezarfen_sms_settings.strings.network_error_loading_senders, 'error');
			},
			complete: function() {
				$loadButton.find('svg').removeClass('animate-spin');
			}
		});
	}

	// Auto-save SMS rule when main "Save Changes" button is clicked
	$('form#mainform').on('submit', function(e) {
		// Check if SMS rule form is visible and has data
		if ($('#hezarfen-sms-rule-form-container').is(':visible')) {
			// Check if there's any data in the form that should be saved
			const conditionStatus = $('#condition-status').val();
			const actionType = $('#action-type').val();
			
			if (conditionStatus && actionType) {
				// Prevent the main form from submitting immediately
				e.preventDefault();
				
				// Show a brief message to user
				const $submitButton = $(this).find('input[type="submit"], button[type="submit"]');
				const originalText = $submitButton.val() || $submitButton.text();
				$submitButton.prop('disabled', true);
				if ($submitButton.is('input')) {
					$submitButton.val(hezarfen_sms_settings.strings.saving_rule || 'Saving rule...');
				} else {
					$submitButton.text(hezarfen_sms_settings.strings.saving_rule || 'Saving rule...');
				}
				
				// Save the SMS rule first
				saveSmsRule();
				
				// Wait a moment for the AJAX to complete, then submit the main form
				setTimeout(function() {
					// Restore button state
					$submitButton.prop('disabled', false);
					if ($submitButton.is('input')) {
						$submitButton.val(originalText);
					} else {
						$submitButton.text(originalText);
					}
					
					// Submit the main form
					$('form#mainform').off('submit').submit();
				}, 1000);
			}
		}
	});
});