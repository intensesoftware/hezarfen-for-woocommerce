var wc_hezarfen_checkout = {
    mbgb_plugin_active: typeof wc_hezarfen_mbgb_backend !== 'undefined',
    should_notify_neighborhood_changed: function (type) {
        if( wc_hezarfen_ajax_object.should_notify_neighborhood_changed ) {
            return true;
        }

        return this.mbgb_plugin_active && (wc_hezarfen_mbgb_backend.address_source === type || (wc_hezarfen_mbgb_backend.address_source === 'shipping' && !this.ship_to_different_checked()))
    },
    ship_to_different_checked: function () {
        return jQuery('#ship-to-different-address input').is(':checked');
    }
};

jQuery(function ($) {
    $(document).ready(function () {
        for (const type of ['billing', 'shipping']) {
            let wrapper = $(`.woocommerce-${type}-fields`);
            let mahalle_helper = new hezarfen_mahalle_helper(wrapper, type, 'checkout');

            let current_country_code = mahalle_helper.get_country_field().val();

            if (!current_country_code || current_country_code === 'TR') {
                mahalle_helper.convert_fields_to_selectwoo();
            }

            if (current_country_code === 'TR') {
                mahalle_helper.add_event_handlers();
                add_checkout_event_handlers(type, wrapper);
            }
        }

        $(document.body).on('country_to_state_changing', function (event, country_code, wrapper) {
            let type = wrapper.hasClass('woocommerce-billing-fields') ? 'billing' : 'shipping';
            new hezarfen_mahalle_helper(wrapper, type, 'checkout').on_country_change(country_code, get_additional_classes(type));
            country_on_change(type, country_code, wrapper);
        });

        new hezarfen_tax_fields_helper('checkout').add_event_handlers();
    });

    function add_checkout_event_handlers(type, wrapper) {
        // prevent adding event handlers multiple times.
        $(`#${type}_address_1`).off('change.hezarfen');
        $('#ship-to-different-address input').off('change.hezarfen');

        $(`#${type}_address_1`).on("change.hezarfen", function () {
            neighborhood_on_change($(this).val(), type, wrapper);
        });

        $('#ship-to-different-address input').on('change.hezarfen', function () {
            ship_to_different_on_change(this);
        });
    }

    function country_on_change(type, country_code, wrapper) {
        if (country_code === 'TR') {
            add_checkout_event_handlers(type, wrapper);
        }
    }

    function neighborhood_on_change(neighborhood, type, checkout_fields_wrapper) {
        if (wc_hezarfen_checkout.should_notify_neighborhood_changed(type)) {
            let province_plate_number = checkout_fields_wrapper.find(`#${type}_state`).val();
            let district = checkout_fields_wrapper.find(`#${type}_city`).val();

            notify_neighborhood_changed(province_plate_number, district, neighborhood, type);
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

    function notify_neighborhood_changed(province_plate_number, district, neighborhood, type) {
        if (!province_plate_number || !district || !neighborhood) {
            return;
        }

        var data = {
            'action': 'wc_hezarfen_neighborhood_changed',
            'security': wc_hezarfen_ajax_object.mahalleio_nonce,
            'cityPlateNumber': province_plate_number,
            'district': district,
            'neighborhood': neighborhood,
            'type': type
        };

        jQuery.post(wc_hezarfen_ajax_object.ajax_url, data, function (response) {
            if (response.update_checkout)
                jQuery('body').trigger('update_checkout');
        }, 'json');
    }

    function get_additional_classes(type) {
        return {
            district: type === 'billing' ? wc_hezarfen_ajax_object.billing_district_field_classes : wc_hezarfen_ajax_object.shipping_district_field_classes,
            neighborhood: type === 'billing' ? wc_hezarfen_ajax_object.billing_neighborhood_field_classes : wc_hezarfen_ajax_object.shipping_neighborhood_field_classes
        };
    }
});
