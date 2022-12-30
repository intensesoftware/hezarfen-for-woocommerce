jQuery(function ($) {
	$(document).ready(function () {
		const metabox_wrapper = $('#hezarfen-mst-order-edit-metabox');
		const shipment_forms_wrapper = metabox_wrapper.find('.shipment-forms-wrapper');
		const duplicate_button = metabox_wrapper.find('.duplicate-form');

		convert_selects_to_selectwoo(metabox_wrapper);

		duplicate_button.on('click', function () {
			const last_shipment_form = metabox_wrapper.find('.shipment-form').last();
			duplicate_shipment_form(last_shipment_form).appendTo(shipment_forms_wrapper);
			convert_selects_to_selectwoo(metabox_wrapper);
		});
	});

	function duplicate_shipment_form(form) {
		form.find('.courier-company-select').selectWoo('destroy');

		const form_id = form.data('id') + 1;
		const new_form = form.clone();
		const courier_select = new_form.find('.courier-company-select');
		const tracking_num_input = new_form.find('.tracking-num-input');

		new_form.data('id', form_id);
		courier_select.attr('name', courier_select.attr('name').replace(/\[[0-9]\]/, `[${form_id}]`));
		tracking_num_input.attr('name', tracking_num_input.attr('name').replace(/\[[0-9]\]/, `[${form_id}]`));

		courier_select.val('');
		tracking_num_input.val('');

		new_form.find('.custom-courier-title').remove();
		new_form.find('.tracking-num-input-wrapper a').remove();

		return new_form;
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
