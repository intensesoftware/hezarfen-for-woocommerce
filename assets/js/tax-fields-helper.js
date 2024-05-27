class hezarfen_tax_fields_helper {
    page;

    constructor(page) {
        this.page = page;
    }

    add_event_handlers() {
        // prevent adding event handlers multiple times.
        this.remove_event_handlers();

        this.get_invoice_type_field().on('change.hezarfen', this.invoice_type_on_change);
        this.get_tax_number_field().on('input.hezarfen', this.tax_number_on_input);
        if (this.page === 'checkout') {
            this.get_tc_number_field().on('blur.hezarfen', { thisHelper: this }, this.validate);
        }
    }

    remove_event_handlers() {
        this.get_invoice_type_field().off('change.hezarfen');
        this.get_tax_number_field().off('input.hezarfen');
        if (this.page === 'checkout') {
            this.get_tc_number_field().off('blur.hezarfen');
        }
    }

    invoice_type_on_change() {
        var invoice_type = jQuery(this).val();

        if (invoice_type == 'person') {
            jQuery('#hezarfen_TC_number_field').removeClass('hezarfen-hide-form-field');
            jQuery('#hezarfen_tax_number_field, #hezarfen_tax_office_field, #billing_company_field').addClass('hezarfen-hide-form-field');
        } else if (invoice_type == 'company') {
            jQuery('#hezarfen_TC_number_field').addClass('hezarfen-hide-form-field');
            jQuery('#hezarfen_tax_number_field, #hezarfen_tax_office_field, #billing_company_field').removeClass('hezarfen-hide-form-field');
        }
    }

    tax_number_on_input() {
        var inputValue = jQuery(this).val();
        if (/[^0-9]/.test(inputValue)) {
            jQuery(this).val(inputValue.replace(/[^0-9]/g, ''));
        }
    }

    validate(event) {
        let thisHelper = event.data.thisHelper;

        const $this = jQuery(this);
        const value = $this.val();
        const parent = $this.closest('.form-row');
        let validated = true;

        // TC Number
        if ($this.is(thisHelper.get_tc_number_field())) {
            if (value && value.length !== 11) {
                validated = false;
            }
        }

        if (!validated) {
            parent.removeClass('woocommerce-validated').addClass('woocommerce-invalid');
            if ($this.hasClass('validate-required')) {
                parent.addClass('woocommerce-invalid-required-field');
            }
        }
    }

    get_invoice_type_field() {
        return jQuery('#hezarfen_invoice_type');
    }

    get_tc_number_field() {
        return jQuery('#hezarfen_TC_number');
    }

    get_tax_number_field() {
        return jQuery('#hezarfen_tax_number');
    }
}
