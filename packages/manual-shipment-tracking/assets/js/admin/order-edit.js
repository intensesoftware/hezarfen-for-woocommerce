jQuery(function ($) {
	$(document).ready(function () {
		const metabox_wrapper = $('#hezarfen-lite');
		const remove_buttons = metabox_wrapper.find('.remove-shipment-data');

		remove_buttons.on('click', function () {
			let shipment_row = $(this).parents('tr');

			create_confirmation_modal(
				metabox_wrapper,
				shipment_row
			);
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
