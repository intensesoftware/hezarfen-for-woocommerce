jQuery(document).ready(function($){
    $.each(["billing", "shipping"], function(index, type){
        let current_country_code = $('#' + type + '_country').val();

        if (!current_country_code || current_country_code === 'TR') {
            $('#' + type + '_city').select2();
            $('#' + type + '_address_1').select2({ language: "tr" });
        }
    });

    $('#hezarfen_invoice_type').select2({ language: "tr" });
});

jQuery( function( $ ) {
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

        if (country_code === 'TR') {
            // If Turkey is selected, replace city and address_1 fields with select fields.
            replaceElementsWith(wrapper, elements, 'select');

            let type = wrapper.hasClass('woocommerce-billing-fields') ? 'billing' : 'shipping';
            add_event_handlers(type);
        } else {
            // If a country other than Turkey is selected, replace city and address_1 fields with input fields.
            replaceElementsWith(wrapper, elements, 'input');
        }
    });

    $.each(["billing", "shipping"], function(index, type){
        add_event_handlers(type);
    });

    function add_event_handlers(type) {
        let checkout_fields_wrapper = $('.woocommerce-' + type + '-fields');

        // prevent adding event handlers multiple times.
        $('#' + type + '_state' + ', #' + type + '_city' + ', #' + type + '_address_1').off('select2:select.hezarfen');

        $("#"+type+"_state").on("select2:select.hezarfen", function(e){
            province_on_change(e, type);
        });

        $('#' + type + '_city').on("select2:select.hezarfen", function(e){
            district_on_change(e, type, checkout_fields_wrapper);
        });

        $('#' + type + '_address_1').on("select2:select.hezarfen", function(e){
            neighborhood_on_change(e, type, checkout_fields_wrapper);
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

            $.each(neighborhoods, function (neighborhood_id, neighborhood_name) {
                $('#' + type + '_address_1')
                    .append($("<option></option>")
                        .attr("value", neighborhood_name)
                        .text(neighborhood_name));
            });

            $('#' + type + '_address_1').prop("disabled", false);
        });
    }

    function neighborhood_on_change(e, type, checkout_fields_wrapper) {
        // get selected data
        var selected = e.params.data;

        var data = {
            'action':'wc_hezarfen_neighborhood_changed',
            'security': wc_hezarfen_ajax_object.mahalleio_nonce,
            'cityPlateNumber': checkout_fields_wrapper.find('#' + type + '_state').val(),
            'district': checkout_fields_wrapper.find('#' + type + '_city').val(),
            'neighborhood': selected.id,
            'type': type
        };

        jQuery.post(wc_hezarfen_ajax_object.ajax_url, data, function(response){
            var args = JSON.parse(response);

            if(args.update_checkout)
                jQuery('body').trigger('update_checkout');
        });
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
                element_id     = element.attr('id');

            if (type === 'input') {
                parent_element.show().find('.select2-container').remove();
                new_element = $('<input type="text" />').addClass('input-text');
            } else if (type === 'select') {
                new_element = $('<select></select>');

                let hezarfen_classes = '';
                if (element_id.includes('billing')) {
                    hezarfen_classes = element_id.includes('_city') ? wc_hezarfen_ajax_object.billing_district_field_classes : wc_hezarfen_ajax_object.billing_neighborhood_field_classes;
                } else {
                    hezarfen_classes = element_id.includes('_city') ? wc_hezarfen_ajax_object.shipping_district_field_classes : wc_hezarfen_ajax_object.shipping_neighborhood_field_classes;
                }

                new_element.addClass(hezarfen_classes);
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
