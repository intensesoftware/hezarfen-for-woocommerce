import { initFlowbite } from 'flowbite';
import './style.css';

jQuery(document).ready(($)=>{
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
});
