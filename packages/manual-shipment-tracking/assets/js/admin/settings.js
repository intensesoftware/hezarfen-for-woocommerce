jQuery(function ($) {
	$(document).ready(function () {
		const notif_settings_rows = $('.notification').closest('tr');
		const notif_providers = $('.notif-provider');
		const recognition_settings_rows = $('.recognition').closest('tr');
		const recognition_types = $('.recognition-type');

		// add classes to the "tr" elements to style them with CSS.
		notif_settings_rows.addClass('notification');
		recognition_settings_rows.addClass('recognition');

		checkbox_show_hide_related_settings($('.enable-sms-notif'), notif_settings_rows, notif_providers);
		radio_show_hide_related_settings(notif_providers, $('.netgsm').closest('tr'), 'netgsm');

		checkbox_show_hide_related_settings($('.recogize-data'), recognition_settings_rows, recognition_types);
		radio_show_hide_related_settings(recognition_types, $('.custom-meta').closest('tr'), 'hezarfen_mst_recognize_custom_meta');

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
	});

	function checkbox_show_hide_related_settings(checkbox, related_settings, trigger_change_elements) {
		checkbox.on('change', function () {
			related_settings.toggle($(this).is(':checked')); // toggle visibility of the related settings.
			trigger_change_elements.trigger('change');
		}).trigger('change');
	}

	function radio_show_hide_related_settings(radio_buttons, related_settings, radio_button_value) {
		radio_buttons.on('change', function () {
			const $this = $(this);
			if ($this.is(':visible')) {
				related_settings.toggle($this.val() === radio_button_value && $this.is(':checked'));
			}
		}).trigger('change');
	}
});
