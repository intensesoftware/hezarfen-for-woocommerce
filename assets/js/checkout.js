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
        function replaceWithInput(element) {
            parent_element = element.closest('.form-row'),
            input_name     = element.attr('name'),
            input_id       = element.attr('id'),
            input_classes  = element.attr('data-input-classes'),
            placeholder    = element.attr('placeholder') || element.attr('data-placeholder') || '',

            new_element = $('<input type="text" />')
                .prop('id', input_id)
                .prop('name', input_name)
                .prop('placeholder', placeholder)
                .attr('data-input-classes', input_classes)
                .addClass('input-text  ' + input_classes);
            parent_element.show().find('.select2-container').remove();
            element.replaceWith(new_element);
        }

        // If a country other than Turkey is selected, handle this situation.
        if (country_code !== 'TR') {
            let elements = wrapper.find('#billing_city, #shipping_city, #billing_address_1, #shipping_address_1');
            elements.each(function () {
                let $this = $(this);
                if ($this.is('select')) {
                    replaceWithInput($this);
                }
            });
        }
    });

    $.each(["billing", "shipping"], function(index, type){
        let checkout_fields_wrapper = $('.woocommerce-' + type + '-fields');

        $("#"+type+"_state").on("select2:select", function(e){
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
        });

        $('#' + type + '_city').on("select2:select", function(e){
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
        });

        $('#' + type + '_address_1').on("select2:select", function(e){
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
        });
    });
} );
