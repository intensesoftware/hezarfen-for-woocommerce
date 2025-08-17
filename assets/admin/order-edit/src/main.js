import { initFlowbite } from 'flowbite';
import './style.css';

jQuery(document).ready(($)=>{
  // Initialize Flowbite components (tabs, etc.)
  initFlowbite();
  
  // Handle non-pro interface - show manual tracking content by default
  function initializeInterface() {
    const $tabContainer = $('#default-tab');
    const $manualContent = $('#hezarfen-lite');
    
    // If no tabs exist (pro not available), show manual content directly
    if ($tabContainer.length === 0) {
      $manualContent.removeClass('hidden').show();
    }
  }
  
  // Initialize the interface
  initializeInterface();
  // Check if feedback should be shown
  function shouldShowFeedback() {
    const feedbackDismissed = localStorage.getItem('hezarfen_feedback_dismissed');
    const reviewClicked = localStorage.getItem('hezarfen_review_clicked');
    return !feedbackDismissed && !reviewClicked;
  }

  // Initialize feedback request
  function initFeedbackRequest() {
    const $feedbackRequest = $('#hezarfen-feedback-request');
    
    if (!shouldShowFeedback()) {
      $feedbackRequest.hide();
      return;
    }

    // Show feedback after a short delay to not interrupt user flow
    setTimeout(() => {
      $feedbackRequest.fadeIn(300);
    }, 1000);

    // Handle review link click
    $('#hezarfen-review-positive').on('click', function() {
      // Track that user clicked review link
      localStorage.setItem('hezarfen_review_clicked', 'true');
      
      // Show thank you message
      showThankYouMessage(hezarfen_mst_backend.thank_you_message);
      
      // Hide after 3 seconds
      setTimeout(() => {
        $feedbackRequest.fadeOut(300);
      }, 3000);
    });

    // Handle dismiss button
    $('#hezarfen-feedback-dismiss').on('click', function() {
      localStorage.setItem('hezarfen_feedback_dismissed', 'true');
      $feedbackRequest.fadeOut(300);
    });

    // Handle close button
    $('#hezarfen-feedback-close').on('click', function() {
      $feedbackRequest.fadeOut(300);
    });
  }

  // Show thank you message
  function showThankYouMessage(message) {
    const $feedbackRequest = $('#hezarfen-feedback-request');
    
    $feedbackRequest.html(`
      <div class="flex items-center space-x-3">
        <div class="flex-shrink-0">
          <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
        </div>
        <div class="flex-1">
          <p class="text-sm text-green-700 font-medium">
            ${message}
          </p>
        </div>
      </div>
    `);
  }

  // Initialize feedback request
  initFeedbackRequest();

  $('#hezarfen-lite .h-expand').click(function () {
    var $content = $('#hezarfen-lite #shipping-companies');

    var $button = $(this);
    const $buttonText = $(this).find('span');
    $button.find('svg').toggleClass('rotate-180');

    if ($content.hasClass('max-h-24')) {
      $content.removeClass('max-h-24').addClass('max-h-[1000px]');
      $buttonText.text($button.data('show-less-label'));
    } else {
      $content.removeClass('max-h-[1000px]').addClass('max-h-24');
      $buttonText.text($button.data('show-more-label'));
    }
  });

  const metabox_wrapper = $('#hezarfen-lite');
  const remove_buttons = metabox_wrapper.find('.remove-shipment-data');

  remove_buttons.on('click', function () {
    let shipment_row = $(this).parents('tr');

    create_confirmation_modal(
      metabox_wrapper,
      shipment_row
    );
  });

  const addToTrackList = metabox_wrapper.find('#add-to-tracking-list');

  addToTrackList.on('click', function() {
    const form = $(this).parents('#hezarfen-lite');

    const data = {
      action: hezarfen_mst_backend.new_shipment_data_action,
      _wpnonce: hezarfen_mst_backend.new_shipment_data_nonce,
      order_id: $(this).data('order_id')
    };


    data[hezarfen_mst_backend.new_shipment_courier_html_name] = form.find('input[name="courier-company-select"]:checked').val();
    data[hezarfen_mst_backend.new_shipment_tracking_num_html_name] = form.find('#tracking-num-input').val();

    $.post(
      ajaxurl,
      data,
      function () {
        location.reload();
      }
    ).fail(function () {
    });
  });

  function create_confirmation_modal(metabox_wrapper, shipment_row) {
    const modal_body = metabox_wrapper.find('#modal-body');

    modal_body.dialog({
      resizable: false,
      draggable: false,
      height: 'auto',
      width: 400,
      modal: true,
      dialogClass: 'hezarfen-mst-confirm-removal',
      buttons: [
        {
          text: hezarfen_mst_backend.modal_btn_delete_text,
          click: function () {
            $.post(
              ajaxurl,
              {
                action: hezarfen_mst_backend.remove_shipment_data_action,
                _wpnonce: hezarfen_mst_backend.remove_shipment_data_nonce,
                order_id: $('input#post_ID').val(),
                meta_id: shipment_row.data('meta_id')
              },
              function () {
                shipment_row.remove();
                modal_body.dialog('destroy');
              }
            ).fail(function () {
              modal_body.dialog('destroy');
            });
          }
        },
        {
          text: hezarfen_mst_backend.modal_btn_cancel_text,
          click: function () {
            modal_body.dialog('destroy');
          }
        }
      ]
    });
  }

  function updateCountdown() {
      var endTime = new Date("May 3, 2024 23:59:00").getTime(); // Set the countdown end date and time
      var now = new Date().getTime(); // Current time
      var timeLeft = endTime - now; // Time remaining in milliseconds

      var days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
      var hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
      var minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
      var seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);

      // Display the result in the respective span elements
      $('#days').text(days);
      $('#hours').text(hours);
      $('#minutes').text(minutes);
      $('#seconds').text(seconds);

      if (timeLeft < 0) {
          clearInterval(timer);
          $('#countdown').html("Kampanya sona erdi");
      }
  }

  updateCountdown(); // Run function once at first to avoid delay
  var timer = setInterval(updateCountdown, 1000); // Update the countdown every second

  const invoice_type_field = $('.hezarfen_billing_invoice_type_field');

	$('a.edit_address').on('click', function () {
		$('.hezarfen-tc-num-field').hide();
	});

	update_field_showing_statuses(invoice_type_field.val());

	invoice_type_field.on('change', function () {
		var invoice_type = $(this).val();
		update_field_showing_statuses(invoice_type);
	});

	function update_field_showing_statuses(invoice_type) {
		if (invoice_type == 'person') {
			$('._billing_hez_TC_number_field').removeClass('hezarfen-hide-form-field');
			$('._billing_hez_tax_number_field').addClass('hezarfen-hide-form-field');
			$('._billing_hez_tax_office_field').addClass('hezarfen-hide-form-field');
		} else if (invoice_type == 'company') {
			$('._billing_hez_TC_number_field').addClass('hezarfen-hide-form-field');
			$('._billing_hez_tax_number_field').removeClass('hezarfen-hide-form-field');
			$('._billing_hez_tax_office_field').removeClass('hezarfen-hide-form-field');
		}
	}
});
