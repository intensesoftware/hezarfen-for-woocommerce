var wc_hezarfen_checkout = {
    notify_neighborhood_changed: function (province_plate_number, district, neighborhood, type) {
        if (!province_plate_number || !district || !neighborhood) {
            return;
        }

        var data = {
            'action':'wc_hezarfen_neighborhood_changed',
            'security': wc_hezarfen_ajax_object.mahalleio_nonce,
            'cityPlateNumber': province_plate_number,
            'district': district,
            'neighborhood': neighborhood,
            'type': type
        };

        jQuery.post(wc_hezarfen_ajax_object.ajax_url, data, function(response){
            var args = JSON.parse(response);

            if(args.update_checkout)
                jQuery('body').trigger('update_checkout');
        });
    },
    mbgb_plugin_active: typeof wc_hezarfen_mbgb_backend !== 'undefined',
    should_notify_neighborhood_changed: function (type) {
        return this.mbgb_plugin_active && (wc_hezarfen_mbgb_backend.address_source === type || (wc_hezarfen_mbgb_backend.address_source === 'shipping' && !this.ship_to_different_checked()))
    },
    ship_to_different_checked: function () {
        return jQuery('#ship-to-different-address input').is(':checked');
    }
};

jQuery( function( $ ) {
    let select2_args = {width: '100%'};
    let select2_tr_args = Object.assign(select2_args, { language: "tr" });

    $.each(["billing", "shipping"], function(index, type){
        let current_country_code = $('#' + type + '_country').val();

        if (!current_country_code || current_country_code === 'TR') {
            $('#' + type + '_state').select2(select2_args);
            $('#' + type + '_city').select2(select2_args);
            $('#' + type + '_address_1').select2(select2_tr_args);
        }

        if ( $('#' + type + '_country').val() === 'TR' ) {
            add_event_handlers(type);
        }
    });

    $('#hezarfen_invoice_type').select2(select2_tr_args);

    $('#hezarfen_invoice_type').change(function(){
        var invoice_type = $(this).val();

        if( invoice_type == 'person' ){
            $('#hezarfen_TC_number_field').removeClass('hezarfen-hide-form-field');
            $('#hezarfen_tax_number_field').addClass('hezarfen-hide-form-field');
            $('#hezarfen_tax_office_field').addClass('hezarfen-hide-form-field');
            $('#billing_company_field').addClass('hezarfen-hide-form-field');
        }else if( invoice_type == 'company' ){
            $('#hezarfen_TC_number_field').addClass('hezarfen-hide-form-field');
            $('#hezarfen_tax_number_field').removeClass('hezarfen-hide-form-field');
            $('#hezarfen_tax_office_field').removeClass('hezarfen-hide-form-field');
            $('#billing_company_field').removeClass('hezarfen-hide-form-field');
        }
    });

    $(document.body).on('country_to_state_changing', function (event, country_code, wrapper) {
        let elements = wrapper.find('#billing_city, #shipping_city, #billing_address_1, #shipping_address_1');
        let type = wrapper.hasClass('woocommerce-billing-fields') ? 'billing' : 'shipping';

        if (country_code === 'TR') {
            // If Turkey is selected, replace city and address_1 fields with select fields.
            replaceElementsWith(wrapper, elements, 'select');

            add_event_handlers(type);
        } else {
            // Remove select2:select event handler from state field.
            $('#' + type + '_state').off('select2:select.hezarfen');

            // Replace city and address_1 fields with input fields.
            replaceElementsWith(wrapper, elements, 'input');
        }
    });

    function add_event_handlers(type) {
        let checkout_fields_wrapper = $('.woocommerce-' + type + '-fields');

        // prevent adding event handlers multiple times.
        $('#' + type + '_state' + ', #' + type + '_city' + ', #' + type + '_address_1').off('select2:select.hezarfen');
        $('#ship-to-different-address input').off('change.hezarfen');

        $("#"+type+"_state").on("select2:select.hezarfen", function(e){
            province_on_change(e, type);
        });

        $('#' + type + '_city').on("select2:select.hezarfen", function(e){
            district_on_change(e, type, checkout_fields_wrapper);
        });

        $('#' + type + '_address_1').on("select2:select.hezarfen", function(e){
            neighborhood_on_change($(this).val(), type, checkout_fields_wrapper);
        });

        $('#ship-to-different-address input').on('change.hezarfen', function () {
            ship_to_different_on_change(this);
        });
    }

    function province_on_change(e, type) {
        $('#' + type + '_city').prop("disabled", true);

        // empty district select box
        $('#' + type + '_city').empty();

        // push placeholder data
        $('#' + type + '_city')
            .append($("<option></option>")
                .attr("value", "")
                .text("Lütfen seçiniz"));

        // get selected data
        var selected = e.params.data;

        var data = {
            'dataType': 'district',
            'cityPlateNumber': selected.id
        };

        jQuery.get(wc_hezarfen_ajax_object.api_url, data, function(response){
            var districts = JSON.parse(response);

            $.each(districts, function (index, district_name) {
                $('#' + type + '_city')
                    .append($("<option></option>")
                        .attr("value", district_name)
                        .text(district_name));
            });

            $('#' + type + '_city').prop("disabled", false);
        });
    }

    function district_on_change(e, type, checkout_fields_wrapper) {
        $('#' + type + '_address_1').prop("disabled", true);

        // empty neighborhood select box
        $('#' + type + '_address_1').empty();

        // push placeholder data
        $('#' + type + '_address_1')
            .append($("<option></option>")
                .attr("value", "")
                .text("Lütfen seçiniz"));

        // get selected data
        var selected = e.params.data;

        var data = {
            'dataType': 'neighborhood',
            'cityPlateNumber': checkout_fields_wrapper.find('#' + type + '_state').val(),
            'district': selected.id
        };

        jQuery.get(wc_hezarfen_ajax_object.api_url, data, function(response){
            var neighborhoods = JSON.parse(response);

            $.each(neighborhoods, function (i, neighborhood_name) {
                $('#' + type + '_address_1')
                    .append($("<option></option>")
                        .attr("value", neighborhood_name)
                        .text(neighborhood_name));
            });

            $('#' + type + '_address_1').prop("disabled", false);
        });
    }

    function neighborhood_on_change(neighborhood, type, checkout_fields_wrapper) {
        if (wc_hezarfen_checkout.should_notify_neighborhood_changed(type)) {
            let province_plate_number = checkout_fields_wrapper.find('#' + type + '_state').val();
            let district = checkout_fields_wrapper.find('#' + type + '_city').val();

            wc_hezarfen_checkout.notify_neighborhood_changed(province_plate_number, district, neighborhood, type);
        }
    }

    function ship_to_different_on_change(checkbox) {
        if (wc_hezarfen_checkout.mbgb_plugin_active && wc_hezarfen_mbgb_backend.address_source === 'shipping') {
            let address_type = $(checkbox).is(':checked') ? 'shipping' : 'billing';
            let neighborhood_select = $(`#${address_type}_address_1`);

            // set a small timeout to prevent conflict with the Woocommerce's "update_checkout" trigger.
            setTimeout(() => {
                neighborhood_select.trigger('select2:select');
            }, 300);
        }
    }

    function replaceElementsWith(wrapper, elements, type) {
        elements.each(function () {
            let element = $(this);
            let new_element;

            if (element.is(type)) {
                return;
            }

            let parent_element = element.closest('.form-row'),
                element_name   = element.attr('name'),
                element_id     = element.attr('id'),
                hezarfen_classes = '';

            if (element_id.includes('billing')) {
                hezarfen_classes = element_id.includes('_city') ? wc_hezarfen_ajax_object.billing_district_field_classes : wc_hezarfen_ajax_object.billing_neighborhood_field_classes;
            } else {
                hezarfen_classes = element_id.includes('_city') ? wc_hezarfen_ajax_object.shipping_district_field_classes : wc_hezarfen_ajax_object.shipping_neighborhood_field_classes;
            }

            if (type === 'input') {
                parent_element.show().find('.select2-container').remove();
                new_element = $('<input type="text" />').addClass('input-text');

                // remove 'form-row-wide' class from the hezarfen_classes array
                hezarfen_classes = jQuery.grep(hezarfen_classes, function (value) {
                    return value != 'form-row-wide';
                });

                parent_element.removeClass(hezarfen_classes);
            } else if (type === 'select') {
                new_element = $('<select></select>');

                parent_element.addClass(hezarfen_classes);
            }

            new_element
                .prop('id', element_id)
                .prop('name', element_name)
            element.replaceWith(new_element);

            if (type === 'select') {
                element = wrapper.find('#' + element_id);

                let default_option = $('<option value=""></option>').text(wc_hezarfen_ajax_object.select_option_text);
                element.append(default_option);

                element.select2({
                    width: '100%',
                    placeholder: wc_hezarfen_ajax_object.select_option_text
                });
            }
        });
    }
} );
