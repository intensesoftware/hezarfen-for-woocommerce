/**
 * HepsiJet Bulk Barcode — Sequential AJAX processing, progress UI, and print.
 *
 * @package Hezarfen\ManualShipmentTracking
 */
(function ($) {
	'use strict';

	var config = window.hezarfen_bulk_barcode || {};
	var i18n = config.i18n || {};

	/** Processing state */
	var state = {
		isProcessing: false,
		isCancelled: false,
		queue: [],        // Orders to process: { order_id, order_number, packages: [{ desi }] }
		results: [],      // { order_id, order_number, success, barcode, message }
		skipped: [],      // Orders that already had barcodes
		totalToProcess: 0,
		processed: 0,
		successCount: 0,
		errorCount: 0,
		startTime: 0,
		elapsedTimes: []  // Track individual request durations for ETA
	};

	/**
	 * Initialization.
	 */
	function init() {
		$('#hezarfen-bulk-desi-apply').on('click', applyBulkDesi);
		$('#hezarfen-bulk-create-print').on('click', startProcessing);
		$('#hezarfen-cancel-btn').on('click', cancelProcessing);
		$('#hezarfen-print-btn').on('click', printBarcodes);
		$('#hezarfen-retry-btn').on('click', retryFailed);

		// Bulk packages section: add/remove.
		$('#hezarfen-bulk-add-package').on('click', function () {
			addPackageToContainer($('#hezarfen-bulk-packages-container'));
		});

		$('#hezarfen-bulk-packages-container').on('click', '.hezarfen-remove-package', function () {
			var $container = $(this).closest('.hezarfen-packages-container');
			if ($container.find('.hezarfen-package-item').length > 1) {
				$(this).closest('.hezarfen-package-item').remove();
				updatePackageLabels($container);
			}
		});

		// Per-order package handlers (delegated on table).
		$('#hezarfen-bulk-orders-table').on('click', '.hezarfen-add-package', function () {
			var $container = $(this).siblings('.hezarfen-packages-container');
			addPackageToContainer($container);
		});

		$('#hezarfen-bulk-orders-table').on('click', '.hezarfen-remove-package', function () {
			var $container = $(this).closest('.hezarfen-packages-container');
			if ($container.find('.hezarfen-package-item').length > 1) {
				$(this).closest('.hezarfen-package-item').remove();
				updatePackageLabels($container);
			}
		});

		// If all orders already have barcodes, show print button directly.
		checkAllBarcodesReady();
	}

	/**
	 * Adds a new package input to a container.
	 */
	function addPackageToContainer($container) {
		var $item = $('<div class="hezarfen-package-item">' +
			'<span class="hezarfen-package-label"></span>' +
			'<input type="number" class="hezarfen-package-desi" min="0.01" max="9999" step="0.01" placeholder="Desi" />' +
			'<button type="button" class="hezarfen-remove-package" title="' + escapeHtml(i18n.remove || 'Kaldır') + '">&times;</button>' +
			'</div>');
		$container.append($item);
		updatePackageLabels($container);
	}

	/**
	 * Updates package labels (Koli 1, Koli 2, ...) and remove button visibility.
	 */
	function updatePackageLabels($container) {
		$container.find('.hezarfen-package-item').each(function (index) {
			$(this).find('.hezarfen-package-label').text((i18n.package_label || 'Koli') + ' ' + (index + 1) + ':');
			if (index === 0) {
				$(this).find('.hezarfen-remove-package').css('visibility', 'hidden');
			} else {
				$(this).find('.hezarfen-remove-package').css('visibility', 'visible');
			}
		});
	}

	/**
	 * Checks if all orders already have barcodes on page load.
	 * If so, populates state.skipped and shows the print button directly.
	 */
	function checkAllBarcodesReady() {
		var $rows = $('#hezarfen-bulk-orders-table tbody tr');
		if ($rows.length === 0) {
			return;
		}

		var allReady = true;
		var skipped = [];

		$rows.each(function () {
			var $row = $(this);
			var hasBarcode = $row.data('has-barcode') === 1 || $row.data('hasBarcode') === 1;

			if (!hasBarcode) {
				allReady = false;
				return false; // break
			}

			skipped.push({
				order_id: parseInt($row.data('order-id'), 10),
				order_number: $row.find('.column-order-number strong').text().trim(),
				barcode: $row.data('delivery-no') || $row.data('deliveryNo')
			});
		});

		if (allReady && skipped.length > 0) {
			state.skipped = skipped;

			// Hide the create button, show print button directly.
			$('#hezarfen-bulk-create-print').hide();
			$('.hezarfen-bulk-desi-section').hide();
			$('#hezarfen-bulk-results-section').show();
			$('#hezarfen-results-summary').html(
				'<div class="notice notice-success inline"><p>' +
				escapeHtml(i18n.all_barcodes_ready || 'Tüm barkodlar hazır.') +
				'</p></div>'
			);
			$('#hezarfen-print-btn').text((i18n.print_barcodes_btn || 'Barkodları Yazdır') + ' (' + skipped.length + ')').show();
		}
	}

	/**
	 * Apply bulk packages to all orders without barcodes.
	 * Clones the package structure from the bulk section into each order row.
	 */
	function applyBulkDesi() {
		// Collect packages from the bulk section.
		var bulkPackages = [];
		var hasError = false;

		$('#hezarfen-bulk-packages-container .hezarfen-package-desi').each(function () {
			var desiVal = parseDesi($(this).val());
			if (!desiVal || desiVal < 0.01) {
				$(this).addClass('hezarfen-input-error');
				hasError = true;
			} else {
				$(this).removeClass('hezarfen-input-error');
				bulkPackages.push(desiVal);
			}
		});

		if (hasError || bulkPackages.length === 0) {
			return;
		}

		$('#hezarfen-bulk-orders-table tbody tr').each(function () {
			var $row = $(this);
			var hasBarcode = $row.data('has-barcode') === 1 || $row.data('hasBarcode') === 1;
			if (hasBarcode) {
				return; // continue
			}

			var $container = $row.find('.hezarfen-packages-container');

			// Clear existing packages.
			$container.empty();

			// Clone package structure from bulk section.
			for (var i = 0; i < bulkPackages.length; i++) {
				var $item = $('<div class="hezarfen-package-item">' +
					'<span class="hezarfen-package-label"></span>' +
					'<input type="number" class="hezarfen-package-desi" min="0.01" max="9999" step="0.01" placeholder="Desi" />' +
					'<button type="button" class="hezarfen-remove-package" title="' + escapeHtml(i18n.remove || 'Kaldır') + '">&times;</button>' +
					'</div>');
				$item.find('.hezarfen-package-desi').val(bulkPackages[i]);
				$container.append($item);
			}

			updatePackageLabels($container);
		});
	}

	/**
	 * Validates inputs and starts the sequential processing.
	 */
	function startProcessing() {
		var valid = true;
		var queue = [];

		$('#hezarfen-bulk-orders-table tbody tr').each(function () {
			var $row = $(this);
			var orderId = parseInt($row.data('order-id'), 10);
			var hasBarcode = $row.data('has-barcode') === 1 || $row.data('hasBarcode') === 1;
			var orderNumber = $row.find('.column-order-number strong').text().trim();

			if (hasBarcode) {
				// Will skip but still track for printing.
				state.skipped.push({
					order_id: orderId,
					order_number: orderNumber,
					barcode: $row.data('delivery-no') || $row.data('deliveryNo')
				});
				return; // continue
			}

			// Collect packages for this order.
			var packages = [];
			var orderValid = true;

			$row.find('.hezarfen-package-desi').each(function () {
				var desiVal = parseDesi($(this).val());
				if (!desiVal || desiVal < 0.01) {
					$(this).addClass('hezarfen-input-error');
					orderValid = false;
				} else {
					$(this).removeClass('hezarfen-input-error');
					packages.push({ desi: desiVal });
				}
			});

			if (!orderValid || packages.length === 0) {
				valid = false;
			} else {
				queue.push({
					order_id: orderId,
					order_number: orderNumber,
					packages: packages
				});
			}
		});

		if (!valid) {
			alert(i18n.desi_required);
			return;
		}

		if (queue.length === 0 && state.skipped.length === 0) {
			alert(i18n.no_orders_to_process);
			return;
		}

		// Initialize state.
		state.queue = queue;
		state.totalToProcess = queue.length;
		state.processed = 0;
		state.successCount = 0;
		state.errorCount = 0;
		state.results = [];
		state.isCancelled = false;
		state.isProcessing = true;
		state.startTime = Date.now();
		state.elapsedTimes = [];

		// If all already have barcodes, skip directly to results.
		if (queue.length === 0) {
			showResults();
			return;
		}

		showProgressUI();
		processNext(0);
	}

	/**
	 * Shows the progress section.
	 */
	function showProgressUI() {
		$('#hezarfen-bulk-create-print').prop('disabled', true);
		$('#hezarfen-bulk-progress-section').show();
		$('#hezarfen-bulk-results-section').hide();
		$('#hezarfen-progress-log').empty();
		updateProgress();

		// Add initial log entries.
		for (var i = 0; i < state.queue.length; i++) {
			addLogEntry(state.queue[i].order_id, state.queue[i].order_number, 'waiting', i18n.waiting);
		}
	}

	/**
	 * Processes the next order in the queue (sequential).
	 *
	 * @param {number} index Current index in the queue.
	 */
	function processNext(index) {
		if (state.isCancelled || index >= state.queue.length) {
			finishProcessing();
			return;
		}

		var item = state.queue[index];
		updateLogEntry(item.order_id, 'processing', i18n.processing);

		var requestStart = Date.now();

		$.ajax({
			url: config.ajax_url,
			type: 'POST',
			data: {
				action: config.create_action,
				_ajax_nonce: config.create_nonce,
				order_id: item.order_id,
				packages: JSON.stringify(item.packages)
			},
			timeout: 60000, // 60 seconds per request
			success: function (response) {
				var elapsed = Date.now() - requestStart;
				state.elapsedTimes.push(elapsed);
				state.processed++;

				if (response.success && response.data) {
					state.successCount++;
					var barcode = response.data.barcode || '';
					state.results.push({
						order_id: item.order_id,
						order_number: item.order_number,
						success: true,
						barcode: barcode,
						message: ''
					});
					updateLogEntry(item.order_id, 'success', barcode);

					// Update the table row.
					var $row = $('#hezarfen-bulk-orders-table tbody tr[data-order-id="' + item.order_id + '"]');
					$row.data('has-barcode', 1).attr('data-has-barcode', '1');
					$row.data('delivery-no', barcode).attr('data-delivery-no', barcode);
					$row.find('.column-barcode-status').html('<span class="hezarfen-status hezarfen-status-exists" title="">&#10004;</span>');
					$row.find('.column-barcode-number').html('<code>' + escapeHtml(barcode) + '</code> <span class="hezarfen-barcode-created-badge">' + escapeHtml(i18n.barcode_created) + '</span>');
				} else {
					state.errorCount++;
					var msg = (response.data && response.data.message) ? response.data.message : i18n.error;
					state.results.push({
						order_id: item.order_id,
						order_number: item.order_number,
						success: false,
						barcode: '',
						message: msg
					});
					updateLogEntry(item.order_id, 'error', msg);
				}

				updateProgress();
				processNext(index + 1);
			},
			error: function (xhr) {
				var elapsed = Date.now() - requestStart;
				state.elapsedTimes.push(elapsed);
				state.processed++;
				state.errorCount++;

				var msg = xhr.statusText || i18n.error;
				state.results.push({
					order_id: item.order_id,
					order_number: item.order_number,
					success: false,
					barcode: '',
					message: msg
				});
				updateLogEntry(item.order_id, 'error', msg);

				updateProgress();
				processNext(index + 1);
			}
		});
	}

	/**
	 * Updates the progress bar and counter.
	 */
	function updateProgress() {
		var total = state.totalToProcess;
		var done = state.processed;
		var pct = total > 0 ? Math.round((done / total) * 100) : 0;

		$('#hezarfen-progress-counter').text(done + '/' + total);
		$('#hezarfen-progress-percent').text('(' + pct + '%)');
		$('#hezarfen-progress-bar').css('width', pct + '%');

		// Estimated remaining time.
		if (state.elapsedTimes.length > 0) {
			var avg = state.elapsedTimes.reduce(function (a, b) { return a + b; }, 0) / state.elapsedTimes.length;
			var remaining = Math.ceil(((total - done) * avg) / 1000);
			$('#hezarfen-progress-estimated').text(i18n.estimated_remaining + ': ~' + remaining + ' ' + i18n.seconds_abbr).show();
		}
	}

	/**
	 * Adds a log entry to the progress log.
	 */
	function addLogEntry(orderId, orderNumber, status, message) {
		var icon = getStatusIcon(status);
		var $entry = $('<div class="hezarfen-log-entry" data-log-order="' + orderId + '">' +
			'<span class="hezarfen-log-icon">' + icon + '</span> ' +
			'<span class="hezarfen-log-order">' + escapeHtml(orderNumber) + '</span>' +
			' &mdash; <span class="hezarfen-log-message">' + escapeHtml(message) + '</span>' +
			'</div>');
		$('#hezarfen-progress-log').append($entry);
	}

	/**
	 * Updates an existing log entry.
	 */
	function updateLogEntry(orderId, status, message) {
		var $entry = $('.hezarfen-log-entry[data-log-order="' + orderId + '"]');
		if ($entry.length) {
			$entry.find('.hezarfen-log-icon').html(getStatusIcon(status));
			$entry.find('.hezarfen-log-message').text(message);
			$entry.attr('class', 'hezarfen-log-entry hezarfen-log-' + status);
		}
	}

	/**
	 * Returns the icon for a given status.
	 */
	function getStatusIcon(status) {
		switch (status) {
			case 'success':   return '<span class="hezarfen-icon-success">&#10004;</span>';
			case 'error':     return '<span class="hezarfen-icon-error">&#10008;</span>';
			case 'processing': return '<span class="hezarfen-icon-processing">&#9203;</span>';
			case 'cancelled':  return '<span class="hezarfen-icon-cancelled">&#9724;</span>';
			default:           return '<span class="hezarfen-icon-waiting">&#11036;</span>';
		}
	}

	/**
	 * Cancels the processing.
	 */
	function cancelProcessing() {
		if (confirm(i18n.confirm_cancel)) {
			state.isCancelled = true;

			// Mark remaining items as cancelled.
			for (var i = state.processed; i < state.queue.length; i++) {
				updateLogEntry(state.queue[i].order_id, 'cancelled', i18n.cancelled);
			}
		}
	}

	/**
	 * Finishes the processing and shows results.
	 */
	function finishProcessing() {
		state.isProcessing = false;
		$('#hezarfen-cancel-btn').hide();
		$('#hezarfen-progress-estimated').hide();
		$('#hezarfen-progress-title').text(i18n.completed);
		showResults();
	}

	/**
	 * Shows the results section.
	 */
	function showResults() {
		var total = state.successCount + state.skipped.length;
		var summaryHtml = '';

		if (state.errorCount === 0 && state.totalToProcess > 0) {
			summaryHtml = '<div class="notice notice-success inline"><p>' +
				i18n.completed + ' &mdash; ' + total + ' ' + i18n.success.toLowerCase() +
				'</p></div>';
		} else if (state.errorCount > 0) {
			summaryHtml = '<div class="notice notice-warning inline"><p>' +
				i18n.completed + ' &mdash; ' + total + ' ' + i18n.success.toLowerCase() +
				', ' + state.errorCount + ' ' + i18n.error.toLowerCase() +
				'</p></div>';
		} else {
			summaryHtml = '<div class="notice notice-info inline"><p>' +
				i18n.completed + ' &mdash; ' + state.skipped.length + ' ' + i18n.skipped.toLowerCase() +
				'</p></div>';
		}

		$('#hezarfen-results-summary').html(summaryHtml);
		$('#hezarfen-bulk-results-section').show();

		// Show print button if there are any successful/existing barcodes.
		if (total > 0) {
			$('#hezarfen-print-btn').text(i18n.print_btn + ' (' + total + ')').show();
		}

		// Show retry button if there are errors.
		if (state.errorCount > 0) {
			$('#hezarfen-retry-btn').text(i18n.retry_btn + ' (' + state.errorCount + ')').show();
			$('#hezarfen-bulk-create-print').prop('disabled', false);
		} else {
			// All successful — hide create button and desi section.
			$('#hezarfen-bulk-create-print').hide();
			$('.hezarfen-bulk-desi-section').hide();
		}
	}

	/**
	 * Retries failed orders.
	 */
	function retryFailed() {
		var failedItems = [];

		for (var i = 0; i < state.results.length; i++) {
			if (!state.results[i].success) {
				// Find the original queue item to get the packages.
				for (var j = 0; j < state.queue.length; j++) {
					if (state.queue[j].order_id === state.results[i].order_id) {
						failedItems.push(state.queue[j]);
						break;
					}
				}
			}
		}

		if (failedItems.length === 0) {
			return;
		}

		// Remove failed results.
		state.results = state.results.filter(function (r) { return r.success; });

		// Reset counters.
		state.queue = failedItems;
		state.totalToProcess = failedItems.length;
		state.processed = 0;
		state.errorCount = 0;
		state.isCancelled = false;
		state.isProcessing = true;
		state.startTime = Date.now();
		state.elapsedTimes = [];

		// Keep existing successCount intact.
		showProgressUI();
		processNext(0);
	}

	/**
	 * Collects all successful barcodes and requests a combined PDF.
	 */
	function printBarcodes() {
		var printItems = [];

		// Gather from skipped (already had barcodes).
		for (var i = 0; i < state.skipped.length; i++) {
			printItems.push({
				order_id: state.skipped[i].order_id,
				delivery_no: state.skipped[i].barcode
			});
		}

		// Gather from successful results.
		for (var j = 0; j < state.results.length; j++) {
			if (state.results[j].success) {
				printItems.push({
					order_id: state.results[j].order_id,
					delivery_no: state.results[j].barcode
				});
			}
		}

		if (printItems.length === 0) {
			return;
		}

		// Open new tab synchronously (before AJAX) to avoid popup blocker.
		var printWin = window.open('about:blank', '_blank');

		// Show loading message in the new tab while PDF is generated.
		if (printWin) {
			printWin.document.write(
				'<!DOCTYPE html><html><head><meta charset="UTF-8"><title>PDF Hazırlanıyor...</title>' +
				'<style>body{display:flex;align-items:center;justify-content:center;height:100vh;margin:0;' +
				'font-family:Arial,sans-serif;background:#f0f0f1;color:#333;}' +
				'.loader{text-align:center;}.spinner{border:4px solid #e5e5e5;border-top:4px solid #2271b1;' +
				'border-radius:50%;width:40px;height:40px;animation:spin 1s linear infinite;margin:0 auto 16px;}' +
				'@keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}</style></head>' +
				'<body><div class="loader"><div class="spinner"></div><p>' + escapeHtml(i18n.preparing_print) + '</p></div></body></html>'
			);
			printWin.document.close();
		}

		$('#hezarfen-print-btn').prop('disabled', true).text(i18n.preparing_print);

		$.ajax({
			url: config.ajax_url,
			type: 'POST',
			data: {
				action: config.combined_action,
				_ajax_nonce: config.combined_nonce,
				orders: JSON.stringify(printItems)
			},
			timeout: 120000, // 2 minutes for combined PDF
			success: function (response) {
				$('#hezarfen-print-btn').prop('disabled', false).text(i18n.print_btn + ' (' + (state.successCount + state.skipped.length) + ')');

				if (response.success && response.data && response.data.pdf_url) {
					if (printWin) {
						var blobUrl = dataUriToBlobUrl(response.data.pdf_url);
						printWin.location.href = blobUrl;
					}
				} else {
					var msg = (response.data && response.data.message) ? response.data.message : i18n.print_error;
					alert(msg);
					if (printWin) {
						printWin.close();
					}
				}
			},
			error: function () {
				$('#hezarfen-print-btn').prop('disabled', false).text(i18n.print_btn + ' (' + (state.successCount + state.skipped.length) + ')');
				alert(i18n.print_error);
				if (printWin) {
					printWin.close();
				}
			}
		});
	}

	/**
	 * Converts a base64 data URI to a Blob URL (browsers block data: URL navigation).
	 */
	function dataUriToBlobUrl(dataUri) {
		var parts = dataUri.split(',');
		var mime = parts[0].match(/:(.*?);/)[1];
		var raw = atob(parts[1]);
		var arr = new Uint8Array(raw.length);
		for (var i = 0; i < raw.length; i++) {
			arr[i] = raw.charCodeAt(i);
		}
		var blob = new Blob([arr], { type: mime });
		return URL.createObjectURL(blob);
	}

	/**
	 * Parses a desi value, converting comma decimal separator to dot.
	 */
	function parseDesi(val) {
		if (typeof val === 'string') {
			val = val.replace(',', '.');
		}
		return parseFloat(val);
	}

	/**
	 * Escapes HTML characters.
	 */
	function escapeHtml(text) {
		if (!text) return '';
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(text));
		return div.innerHTML;
	}

	$(document).ready(init);

})(jQuery);
