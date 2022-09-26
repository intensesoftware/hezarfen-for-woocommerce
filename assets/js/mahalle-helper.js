class hezarfen_mahalle_helper {
	fields_wrapper;
	type;
	page;

	constructor(fields_wrapper, type, page) {
		this.fields_wrapper = fields_wrapper;
		this.type = type;
		this.page = page;
	}

	convert_fields_to_selectwoo() {
		for (let field of this.get_fields(true)) {
			this.convert_field_to_selectwoo(field);
		}
	}

	convert_field_to_selectwoo(field) {
		field = jQuery(field);
		field.selectWoo({
			width: '100%',
			placeholder: hezarfen_mahalle_helper_backend.select_option_text
		});
	}

	add_event_handlers() {
		// prevent adding event handlers multiple times.
		this.get_fields().off('select2:select.hezarfen');

		this.get_state_field().on('select2:select.hezarfen', { thisHelper: this }, this.province_on_change);

		this.get_city_field().on('select2:select.hezarfen', { thisHelper: this }, this.district_on_change);
	}

	province_on_change(event) {
		let thisHelper = event.data.thisHelper;

		thisHelper.get_city_field().prop("disabled", true);

		// empty district select box
		thisHelper.get_city_field().empty().trigger('change');

		// push placeholder data
		thisHelper.get_city_field().append(thisHelper.create_default_option());

		// get selected data
		var selected = event.params.data;

		var data = {
			'dataType': 'district',
			'cityPlateNumber': selected.id
		};

		jQuery.get(hezarfen_mahalle_helper_backend.api_url, data, function (response) {
			for (const district_name of response) {
				thisHelper.get_city_field().append(thisHelper.create_option(district_name, district_name));
			}

			thisHelper.get_city_field().prop("disabled", false);
		}, 'json');
	}

	district_on_change(event) {
		let thisHelper = event.data.thisHelper;

		thisHelper.get_nbrhood_field().prop("disabled", true);

		// empty neighborhood select box
		thisHelper.get_nbrhood_field().empty().trigger('change');

		// push placeholder data
		thisHelper.get_nbrhood_field().append(thisHelper.create_default_option());

		// get selected data
		var selected = event.params.data;

		var data = {
			'dataType': 'neighborhood',
			'cityPlateNumber': thisHelper.get_state_field().val(),
			'district': selected.id,
			'return_nbrhood_ids': false
		};

		jQuery.get(hezarfen_mahalle_helper_backend.api_url, data, function (response) {
			for (const neighborhood_name of response) {
				thisHelper.get_nbrhood_field().append(thisHelper.create_option(neighborhood_name, neighborhood_name));
			}

			thisHelper.get_nbrhood_field().prop("disabled", false);
		}, 'json');
	}

	on_country_change(country_code) {
		let elements = [this.get_city_field(), this.get_nbrhood_field()];

		if (country_code === 'TR') {
			// If Turkey is selected, replace city and address_1 fields with select elements.
			this.replaceElementsWith(elements, 'select');

			this.add_event_handlers();
		} else {
			// Remove select2:select event handler from the state field.
			this.get_state_field().off('select2:select.hezarfen');

			// Replace city and address_1 fields with input elements.
			this.replaceElementsWith(elements, 'input');
		}
	}

	replaceElementsWith(elements, element_type) {
		for (let element of elements) {
			let new_element;

			if (element.is(element_type)) {
				continue;
			}

			let parent_element = element.closest('.form-row'),
				element_name = element.attr('name'),
				element_id = element.attr('id');

			if (element_type === 'input') {
				parent_element.show().find('.select2-container').remove();
				new_element = jQuery('<input type="text" />').addClass('input-text');
			} else if (element_type === 'select') {
				new_element = jQuery('<select></select>');
			}

			new_element
				.prop('id', element_id)
				.prop('name', element_name)
			element.replaceWith(new_element);

			if (element_type === 'select') {
				element = this.fields_wrapper.find('#' + element_id);
				element.append(this.create_default_option());

				this.convert_field_to_selectwoo(element);
			}
		}
	}

	create_default_option() {
		return this.create_option('', hezarfen_mahalle_helper_backend.select_option_text);
	}

	create_option(value, text) {
		return jQuery('<option></option>').prop('value', value).text(text);
	}

	get_fields(returnArray = false) {
		let fields = this.fields_wrapper.find(`#${this.type}_state, #${this.type}_city, #${this.type}_address_1`);

		if (returnArray) {
			return fields.toArray();
		}

		return fields;
	}

	get_country_field() {
		return this.fields_wrapper.find(`#${this.type}_country`);
	}

	get_state_field() {
		return this.fields_wrapper.find(`#${this.type}_state`);
	}

	get_city_field() {
		return this.fields_wrapper.find(`#${this.type}_city`);
	}

	get_nbrhood_field() {
		return this.fields_wrapper.find(`#${this.type}_address_1`);
	}

	get_ship_to_different_checkbox() {
		return jQuery('#ship-to-different-address input');
	}
};
