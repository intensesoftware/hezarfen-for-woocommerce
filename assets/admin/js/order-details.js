jQuery(document).ready(function ($) {
	const invoice_type_field = $('.hezarfen_billing_invoice_type_field');

	$('a.edit_address').on('click', function () {
		$('.hezarfen-tc-num-field').hide();
	});

	update_field_showing_statuses(invoice_type_field.val());

	invoice_type_field.on('change', function () {
		var invoice_type = $(this).val();
		update_field_showing_statuses(invoice_type);
	});

	function update_field_showing_statuses(invoice_type) {
		if (invoice_type == 'person') {
			$('._billing_hez_TC_number_field').removeClass('hezarfen-hide-form-field');
			$('._billing_hez_tax_number_field').addClass('hezarfen-hide-form-field');
			$('._billing_hez_tax_office_field').addClass('hezarfen-hide-form-field');
		} else if (invoice_type == 'company') {
			$('._billing_hez_TC_number_field').addClass('hezarfen-hide-form-field');
			$('._billing_hez_tax_number_field').removeClass('hezarfen-hide-form-field');
			$('._billing_hez_tax_office_field').removeClass('hezarfen-hide-form-field');
		}
	}
});
