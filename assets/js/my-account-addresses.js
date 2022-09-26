jQuery(function ($) {
	let wrapper = $('.woocommerce-address-fields');
	let type = wrapper.find('#billing_state_field').length ? 'billing' : 'shipping';
	let mahalle_helper = new hezarfen_mahalle_helper(wrapper, type, 'edit-address');

	let current_country_code = mahalle_helper.get_country_field().val();

	if (!current_country_code || current_country_code === 'TR') {
		mahalle_helper.convert_fields_to_selectwoo();
	}

	if (current_country_code === 'TR') {
		mahalle_helper.add_event_handlers();
	}

	$(document.body).on('country_to_state_changing', {thisHelper: mahalle_helper}, mahalle_helper.on_country_change);
});
