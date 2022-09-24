jQuery(function ($) {
	$('select#billing_city, select#billing_address_1, select#shipping_city, select#shipping_address_1').each(function () {
		let self = $(this);
		self.selectWoo({
			placeholder: self.attr('data-placeholder') || self.attr('placeholder') || '',
			width: '100%'
		});
	});
});
