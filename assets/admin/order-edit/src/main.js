import './style.css';

jQuery(document).ready(($)=>{
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
    $('#hezarfen-review-positive').on('click', function() {
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
    $('#hezarfen-feedback-dismiss').on('click', function() {
      localStorage.setItem('hezarfen_feedback_dismissed', 'true');
      $feedbackRequest.fadeOut(300);
    });

    // Handle close button
    $('#hezarfen-feedback-close').on('click', function() {
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

    create_confirmation_modal(
      metabox_wrapper,
      shipment_row
    );
  });

  // Handle courier selection changes
  metabox_wrapper.find('input[name="courier-company-select"]').on('change', function() {
    const selectedCourier = $(this).val();
    const standardFields = $('#standard-tracking-fields');
    const hepsijetFields = $('#hepsijet-integration-fields');
    const standardButton = $('#add-to-tracking-list');
    const hepsijetButton = $('#create-hepsijet-shipment');

    if (selectedCourier === 'hepsijet-entegrasyon') {
      // Show Hepsijet integration fields
      standardFields.addClass('hidden');
      hepsijetFields.removeClass('hidden');
      standardButton.addClass('hidden');
      // Hepsijet rotation continues automatically
      // Always show hepsijet button when HepsiJet is selected
      if (hepsijetButton.length > 0) {
        hepsijetButton.removeClass('hidden');
      }
      
    } else if (selectedCourier) {
      // Show standard tracking fields for any other selected courier
      standardFields.removeClass('hidden');
      hepsijetFields.addClass('hidden');
      standardButton.removeClass('hidden');
      // Hepsijet rotation continues automatically
      // Only hide hepsijet button if it exists
      if (hepsijetButton.length > 0) {
        hepsijetButton.addClass('hidden');
      }
    } else {
      // No courier selected - hide all tracking fields
      standardFields.addClass('hidden');
      hepsijetFields.addClass('hidden');
      standardButton.addClass('hidden');
      // Hepsijet rotation continues automatically
      if (hepsijetButton.length > 0) {
        hepsijetButton.addClass('hidden');
      }
    }
  });
  
  // Initialize: hide all tracking fields since no courier is selected by default
  const standardFields = $('#standard-tracking-fields');
  const hepsijetFields = $('#hepsijet-integration-fields');
  const standardButton = $('#add-to-tracking-list');
  const hepsijetButton = $('#create-hepsijet-shipment');
  
  // Hide all fields and buttons by default
  standardFields.addClass('hidden');
  hepsijetFields.addClass('hidden');
  standardButton.addClass('hidden');
  if (hepsijetButton.length > 0) {
    hepsijetButton.addClass('hidden');
  }
  
  // Hepsijet Rotating Info Functions
  let hepsijetRotationInterval = null;

  function startHepsijetRotation() {
    const rotatingContainer = $('#hepsijet-rotating-info');
    if (rotatingContainer.length === 0) return;

    const items = rotatingContainer.find('.rotating-item');
    if (items.length === 0) return;

    let currentIndex = 0;
    
    // Clear any existing interval
    if (hepsijetRotationInterval) {
      clearInterval(hepsijetRotationInterval);
    }

    // Start rotation
    hepsijetRotationInterval = setInterval(function() {
      const currentItem = items.eq(currentIndex);
      const nextIndex = (currentIndex + 1) % items.length;
      const nextItem = items.eq(nextIndex);

      // Hide current item and show next
      currentItem.removeClass('active');
      nextItem.addClass('active');
      currentIndex = nextIndex;
      
    }, 1250); // Change every 2 seconds
  }

  function stopHepsijetRotation() {
    if (hepsijetRotationInterval) {
      clearInterval(hepsijetRotationInterval);
      hepsijetRotationInterval = null;
    }
    
    // Reset to first item
    const rotatingContainer = $('#hepsijet-rotating-info');
    if (rotatingContainer.length > 0) {
      const items = rotatingContainer.find('.rotating-item');
      items.removeClass('active');
      items.eq(0).addClass('active');
    }
  }

  // Start Hepsijet rotation automatically on page load
  startHepsijetRotation();
  
  // Handle help button toggle
  metabox_wrapper.find('#hepsijet-help-toggle').on('click', function() {
    const helpContent = $('#hepsijet-help-content');
    const button = $(this);
    
    if (helpContent.hasClass('hidden')) {
      helpContent.removeClass('hidden');
      button.html('<svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>Kapat');
    } else {
      helpContent.addClass('hidden');
      button.html('<svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>Yardım');
    }
  });

  // Handle delivery type selection
  $(document).on('change', 'input[name="hepsijet-delivery-type"]', function() {
    const selectedType = $(this).val();
    console.log('Delivery type changed:', selectedType);

    const deliverySlotContainer = $('#hepsijet-delivery-slot-container');
    const returnFieldsContainer = $('#hepsijet-return-fields-container');
    const warehouseContainer = $('#hepsijet-warehouse-container');
    const addPackageButton = $('#add-hepsijet-package');

    console.log('Return fields container found:', returnFieldsContainer.length);

    // Hide all conditional containers first (use jQuery hide/show for reliability)
    deliverySlotContainer.hide();
    returnFieldsContainer.hide();

    // Show relevant container based on selection
    if (selectedType === 'sameday' || selectedType === 'nextday') {
      deliverySlotContainer.show();
      // Show warehouse and allow multiple packages for shipment types
      warehouseContainer.show();
      addPackageButton.show();
    } else if (selectedType === 'returned') {
      console.log('Showing return fields container');
      returnFieldsContainer.show();
      loadReturnDates();
      // Hide warehouse selection for returns
      warehouseContainer.hide();
      // Hide add package button and keep only one package for returns
      console.log('Add package button found:', addPackageButton.length);
      addPackageButton.hide();
      addPackageButton.css('display', 'none');
      // Remove extra packages, keep only the first one
      const packagesContainer = $('#hepsijet-packages-container');
      packagesContainer.find('.hepsijet-package-item:not(:first)').remove();
    } else {
      // Standard shipment - show warehouse and allow multiple packages
      warehouseContainer.show();
      addPackageButton.show();
    }
  });
  
  // Show notification function
  function showNotification(message, type = 'info') {
    // Remove any existing notifications
    $('.hez-notification').remove();
    
    // Create notification element
    const notification = $(`
      <div class="hez-notification fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-md transform transition-all duration-300 translate-x-full">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            ${type === 'success' ? '<svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>' : 
              type === 'error' ? '<svg class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>' :
              '<svg class="w-5 h-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>'}
          </div>
          <div class="ml-3">
            <p class="text-sm font-medium text-gray-900">${message}</p>
          </div>
          <div class="ml-auto pl-3">
            <button class="hez-notification-close inline-flex text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600">
              <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
              </svg>
            </button>
          </div>
        </div>
      </div>
    `);
    
    // Add to body
    $('body').append(notification);
    
    // Animate in
    setTimeout(() => {
      notification.removeClass('translate-x-full');
    }, 100);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
      notification.addClass('translate-x-full');
      setTimeout(() => {
        notification.remove();
      }, 300);
    }, 5000);
    
    // Handle close button
    notification.find('.hez-notification-close').on('click', function() {
      notification.addClass('translate-x-full');
      setTimeout(() => {
        notification.remove();
      }, 300);
    });
  }

  // Load available return dates from API
  function loadReturnDates() {
    const returnDateSelect = $('#hepsijet-return-date');
        returnDateSelect.html(`<option value="">${hezarfen_mst_backend.loading_available_dates}</option>`);
    
    // Get order shipping city and district
    const orderId = $('#create-hepsijet-shipment').data('order_id');
    
    // Get shipping city and district from WooCommerce order edit screen shipping metabox
    const shippingDistrict = $('#_shipping_city').val() || 'Istanbul';
    
    // Get human-readable district name from the shipping state field display
    let shippingCity = '';
    const shippingStateField = $('#_shipping_state');
    if (shippingStateField.length) {
      // Try to get the selected option text (human-readable name)
      const selectedOption = shippingStateField.find('option:selected');
      if (selectedOption.length && selectedOption.text()) {
        shippingCity = selectedOption.text();
      } else {
        // Fallback: try to get from the field's display value
        shippingCity = shippingStateField.val() ;
      }
    }
    
    const startDate = new Date().toISOString().split('T')[0]; // Today
    const endDate = new Date(Date.now() + 10 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]; // 10 days later
    
    const data = {
      action: 'hezarfen_mst_get_return_dates',
      _wpnonce: hezarfen_mst_backend.get_return_dates_nonce,
      start_date: startDate,
      end_date: endDate,
      city: shippingCity,
      district: shippingDistrict
    };
    
    $.post(ajaxurl, data, function(response) {
      console.log('Return dates API response:', response);

      if (response.success && response.data) {
        console.log('Response data:', response.data);

        /*
         * Backend AJAX response format:
         * {
         *   "success": true,
         *   "data": {
         *     "dates": {
         *       "SARIYER": ["2025-12-23", "2025-12-24"],
         *       "ZEKERİYAKÖY": ["2025-12-23", "2025-12-24"]
         *     }
         *   }
         * }
         * or when no dates: { "success": true, "data": { "dates": [], "message": "..." } }
         */
        const datesData = response.data.dates;

        // Check if we have a message (no dates available case)
        if (response.data.message) {
          showNotification(response.data.message, 'info');
          returnDateSelect.html('<option value="">Müsait tarih bulunamadı</option>');
          return;
        }

        // Check if datesData is an object with regions as keys
        if (datesData && typeof datesData === 'object' && !Array.isArray(datesData)) {
          let options = '<option value="">Randevu tarihi seçiniz</option>';
          let hasAnyDates = false;

          Object.keys(datesData).forEach(function(region) {
            if (Array.isArray(datesData[region]) && datesData[region].length > 0) {
              hasAnyDates = true;
              datesData[region].forEach(function(date) {
                // Format date from YYYY-MM-DD to DD.MM.YYYY
                const [year, month, day] = date.split('-');
                const formattedDate = `${day}.${month}.${year}`;
                options += `<option value="${date}">${formattedDate} - ${region}</option>`;
              });
            }
          });

          if (hasAnyDates) {
            returnDateSelect.html(options);
          } else {
            showNotification('Müsait tarih bulunamadı', 'info');
            returnDateSelect.html('<option value="">Müsait tarih bulunamadı</option>');
          }
        } else {
          // No dates available or unexpected format
          showNotification('Müsait tarih bulunamadı', 'info');
          returnDateSelect.html('<option value="">Müsait tarih bulunamadı</option>');
        }
      } else {
        console.log('Response not successful or missing data:', response);
        returnDateSelect.html(`<option value="">${hezarfen_mst_backend.error_loading_dates}</option>`);
      }
    }).fail(function(xhr, status, error) {
      console.error('Failed to load return dates:', {xhr, status, error});
      returnDateSelect.html(`<option value="">${hezarfen_mst_backend.error_loading_dates}</option>`);
    });
  }

  const addToTrackList = metabox_wrapper.find('#add-to-tracking-list');

  addToTrackList.on('click', function() {
    const form = $(this).parents('#hezarfen-lite');

    const data = {
      action: hezarfen_mst_backend.new_shipment_data_action,
      _wpnonce: hezarfen_mst_backend.new_shipment_data_nonce,
      order_id: $(this).data('order_id')
    };

    data[hezarfen_mst_backend.new_shipment_courier_html_name] = form.find('input[name="courier-company-select"]:checked').val();
    data[hezarfen_mst_backend.new_shipment_tracking_num_html_name] = form.find('#tracking-num-input').val();

    $.post(
      ajaxurl,
      data,
      function () {
        location.reload();
      }
    ).fail(function () {
    });
  });

  // Initialize Hepsijet packages system
  function initHepsijetPackages() {
    const $container = $('#hepsijet-packages-container');
    
    // Clear container
    $container.empty();
    
    // Add initial package
    addHepsijetPackage();
  }

  // Add a new package input
  function addHepsijetPackage() {
    const $container = $('#hepsijet-packages-container');
    const packageIndex = $container.find('.hepsijet-package-item').length;
    
    const packageHtml = `
      <div class="hepsijet-package-item flex items-center gap-2 p-2 bg-gray-50 border border-gray-200 rounded-lg dark:bg-gray-700 dark:border-gray-600">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300 min-w-[60px]">Koli ${packageIndex + 1}:</span>
        <div class="flex-1">
          <input type="number" 
                 name="hezarfen_hepsijet_package_desi[]"
                 class="hepsijet-package-desi shadow-sm bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2 dark:bg-gray-600 dark:border-gray-500 dark:text-white" 
                 step="0.01" 
                 min="0.01" 
                 placeholder="Desi" />
        </div>
        <button type="button" class="remove-hepsijet-package inline-flex items-center p-2 text-sm font-medium text-red-600 bg-red-50 rounded hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/30" ${packageIndex === 0 ? 'style="visibility: hidden;"' : ''}>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
    `;
    
    $container.append(packageHtml);
    updatePackageLabels();
  }

  // Update package labels after add/remove
  function updatePackageLabels() {
    const $container = $('#hepsijet-packages-container');
    $container.find('.hepsijet-package-item').each(function(index) {
      $(this).find('span').first().text('Koli ' + (index + 1) + ':');
      
      // Hide remove button for first package, show for others
      if (index === 0) {
        $(this).find('.remove-hepsijet-package').css('visibility', 'hidden');
      } else {
        $(this).find('.remove-hepsijet-package').css('visibility', 'visible');
      }
    });
  }

  // Add package button handler
  $(document).on('click', '#add-hepsijet-package', function(e) {
    e.preventDefault();
    addHepsijetPackage();
  });

  // Remove package button handler
  $(document).on('click', '.remove-hepsijet-package', function(e) {
    e.preventDefault();
    const $container = $('#hepsijet-packages-container');
    
    // Don't allow removing the last package
    if ($container.find('.hepsijet-package-item').length > 1) {
      $(this).closest('.hepsijet-package-item').remove();
      updatePackageLabels();
    }
  });

  // Initialize packages when the modal is shown
  initHepsijetPackages();

  // Warehouses are now loaded server-side in PHP template
  // No need to load via AJAX - better performance and no race conditions
  // loadHepsijetWarehouses(); // DISABLED

  // Handle Hepsijet shipment creation
  const createHepsijetShipment = metabox_wrapper.find('#create-hepsijet-shipment');

  createHepsijetShipment.on('click', function() {
    const $button = $(this);
    const originalText = $button.text();
    const deliveryType = $('input[name="hepsijet-delivery-type"]:checked').val();
    const deliverySlot = $('#hepsijet-delivery-slot').val();
    const returnDate = $('#hepsijet-return-date').val();
    const warehouseId = $('#hepsijet-warehouse').val();

    // Validate warehouse selection (only for non-return types)
    if (deliveryType !== 'returned' && !warehouseId) {
      alert('Lütfen depo seçiniz.');
      return;
    }

    // Collect packages data
    const packages = [];
    let hasError = false;

    $('#hepsijet-packages-container .hepsijet-package-item').each(function() {
      const desi = $(this).find('.hepsijet-package-desi').val();

      if (!desi || parseFloat(desi) < 0.01) {
        hasError = true;
        return false; // Break loop
      }

      packages.push({
        desi: parseFloat(desi)
      });
    });

    // Validate inputs
    if (hasError || packages.length === 0) {
      alert('Lütfen tüm koliler için desi değerlerini doğru giriniz.');
      return;
    }

    // Validate delivery type specific fields
    if ((deliveryType === 'sameday' || deliveryType === 'nextday') && !deliverySlot) {
      alert('Lütfen teslimat saatini seçiniz.');
      return;
    }

    // Validate return specific fields
    if (deliveryType === 'returned') {
      if (!returnDate) {
        alert('Lütfen iade tarihini seçiniz.');
        return;
      }

      // Validate return address fields
      const returnFirstName = $('#hepsijet-return-first-name').val().trim();
      const returnLastName = $('#hepsijet-return-last-name').val().trim();
      const returnCity = $('#hepsijet-return-city').val().trim();
      const returnDistrict = $('#hepsijet-return-district').val().trim();
      const returnNeighborhood = $('#hepsijet-return-neighborhood').val().trim();
      const returnAddress = $('#hepsijet-return-address').val().trim();
      const returnPhone = $('#hepsijet-return-phone').val().trim();

      if (!returnFirstName || !returnLastName || !returnCity || !returnDistrict || !returnNeighborhood || !returnAddress || !returnPhone) {
        alert('Lütfen tüm iade adres bilgilerini doldurunuz.');
        return;
      }
    }

    // Disable button and show loading state
    $button.prop('disabled', true).text(hezarfen_mst_backend.creating_shipment_text);

    const data = {
      action: hezarfen_mst_backend.create_hepsijet_shipment_action,
      _wpnonce: hezarfen_mst_backend.create_hepsijet_shipment_nonce,
      order_id: $(this).data('order_id'),
      packages: JSON.stringify(packages),
      type: deliveryType,
      delivery_slot: deliverySlot || '',
      delivery_date: returnDate || '',
      warehouse_id: warehouseId || ''
    };

    // Add return address data if return type
    if (deliveryType === 'returned') {
      data.return_address = JSON.stringify({
        first_name: $('#hepsijet-return-first-name').val().trim(),
        last_name: $('#hepsijet-return-last-name').val().trim(),
        city: $('#hepsijet-return-city').val().trim(),
        district: $('#hepsijet-return-district').val().trim(),
        neighborhood: $('#hepsijet-return-neighborhood').val().trim(),
        address: $('#hepsijet-return-address').val().trim(),
        phone: $('#hepsijet-return-phone').val().trim()
      });
    }

    $.post(
      ajaxurl,
      data,
      function (response) {
        if (response.success) {
          // Debug: Log the response to console
          console.log('Hepsijet Response:', response);
          
          // Check multiple possible paths for cost_info
          let costInfo = null;
          
          if (response.data && response.data.response_data && response.data.response_data.cost_info) {
            costInfo = response.data.response_data.cost_info;
          } else if (response.data && response.data.cost_info) {
            costInfo = response.data.cost_info;
          } else if (response.cost_info) {
            costInfo = response.cost_info;
          }
          
          console.log('Cost Info:', costInfo);
          
          if (costInfo) {
            showShipmentCostModal(costInfo);
          } else {
            // No cost info, just reload
            console.log('No cost_info found, reloading page');
            location.reload();
          }
        } else {
          alert('Hata: ' + (response.data || 'Bilinmeyen hata'));
        }
      }
    ).fail(function (xhr) {
      const errorMsg = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : 'Bağlantı hatası';
      alert('Hata: ' + errorMsg);
    }).always(function() {
      // Re-enable button
      $button.prop('disabled', false).text(originalText);
    });
  });

  // Show shipment cost alert
  function showShipmentCostModal(costInfo) {
    const packages = costInfo.packages || [];
    const summary = costInfo.summary || {};
    
    // Build alert message
    let message = '✓ Barkod Başarıyla Oluşturuldu!\n\n';
    message += '━━━━━━━━━━━━━━━━━━━━━━\n';
    message += 'Toplam Maliyet (Tahmini):\n';
    message += parseFloat(summary.total_cost_with_vat).toFixed(2) + ' ' + (summary.currency || 'TRY');
    
    if (summary.vat_rate) {
      message += ' (KDV Dahil %' + (summary.vat_rate * 100).toFixed(0) + ')';
    }
    
    message += '\n━━━━━━━━━━━━━━━━━━━━━━\n\n';
    message += 'Koli Detayları:\n\n';
    
    packages.forEach((pkg, index) => {
      message += 'Koli ' + (pkg.package_number || (index + 1)) + ': ';
      message += parseFloat(pkg.desi).toFixed(2) + ' desi → ';
      message += parseFloat(pkg.cost_with_vat).toFixed(2) + ' ' + (summary.currency || 'TRY');
      message += '\n';
    });
    
    alert(message);
    location.reload();
  }

  // Handle Hepsijet check details button (using event delegation)
  $(document).off('click', '.check-hepsijet-details').on('click', '.check-hepsijet-details', function(e) {
    e.preventDefault(); // Prevent form submission
    e.stopPropagation(); // Stop event bubbling
    
    const deliveryNo = $(this).data('delivery_no');
    const orderId = $(this).data('order_id');
    const modal = $('#hepsijet-details-modal');
    const content = $('#hepsijet-details-content');

    // Validate required data
    if (!deliveryNo || !orderId) {
      alert('Missing delivery number or order ID');
      return;
    }

    // Show modal
    modal.removeClass('hidden');
    setTimeout(() => {
      modal.find('.hez-modal-content').removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100');
    }, 10);

    // Set loading state
    content.html(`<p class="text-sm text-gray-600">${hezarfen_mst_backend.loading_shipment_details}</p>`);

    // Make AJAX request
    const data = {
      action: hezarfen_mst_backend.track_hepsijet_shipment_action,
      _wpnonce: hezarfen_mst_backend.track_hepsijet_shipment_nonce,
      delivery_no: deliveryNo
    };

    console.log('Tracking request data:', data);

    $.post(ajaxurl, data, function(response) {
      if (response.success) {
        content.html(formatHepsijetDetails(response.data));
      } else {
        const errorMsg = response.data || 'Unknown error';
        content.html('<div class="bg-red-50 border border-red-200 rounded p-3"><p class="text-sm text-red-600"><strong>Error:</strong> ' + errorMsg + '</p></div>');
      }
    }).fail(function(xhr) {
      const errorMsg = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : 'Connection error occurred';
      content.html('<div class="bg-red-50 border border-red-200 rounded p-3"><p class="text-sm text-red-600"><strong>Connection Error:</strong> ' + errorMsg + '</p></div>');
    });
  });

  // Handle Hepsijet barcode button (using event delegation)
  $(document).off('click', '.get-hepsijet-barcode').on('click', '.get-hepsijet-barcode', function(e) {
    e.preventDefault(); // Prevent form submission
    e.stopPropagation(); // Stop event bubbling
    
    const $button = $(this);
    const deliveryNo = $button.data('delivery_no');
    const orderId = $button.data('order_id');
    const originalText = $button.text();

    // Validate required data
    if (!deliveryNo || !orderId) {
      alert('Missing delivery number or order ID');
      return;
    }

    // Set loading state
    $button.prop('disabled', true).text(hezarfen_mst_backend.loading_text);

    // Show barcode modal
    showBarcodeModal(deliveryNo, orderId, function() {
      $button.prop('disabled', false).text(originalText);
    });
  });

  // Handle Hepsijet cancel button (using event delegation)
  $(document).off('click', '.cancel-hepsijet-shipment').on('click', '.cancel-hepsijet-shipment', function(e) {
    e.preventDefault(); // Prevent form submission
    e.stopPropagation(); // Stop event bubbling
    
    if (!confirm('Gönderi iptal edilecektir, onaylıyor musunuz?')) {
      return;
    }

    const $button = $(this);
    const deliveryNo = $button.data('delivery_no');
    const originalText = $button.text();

    // Set loading state
    $button.prop('disabled', true).text(hezarfen_mst_backend.cancelling_shipment_text);

    const orderId = $button.data('order_id');

    // Validate required data
    if (!deliveryNo || !orderId) {
      alert('Missing required data for cancellation');
      $button.prop('disabled', false).text(originalText);
      return;
    }

    const data = {
      action: hezarfen_mst_backend.cancel_hepsijet_shipment_action,
      _wpnonce: hezarfen_mst_backend.cancel_hepsijet_shipment_nonce,
      delivery_no: deliveryNo,
      order_id: orderId
    };

    console.log('Cancel request data:', data);

    $.post(ajaxurl, data, function(response) {
      if (response.success) {
        // Update UI to show cancelled state instead of reloading
        updateShipmentToCancelledState($button);
        alert('Gönderi başarıyla iptal edildi');
      } else {
        // Show the specific error message from the API response
        const errorMessage = response.data || 'Bilinmeyen hata';
        alert('Hata: ' + errorMessage);
      }
    }).fail(function(xhr, status, error) {
      // Handle HTTP errors (like 400, 500, etc.)
      let errorMessage = 'Bağlantı hatası';
      
      try {
        // Try to parse the response to get the error message
        const response = JSON.parse(xhr.responseText);
        if (response.data) {
          errorMessage = response.data;
        }
      } catch (e) {
        // If parsing fails, use the HTTP status text
        if (xhr.statusText) {
          errorMessage = xhr.statusText;
        }
      }
      
      alert('Hata: ' + errorMessage);
    }).always(function() {
      $button.prop('disabled', false).text(originalText);
    });
  });

  // Handle Hepsijet ile Avantajlı Kargo Fiyatları balance check button
  $(document).on('click', '#check-kargogate-balance', function(e) {
    e.preventDefault();
    
    const $button = $(this);
    const originalText = $button.text();
    
    // Set loading state
    $button.prop('disabled', true).text(hezarfen_mst_backend.checking_balance_text);
    
    loadKargoGateBalance().always(function() {
      // Reset button state
      $button.prop('disabled', false).text(originalText);
    });
  });

  // Close details modal
  $(document).on('click', '.hez-details-modal-close', function() {
    closeDetailsModal();
  });

  // Close details modal on backdrop click
  $(document).on('click', '#hepsijet-details-modal', function(e) {
    if (e.target === this) {
      closeDetailsModal();
    }
  });

  // Close details modal on escape key
  $(document).on('keydown', function(e) {
    if (e.key === 'Escape') {
      if (!$('#hepsijet-details-modal').hasClass('hidden')) {
        closeDetailsModal();
      }
      if (!$('#hepsijet-barcode-modal').hasClass('hidden')) {
        closeBarcodeModal();
      }
    }
  });

  // Close barcode modal handlers
  $(document).on('click', '.hez-barcode-modal-close', function() {
    closeBarcodeModal();
  });

  $(document).on('click', '#hepsijet-barcode-modal', function(e) {
    if (e.target === this) {
      closeBarcodeModal();
    }
  });

  function closeDetailsModal() {
    const modal = $('#hepsijet-details-modal');
    modal.find('.hez-modal-content').removeClass('scale-100 opacity-100').addClass('scale-95 opacity-0');
    setTimeout(() => {
      modal.addClass('hidden');
    }, 300);
  }

  function closeBarcodeModal() {
    const modal = $('#hepsijet-barcode-modal');
    modal.find('.hez-modal-content').removeClass('scale-100 opacity-100').addClass('scale-95 opacity-0');
    setTimeout(() => {
      modal.addClass('hidden');
      // Clear iframe src and reset container
      $('#barcode-pdf-frame').attr('src', '');
      $('#hepsijet-barcode-content').html(`
        <div class="transform -rotate-90 origin-center">
          <iframe id="barcode-pdf-frame" style="width: 600px; height: 400px; border: 1px solid #ddd;" src=""></iframe>
        </div>
      `);
    }, 300);
  }

  // Update shipment row to show cancelled state
  function updateShipmentToCancelledState($button) {
    const $row = $button.closest('tr');
    
    // Update row styling
    $row.removeClass('bg-white').addClass('bg-gray-100 opacity-60');
    
    // Update courier title with strikethrough and badge
    const $courierCell = $row.find('th:first');
    $courierCell.addClass('text-gray-500 line-through');
    $courierCell.append('<span class="ml-2 px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">Cancelled</span>');
    
    // Update details cell (now the second column after removing tracking number column)
    const $detailsCell = $row.find('td:first');
    const currentTime = new Date().toLocaleString('tr-TR', {
      day: '2-digit',
      month: '2-digit', 
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
    
    // Extract current koli and desi values
    const koliText = $detailsCell.text().includes('Koli:') ? $detailsCell.text().split('Koli: ')[1].split(' ')[0] : 'N/A';
    const desiText = $detailsCell.text().includes('Desi:') ? $detailsCell.text().split('Desi: ')[1].trim() : 'N/A';
    
    $detailsCell.html(`
      <div class="text-xs text-gray-500">
        <div class="line-through">Koli: ${koliText}</div>
        <div class="line-through">Desi: ${desiText}</div>
        <div class="text-red-600 font-medium mt-1">Cancelled: ${currentTime}</div>
      </div>
    `);
    
    // Update actions cell (now the last column)
    const $actionsCell = $row.find('td:last');
    const deliveryNo = $button.data('delivery_no');
    const orderId = $button.data('order_id');
    
    $actionsCell.html(`
      <div class="flex gap-1 flex-wrap">
        <span class="px-2 py-1 bg-gray-300 text-gray-600 rounded text-xs cursor-not-allowed" title="Barcode not available for cancelled shipments">Barcode</span>
        <button type="button" data-delivery_no="${deliveryNo}" data-order_id="${orderId}" class="check-hepsijet-details cursor-pointer focus:outline-none hover:opacity-80 bg-blue-600 text-white px-2 py-1 rounded text-xs" title="Check tracking details">Details</button>
        <span class="px-2 py-1 bg-gray-300 text-gray-600 rounded text-xs cursor-not-allowed">Cancelled</span>
      </div>
    `);
  }

  // Show barcode modal with PDF
  function showBarcodeModal(deliveryNo, orderId, callback) {
    const modal = $('#hepsijet-barcode-modal');
    const pdfFrame = $('#barcode-pdf-frame');
    
    console.log('Opening barcode modal for:', { deliveryNo, orderId });
    
    // Store data on modal for later use
    modal.data('current-delivery-no', deliveryNo);
    modal.data('current-order-id', orderId);
    
    // Show modal
    modal.removeClass('hidden');
    setTimeout(() => {
      modal.find('.hez-modal-content').removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100');
    }, 10);

    // Show loading state
    pdfFrame.parent().html(`<p class="text-sm text-gray-600">${hezarfen_mst_backend.preparing_barcode_text}</p>`);

    // Single AJAX call to get everything
    const data = {
      action: hezarfen_mst_backend.get_hepsijet_barcode_pdf_action,
      _wpnonce: hezarfen_mst_backend.get_hepsijet_barcode_pdf_nonce,
      delivery_no: deliveryNo,
      order_id: orderId
    };

    $.post(ajaxurl, data, function(response) {
      console.log('AJAX Response received:', response);
      console.log('Response success:', response.success);
      console.log('Response data:', response.data);
      
      if (response.success) {
        // Store PDF data FIRST before displaying anything (now contains base64 data)
        modal.data('pdf-url', response.data.pdf_url);
        
        // Display barcode image (now PDF data is available) - handle async
        displayBarcodeImage(response.data.barcode_data, pdfFrame).catch(error => {
          console.error('Error displaying barcode/PDF:', error);
          pdfFrame.parent().html('<div class="bg-red-50 border border-red-200 rounded p-3"><p class="text-sm text-red-600"><strong>Error:</strong> ' + error.message + '</p></div>');
        });
        
        console.log('Combined request successful:', response.data);
        console.log('PDF data stored (base64):', response.data.pdf_url ? 'Yes' : 'No');
      } else {
        console.error('AJAX Response error:', response.data);
        pdfFrame.parent().html('<div class="bg-red-50 border border-red-200 rounded p-3"><p class="text-sm text-red-600"><strong>Error:</strong> ' + (response.data || 'Unknown error') + '</p></div>');
      }
      
      if (callback) callback();
    }).fail(function(xhr) {
      console.error('AJAX Request failed:', xhr);
      console.error('Status:', xhr.status);
      console.error('Response Text:', xhr.responseText);
      
      const errorMsg = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : 'Connection error occurred';
      pdfFrame.parent().html('<div class="bg-red-50 border border-red-200 rounded p-3"><p class="text-sm text-red-600"><strong>Connection Error:</strong> ' + errorMsg + '</p></div>');
      
      if (callback) callback();
    });
  }

  // Load order information
    // function loadOrderInfo(orderId, container) {
  //   const data = {
  //     action: hezarfen_mst_backend.get_order_info_action,
  //     _wpnonce: hezarfen_mst_backend.get_order_info_nonce,
  //     order_id: orderId
  //   };
  //
  //   $.post(ajaxurl, data, function(response) {
  //     if (response.success) {
  //       container.html(formatOrderInfo(response.data));
  //     } else {
  //       container.html('<p class="text-sm text-red-600">Error loading order info</p>');
  //     }
  //   }).fail(function() {
  //       container.html('<p class="text-sm text-red-600">Failed to load order info</p>');
  //   });
  // }

  // Load barcode image via Relay API - NO LONGER NEEDED
  // function loadBarcodePDF(deliveryNo, iframe, callback) {
  //   const data = {
  //     action: hezarfen_mst_backend.get_hepsijet_barcode_action,
  //     _wpnonce: hezarfen_mst_backend.get_hepsijet_barcode_nonce,
  //     delivery_no: deliveryNo
  //   };
  //
  //   console.log('Loading barcode image for delivery:', deliveryNo);
  //
  //   $.post(ajaxurl, data, function(response) {
  //     console.log('Barcode image response:', response);
  //     console.log('Response success:', response.success);
  //     console.log('Response data type:', typeof response.data);
  //     console.log('Response data:', response.data);
  //     
  //     if (response.success && response.data && Array.isArray(response.data) && response.data.length > 0) {
  //       try {
  //         // Relay API returns array of base64 images directly
  //         let imageData = response.data[0];
  //         
  //         console.log('Image data length:', imageData.length);
  //         console.log('Image data prefix:', imageData.substring(0, 50));
  //         console.log('Image data type:', typeof imageData);
  //         
  //         // The data is already in data:image/jpeg;base64 format
  //         if (!imageData.startsWith('data:image/')) {
  //           imageData = 'data:image/jpeg;base64,' + imageData;
  //         }
  //         
  //         // Replace iframe with image element for better display
  //         const container = iframe.parent();
  //         container.html(`
  //           <div class="transform -rotate-90 origin-center flex justify-center">
  //             <img id="barcode-image" src="${imageData}" 
  //                  style="max-width: 600px; max-height: 400px; border: 1px solid #ddd;" 
  //                  alt="Hepsijet Barcode Label" />
  //           </div>
  //         `);
  //         
  //         // Store data for download button on both the image and modal
  //         $('#barcode-image').data('image-data', imageData);
  //         $('#barcode-image').data('delivery-no', deliveryNo);
  //         $('#hepsijet-barcode-modal').data('image-data', imageData);
  //         $('#hepsijet-barcode-modal').data('delivery-no', deliveryNo);
  //         
  //         console.log('Barcode image loaded successfully');
  //         console.log('Data stored on elements:', {
  //           imageElement: $('#barcode-image').data('image-data') ? 'YES' : 'NO',
  //           modalElement: $('#hepsijet-barcode-modal').data('image-data') ? 'YES' : 'NO',
  //           imageDataLength: imageData.length
  //         });
  //         
  //       } catch (error) {
  //         console.error('Image display error:', error);
  //         iframe.parent().html('<p class="text-red-600 text-center">Barcode yüklenemedi: ' + error.message + '</p>');
  //       }
  //     } else {
  //       console.error('Invalid barcode response:', response);
  //       iframe.parent().html('<p class="text-red-600 text-center">Barcode alınamadı: ' + (response.data || 'Invalid response') + '</p>');
  //       }
  //     
  //     if (callback) callback();
  //   }).fail(function(xhr) {
  //     console.error('Barcode request failed:', xhr);
  //     iframe.parent().html('<p class="text-red-600 text-center">Bağlantı hatası</p>');
  //     if (callback) callback();
  //   });
  // }



    // Display PDF directly in the modal with async loading
  async function displayBarcodeImage(barcodeData, container) {
    const modal = $('#hepsijet-barcode-modal');
    const pdfData = modal.data('pdf-url'); // This now contains base64 data
    
    console.log('displayBarcodeImage called with:', { barcodeData, pdfData });
    console.log('Modal data:', modal.data());
    
    if (pdfData) {
      console.log('PDF data found, embedding PDF viewer...');
      
      // Show loading state with spinner - target the specific container by ID
      const targetContainer = $('#hepsijet-barcode-content');
      console.log('Targeting container by ID:', targetContainer);
      
      targetContainer.html(`
        <div class="w-full h-96 flex flex-col justify-center items-center">
          <div class="text-sm text-gray-600 mb-4">PDF hazırlanıyor...</div>
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        </div>
      `);
      
      try {
        // Since PDF is already generated as base64, we can display it immediately
        console.log('PDF data ready, embedding PDF viewer');
        // Replace container with PDF viewer using base64 data
        targetContainer.html(`
          <div class="w-full h-5/6 flex justify-center">
            <iframe 
              id="pdf-viewer" 
              src="${pdfData}" 
              style="width: 100%; height: 100%; border: 1px solid #ddd; border-radius: 4px;" 
              frameborder="0"
              title="Hepsijet Shipment Label PDF">
            </iframe>
          </div>
        `);
        
        console.log('PDF embedded successfully using base64 data');
      } catch (error) {
        console.error('PDF display failed:', error);
        // Fallback to barcode image if PDF display fails
        displayBarcodeImageFallback(barcodeData, container);
      }
    } else {
      console.log('No PDF data found, falling back to barcode image');
      displayBarcodeImageFallback(barcodeData, container);
    }
  }
  

  
  // Fallback to barcode image display
  function displayBarcodeImageFallback(barcodeData, container) {
    if (Array.isArray(barcodeData) && barcodeData.length > 0) {
      try {
        // Get the first barcode image (base64 data)
        let imageData = barcodeData[0];
        
        // The data is already in data:image/jpeg;base64 format
        if (!imageData.startsWith('data:image/')) {
          imageData = 'data:image/jpeg;base64,' + imageData;
        }
        
        // Replace container with image element
        container.parent().html(`
          <div class="transform -rotate-90 origin-center flex justify-center">
            <img id="barcode-image" src="${imageData}" 
                 style="max-width: 600px; max-height: 400px; border: 1px solid #ddd;" 
                 alt="Hepsijet Barcode Label" />
          </div>
        `);
        
        console.log('Barcode image displayed as fallback');
      } catch (error) {
        console.error('Image display error:', error);
        container.parent().html('<p class="text-red-600 text-center">Barcode yüklenemedi: ' + error.message + '</p>');
      }
    } else {
      console.log('No barcode data available either');
      container.parent().html('<p class="text-red-600 text-center">PDF ve barcode verisi bulunamadı</p>');
    }
  }

  // Format Hepsijet details response
  function formatHepsijetDetails(data) {
    if (!data || !data.data || !Array.isArray(data.data) || data.data.length === 0) {
      return `<div class="bg-yellow-50 border border-yellow-200 rounded p-3"><p class="text-sm text-yellow-600">${hezarfen_mst_backend.no_tracking_information}</p></div>`;
    }

    const shipmentData = data.data[0];
    const transactions = shipmentData.transactions || [];

    let html = '<div class="space-y-4">';
    
    // Basic info
    html += '<div class="bg-blue-50 border border-blue-200 p-3 rounded">';
    html += '<h4 class="font-medium text-blue-900 mb-2">📦 Shipment Information</h4>';
    html += '<div class="text-sm text-blue-700 space-y-1">';
    html += '<div><strong>Status:</strong> <span class="px-2 py-1 bg-blue-100 rounded text-xs">' + (shipmentData.deliveryStatus || 'Unknown') + '</span></div>';
    html += '<div><strong>Delivery No:</strong> ' + (shipmentData.customerDeliveryNo || 'N/A') + '</div>';
    if (shipmentData.trackingUrl) {
      html += '<div><strong>Tracking URL:</strong> <a href="' + shipmentData.trackingUrl + '" target="_blank" class="text-blue-600 underline">Open</a></div>';
    }
    html += '</div>';
    html += '</div>';

    // Tracking history
    if (transactions.length > 0) {
      html += '<div class="bg-gray-50 border border-gray-200 p-3 rounded mt-6">';
      html += '<h4 class="font-medium text-gray-900 mb-2">📋 Tracking History</h4>';
      html += '<div class="space-y-2 max-h-64 overflow-y-auto">';
      
      // Sort transactions by date (newest first)
      const sortedTransactions = [...transactions].sort((a, b) => {
        const dateA = new Date(a.transactionDateTime || 0);
        const dateB = new Date(b.transactionDateTime || 0);
        return dateB - dateA;
      });
      
      sortedTransactions.forEach(function(transaction, index) {
        const isLatest = index === 0;
        html += '<div class="' + (isLatest ? 'bg-green-50 border border-green-200' : 'bg-white border border-gray-200') + ' p-2 rounded text-xs">';
        html += '<div class="font-medium text-gray-900">' + (transaction.transaction || 'N/A') + '</div>';
        if (transaction.location) {
          html += '<div class="text-gray-600">📍 ' + transaction.location + '</div>';
        }
        if (transaction.transactionDateTime) {
          html += '<div class="text-gray-500">🕒 ' + transaction.transactionDateTime + '</div>';
        }
        html += '</div>';
      });
      
      html += '</div>';
      html += '</div>';
    } else {
      html += '<div class="bg-gray-50 border border-gray-200 p-3 rounded">';
      html += '<p class="text-sm text-gray-600">No tracking history available yet</p>';
      html += '</div>';
    }

    html += '</div>';
    return html;
  }

  function create_confirmation_modal(metabox_wrapper, shipment_row) {
    const modal_overlay = metabox_wrapper.find('#modal-body');
    const modal_content = modal_overlay.find('.hez-modal-content');
    const $confirmButton = modal_overlay.find('.hez-modal-confirm');
    
    // Reset button state before showing modal
    $confirmButton.prop('disabled', false).text(hezarfen_mst_backend.modal_btn_delete_text);
    
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
    modal_overlay.on('keydown', function(e) {
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
    modal_overlay.find('.hez-modal-confirm').off('click').on('click', function() {
      const $confirmButton = $(this);
      const originalText = $confirmButton.text();
      
      $confirmButton.prop('disabled', true).text(hezarfen_mst_backend.removing_text);
      
      $.post(
        ajaxurl,
        {
          action: hezarfen_mst_backend.remove_shipment_data_action,
          _wpnonce: hezarfen_mst_backend.remove_shipment_data_nonce,
          order_id: $('input#post_ID').val(),
          meta_id: shipment_row.data('meta_id')
        },
        function () {
          shipment_row.fadeOut(300, function() {
            $(this).remove();
            // Check if no shipments left and show empty state
            const remainingShipments = metabox_wrapper.find('tbody tr');
            if (remainingShipments.length === 0) {
              location.reload(); // Reload to show empty state
            }
          });
          closeModal();
        }
      ).fail(function () {
        // Reset button state on error
        $confirmButton.prop('disabled', false).text(originalText);
        closeModal();
        // Show error message
        alert(hezarfen_mst_backend.error_removing_shipment);
      });
    });
    
    // Handle cancel and close buttons
    modal_overlay.find('.hez-modal-cancel, .hez-modal-close').off('click').on('click', function() {
      closeModal();
    });
    
    // Handle backdrop click
    modal_overlay.on('click', function(e) {
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
      var hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
      var minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
      var seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);

      // Display the result in the respective span elements
      $('#days').text(days);
      $('#hours').text(hours);
      $('#minutes').text(minutes);
      $('#seconds').text(seconds);

      if (timeLeft < 0) {
          clearInterval(timer);
          $('#countdown').html(hezarfen_mst_backend.campaign_ended);
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

  // Load Hepsijet ile Avantajlı Kargo Fiyatları wallet balance
  function loadKargoGateBalance() {
    const balanceElement = $('#kargogate-balance');
    
    // Set loading state
    balanceElement.text(hezarfen_mst_backend.loading_text);
    
    const data = {
      action: hezarfen_mst_backend.get_kargogate_balance_action,
      _wpnonce: hezarfen_mst_backend.get_kargogate_balance_nonce
    };

    return $.post(ajaxurl, data, function(response) {
      if (response.success && response.data.balance && response.data.balance.formatted) {
        // Use HTML content since the formatted balance includes HTML
        balanceElement.html(response.data.balance.formatted);
      } else {
        balanceElement.text(hezarfen_mst_backend.error_loading_balance);
        console.error('Balance error:', response.data);
      }
    }).fail(function(xhr, status, error) {
      balanceElement.text(hezarfen_mst_backend.connection_error);
      console.error('Balance AJAX error:', error);
    });
  }

  /**
   * Load HepsiJet warehouses with 3-hour caching
   */
  function loadHepsijetWarehouses() {
    const $warehouseSelect = $('#hepsijet-warehouse');
    
    if (!$warehouseSelect.length) {
      return; // Element not found
    }

    // Check cache first (3 hours = 10800 seconds)
    const cacheKey = 'hepsijet_warehouses';
    const cacheExpiry = 3 * 60 * 60 * 1000; // 3 hours in milliseconds
    const cached = localStorage.getItem(cacheKey);
    const cacheTime = localStorage.getItem(cacheKey + '_time');
    
    if (cached && cacheTime) {
      const age = Date.now() - parseInt(cacheTime);
      if (age < cacheExpiry) {
        // Use cached data
        try {
          const warehouses = JSON.parse(cached);
          populateWarehouseDropdown(warehouses);
          return;
        } catch (e) {
          // Invalid cache, continue to fetch
        }
      }
    }

    // Fetch from API
    const data = {
      action: 'hepsijet_get_warehouses',
      _wpnonce: hezarfen_mst_backend.create_hepsijet_shipment_nonce
    };

    $.post(hezarfen_mst_backend.ajax_url, data, function(response) {
      if (response.success && response.data && response.data.warehouses) {
        const warehouses = response.data.warehouses;
        
        // Cache the response
        localStorage.setItem(cacheKey, JSON.stringify(warehouses));
        localStorage.setItem(cacheKey + '_time', Date.now().toString());
        
        // Populate dropdown
        populateWarehouseDropdown(warehouses);
      } else {
        $warehouseSelect.html('<option value="">Adres yüklenemedi</option>');
      }
    }).fail(function() {
      $warehouseSelect.html('<option value="">Adres yüklenemedi</option>');
    });
  }

  /**
   * Populate warehouse dropdown
   */
  function populateWarehouseDropdown(warehouses) {
    const $warehouseSelect = $('#hepsijet-warehouse');
    const $container = $('#hepsijet-warehouse-container');
    
    $warehouseSelect.empty();
    
    if (!warehouses || warehouses.length === 0) {
      $warehouseSelect.html('<option value="">Kayıtlı adres bulunamadı</option>');
      return;
    }
    
    // If only one warehouse (merchant only), select it by default and hide dropdown
    if (warehouses.length === 1) {
      $warehouseSelect.append(
        $('<option></option>').val(warehouses[0].id).text(warehouses[0].label).attr('selected', 'selected')
      );
      $container.hide(); // Hide the entire container
    } else {
      // Multiple warehouses - show dropdown
      $warehouseSelect.append('<option value="">-- Depo Seçiniz --</option>');
      
      $.each(warehouses, function(index, warehouse) {
        $warehouseSelect.append(
          $('<option></option>')
            .val(warehouse.id)
            .text(warehouse.label)
            .data('warehouse', warehouse)
        );
      });
      
      $container.show(); // Make sure container is visible
    }
  }

});
