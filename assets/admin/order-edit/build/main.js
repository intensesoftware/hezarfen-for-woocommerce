/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./assets/admin/order-edit/src/main.js":
/*!*********************************************!*\
  !*** ./assets/admin/order-edit/src/main.js ***!
  \*********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _style_css__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./style.css */ "./assets/admin/order-edit/src/style.css");

jQuery(document).ready($ => {
  // Handle non-pro interface - show manual tracking content by default
  function initializeInterface() {
    const $tabContainer = $('#default-tab');
    const $manualContent = $('#hezarfen-lite');

    // If no tabs exist (pro not available), show manual content directly
    if ($tabContainer.length === 0) {
      $manualContent.removeClass('hidden').show();
    }
  }

  // Initialize the interface
  initializeInterface();
  // Check if feedback should be shown
  function shouldShowFeedback() {
    const feedbackDismissed = localStorage.getItem('hezarfen_feedback_dismissed');
    const reviewClicked = localStorage.getItem('hezarfen_review_clicked');
    return !feedbackDismissed && !reviewClicked;
  }

  // Initialize feedback request
  function initFeedbackRequest() {
    const $feedbackRequest = $('#hezarfen-feedback-request');
    if (!shouldShowFeedback()) {
      $feedbackRequest.hide();
      return;
    }

    // Show feedback after a short delay to not interrupt user flow
    setTimeout(() => {
      $feedbackRequest.fadeIn(300);
    }, 1000);

    // Handle review link click
    $('#hezarfen-review-positive').on('click', function () {
      // Track that user clicked review link
      localStorage.setItem('hezarfen_review_clicked', 'true');

      // Show thank you message
      showThankYouMessage(hezarfen_mst_backend.thank_you_message);

      // Hide after 3 seconds
      setTimeout(() => {
        $feedbackRequest.fadeOut(300);
      }, 3000);
    });

    // Handle dismiss button
    $('#hezarfen-feedback-dismiss').on('click', function () {
      localStorage.setItem('hezarfen_feedback_dismissed', 'true');
      $feedbackRequest.fadeOut(300);
    });

    // Handle close button
    $('#hezarfen-feedback-close').on('click', function () {
      $feedbackRequest.fadeOut(300);
    });
  }

  // Show thank you message
  function showThankYouMessage(message) {
    const $feedbackRequest = $('#hezarfen-feedback-request');
    $feedbackRequest.html(`
      <div class="flex items-center space-x-3">
        <div class="flex-shrink-0">
          <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
        </div>
        <div class="flex-1">
          <p class="text-sm text-green-700 font-medium">
            ${message}
          </p>
        </div>
      </div>
    `);
  }

  // Initialize feedback request
  initFeedbackRequest();
  $('#hezarfen-lite .h-expand').click(function () {
    var $content = $('#hezarfen-lite #shipping-companies');
    var $button = $(this);
    const $buttonText = $(this).find('span');
    $button.find('svg').toggleClass('rotate-180');
    if ($content.hasClass('max-h-24')) {
      $content.removeClass('max-h-24').addClass('max-h-[1000px]');
      $buttonText.text($button.data('show-less-label'));
    } else {
      $content.removeClass('max-h-[1000px]').addClass('max-h-24');
      $buttonText.text($button.data('show-more-label'));
    }
  });
  const metabox_wrapper = $('#hezarfen-lite');
  const remove_buttons = metabox_wrapper.find('.remove-shipment-data');
  remove_buttons.on('click', function () {
    let shipment_row = $(this).parents('tr');
    create_confirmation_modal(metabox_wrapper, shipment_row);
  });
  const addToTrackList = metabox_wrapper.find('#add-to-tracking-list');
  addToTrackList.on('click', function () {
    const form = $(this).parents('#hezarfen-lite');
    const data = {
      action: hezarfen_mst_backend.new_shipment_data_action,
      _wpnonce: hezarfen_mst_backend.new_shipment_data_nonce,
      order_id: $(this).data('order_id')
    };
    data[hezarfen_mst_backend.new_shipment_courier_html_name] = form.find('input[name="courier-company-select"]:checked').val();
    data[hezarfen_mst_backend.new_shipment_tracking_num_html_name] = form.find('#tracking-num-input').val();
    $.post(ajaxurl, data, function () {
      location.reload();
    }).fail(function () {});
  });
  function create_confirmation_modal(metabox_wrapper, shipment_row) {
    const modal_overlay = metabox_wrapper.find('#modal-body');
    const modal_content = modal_overlay.find('.hez-modal-content');

    // Show modal with animation
    modal_overlay.removeClass('hidden');
    setTimeout(() => {
      modal_overlay.addClass('show');
      modal_content.removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100');
    }, 10);

    // Focus management for accessibility
    const focusableElements = modal_overlay.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    const firstFocusable = focusableElements.first();
    const lastFocusable = focusableElements.last();
    firstFocusable.focus();

    // Trap focus within modal
    modal_overlay.on('keydown', function (e) {
      if (e.key === 'Tab') {
        if (e.shiftKey) {
          if (document.activeElement === firstFocusable[0]) {
            e.preventDefault();
            lastFocusable.focus();
          }
        } else {
          if (document.activeElement === lastFocusable[0]) {
            e.preventDefault();
            firstFocusable.focus();
          }
        }
      } else if (e.key === 'Escape') {
        closeModal();
      }
    });
    function closeModal() {
      modal_content.removeClass('scale-100 opacity-100').addClass('scale-95 opacity-0');
      setTimeout(() => {
        modal_overlay.removeClass('show').addClass('hidden');
        modal_overlay.off('keydown');
      }, 300);
    }

    // Handle confirm button click
    modal_overlay.find('.hez-modal-confirm').off('click').on('click', function () {
      $(this).prop('disabled', true).text('Removing...');
      $.post(ajaxurl, {
        action: hezarfen_mst_backend.remove_shipment_data_action,
        _wpnonce: hezarfen_mst_backend.remove_shipment_data_nonce,
        order_id: $('input#post_ID').val(),
        meta_id: shipment_row.data('meta_id')
      }, function () {
        shipment_row.fadeOut(300, function () {
          $(this).remove();
          // Check if no shipments left and show empty state
          const remainingShipments = metabox_wrapper.find('tbody tr');
          if (remainingShipments.length === 0) {
            location.reload(); // Reload to show empty state
          }
        });
        closeModal();
      }).fail(function () {
        closeModal();
        // Show error message
        alert('Error removing shipment data. Please try again.');
      });
    });

    // Handle cancel and close buttons
    modal_overlay.find('.hez-modal-cancel, .hez-modal-close').off('click').on('click', function () {
      closeModal();
    });

    // Handle backdrop click
    modal_overlay.on('click', function (e) {
      if (e.target === modal_overlay[0]) {
        closeModal();
      }
    });
  }
  function updateCountdown() {
    var endTime = new Date("May 3, 2024 23:59:00").getTime(); // Set the countdown end date and time
    var now = new Date().getTime(); // Current time
    var timeLeft = endTime - now; // Time remaining in milliseconds

    var days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
    var hours = Math.floor(timeLeft % (1000 * 60 * 60 * 24) / (1000 * 60 * 60));
    var minutes = Math.floor(timeLeft % (1000 * 60 * 60) / (1000 * 60));
    var seconds = Math.floor(timeLeft % (1000 * 60) / 1000);

    // Display the result in the respective span elements
    $('#days').text(days);
    $('#hours').text(hours);
    $('#minutes').text(minutes);
    $('#seconds').text(seconds);
    if (timeLeft < 0) {
      clearInterval(timer);
      $('#countdown').html("Kampanya sona erdi");
    }
  }
  updateCountdown(); // Run function once at first to avoid delay
  var timer = setInterval(updateCountdown, 1000); // Update the countdown every second

  const invoice_type_field = $('.hezarfen_billing_invoice_type_field');
  $('a.edit_address').on('click', function () {
    $('.hezarfen-tc-num-field').hide();
  });
  update_field_showing_statuses(invoice_type_field.val());
  invoice_type_field.on('change', function () {
    var invoice_type = $(this).val();
    update_field_showing_statuses(invoice_type);
  });
  function update_field_showing_statuses(invoice_type) {
    if (invoice_type == 'person') {
      $('._billing_hez_TC_number_field').removeClass('hezarfen-hide-form-field');
      $('._billing_hez_tax_number_field').addClass('hezarfen-hide-form-field');
      $('._billing_hez_tax_office_field').addClass('hezarfen-hide-form-field');
    } else if (invoice_type == 'company') {
      $('._billing_hez_TC_number_field').addClass('hezarfen-hide-form-field');
      $('._billing_hez_tax_number_field').removeClass('hezarfen-hide-form-field');
      $('._billing_hez_tax_office_field').removeClass('hezarfen-hide-form-field');
    }
  }
});

