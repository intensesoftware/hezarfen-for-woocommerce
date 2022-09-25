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
		this.get_fields().each(function () {
			let self = jQuery(this);
			self.selectWoo({
				placeholder: self.attr('data-placeholder') || self.attr('placeholder') || '',
				width: '100%'
			});
		});
	}

	add_event_handlers() {
		// prevent adding event handlers multiple times.
		this.get_fields().off('select2:select.hezarfen');

		this.get_state_field().on('select2:select.hezarfen', {thisHelper: this}, this.province_on_change);

		this.get_city_field().on('select2:select.hezarfen', {thisHelper: this}, this.district_on_change);
	}

	province_on_change(e) {
		let thisHelper = e.data.thisHelper;

		thisHelper.get_city_field().prop("disabled", true);

		// empty district select box
		thisHelper.get_city_field().empty();

		// push placeholder data
		thisHelper.get_city_field()
			.append(jQuery("<option></option>")
				.attr("value", "")
				.text("Lütfen seçiniz"));

		// get selected data
		var selected = e.params.data;

		var data = {
			'dataType': 'district',
			'cityPlateNumber': selected.id
		};

		jQuery.get(hezarfen_mahalle_helper_backend.api_url, data, function (response) {
			var districts = JSON.parse(response);

			jQuery.each(districts, function (index, district_name) {
				thisHelper.get_city_field()
					.append(jQuery("<option></option>")
						.attr("value", district_name)
						.text(district_name));
			});

			thisHelper.get_city_field().prop("disabled", false);
		});
	}

	district_on_change(e) {
		let thisHelper = e.data.thisHelper;

		thisHelper.get_nbrhood_field().prop("disabled", true);

		// empty neighborhood select box
		thisHelper.get_nbrhood_field().empty();

		// push placeholder data
		thisHelper.get_nbrhood_field()
			.append(jQuery("<option></option>")
				.attr("value", "")
				.text("Lütfen seçiniz"));

		// get selected data
		var selected = e.params.data;

		var data = {
			'dataType': 'neighborhood',
			'cityPlateNumber': thisHelper.get_state_field().val(),
			'district': selected.id,
			'return_nbrhood_ids': false
		};

		jQuery.get(hezarfen_mahalle_helper_backend.api_url, data, function (response) {
			var neighborhoods = JSON.parse(response);

			jQuery.each(neighborhoods, function (i, neighborhood_name) {
				thisHelper.get_nbrhood_field()
					.append(jQuery("<option></option>")
						.attr("value", neighborhood_name)
						.text(neighborhood_name));
			});

			thisHelper.get_nbrhood_field().prop("disabled", false);
		});
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

				let default_option = jQuery('<option value=""></option>').text(hezarfen_mahalle_helper_backend.select_option_text); //TODO: bunu wc_address_i18n_params vs'den veya orjinal elementten çek?
				element.append(default_option);

				element.select2({
					width: '100%',
					placeholder: hezarfen_mahalle_helper_backend.select_option_text //TODO: bunu wc_address_i18n_params vs'den veya orjinal elementten çek?
				});
			}
		}
	}

	get_fields() {
		return this.fields_wrapper.find(`#${this.type}_state, #${this.type}_city, #${this.type}_address_1`);
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