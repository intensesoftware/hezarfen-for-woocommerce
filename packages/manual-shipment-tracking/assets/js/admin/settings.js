jQuery(function ($) {
	$(document).ready(function () {
		const notif_settings_rows = $('.notification').closest('tr');
		const notif_providers = $('.notif-provider');
		const pandasms_radio = $(`input[value="${hezarfen_mst_backend.pandasms_key}"]`);
		const recognition_settings_rows = $('.recognition').closest('tr');
		const recognition_types = $('.recognition-type');
		const sms_textarea = $('.netgsm.sms-content');

		// add classes to the "tr" elements to style them with CSS.
		notif_settings_rows.addClass('notification');
		recognition_settings_rows.addClass('recognition');

		checkbox_show_hide_related_settings($('.enable-sms-notif'), notif_settings_rows, notif_providers);
		radio_show_hide_related_settings(notif_providers, $('.netgsm').closest('tr'), hezarfen_mst_backend.netgsm_key);

		checkbox_show_hide_related_settings($('.recogize-data'), recognition_settings_rows, recognition_types);
		radio_show_hide_related_settings(recognition_types, $('.custom-meta').closest('tr'), hezarfen_mst_backend.recognize_custom_meta_key);

		if (pandasms_radio.is(':disabled')) {
			const install_pandasms_link = $('<a class="pandasms-link"></a>').text(hezarfen_mst_backend.install_pandasms_link_text);
			pandasms_radio.parent().append(install_pandasms_link);

			install_pandasms_link.on('click', function () {
				if (hezarfen_mst_backend.is_pandasms_installed) {
					activate_pandasms_plugin();
				} else {
					$.post(
						ajaxurl,
						{
							action: 'install-plugin',
							_ajax_nonce: hezarfen_mst_backend.plugin_install_nonce,
							slug: 'pandasms-for-woocommerce'
						},
						function (response) {
							if (response.success) {
								activate_pandasms_plugin();
							} else {
								alert(`${hezarfen_mst_backend.install_pandasms_fail_text}\nError message: "${response.data.errorMessage}"`);
							}
						}
					);
				}
			});
		}

		if (sms_textarea.is(':enabled')) {
			$('.sms-variable').on('click', function () { // Insert variable to textarea.
				const start = sms_textarea.prop('selectionStart');
				const end = sms_textarea.prop('selectionEnd');
				const textarea_text = sms_textarea.val();
				const inserted = textarea_text.substring(0, start) + this.innerText + textarea_text.substring(end);

				sms_textarea.val(inserted).prop('selectionEnd', end + this.innerText.length).focus();
			});
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

	function activate_pandasms_plugin() {
		$.get(
			hezarfen_mst_backend.activate_pandasms_url,
			function () {
				alert(hezarfen_mst_backend.install_pandasms_success_text);
				location.reload();
			}
		).fail(function () {
			alert(hezarfen_mst_backend.install_pandasms_fail_text);
		});
	}
});
