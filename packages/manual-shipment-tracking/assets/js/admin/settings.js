jQuery(function ($) {
	$(document).ready(function () {
		const notif_settings_rows = $('.notification').closest('tr');
		const notif_providers_row = $('.notif-provider').closest('tr');
		const custom_meta_rows = $('.custom-meta').closest('tr');

		// add classes to the "tr" elements to style them with CSS.
		notif_settings_rows.addClass('notification');
		custom_meta_rows.addClass('custom-meta');

		show_hide_related_settings($('.enable-sms-notif'), notif_settings_rows, notif_providers_row);

		notif_providers_row.on('change', function () {
			const $this = $(this);
			if ($this.is(':visible')) {
				const is_netgsm_selected = $this.find('.notif-provider:checked').val() === 'netgsm';
				$('.netgsm').closest('tr').toggle(is_netgsm_selected); // toggle visibility of the NetGSM settings.
			}
		}).trigger('change');

		const sms_textarea = $('.netgsm.sms-content');

		if (sms_textarea.is(':enabled')) {
			$('.sms-variable').on('click', insertVariable);
		}

		function insertVariable() {
			const start = sms_textarea.prop('selectionStart');
			const end = sms_textarea.prop('selectionEnd');
			const textarea_text = sms_textarea.val();
			const inserted = textarea_text.substring(0, start) + this.innerText + textarea_text.substring(end);

			sms_textarea.val(inserted).prop('selectionEnd', end + this.innerText.length).focus();
		}

		$('.recognize-custom-meta').on('change', function () {
			custom_meta_rows.toggle($(this).is(':checked')); // toggle visibility of the "Recognize custom post meta data" settings.
		}).trigger('change');
	});

	function show_hide_related_settings(checkbox, related_settings, trigger_change_elements = null) {
		checkbox.on('change', function () {
			related_settings.toggle($(this).is(':checked')); // toggle visibility of the related settings.
			if (trigger_change_elements) {
				trigger_change_elements.trigger('change');
			}
		}).trigger('change');
	}
});