/***/ }),

/***/ "./assets/admin/order-edit/src/style.css":
/*!***********************************************!*\
  !*** ./assets/admin/order-edit/src/style.css ***!
  \***********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = __webpack_modules__;
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/chunk loaded */
/******/ 	(() => {
/******/ 		var deferred = [];
/******/ 		__webpack_require__.O = (result, chunkIds, fn, priority) => {
/******/ 			if(chunkIds) {
/******/ 				priority = priority || 0;
/******/ 				for(var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
/******/ 				deferred[i] = [chunkIds, fn, priority];
/******/ 				return;
/******/ 			}
/******/ 			var notFulfilled = Infinity;
/******/ 			for (var i = 0; i < deferred.length; i++) {
/******/ 				var [chunkIds, fn, priority] = deferred[i];
/******/ 				var fulfilled = true;
/******/ 				for (var j = 0; j < chunkIds.length; j++) {
/******/ 					if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O).every((key) => (__webpack_require__.O[key](chunkIds[j])))) {
/******/ 						chunkIds.splice(j--, 1);
/******/ 					} else {
/******/ 						fulfilled = false;
/******/ 						if(priority < notFulfilled) notFulfilled = priority;
/******/ 					}
/******/ 				}
/******/ 				if(fulfilled) {
/******/ 					deferred.splice(i--, 1)
/******/ 					var r = fn();
/******/ 					if (r !== undefined) result = r;
/******/ 				}
/******/ 			}
/******/ 			return result;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	(() => {
/******/ 		// no baseURI
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			"main": 0,
/******/ 			"./style-main": 0
/******/ 		};
/******/ 		
/******/ 		// no chunk on demand loading
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
/******/ 		
/******/ 		// no HMR
/******/ 		
/******/ 		// no HMR manifest
/******/ 		
/******/ 		__webpack_require__.O.j = (chunkId) => (installedChunks[chunkId] === 0);
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = (parentChunkLoadingFunction, data) => {
/******/ 			var [chunkIds, moreModules, runtime] = data;
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some((id) => (installedChunks[id] !== 0))) {
/******/ 				for(moduleId in moreModules) {
/******/ 					if(__webpack_require__.o(moreModules, moduleId)) {
/******/ 						__webpack_require__.m[moduleId] = moreModules[moduleId];
/******/ 					}
/******/ 				}
/******/ 				if(runtime) var result = runtime(__webpack_require__);
/******/ 			}
/******/ 			if(parentChunkLoadingFunction) parentChunkLoadingFunction(data);
/******/ 			for(;i < chunkIds.length; i++) {
/******/ 				chunkId = chunkIds[i];
/******/ 				if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 					installedChunks[chunkId][0]();
/******/ 				}
/******/ 				installedChunks[chunkId] = 0;
/******/ 			}
/******/ 			return __webpack_require__.O(result);
/******/ 		}
/******/ 		
/******/ 		var chunkLoadingGlobal = globalThis["webpackChunkhezarfen_for_woocommerce"] = globalThis["webpackChunkhezarfen_for_woocommerce"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["./style-main"], () => (__webpack_require__("./assets/admin/order-edit/src/main.js")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=main.js.map