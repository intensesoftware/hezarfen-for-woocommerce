jQuery(function ($) {
	$(document).ready(function () {
		const metabox_wrapper = $('#hezarfen-mst-order-edit-metabox');
		const courier_selects = metabox_wrapper.find('.courier-company-select');

		courier_selects.selectWoo();
	});
});
