/**
 * Hezarfen Roadmap Voting Script
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		// Check if roadmap voting exists on this page
		if (!$('.hezarfen-roadmap-container').length || typeof hezarfenRoadmap === 'undefined') {
			return;
		}

		// Update counter display
		function updateCounter(type) {
			const count = $('.hezarfen-features-list[data-type="' + type + '"] input[type="checkbox"]:checked').length;
			const max = 5;
			const $counter = $('#' + type + '-counter');
			
			$counter.text('(' + count + '/' + max + ' seçildi)');
			
			if (count >= max) {
				$counter.css('color', '#d63638').css('font-weight', 'bold');
			} else {
				$counter.css('color', '#666').css('font-weight', 'normal');
			}
		}

		// Handle checkbox limit enforcement
		$('.hezarfen-features-list input[type="checkbox"]').on('change', function() {
			const $list = $(this).closest('.hezarfen-features-list');
			const max = parseInt($list.data('max'));
			const type = $list.data('type');
			const $checkboxes = $list.find('input[type="checkbox"]');
			const checkedCount = $checkboxes.filter(':checked').length;
			const $item = $(this).closest('.hezarfen-feature-item');

			// Update counter
			updateCounter(type);

			// Disable unchecked checkboxes if limit reached
			if (checkedCount >= max) {
				$checkboxes.not(':checked').prop('disabled', true).closest('.hezarfen-feature-item')
					.css({
						'opacity': '0.4',
						'cursor': 'not-allowed',
						'background-color': '#f5f5f5'
					})
					.find('input').css('cursor', 'not-allowed');
			} else {
				$checkboxes.prop('disabled', false).closest('.hezarfen-feature-item')
					.css({
						'opacity': '1',
						'cursor': 'pointer'
					})
					.find('input').css('cursor', 'pointer');
			}

			// Visual feedback for selected items
			if ($(this).is(':checked')) {
				$item.css({
					'background-color': type === 'free' ? '#e7f5ff' : '#ecfdf5',
					'border-color': type === 'free' ? '#2271b1' : '#16a34a',
					'border-width': '2px',
					'box-shadow': type === 'free' ? '0 0 0 1px #2271b1' : '0 0 0 1px #16a34a'
				});
			} else {
				$item.css({
					'background-color': '#fff',
					'border-color': '#ddd',
					'border-width': '1px',
					'box-shadow': 'none'
				});
			}
		});

		// Handle vote submission
		$('#hezarfen-submit-votes').on('click', function(e) {
			e.preventDefault();

			const $button = $(this);
			const $message = $('.hezarfen-vote-message');
			
			// Get selected features
			const freeFeatures = [];
			const proFeatures = [];
			
			$('input[name="free_features[]"]:checked').each(function() {
				freeFeatures.push($(this).val());
			});
			
			$('input[name="pro_features[]"]:checked').each(function() {
				proFeatures.push($(this).val());
			});

			// Validate
			if (freeFeatures.length === 0 && proFeatures.length === 0) {
				$message.css('color', '#d63638').text('Lütfen en az bir özellik seçin.').show();
				return;
			}

			if (freeFeatures.length > 5) {
				$message.css('color', '#d63638').text('En fazla 5 ücretsiz özellik seçebilirsiniz.').show();
				return;
			}

			if (proFeatures.length > 5) {
				$message.css('color', '#d63638').text('En fazla 5 ücretli özellik seçebilirsiniz.').show();
				return;
			}

			// Disable button and show loading
			$button.prop('disabled', true).text('Gönderiliyor...');
			$message.hide();

			// Send AJAX request
			$.ajax({
				url: hezarfenRoadmap.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'hezarfen_submit_roadmap_votes',
					nonce: hezarfenRoadmap.nonce,
					free_features: freeFeatures,
					pro_features: proFeatures
				},
				success: function(response) {
					if (response.success) {
						$message.css('color', '#00a32a').html('✓ ' + response.data.message).show();
						
						// Disable all checkboxes after successful submission
						$('.hezarfen-features-list input[type="checkbox"]').prop('disabled', true);
						$button.text('Gönderildi').css('background-color', '#00a32a');
					} else {
						var errorMsg = response.data && response.data.message ? response.data.message : 'Bir hata oluştu.';
						$message.css('color', '#d63638').html('✗ ' + errorMsg).show();
						$button.prop('disabled', false).text('Oylarımı Gönder');
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX Error:', xhr.responseText, status, error);
					var errorMsg = 'Bir hata oluştu. Lütfen tekrar deneyin.';
					if (xhr.status === 400) {
						errorMsg = 'Geçersiz istek. Lütfen sayfayı yenileyip tekrar deneyin.';
					} else if (xhr.status === 403) {
						errorMsg = 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.';
					}
					$message.css('color', '#d63638').text(errorMsg).show();
					$button.prop('disabled', false).text('Oylarımı Gönder');
				}
			});
		});

		// Hover effects
		$(document).on('mouseenter', '.hezarfen-feature-item', function() {
			const $checkbox = $(this).find('input[type="checkbox"]');
			const type = $(this).attr('data-feature-type');
			
			if ($checkbox.is(':disabled')) {
				// Already disabled, no hover effect
				return;
			}
			
			if ($checkbox.is(':checked')) {
				// Brighten the selected color
				$(this).css({
					'background-color': type === 'free' ? '#d4ebff' : '#d1fae5',
					'transform': 'translateX(2px)'
				});
			} else {
				// Light hover for unselected
				$(this).css({
					'background-color': '#fafafa',
					'border-color': '#999',
					'transform': 'translateX(2px)'
				});
			}
		}).on('mouseleave', '.hezarfen-feature-item', function() {
			const $checkbox = $(this).find('input[type="checkbox"]');
			const type = $(this).attr('data-feature-type');
			
			if ($checkbox.is(':disabled')) {
				return;
			}
			
			if ($checkbox.is(':checked')) {
				$(this).css({
					'background-color': type === 'free' ? '#e7f5ff' : '#ecfdf5',
					'transform': 'translateX(0)'
				});
			} else {
				$(this).css({
					'background-color': '#fff',
					'border-color': '#ddd',
					'transform': 'translateX(0)'
				});
			}
		});
	});

})(jQuery);
