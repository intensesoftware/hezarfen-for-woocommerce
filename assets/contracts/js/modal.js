/**
 * Hezarfen Modal System - Modern JavaScript Modal
 */

(function($) {
    'use strict';

    // Initialize modals when document is ready
    $(document).ready(function() {
        HezarfenModal.init();
    });

    // Hezarfen Modal Object
    window.HezarfenModal = {
        
        // Initialize modal system
        init: function() {
            this.bindEvents();
            this.handleKeyboard();
        },

        // Bind click events
        bindEvents: function() {
            var self = this;

            // Modal trigger buttons (checkout page)
            $(document).on('click', '.contract-modal-btn', function(e) {
                e.preventDefault();
                var contractId = $(this).data('contract-id');
                var modalId = '#hezarfen-modal-' + contractId;
                self.openModal(modalId);
            });

            // Modal trigger buttons (order details page)
            $(document).on('click', '.hezarfen-contract-btn', function(e) {
                e.preventDefault();
                var contractId = $(this).data('contract-id');
                var modalId = '#hezarfen-contract-modal-' + contractId;
                self.openModal(modalId);
            });

            // Close modal buttons
            $(document).on('click', '.hezarfen-modal-close', function(e) {
                e.preventDefault();
                var modal = $(this).closest('.hezarfen-modal');
                self.closeModal(modal);
            });

            // Close modal when clicking overlay
            $(document).on('click', '.hezarfen-modal-overlay', function(e) {
                var modal = $(this).closest('.hezarfen-modal');
                self.closeModal(modal);
            });

            // Prevent closing when clicking inside modal content
            $(document).on('click', '.hezarfen-modal-container', function(e) {
                e.stopPropagation();
            });
        },

        // Handle keyboard events
        handleKeyboard: function() {
            var self = this;
            
            $(document).on('keydown', function(e) {
                // Close modal on ESC key
                if (e.keyCode === 27) {
                    var activeModal = $('.hezarfen-modal.active');
                    if (activeModal.length) {
                        self.closeModal(activeModal);
                    }
                }
            });
        },

        // Open modal
        openModal: function(modalSelector) {
            var modal = $(modalSelector);
            if (modal.length) {
                // Close any other open modals first
                this.closeAllModals();
                
                // Open the requested modal
                modal.addClass('active');
                $('body').addClass('hezarfen-modal-open');
                
                // Focus management for accessibility
                var closeButton = modal.find('.hezarfen-modal-close').first();
                if (closeButton.length) {
                    closeButton.focus();
                }
            }
        },

        // Close specific modal
        closeModal: function(modal) {
            if (modal && modal.length) {
                modal.removeClass('active');
                
                // Remove body class if no modals are open
                if (!$('.hezarfen-modal.active').length) {
                    $('body').removeClass('hezarfen-modal-open');
                }
            }
        },

        // Close all modals
        closeAllModals: function() {
            $('.hezarfen-modal.active').removeClass('active');
            $('body').removeClass('hezarfen-modal-open');
        }
    };

    // Add CSS to prevent body scroll when modal is open
    var modalStyle = $('<style>');
    modalStyle.text(`
        body.hezarfen-modal-open {
            overflow: hidden;
        }
        
        .hezarfen-modal.active {
            display: flex !important;
        }
    `);
    $('head').append(modalStyle);

})(jQuery);