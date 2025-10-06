/**
 * Hezarfen Pro Deactivation Wizard
 * 
 * Handles the deactivation modal and order migration process.
 */
(function($) {
    'use strict';

    let deactivateLink = '';

    // Use delegated event handling on document to catch clicks early
    $(document).on('click', 'tr[data-slug="hezarfen-for-woocommerce"] .deactivate a, a[href*="hezarfen-for-woocommerce"][href*="action=deactivate"]', function(e) {
        console.log('Deactivate link clicked! (delegated handler)');
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        deactivateLink = $(this).attr('href');
        console.log('Deactivate URL:', deactivateLink);
        
        // Show the modal
        $('#hez-pro-deactivation-modal').fadeIn(300);
        $('body').addClass('hez-pro-modal-open');
        
        return false;
    });

    $(document).ready(function() {
        console.log('Deactivation wizard: Script loaded');
        console.log('Looking for plugin slug:', hezProDeactivation.pluginSlug);
        
        // Find the deactivate link for our plugin - try both with and without directory
        let $deactivateLink = $('tr[data-slug="hezarfen-for-woocommerce"] .deactivate a');
        
        // If not found, try with full path
        if ($deactivateLink.length === 0) {
            $deactivateLink = $('a[href*="hezarfen-for-woocommerce"][href*="action=deactivate"]');
        }
        
        console.log('Deactivate link found:', $deactivateLink.length);
        
        if ($deactivateLink.length === 0) {
            console.warn('Deactivation wizard: Could not find deactivate link');
            return;
        }

        console.log('Deactivation wizard: Found deactivate link');
        console.log('Deactivate link element:', $deactivateLink[0]);
        console.log('Deactivate link href:', $deactivateLink.attr('href'));
        
        // Store the original deactivate URL
        deactivateLink = $deactivateLink.attr('href');
        
        // Add a class to identify our link
        $deactivateLink.addClass('hez-pro-deactivate-link');

        // Handle button clicks
        $('#hez-pro-deactivation-modal').on('click', 'button', function(e) {
            e.preventDefault();
            
            const action = $(this).data('action');
            
            switch(action) {
                case 'move-and-deactivate':
                    handleMoveAndDeactivate();
                    break;
                case 'deactivate-only':
                    handleDeactivateOnly();
                    break;
                case 'cancel':
                    handleCancel();
                    break;
            }
        });

        // Close modal when clicking overlay
        $('.hez-pro-modal-overlay').on('click', function() {
            handleCancel();
        });

        // Close modal on ESC key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('#hez-pro-deactivation-modal').is(':visible')) {
                handleCancel();
            }
        });
    });

    /**
     * Handle "Move and Deactivate" action
     */
    function handleMoveAndDeactivate() {
        // Disable buttons and show processing message
        disableButtons();
        showProcessingMessage();

        // Send AJAX request to move orders
        $.ajax({
            url: hezProDeactivation.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hez_pro_move_shipped_to_processing',
                nonce: hezProDeactivation.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Show success message briefly
                    showSuccessMessage(response.data.message);
                    
                    // Wait a moment then deactivate
                    setTimeout(function() {
                        window.location.href = deactivateLink;
                    }, 1500);
                } else {
                    showErrorMessage(response.data.message);
                    enableButtons();
                    hideProcessingMessage();
                }
            },
            error: function(xhr, status, error) {
                showErrorMessage('Bir hata oluştu: ' + error);
                enableButtons();
                hideProcessingMessage();
            }
        });
    }

    /**
     * Handle "Deactivate Only" action
     */
    function handleDeactivateOnly() {
        // Directly go to deactivation
        window.location.href = deactivateLink;
    }

    /**
     * Handle "Cancel" action
     */
    function handleCancel() {
        $('#hez-pro-deactivation-modal').fadeOut(300);
        $('body').removeClass('hez-pro-modal-open');
        enableButtons();
        hideProcessingMessage();
        hideMessages();
    }

    /**
     * Disable all modal buttons
     */
    function disableButtons() {
        $('#hez-pro-deactivation-modal button').prop('disabled', true).addClass('disabled');
    }

    /**
     * Enable all modal buttons
     */
    function enableButtons() {
        $('#hez-pro-deactivation-modal button').prop('disabled', false).removeClass('disabled');
    }

    /**
     * Show processing message
     */
    function showProcessingMessage() {
        $('.hez-pro-processing-message').slideDown(200);
    }

    /**
     * Hide processing message
     */
    function hideProcessingMessage() {
        $('.hez-pro-processing-message').slideUp(200);
    }

    /**
     * Show success message
     */
    function showSuccessMessage(message) {
        hideMessages();
        const $success = $('<div class="hez-pro-message hez-pro-success">' + message + '</div>');
        $('.hez-pro-modal-body').append($success);
        $success.slideDown(200);
    }

    /**
     * Show error message
     */
    function showErrorMessage(message) {
        hideMessages();
        const $error = $('<div class="hez-pro-message hez-pro-error">' + message + '</div>');
        $('.hez-pro-modal-body').append($error);
        $error.slideDown(200);
    }

    /**
     * Hide all messages
     */
    function hideMessages() {
        $('.hez-pro-message').remove();
    }

})(jQuery);