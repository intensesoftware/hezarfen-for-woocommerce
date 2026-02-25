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
		queue: [],        // Orders to process: { order_id, desi }
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
	}

	/**
	 * Apply bulk desi value to all orders without barcodes.
	 */
	function applyBulkDesi() {
		var desiVal = parseFloat($('#hezarfen-bulk-desi-input').val());

		if (!desiVal || desiVal < 0.01) {
			$('#hezarfen-bulk-desi-input').focus();
			return;
		}

		$('#hezarfen-bulk-orders-table tbody tr').each(function () {
			var $row = $(this);
			if ($row.data('has-barcode') !== 1 && $row.data('hasBarcode') !== 1) {
				$row.find('.hezarfen-desi-editable').val(desiVal);
			}
		});
	}

	/**
	 * Validates inputs and starts the sequential processing.
	 */
	function startProcessing() {
		// Client-side validation: every order without a barcode must have a desi.
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

			var desiVal = parseFloat($row.find('.hezarfen-desi-editable').val());
			if (!desiVal || desiVal < 0.01) {
				$row.find('.hezarfen-desi-editable').addClass('hezarfen-input-error');
				valid = false;
			} else {
				$row.find('.hezarfen-desi-editable').removeClass('hezarfen-input-error');
				queue.push({
					order_id: orderId,
					order_number: orderNumber,
					desi: desiVal
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
				desi: item.desi
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
		}

		$('#hezarfen-bulk-create-print').prop('disabled', false);
	}

	/**
	 * Retries failed orders.
	 */
	function retryFailed() {
		var failedItems = [];

		for (var i = 0; i < state.results.length; i++) {
			if (!state.results[i].success) {
				// Find the original queue item to get the desi.
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
	 * Collects all successful barcodes and opens a print window.
	 */
	function printBarcodes() {
		var printItems = [];

		// Gather from skipped (already had barcodes).
		for (var i = 0; i < state.skipped.length; i++) {
			printItems.push({
				order_id: state.skipped[i].order_id,
				order_number: state.skipped[i].order_number,
				delivery_no: state.skipped[i].barcode
			});
		}

		// Gather from successful results.
		for (var j = 0; j < state.results.length; j++) {
			if (state.results[j].success) {
				printItems.push({
					order_id: state.results[j].order_id,
					order_number: state.results[j].order_number,
					delivery_no: state.results[j].barcode
				});
			}
		}

		if (printItems.length === 0) {
			return;
		}

		$('#hezarfen-print-btn').prop('disabled', true).text(i18n.preparing_print);

		fetchAndPrintBarcodes(printItems);
	}

	/**
	 * Fetches barcode label data sequentially, then opens a print window.
	 *
	 * @param {Array} items Items to print.
	 */
	function fetchAndPrintBarcodes(items) {
		var fetched = [];
		var fetchIndex = 0;

		function fetchNext() {
			if (fetchIndex >= items.length) {
				openPrintWindow(fetched);
				return;
			}

			var item = items[fetchIndex];
			fetchIndex++;

			$.ajax({
				url: config.ajax_url,
				type: 'POST',
				data: {
					action: config.get_barcode_action,
					_ajax_nonce: config.get_barcode_nonce,
					delivery_no: item.delivery_no,
					order_id: item.order_id
				},
				timeout: 30000,
				success: function (response) {
					if (response.success && response.data) {
						fetched.push({
							order_number: item.order_number,
							delivery_no: item.delivery_no,
							barcode_data: response.data.barcode_data
						});
					}
					fetchNext();
				},
				error: function () {
					// Skip items we can't fetch barcode data for.
					fetchNext();
				}
			});
		}

		fetchNext();
	}

	/**
	 * Opens a new window with all barcode labels for printing.
	 *
	 * @param {Array} items Array of { order_number, delivery_no, barcode_data }.
	 */
	function openPrintWindow(items) {
		$('#hezarfen-print-btn').prop('disabled', false).text(i18n.print_btn + ' (' + (state.successCount + state.skipped.length) + ')');

		if (items.length === 0) {
			alert(i18n.print_error);
			return;
		}

		var html = '<!DOCTYPE html><html><head><meta charset="UTF-8">' +
			'<title>HepsiJet Barkodlar</title>' +
			'<style>' +
			'* { margin: 0; padding: 0; box-sizing: border-box; }' +
			'body { font-family: Arial, sans-serif; }' +
			'.barcode-page { page-break-after: always; padding: 10mm; text-align: center; }' +
			'.barcode-page:last-child { page-break-after: auto; }' +
			'.barcode-header { font-size: 14px; margin-bottom: 5mm; font-weight: bold; }' +
			'.barcode-img { max-width: 100%; height: auto; }' +
			'@media print {' +
			'  .barcode-page { page-break-after: always; padding: 5mm; }' +
			'  .barcode-page:last-child { page-break-after: auto; }' +
			'}' +
			'</style></head><body>';

		for (var i = 0; i < items.length; i++) {
			var item = items[i];
			html += '<div class="barcode-page">';
			html += '<div class="barcode-header">#' + escapeHtml(item.order_number) + ' &mdash; ' + escapeHtml(item.delivery_no) + '</div>';

			if (item.barcode_data && Array.isArray(item.barcode_data) && item.barcode_data.length > 0) {
				for (var j = 0; j < item.barcode_data.length; j++) {
					var imgSrc = item.barcode_data[j];
					// Add data URI prefix if not present.
					if (imgSrc && imgSrc.indexOf('data:') !== 0) {
						imgSrc = 'data:image/jpeg;base64,' + imgSrc;
					}
					html += '<img class="barcode-img" src="' + imgSrc + '" alt="Barcode" />';
				}
			}

			html += '</div>';
		}

		html += '<script>window.onload=function(){window.print();}<\/script>';
		html += '</body></html>';

		var printWin = window.open('', '_blank', 'width=800,height=600');
		if (printWin) {
			printWin.document.write(html);
			printWin.document.close();
		}
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