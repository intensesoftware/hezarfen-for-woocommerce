jQuery(function ($) {
	$(document).ready(function () {
		const metabox_wrapper = $('#hezarfen-mst-order-edit-metabox');
		const duplicate_button = metabox_wrapper.find('.duplicate-form');
		const remove_buttons = metabox_wrapper.find('.remove-form');

		convert_selects_to_selectwoo(metabox_wrapper);

		duplicate_button.on('click', function () {
			duplicate_shipment_form(metabox_wrapper);
		});

		remove_buttons.on('click', function () {
			let shipment_form = $(this).parent();

			create_confirmation_modal(
				metabox_wrapper,
				shipment_form
			);
		});
	});

	function create_confirmation_modal(metabox_wrapper, shipment_form) {
		const modal_body = metabox_wrapper.find('#modal-body');

		modal_body.dialog({
			resizable: false,
			draggable: false,
			height: "auto",
			width: 400,
			modal: true,
			dialogClass: "hezarfen-mst-confirm-removal",
			buttons: {
				Delete: function () {
					$.post(
						ajaxurl,
						{
							action: hezarfen_mst_backend.remove_shipment_data_action,
							_wpnonce: hezarfen_mst_backend.remove_shipment_data_nonce,
							order_id: $('input#post_ID').val(),
							data_id: shipment_form.data('id')
						},
						function () {
							if (metabox_wrapper.find('.shipment-form').length === 1) {
								duplicate_shipment_form(metabox_wrapper);
							}

							shipment_form.remove();
							modal_body.dialog('destroy');
						}
					).fail(function () {
						modal_body.dialog('destroy');
					});
				},
				Cancel: function () {
					modal_body.dialog('destroy');
				}
			}
		});
	}

	function duplicate_shipment_form(metabox_wrapper) {
		const last_shipment_form = metabox_wrapper.find('.shipment-form').last();

		last_shipment_form.find('.courier-company-select').selectWoo('destroy');

		const form_id = last_shipment_form.data('id') + 1;
		const new_form = last_shipment_form.clone(true);
		const courier_select = new_form.find('.courier-company-select');
		const tracking_num_input = new_form.find('.tracking-num-input');

		new_form.data('id', form_id);
		courier_select.attr('name', courier_select.attr('name').replace(/\[[0-9]\]/, `[${form_id}]`));
		tracking_num_input.attr('name', tracking_num_input.attr('name').replace(/\[[0-9]\]/, `[${form_id}]`));

		courier_select.val('');
		tracking_num_input.val('');

		new_form.find('.custom-courier-title').remove();
		new_form.find('.tracking-num-input-wrapper a').remove();

		new_form.appendTo(metabox_wrapper.find('.shipment-forms-wrapper'));
		convert_selects_to_selectwoo(metabox_wrapper);
	}

	function convert_selects_to_selectwoo(metabox_wrapper) {
		metabox_wrapper.find('.courier-company-select').selectWoo({
			placeholder: hezarfen_mst_backend.courier_select_placeholder,
			templateResult: courier_options_template
		});
	}

	function courier_options_template(option) {
		if (!option.id || !option.element.dataset.logo) {
			return option.text;
		}

		const base_url = hezarfen_mst_backend.courier_logo_base_url;

		const wrapper = $('<div></div>').addClass('hezarfen-mst-courier-logo-wrapper');
		const logo = $('<img>').attr('src', base_url + option.element.dataset.logo).addClass('hezarfen-mst-courier-logo').addClass(option.element.value);

		wrapper.append(logo);

		return wrapper;
	}
});
