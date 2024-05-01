jQuery(function ($) {
	$(document).ready(function () {
		const shipment_info_icon = $('.column-hezarfen_mst_shipment_info .shipment-info-icon');

		init_tooltip(shipment_info_icon, hezarfen_mst_backend.tooltip_placeholder);

		shipment_info_icon.on('mouseenter', function () {
			const $this = $(this);
			if ($this.data('shipment-info-saved')) {
				return;
			}

			$.get(
				ajaxurl,
				{
					action: hezarfen_mst_backend.get_shipment_data_action,
					_wpnonce: hezarfen_mst_backend.get_shipment_data_nonce,
					order_id: $this.data('orderId')
				},
				function (response) {
					const tooltip_content = create_tooltip_content(response.data);

					if ($this.is(':hover')) {
						$('#tiptip_content').html(tooltip_content);
					}

					init_tooltip($this, tooltip_content);

					$this.data('shipment-info-saved', true);
				},
				'json'
			);
		});
	});

	function init_tooltip(elements, content) {
		elements.tipTip({
			'fadeIn': 50,
			'fadeOut': 50,
			'delay': 200,
			'content': content
		});
	}

	function create_tooltip_content(shipment_data) {
		let content = $('<div></div>');

		$.each(shipment_data, function (i, data) {
			const wrapper = $('<div></div>');
			const courier_title = $(`<strong>${hezarfen_mst_backend.courier_company_i18n}</strong>: <span>${data.courier_title}</span>`);
			const tracking_num = $(`<strong>${hezarfen_mst_backend.tracking_num_i18n}</strong>: <span>${data.tracking_num}</span>`);

			content.append(wrapper.append(courier_title, $('<br>'), tracking_num));
			if (i !== shipment_data.length - 1) {
				content.append($('<br>'));
			}
		});

		return content;
	}
});
