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

	$(document.body).on('country_to_state_changing', function (event, country_code) {
		let elements = [mahalle_helper.get_city_field(), mahalle_helper.get_nbrhood_field()];

        if (country_code === 'TR') {
            // If Turkey is selected, replace city and address_1 fields with select elements.
            mahalle_helper.replaceElementsWith(elements, 'select');

            mahalle_helper.add_event_handlers();
        } else {
            // Remove select2:select event handler from the state field.
            mahalle_helper.get_state_field().off('select2:select.hezarfen');

            // Replace city and address_1 fields with input elements.
            mahalle_helper.replaceElementsWith(elements, 'input');
        }
    });
});
