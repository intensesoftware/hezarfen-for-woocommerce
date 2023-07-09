jQuery(document).ready(function ($) {
	const recovery_checkbox = $('.encryption-recovery-confirmation');

	$('#mainform').on('submit', function (event) {
		if (!recovery_checkbox.is(':checked')) {
			event.preventDefault();
			recovery_checkbox.addClass('error');
		}
	});

	recovery_checkbox.on('click', function () {
		$(this).removeClass('error');
	});
});
