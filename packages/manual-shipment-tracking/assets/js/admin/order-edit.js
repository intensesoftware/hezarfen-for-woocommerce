jQuery(function ($) {
	$(document).ready(function () {
		const metabox_wrapper = $('#hezarfen-mst-order-edit-metabox');
		const courier_selects = metabox_wrapper.find('.courier-company-select');

		courier_selects.selectWoo({
			placeholder: hezarfen_mst_backend.courier_select_placeholder,
			templateResult: courier_options_template
		});

		function courier_options_template(option) {
			if (!option.id || !option.element.dataset.logo) {
				return option.text;
			}

			const base_url = hezarfen_mst_backend.courier_logo_base_url;

			const wrapper = $('<div></div>').addClass('hezarfen-mst-courier-logo-wrapper');
			const logo = $('<img>').attr('src', base_url + option.element.dataset.logo).addClass('hezarfen-mst-courier-logo').addClass(option.element.value);

			wrapper.append(logo);

			return wrapper;
		};
	});
});
