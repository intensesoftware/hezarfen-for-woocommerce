/**
 * Hezarfen Order Tracking JavaScript
 * Modern, elegant tracking functionality with smooth animations
 */

(function($) {
    'use strict';

    class HezarfenOrderTracking {
        constructor() {
            this.form = $('#hezarfen-tracking-form');
            this.results = $('#hezarfen-tracking-results');
            this.isLoggedIn = $('.hezarfen-order-card').length > 0;
            
            // Set up button references for guest form
            this.button = this.form.find('.hezarfen-tracking-button');
            this.buttonText = this.button.find('.hezarfen-button-text');
            this.buttonSpinner = this.button.find('.hezarfen-button-spinner');
            
            this.init();
        }

        init() {
            this.bindEvents();
            this.setupFormValidation();
        }

        bindEvents() {
            // Guest form submission
            this.form.on('submit', (e) => {
                e.preventDefault();
                this.handleGuestSubmit();
            });

            // Order card click handling
            $(document).on('click', '.hezarfen-order-card', (e) => {
                const $card = $(e.currentTarget);
                const orderId = $card.data('order-id');
                
                // Store reference to clicked card
                this.lastClickedCard = $card;
                
                // Remove active state from other cards and existing details
                $('.hezarfen-order-card').removeClass('active');
                $('.hezarfen-order-details-expanded').remove();
                
                // Add active state to clicked card
                $card.addClass('active');
                
                // Immediately show skeleton loading state
                this.showSkeletonDetails($card);
                
                // Add loading state to card
                this.setCardLoadingState($card, true);
                
                // Track the order
                this.handleUserOrderClick(orderId);
            });

            // Input validation on blur
            $(document).on('blur', '.hezarfen-form-input', (e) => {
                this.validateField($(e.target));
            });

            // Real-time validation
            $(document).on('input', '.hezarfen-form-input', (e) => {
                this.clearFieldError($(e.target));
            });

            // Enter key handling
            $(document).on('keypress', '.hezarfen-form-input', (e) => {
                if (e.which === 13) {
                    e.preventDefault();
                    this.handleGuestSubmit();
                }
            });

            // Order filter dropdown handling
            $(document).on('change', '#hezarfen-order-period-filter', (e) => {
                this.handleOrderFilter($(e.target).val());
            });
        }

        setupFormValidation() {
            // Add custom validation styles
            this.form.find('input[required]').each((index, element) => {
                const $input = $(element);
                const $group = $input.closest('.hezarfen-form-group');
                
                // Add required indicator if not present
                if (!$group.find('.required-indicator').length) {
                    $group.find('.hezarfen-form-label').append(' <span class="required-indicator" style="color: #ef4444;">*</span>');
                }
            });
        }

        validateField($field) {
            const value = $field.val().trim();
            const fieldType = $field.attr('type');
            const fieldName = $field.attr('name');
            const fieldTag = $field.prop('tagName').toLowerCase();
            let isValid = true;
            let errorMessage = '';

            // Required field validation
            if ($field.prop('required') && !value) {
                isValid = false;
                errorMessage = 'This field is required.';
            }

            // Select field validation
            if (fieldTag === 'select' && fieldName === 'order_id' && !value) {
                isValid = false;
                errorMessage = 'Please select an order to track.';
            }

            // Email validation
            if (fieldType === 'email' && value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    isValid = false;
                    errorMessage = 'Please enter a valid email address.';
                }
            }

            // Order number validation
            if (fieldName === 'order_number' && value) {
                // Remove # if present for validation
                const cleanOrderNumber = value.replace(/^#/, '');
                if (!/^\d+$/.test(cleanOrderNumber)) {
                    isValid = false;
                    errorMessage = 'Please enter a valid order number.';
                }
            }

            if (!isValid) {
                this.showFieldError($field, errorMessage);
            } else {
                this.clearFieldError($field);
            }

            return isValid;
        }

        showFieldError($field, message) {
            const $group = $field.closest('.hezarfen-form-group');
            
            // Remove existing error
            $group.find('.field-error').remove();
            
            // Add error styling
            $field.addClass('error').css({
                'border-color': '#ef4444',
                'box-shadow': '0 0 0 3px rgba(239, 68, 68, 0.1)'
            });

            // Add error message
            $group.append(`<div class="field-error" style="color: #ef4444; font-size: 0.875rem; margin-top: 0.25rem;">${message}</div>`);
        }

        clearFieldError($field) {
            const $group = $field.closest('.hezarfen-form-group');
            
            // Remove error styling
            $field.removeClass('error').css({
                'border-color': '',
                'box-shadow': ''
            });

            // Remove error message
            $group.find('.field-error').remove();
        }

        validateForm($form) {
            let isValid = true;
            
            $form.find('input[required], select[required]').each((index, element) => {
                if (!this.validateField($(element))) {
                    isValid = false;
                }
            });

            return isValid;
        }

        async handleGuestSubmit() {
            // Validate form
            if (!this.validateForm(this.form)) {
                this.shakeForm(this.form);
                return;
            }

            // Show loading state
            this.setLoadingState(true);

            // Collect form data
            const formData = {
                action: 'hezarfen_track_order',
                nonce: hezarfen_tracking_ajax.nonce,
                order_number: this.form.find('#order_number').val().trim(),
                billing_email: this.form.find('#billing_email').val().trim()
            };

            try {
                const response = await this.makeRequest(formData);
                
                if (response.success) {
                    this.showResults(response.data.html);
                } else {
                    this.showError(response.data.message || hezarfen_tracking_ajax.strings.error);
                }
            } catch (error) {
                console.error('Order tracking error:', error);
                this.showError(hezarfen_tracking_ajax.strings.error);
            } finally {
                this.setLoadingState(false);
            }
        }

        async handleUserOrderClick(orderId) {
            // Collect form data
            const formData = {
                action: 'hezarfen_track_user_order',
                nonce: hezarfen_tracking_ajax.nonce,
                order_id: orderId
            };

            try {
                const response = await this.makeRequest(formData);
                
                if (response.success) {
                    this.showResults(response.data.html);
                } else {
                    this.showError(response.data.message || hezarfen_tracking_ajax.strings.error);
                }
            } catch (error) {
                console.error('User order tracking error:', error);
                this.showError(hezarfen_tracking_ajax.strings.error);
            } finally {
                // Remove loading state from all cards
                $('.hezarfen-order-card').removeClass('loading');
            }
        }

        makeRequest(data) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: hezarfen_tracking_ajax.ajax_url,
                    type: 'POST',
                    data: data,
                    timeout: 30000, // 30 seconds timeout
                    success: (response) => {
                        resolve(response);
                    },
                    error: (xhr, status, error) => {
                        reject(new Error(`Request failed: ${status} - ${error}`));
                    }
                });
            });
        }

        setLoadingState(loading) {
            if (loading) {
                this.form.addClass('loading');
                this.button.prop('disabled', true);
                this.buttonText.hide();
                this.buttonSpinner.show();
                
                // Update button text
                this.button.attr('aria-label', hezarfen_tracking_ajax.strings.searching);
            } else {
                this.form.removeClass('loading');
                this.button.prop('disabled', false);
                this.buttonText.show();
                this.buttonSpinner.hide();
                
                // Restore button text
                this.button.removeAttr('aria-label');
            }
        }

        showResults(html) {
            // Hide any existing errors
            this.hideError();
            
            // For logged-in users, replace skeleton with actual content
            if (this.isLoggedIn && this.lastClickedCard) {
                const $existingDetails = $('.hezarfen-order-details-expanded');
                
                if ($existingDetails.length) {
                    // Replace skeleton content with actual content
                    $existingDetails.html(html);
                    
                    // Ensure the selected card is still visible after content loads
                    setTimeout(() => {
                        this.ensureCardVisibility(this.lastClickedCard, $existingDetails);
                    }, 100);
                } else {
                    // Fallback: create new details container
                    const $expandedDetails = $('<div class="hezarfen-order-details-expanded">' + html + '</div>');
                    this.lastClickedCard.after($expandedDetails);
                    $expandedDetails.slideDown(400, () => {
                        this.ensureCardVisibility(this.lastClickedCard, $expandedDetails);
                    });
                }
            } else {
                // For guest users, show in the results container
                this.results.html(html).slideDown(400, () => {
                    this.scrollToResults();
                    this.results.find('button, a').first().focus();
                });
            }
        }

        showSkeletonDetails($card) {
            // Create skeleton loading content
            const skeletonHtml = `
                <div class="hezarfen-tracking-details-panel">
                    <div class="hezarfen-skeleton-container">
                        <div class="hezarfen-skeleton-header">
                            <div class="hezarfen-skeleton-line skeleton-title"></div>
                        </div>
                        <div class="hezarfen-skeleton-progress">
                            <div class="hezarfen-skeleton-steps">
                                <div class="hezarfen-skeleton-step">
                                    <div class="hezarfen-skeleton-dot"></div>
                                    <div class="hezarfen-skeleton-line skeleton-text"></div>
                                </div>
                                <div class="hezarfen-skeleton-step">
                                    <div class="hezarfen-skeleton-dot"></div>
                                    <div class="hezarfen-skeleton-line skeleton-text"></div>
                                </div>
                                <div class="hezarfen-skeleton-step">
                                    <div class="hezarfen-skeleton-dot"></div>
                                    <div class="hezarfen-skeleton-line skeleton-text"></div>
                                </div>
                                <div class="hezarfen-skeleton-step">
                                    <div class="hezarfen-skeleton-dot"></div>
                                    <div class="hezarfen-skeleton-line skeleton-text"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Create expanded details container with skeleton
            const $expandedDetails = $('<div class="hezarfen-order-details-expanded">' + skeletonHtml + '</div>');
            
            // Insert after the clicked card
            $card.after($expandedDetails);
            
            // Animate the expansion
            $expandedDetails.slideDown(300, () => {
                // Calculate scroll position to show both card and details
                const cardTop = $card.offset().top;
                const detailsBottom = $expandedDetails.offset().top + $expandedDetails.outerHeight();
                const viewportHeight = $(window).height();
                const headerOffset = 80; // Account for any fixed headers
                
                // If details extend beyond viewport, scroll to show the card at top
                if (detailsBottom - cardTop > viewportHeight - headerOffset) {
                    $('html, body').animate({
                        scrollTop: cardTop - headerOffset
                    }, 400);
                } else {
                    // Otherwise, center the content nicely
                    const idealScrollTop = cardTop - (viewportHeight - (detailsBottom - cardTop)) / 2;
                    $('html, body').animate({
                        scrollTop: Math.max(0, idealScrollTop)
                    }, 400);
                }
            });
        }

        showError(message) {
            // Hide results if showing
            this.results.slideUp(200);
            
            // Create or update error message
            let $error = this.form.find('.hezarfen-tracking-error');
            
            if (!$error.length) {
                $error = $('<div class="hezarfen-tracking-error"></div>');
                this.form.after($error);
            }
            
            $error.html(message).slideDown(300);
            
            // Auto-hide error after 5 seconds
            setTimeout(() => {
                this.hideError();
            }, 5000);
        }

        hideError() {
            this.form.find('.hezarfen-tracking-error').slideUp(200);
        }

        shakeForm($form) {
            // Add shake animation to form
            $form = $form || this.form;
            $form.addClass('shake');
            
            // Remove shake class after animation
            setTimeout(() => {
                $form.removeClass('shake');
            }, 600);
        }

        scrollToResults() {
            // Smooth scroll to results
            $('html, body').animate({
                scrollTop: this.results.offset().top - 20
            }, 500);
        }

        setCardLoadingState($card, loading) {
            if (loading) {
                $card.addClass('loading');
                $card.find('.hezarfen-track-text').text(hezarfen_tracking_ajax.strings.searching);
                $card.find('.hezarfen-track-icon').addClass('spinning');
            } else {
                $card.removeClass('loading');
                $card.find('.hezarfen-track-text').text('Click to track');
                $card.find('.hezarfen-track-icon').removeClass('spinning');
            }
        }

        // Public method to reset form
        reset() {
            this.form[0].reset();
            this.results.slideUp(200);
            this.hideError();
            $('.hezarfen-form-input').each((index, element) => {
                this.clearFieldError($(element));
            });
            // Remove any card loading states and expanded details
            $('.hezarfen-order-card').removeClass('loading active');
            $('.hezarfen-order-details-expanded').slideUp(300, function() {
                $(this).remove();
            });
            this.lastClickedCard = null;
        }

        ensureCardVisibility($card, $details) {
            if (!$card || !$card.length) return;
            
            const cardTop = $card.offset().top;
            const cardBottom = cardTop + $card.outerHeight();
            const detailsBottom = $details ? $details.offset().top + $details.outerHeight() : cardBottom;
            const viewportTop = $(window).scrollTop();
            const viewportBottom = viewportTop + $(window).height();
            const headerOffset = 80;
            
            // Check if the selected card is visible
            const cardVisible = cardTop >= viewportTop + headerOffset && cardBottom <= viewportBottom;
            
            if (!cardVisible) {
                // If card is not visible, scroll to show it at the top
                $('html, body').animate({
                    scrollTop: cardTop - headerOffset
                }, 300);
            }
        }

        async handleOrderFilter(period) {
            // Show loading state for the orders list
            const $ordersList = $('#hezarfen-orders-list');
            $ordersList.addClass('loading');

            const formData = {
                action: 'hezarfen_filter_user_orders',
                nonce: hezarfen_tracking_ajax.nonce,
                period: period
            };

            try {
                const response = await this.makeRequest(formData);
                
                if (response.success) {
                    // Update orders list with filtered results
                    $ordersList.html(response.data.html);
                    
                    // Remove any expanded details
                    $('.hezarfen-order-details-expanded').slideUp(300, function() {
                        $(this).remove();
                    });
                    $('.hezarfen-order-card').removeClass('active');
                } else {
                    this.showError(response.data.message || 'Error filtering orders');
                }
            } catch (error) {
                console.error('Order filter error:', error);
                this.showError('Error filtering orders');
            } finally {
                $ordersList.removeClass('loading');
            }
        }
    }

    // CSS for shake animation
    const shakeCSS = `
        <style>
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        .hezarfen-tracking-form.shake {
            animation: shake 0.6s ease-in-out;
        }
        </style>
    `;
    
    // Add shake CSS to head
    $('head').append(shakeCSS);

    // Initialize when document is ready
    $(document).ready(() => {
        // Initialize if tracking form exists OR if order cards exist
        if ($('#hezarfen-tracking-form').length || $('.hezarfen-order-card').length) {
            window.hezarfenOrderTracking = new HezarfenOrderTracking();
        }
    });

    // Expose reset function globally for "Track Another Order" button
    window.resetHezarfenTracking = function() {
        if (window.hezarfenOrderTracking) {
            window.hezarfenOrderTracking.reset();
        }
    };

    // Function to toggle between user orders and guest tracking
    window.hezarfenToggleGuestMode = function(showGuest = true) {
        const $userInterface = $('.hezarfen-logged-in-interface > div:not(.hezarfen-guest-form)');
        const $guestForm = $('#hezarfen-guest-form');
        
        if (showGuest) {
            // Show guest form, hide user interface
            $userInterface.slideUp(300);
            $guestForm.slideDown(300);
        } else {
            // Show user interface, hide guest form
            $guestForm.slideUp(300);
            $userInterface.slideDown(300);
        }
    };

    // Function to track user order by ID (called from order cards)
    window.hezarfenTrackUserOrder = function(orderId) {
        if (window.hezarfenOrderTracking) {
            window.hezarfenOrderTracking.handleUserOrderClick(orderId);
        }
    };

})(jQuery);