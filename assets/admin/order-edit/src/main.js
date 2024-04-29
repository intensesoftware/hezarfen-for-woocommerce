import { initFlowbite } from 'flowbite';
import './style.css';

jQuery(document).ready(($)=>{
  $('.hez-ui .h-expand').click(function() {
    var $content = $('#shipping-companies');
    var $button = $(this);

    // Toggle max height class
    if ($content.hasClass('max-h-24')) {
      $content.removeClass('max-h-24').addClass('max-h-[1000px]');
      $button.text($button.data('show-less-label')); // Change button label to "Show less"
    } else {
      $content.removeClass('max-h-[1000px]').addClass('max-h-24');
      $button.text($button.data('show-more-label')); // Change button label to "Show more"
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
                data_id: shipment_row.data('id')
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
});
