<?php
defined('ABSPATH') || exit;

use \Hezarfen\ManualShipmentTracking\Helper;
use \Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration;

// Get order data for return address fields
$return_order = wc_get_order($order_id);
$return_address_data = array(
    'first_name' => '',
    'last_name' => '',
    'city' => '',
    'district' => '',
    'neighborhood' => '',
    'address' => '',
    'phone' => '',
);

if ($return_order) {
    // Name fields - only from shipping
    $return_address_data['first_name'] = $return_order->get_shipping_first_name();
    $return_address_data['last_name'] = $return_order->get_shipping_last_name();

    $shipping_country = $return_order->get_shipping_country();
    $shipping_state = $return_order->get_shipping_state();

    // City (Il) - from WooCommerce states (Turkey uses state for city)
    if ($shipping_country && $shipping_state && isset(WC()->countries->states[$shipping_country][$shipping_state])) {
        $return_address_data['city'] = WC()->countries->states[$shipping_country][$shipping_state];
    }

    // District (Ilce) - stored in city field for Turkey
    $return_address_data['district'] = $return_order->get_shipping_city();

    // Neighborhood (Mahalle) - stored in address_1 for Turkey
    $return_address_data['neighborhood'] = $return_order->get_shipping_address_1();

    // Address - stored in address_2 for Turkey
    $return_address_data['address'] = $return_order->get_shipping_address_2();

    // Phone: try shipping first, fallback to billing
    $return_address_data['phone'] = $return_order->get_shipping_phone() ?: $return_order->get_billing_phone();
}

// Fetch warehouses for Hepsijet
$warehouses_data = array();
$has_multiple_warehouses = false;
$warehouse_error = null;
$has_hepsijet_credentials = Courier_Hepsijet_Integration::has_credentials();

if ( $has_hepsijet_credentials ) {
    try {
        $hepsijet_integration_warehouses = new Courier_Hepsijet_Integration();
        $warehouses_response = $hepsijet_integration_warehouses->get_warehouses();

        if ( is_wp_error( $warehouses_response ) ) {
            $warehouse_error = $warehouses_response->get_error_message();
        } elseif ( isset( $warehouses_response['warehouses'] ) ) {
            $warehouses_data = $warehouses_response['warehouses'];
            $has_multiple_warehouses = count( $warehouses_data ) > 1;
        } else {
            $warehouse_error = 'Invalid response format';
        }
    } catch ( Exception $e ) {
        $warehouse_error = $e->getMessage();
    }
}
?>
<style>
@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.7;
  }
}

#hepsijet-rotating-info .hez-pricing {
    font-size: 1.125rem !important;
}

#hepsijet-rotating-info .hez-desi-range {
    font-size: 16px !important;
}

@media (max-width: 1760px) {
    .hezarfen-responsive-btn.text-sm {
        font-size: 0.65rem !important;
        line-height: 1.2 !important;
        padding: 0.375rem 0.625rem !important;
    }
    .hezarfen-responsive-btn.text-sm svg {
        width: 0.875rem !important;
        height: 0.875rem !important;
    }

    .rotating-item .text-xs {
        font-size: 0.65rem !important;
        line-height: 1.2 !important;
        padding: 0.2rem 0.25rem !important;
    }

    #hepsijet-rotating-info .hez-pricing {
        font-size: 0.875rem !important;
        line-height: 1.2 !important;
        padding: 0 !important;
    }

    #hepsijet-rotating-info .hez-desi-range {
        font-size: 0.65rem !important;
        line-height: 1.2 !important;
        padding: 0 !important;
    }
}
</style>
<div id="hez-order-shipments" class="hez-ui">

    <?php if (defined('HEZARFEN_PRO_VERSION')) : ?>
    <div class="mb-4 border-gray-200 dark:border-gray-700">
        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="default-tab" data-tabs-toggle="#default-tab-content" role="tablist">
            <li class="w-1/2" role="presentation">
                <button class="h-16	center flex justify-center items-center	 w-full gap-4 inline-block p-2 border-b-2 rounded-t-lg hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300" id="hezarfen-pro-tab" data-tabs-target="#hezarfen-pro" type="button" role="tab" aria-controls="hezarfen-pro" aria-selected="false">
                    <?php esc_html_e('Shipment Barcode / Automated Shipment Tracking', 'hezarfen-for-woocommerce'); ?>
                </button>
            </li>
            <li class="w-1/2" role="presentation">
                <button class="h-16	w-full inline-block p-2 border-b-2 rounded-t-lg" id="hezarfen-lite-tab" data-tabs-target="#hezarfen-lite" type="button" role="tab" aria-controls="hezarfen-lite" aria-selected="false"><?php esc_html_e('Manual Tracking', 'hezarfen-for-woocommerce'); ?></button>
            </li>
        </ul>
    </div>
    <?php endif; ?>
    <div id="default-tab-content">
        <div class="<?php if (defined('HEZARFEN_PRO_VERSION')) : ?>hidden<?php endif; ?> rounded-lg" id="hezarfen-lite" role="tabpanel" aria-labelledby="hezarfen-lite-tab">
            <div class="grid grid-cols-2 gap-8">
                <div>
                    <p class="text-lg text-black"><?php esc_html_e('Enter Tracking Information', 'hezarfen-for-woocommerce'); ?></p>
                    <p class="text-gray-1 text-xs font-light"><?php esc_html_e('In order to track your shipment, please enter your tracking number and select courier from below and add it to your tracking list.', 'hezarfen-for-woocommerce'); ?></p>

                    <div class="mt-4">
                        <div class="mb-2">
                            <label class="font-light text-gray-1 block mb-2 text-sm dark:text-white"><?php esc_html_e('Select a Courier Company', 'hezarfen-for-woocommerce'); ?></label>
                            
                            
                            <ul id="shipping-companies" class="max-h-24 grid w-full gap-2 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 3xl:grid-cols-5 4xl:grid-cols-6 5xl:grid-cols-7 6xl:grid-cols-8 overflow-hidden">
                                <li class="flex justify-center col-span-2 xl:col-span-2 2xl:col-span-3">
                                    <input type="radio" id="courier-company-select-hepsijet-entegrasyon"
                                            name="courier-company-select" value="hepsijet-entegrasyon"
                                            class="hidden peer">

                                    <label for="courier-company-select-hepsijet-entegrasyon"
                                            class="relative flex items-center justify-between w-full p-1 h-12
                                                    bg-gradient-to-r from-blue-50 to-blue-100 border border-blue-300
                                                    rounded-xl cursor-pointer shadow-sm
                                                    peer-checked:border-2 peer-checked:border-blue-500 peer-checked:shadow-lg
                                                    hover:shadow-md">

                                        <!-- Sol: Logo ve metin -->
                                        <div class="flex items-center gap-2 w-full">
                                            <div class="flex flex-col items-center flex-shrink-0">
                                            <img class="max-h-5 w-auto"
                                                src="<?php echo esc_url(HEZARFEN_MST_COURIER_LOGO_URL . Helper::get_courier_class('hepsijet')::$logo); ?>" 
                                                loading="lazy"
                                                alt="HepsiJet">

                                            <!-- Etiket Rozet -->
                                            <span class="text-[7px] bg-blue-500 text-white px-1 py-0.5 rounded-full shadow-sm font-medium mt-0.5">
                                                Ücretsiz Entegrasyon
                                            </span>
                                            
                                            </div>

                                            <div class="flex flex-col leading-tight overflow-hidden flex-1 min-w-0">
                                            <!-- Döngü halinde gösterilen bilgiler -->
                                            <div id="hepsijet-rotating-info" class="text-xs text-gray-600 min-h-[1.5rem] flex items-center">
                                                <!-- Fiyat bilgisi -->
                                                <div class="rotating-item active">
                                                    <?php
                                                    // Get actual pricing for 1 and 4 desi to determine price range
                                                    try {
                                                        $hepsijet_integration = new \Hezarfen\ManualShipmentTracking\Courier_Hepsijet_Integration();
                                                        $pricing_info = $hepsijet_integration->get_pricing_range_info();
                                                        
                                                        if ($pricing_info !== false) {
                                                            $price = $pricing_info['price_1_desi'];
                                                            $desi_range = $pricing_info['display_text'];
                                                            echo '<span class="hez-pricing" style="font-weight: bold !important; color: #2563eb !important;">' . esc_html(number_format($price, 2, ',', '')) . '₺+KDV</span>';
                                                            echo ' <span class="hez-desi-range text-gray-700 font-medium">(' . esc_html($desi_range) . ')</span>';
                                                        }
                                                    } catch (Exception $e) {
                                                    }
                                                    ?>
                                                </div>
                                                
                                                <!-- Avantaj 1 -->
                                                <div class="rotating-item">
                                                    <span class="text-xs bg-green-500 text-white px-2 py-1 rounded-full shadow-sm font-medium">
                                                        <?php esc_html_e('Kargo anlaşması gerekmez', 'hezarfen-for-woocommerce'); ?>
                                                    </span>
                                                </div>
                                                
                                                <!-- Avantaj 2 -->
                                                <div class="rotating-item">
                                                    <span class="text-xs bg-orange-500 text-white px-2 py-1 rounded-full shadow-sm font-medium">
                                                        <?php esc_html_e('Min. gönderim limiti yok', 'hezarfen-for-woocommerce'); ?>
                                                    </span>
                                                </div>
                                                
                                                <!-- Avantaj 3 -->
                                                <div class="rotating-item">
                                                    <span class="text-xs bg-purple-500 text-white px-2 py-1 rounded-full shadow-sm font-medium">
                                                        <?php esc_html_e('Kargo adresinizden alınır', 'hezarfen-for-woocommerce'); ?>
                                                    </span>
                                                </div>

                                                <!-- Avantaj 3 -->
                                                <div class="rotating-item">
                                                    <span class="text-xs bg-indigo-500 text-white px-2 py-1 rounded-full shadow-sm font-medium">
                                                        <?php esc_html_e('Intense&Hepsijet İşbirliği', 'hezarfen-for-woocommerce'); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="hezarfen-marquee-text text-xs text-gray-600">
                                                <span><?php echo esc_html__('Free integration with barcode creation, instant tracking updates, and complimentary SMS/email notifications. Orders are marked as shipped automatically, and once delivered, status is set to completed.', 'hezarfen-for-woocommerce'); ?></span>
                                            </div>
                                            </div>
                                        </div>
                                    </label>
                                </li>


                                <?php foreach (Helper::courier_company_options() as $courier_id => $courier_label) : if (empty($courier_id)) {
                                        continue;
                                    } ?>
                                    <li class="flex justify-center">
                                        <input type="radio" id="courier-company-select-<?php echo esc_attr($courier_id); ?>" name="courier-company-select" value="<?php echo esc_attr($courier_id); ?>" class="hidden peer" />
                                        <label for="courier-company-select-<?php echo esc_attr($courier_id); ?>" class="flex justify-center h-12 items-center justify-between w-full p-5 text-gray-3 bg-white border border-gray-3 rounded-lg cursor-pointer dark:hover:text-gray-300 dark:border-gray-3 dark:peer-checked:text-blue-500 peer-checked:bg-orange-1 peer-checked:border-2 peer-checked:border-orange-2 peer-checked:text-blue-600 hover:text-gray-600 hover:bg-orange-1 dark:text-gray-400 dark:bg-gray-800 dark:hover:bg-gray-700">
                                            <img style="max-height: 40px !important;" class="max-h-8" src="<?php echo esc_attr(HEZARFEN_MST_COURIER_LOGO_URL . Helper::get_courier_class($courier_id)::$logo); ?>" loading="lazy" alt="<?php echo esc_attr($courier_label); ?>" />
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="flex justify-center">
                            <div class="flex justify-center">
                                <button type="button" class="h-expand items-center text-black px-4 py-2 flex" data-show-more-label="<?php esc_html_e( 'Show More', 'hezarfen-for-woocommerce' ); ?>" data-show-less-label="<?php esc_html_e( 'Show Less', 'hezarfen-for-woocommerce' ); ?>">
                                    <span><?php esc_html_e( 'Show More', 'hezarfen-for-woocommerce' ); ?></span>
                                    <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M8.00048 9.11403L11.3005 5.81403L12.2431 6.75736L8.00048 11L3.75781 6.75736L4.70048 5.81469L8.00048 9.11403Z" fill="black"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Standard tracking number input -->
                        <div class="mb-5 hidden" id="standard-tracking-fields">
                            <label for="tracking-num-input" class="font-light text-gray-1 block mb-2 text-sm dark:text-white"><?php esc_html_e('Tracking Number', 'hezarfen-for-woocommerce'); ?></label>
                            <input type="text" id="tracking-num-input" class="shadow-sm bg-gray-50 border border-gray-300 text-gray-3 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 dark:shadow-sm-light" />
                        </div>

                        <!-- Hepsijet integration fields -->
                        <div class="mb-5 hidden" id="hepsijet-integration-fields">
                            <?php
                            // Check if Hepsijet credentials are configured
                            $consumer_key = get_option( 'hezarfen_hepsijet_consumer_key', '' );
                            $consumer_secret = get_option( 'hezarfen_hepsijet_consumer_secret', '' );
                            $credentials_missing = empty( $consumer_key ) || empty( $consumer_secret );
                            ?>

                            <div class="mb-4 flex items-center justify-between">
                                <span class="text-sm font-medium text-orange-600" style="font-size: 13px !important;">kargokit.com & Hepsijet işbirliği ile Avantajlı Kargo Fiyatları</span>
                                <button type="button" id="hepsijet-help-toggle" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs rounded-lg hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-500 ml-2">
                                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <?php esc_html_e('Help', 'hezarfen-for-woocommerce'); ?>
                                </button>
                            </div>

                            <!-- Help Content (hidden by default) -->
                            <div id="hezarfen-help-content" class="mb-4 hidden p-6 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg border border-blue-200">
                                <div class="text-center max-w-md mx-auto">
                                    <div class="mb-4">
                                        <svg class="w-12 h-12 mx-auto text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2">
                                        <?php if ( $credentials_missing ): ?>
                                            <?php esc_html_e('🚀 Start Shipping Without a Shipping Agreement', 'hezarfen-for-woocommerce'); ?>
                                        <?php else: ?>
                                            <?php esc_html_e('🚀 HepsiJet Integration Guide', 'hezarfen-for-woocommerce'); ?>
                                        <?php endif; ?>
                                    </h3>
                                    <p class="text-sm text-gray-600 mb-4">
                                        <?php if ( $credentials_missing ): ?>
                                            <?php esc_html_e('Quick setup to unlock Hepsijet free integration', 'hezarfen-for-woocommerce'); ?>
                                        <?php else: ?>
                                            <?php esc_html_e('How to use HepsiJet integration effectively', 'hezarfen-for-woocommerce'); ?>
                                        <?php endif; ?>
                                    </p>
                                    <div class="text-left space-y-3 mb-6">
                                        <?php if ( $credentials_missing ): ?>
                                            <div class="flex items-start gap-3">
                                                <span class="flex-shrink-0 w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-bold">1</span>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-800"><?php esc_html_e('Create Account', 'hezarfen-for-woocommerce'); ?></p>
                                                    <p class="text-xs text-gray-600">
                                                        <?php printf( 
                                                            esc_html__('Create or sign in to your %s account', 'hezarfen-for-woocommerce'), 
                                                            '<strong>kargokit.com</strong>' 
                                                        ); ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="flex items-start gap-3">
                                                <span class="flex-shrink-0 w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-bold">2</span>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-800"><?php esc_html_e('Access Hepsijet ile Avantajlı Kargo Fiyatları', 'hezarfen-for-woocommerce'); ?></p>
                                                    <p class="text-xs text-gray-600"><?php esc_html_e('Go to My Account → Click Hepsijet ile Avantajlı Kargo Fiyatları', 'hezarfen-for-woocommerce'); ?></p>
                                                </div>
                                            </div>
                                            <div class="flex items-start gap-3">
                                                <span class="flex-shrink-0 w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-bold">3</span>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-800"><?php esc_html_e('Configure Integration', 'hezarfen-for-woocommerce'); ?></p>
                                                    <p class="text-xs text-gray-600"><?php esc_html_e('Complete the form and copy credentials to Hezarfen settings', 'hezarfen-for-woocommerce'); ?></p>
                                                </div>
                                            </div>
                                            <div class="flex items-start gap-3">
                                                <span class="flex-shrink-0 w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-bold">4</span>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-800"><?php esc_html_e('Add Balance', 'hezarfen-for-woocommerce'); ?></p>
                                                    <p class="text-xs text-gray-600"><?php esc_html_e('Upload balance to your Intense Wallet', 'hezarfen-for-woocommerce'); ?></p>
                                                </div>
                                            </div>
                                            <div class="flex items-start gap-3">
                                                <span class="flex-shrink-0 w-6 h-6 bg-green-500 text-white rounded-full flex items-center justify-center text-xs font-bold">✓</span>
                                                <div>
                                                    <p class="text-sm font-medium text-green-800"><?php esc_html_e('Create first barcode here!', 'hezarfen-for-woocommerce'); ?></p>
                                                    <p class="text-xs text-green-600"><?php esc_html_e('Start creating shipments with automatic tracking', 'hezarfen-for-woocommerce'); ?></p>
                                                </div>
                                            </div>
                                            <div class="flex gap-2 mt-6">
                                                <a href="https://kargokit.com" target="_blank" class="flex-1 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 text-center">
                                                    <?php esc_html_e('Go to kargokit.com', 'hezarfen-for-woocommerce'); ?>
                                                </a>
                                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=hezarfen&section=hepsijet_integration' ) ); ?>" target="_blank" class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 text-center">
                                                    <?php esc_html_e('Settings', 'hezarfen-for-woocommerce'); ?>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <div class="flex items-start gap-3">
                                                <span class="flex-shrink-0 w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-bold">1</span>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-800"><?php esc_html_e('Fill Form Details', 'hezarfen-for-woocommerce'); ?></p>
                                                    <p class="text-xs text-gray-600"><?php esc_html_e('Enter package weight, dimensions, and delivery preferences', 'hezarfen-for-woocommerce'); ?></p>
                                                </div>
                                            </div>
                                            <div class="flex items-start gap-3">
                                                <span class="flex-shrink-0 w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-bold">2</span>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-800"><?php esc_html_e('Create Shipment', 'hezarfen-for-woocommerce'); ?></p>
                                                    <p class="text-xs text-gray-600"><?php esc_html_e('Click Create Shipment to generate barcode and tracking', 'hezarfen-for-woocommerce'); ?></p>
                                                </div>
                                            </div>
                                            <div class="flex items-start gap-3">
                                                <span class="flex-shrink-0 w-6 h-6 bg-green-500 text-white rounded-full flex items-center justify-center text-xs font-bold">✓</span>
                                                <div>
                                                    <p class="text-sm font-medium text-green-800"><?php esc_html_e('Automatic Updates', 'hezarfen-for-woocommerce'); ?></p>
                                                    <p class="text-xs text-green-600"><?php esc_html_e('Order status and customer notifications handled automatically', 'hezarfen-for-woocommerce'); ?></p>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            
                            <!-- Show balance display only if credentials are configured -->

                            <!-- Help Content (hidden by default) -->
                            <div id="hepsijet-help-content" class="mb-4 hidden p-6 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg border border-blue-200">
                                <div class="text-center max-w-md mx-auto">
                                    <div class="mb-4">
                                        <svg class="w-12 h-12 mx-auto text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2">
                                        <?php esc_html_e('🚀 Start Shipping Without a Shipping Agreement', 'hezarfen-for-woocommerce'); ?>
                                    </h3>
                                    <p class="text-sm text-gray-600 mb-4">
                                        <?php esc_html_e('Quick setup to unlock Hepsijet free integration', 'hezarfen-for-woocommerce'); ?>
                                    </p>
                                    <div class="text-left space-y-3 mb-6">
                                        <div class="flex items-start gap-3">
                                            <span class="flex-shrink-0 w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-bold">1</span>
                                            <div>
                                                <p class="text-sm font-medium text-gray-800"><?php esc_html_e('Create Account', 'hezarfen-for-woocommerce'); ?></p>
                                                <p class="text-xs text-gray-600">
                                                    <?php printf( 
                                                        esc_html__('Create or sign in to your %s account', 'hezarfen-for-woocommerce'), 
                                                        '<strong>kargokit.com</strong>' 
                                                    ); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-3">
                                            <span class="flex-shrink-0 w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-bold">2</span>
                                            <div>
                                                <p class="text-sm font-medium text-gray-800"><?php esc_html_e('Access Hepsijet ile Avantajlı Kargo Fiyatları', 'hezarfen-for-woocommerce'); ?></p>
                                                <p class="text-xs text-gray-600"><?php esc_html_e('Go to My Account → Click Hepsijet ile Avantajlı Kargo Fiyatları', 'hezarfen-for-woocommerce'); ?></p>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-3">
                                            <span class="flex-shrink-0 w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-bold">3</span>
                                            <div>
                                                <p class="text-sm font-medium text-gray-800"><?php esc_html_e('Configure Integration', 'hezarfen-for-woocommerce'); ?></p>
                                                <p class="text-xs text-gray-600"><?php esc_html_e('Complete the form and copy credentials to Hezarfen settings', 'hezarfen-for-woocommerce'); ?></p>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-3">
                                            <span class="flex-shrink-0 w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-bold">4</span>
                                            <div>
                                                <p class="text-sm font-medium text-gray-800"><?php esc_html_e('Add Balance', 'hezarfen-for-woocommerce'); ?></p>
                                                <p class="text-xs text-gray-600"><?php esc_html_e('Upload balance to your Intense Wallet', 'hezarfen-for-woocommerce'); ?></p>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-3">
                                            <span class="flex-shrink-0 w-6 h-6 bg-green-500 text-white rounded-full flex items-center justify-center text-xs font-bold">✓</span>
                                            <div>
                                                <p class="text-sm font-medium text-green-800"><?php esc_html_e('Create first barcode here!', 'hezarfen-for-woocommerce'); ?></p>
                                                <p class="text-xs text-green-600"><?php esc_html_e('Start creating shipments with automatic tracking', 'hezarfen-for-woocommerce'); ?></p>
                                            </div>
                                        </div>
                                        
                                        <div class="flex gap-2 mt-6">
                                            <a href="https://kargokit.com" target="_blank" class="flex-1 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 text-center">
                                                <?php esc_html_e('Go to kargokit.com', 'hezarfen-for-woocommerce'); ?>
                                            </a>
                                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=hezarfen&section=hepsijet_integration' ) ); ?>" target="_blank" class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 text-center">
                                                <?php esc_html_e('Settings', 'hezarfen-for-woocommerce'); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Wallet Balance Display -->
                            <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-blue-800">kargokit.com <?php esc_html_e('Shipment Balance:', 'hezarfen-for-woocommerce'); ?></span>
                                    <div class="flex items-center gap-2">
                                        <?php if ( $credentials_missing ): ?>
                                            <span id="kargogate-balance" class="text-sm font-bold text-blue-600">
                                                0,00TL
                                            </span>
                                        <?php else: ?>
                                            <span id="kargogate-balance" class="text-sm font-bold text-blue-600">
                                                <?php echo esc_html__('Click to check', 'hezarfen-for-woocommerce'); ?>
                                            </span>
                                            <button type="button" id="check-kargogate-balance" class="px-2 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <?php esc_html_e('Check', 'hezarfen-for-woocommerce'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Delivery Type -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2"><?php esc_html_e('Delivery Type', 'hezarfen-for-woocommerce'); ?></label>
                                <ul class="grid w-full gap-2 grid-cols-2" style="max-width: 400px;">
                                    <li>
                                        <input type="radio" id="hepsijet-delivery-type-standard" name="hepsijet-delivery-type" value="standard" class="hidden peer" checked>
                                        <label for="hepsijet-delivery-type-standard" class="inline-flex items-center justify-center w-full p-2 text-gray-500 bg-white border border-gray-200 rounded-lg cursor-pointer peer-checked:border-blue-600 peer-checked:text-blue-600 peer-checked:bg-blue-50 hover:text-gray-600 hover:bg-gray-100">
                                            <span class="text-sm font-medium"><?php esc_html_e('Shipment', 'hezarfen-for-woocommerce'); ?></span>
                                        </label>
                                    </li>
                                    <li>
                                        <input type="radio" id="hepsijet-delivery-type-returned" name="hepsijet-delivery-type" value="returned" class="hidden peer">
                                        <label for="hepsijet-delivery-type-returned" class="inline-flex items-center justify-center w-full p-2 text-gray-500 bg-white border border-gray-200 rounded-lg cursor-pointer peer-checked:border-blue-600 peer-checked:text-blue-600 peer-checked:bg-blue-50 hover:text-gray-600 hover:bg-gray-100">
                                            <span class="text-sm font-medium"><?php esc_html_e('Return by appointment', 'hezarfen-for-woocommerce'); ?></span>
                                        </label>
                                    </li>
                                </ul>
                            </div>

                            <!-- Warehouse Selection -->
                            <?php if ( $has_hepsijet_credentials ) : ?>
                            <div class="mb-4" id="hepsijet-warehouse-container">
                                <label for="hepsijet-warehouse" class="block text-sm font-medium text-gray-700 mb-2">
                                    <?php esc_html_e( 'Depo Seçiniz', 'hezarfen-for-woocommerce' ); ?>
                                    <span class="text-red-500">*</span>
                                </label>
                                <?php if ( ! empty( $warehouses_data ) ) : ?>
                                    <select id="hepsijet-warehouse" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" data-loaded-server-side="true">
                                        <?php if ( $has_multiple_warehouses ) : ?>
                                            <option value=""><?php esc_html_e( 'Depo seçiniz', 'hezarfen-for-woocommerce' ); ?></option>
                                        <?php endif; ?>
                                        <?php foreach ( $warehouses_data as $warehouse ) : ?>
                                            <option value="<?php echo esc_attr( $warehouse['id'] ); ?>" 
                                                    data-type="<?php echo esc_attr( $warehouse['type'] ); ?>"
                                                    <?php selected( ! $has_multiple_warehouses ); ?>>
                                                <?php echo esc_html( $warehouse['label'] ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ( ! $has_multiple_warehouses ) : ?>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?php esc_html_e( 'Tek deponuz var. Daha fazla depo eklemek için KargoKit müşteri hizmetleriyle iletişime geçin', 'hezarfen-for-woocommerce' ); ?>
                                    </p>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                                        <p class="text-sm text-red-800">
                                            <?php 
                                            if ( $warehouse_error ) {
                                                printf( 
                                                    esc_html__( 'Depo bilgisi yüklenemedi: %s', 'hezarfen-for-woocommerce' ),
                                                    esc_html( $warehouse_error )
                                                );
                                            } else {
                                                esc_html_e( 'Depo bilgisi yüklenemedi. Lütfen kargokit.com hesabınızda firma kaydınızı tamamladığınızdan emin olun.', 'hezarfen-for-woocommerce' );
                                            }
                                            ?>
                                        </p>
                                    </div>
                                    <select id="hepsijet-warehouse" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100" disabled>
                                        <option value="">Depo bulunamadı</option>
                                    </select>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <div class="mb-4" id="hepsijet-packages-section">
                                <div class="flex justify-between items-center mb-2">
                                    <label class="block text-sm font-medium text-gray-700"><?php esc_html_e('Koliler', 'hezarfen-for-woocommerce'); ?></label>
                                    <button type="button" id="add-hepsijet-package" class="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        <?php esc_html_e('Koli Ekle', 'hezarfen-for-woocommerce'); ?>
                                    </button>
                                </div>
                                <div id="hepsijet-packages-container" class="space-y-2">
                                    <!-- Package items will be dynamically added here -->
                                </div>
                            </div>

                            <!-- Always show the form, regardless of credentials status -->
                            <?php if ( $credentials_missing ): ?>
                                <!-- Show a notice that credentials are needed for actual shipment creation -->
                                <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                    <p class="text-sm text-yellow-800">
                                        <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                        </svg>
                                        <?php esc_html_e('Configure credentials in settings to enable shipment creation.', 'hezarfen-for-woocommerce'); ?>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=hezarfen&section=hepsijet_integration' ) ); ?>" target="_blank" class="underline font-medium"><?php esc_html_e('Go to Settings', 'hezarfen-for-woocommerce'); ?></a>
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Return Fields Container (for return type) -->
                            <div style="display: none;" id="hepsijet-return-fields-container">
                                <!-- Return Address Fields -->
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="hepsijet-return-first-name" class="block text-sm font-medium text-gray-700 mb-2"><?php esc_html_e('First Name', 'hezarfen-for-woocommerce'); ?> <span class="text-red-500">*</span></label>
                                        <input type="text" id="hepsijet-return-first-name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="<?php echo esc_attr($return_address_data['first_name']); ?>">
                                    </div>
                                    <div>
                                        <label for="hepsijet-return-last-name" class="block text-sm font-medium text-gray-700 mb-2"><?php esc_html_e('Last Name', 'hezarfen-for-woocommerce'); ?> <span class="text-red-500">*</span></label>
                                        <input type="text" id="hepsijet-return-last-name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="<?php echo esc_attr($return_address_data['last_name']); ?>">
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="hepsijet-return-phone" class="block text-sm font-medium text-gray-700 mb-2"><?php esc_html_e('Phone', 'hezarfen-for-woocommerce'); ?> <span class="text-red-500">*</span></label>
                                        <input type="text" id="hepsijet-return-phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="<?php echo esc_attr($return_address_data['phone']); ?>">
                                    </div>
                                    <div>
                                        <label for="hepsijet-return-city" class="block text-sm font-medium text-gray-700 mb-2"><?php esc_html_e('City', 'hezarfen-for-woocommerce'); ?> <span class="text-red-500">*</span></label>
                                        <input type="text" id="hepsijet-return-city" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="<?php echo esc_attr($return_address_data['city']); ?>">
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="hepsijet-return-district" class="block text-sm font-medium text-gray-700 mb-2"><?php esc_html_e('District', 'hezarfen-for-woocommerce'); ?> <span class="text-red-500">*</span></label>
                                        <input type="text" id="hepsijet-return-district" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="<?php echo esc_attr($return_address_data['district']); ?>">
                                    </div>
                                    <div>
                                        <label for="hepsijet-return-neighborhood" class="block text-sm font-medium text-gray-700 mb-2"><?php esc_html_e('Neighborhood', 'hezarfen-for-woocommerce'); ?> <span class="text-red-500">*</span></label>
                                        <input type="text" id="hepsijet-return-neighborhood" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="<?php echo esc_attr($return_address_data['neighborhood']); ?>">
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="hepsijet-return-address" class="block text-sm font-medium text-gray-700 mb-2"><?php esc_html_e('Address', 'hezarfen-for-woocommerce'); ?> <span class="text-red-500">*</span></label>
                                    <textarea id="hepsijet-return-address" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"><?php echo esc_textarea($return_address_data['address']); ?></textarea>
                                </div>

                                <!-- Fetch Available Dates Button -->
                                <div class="mb-4">
                                    <button type="button" id="hepsijet-fetch-return-dates" class="w-full px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 rounded-lg transition-colors duration-200">
                                        <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        <?php esc_html_e('Alım Yapılabilecek Tarihleri Listele', 'hezarfen-for-woocommerce'); ?>
                                    </button>
                                </div>

                                <!-- Return Date (hidden by default, shown after fetching dates) -->
                                <div class="mb-4 hidden" id="hepsijet-return-date-container">
                                    <label for="hepsijet-return-date" class="block text-sm font-medium text-gray-700 mb-2"><?php esc_html_e('Return Date', 'hezarfen-for-woocommerce'); ?> <span class="text-red-500">*</span></label>
                                    <select id="hepsijet-return-date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value=""><?php esc_html_e('Select a date', 'hezarfen-for-woocommerce'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-center mt-2">
                            <!-- Standard button -->
                            <button data-order_id="<?php echo esc_attr($order_id); ?>" id="add-to-tracking-list" type="button" class="hidden w-full text-white bg-gray-800 hover:bg-gray-900 focus:outline-none focus:ring-4 focus:ring-gray-300 font-normal rounded-lg text-sm px-5 py-2.5 me-2 mb-2 dark:bg-gray-800 dark:hover:bg-gray-700 dark:focus:ring-gray-700 dark:border-gray-700"><?php esc_html_e('Add to Tracking List', 'hezarfen-for-woocommerce'); ?></button>
                            
                             <!-- Hepsijet integration button -->
                             <button data-order_id="<?php echo esc_attr($order_id); ?>" id="create-hepsijet-shipment" type="button" class="w-full text-white <?php echo $credentials_missing ? 'bg-gray-400 cursor-not-allowed' : 'bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-4 focus:ring-orange-300'; ?> font-normal rounded-lg text-sm px-5 py-2.5 me-2 mb-2" <?php echo $credentials_missing ? 'disabled' : ''; ?>><?php esc_html_e('Create Shipment', 'hezarfen-for-woocommerce'); ?></button>
                        </div>
                    </div>
                </div>
                <div id="hezarfen-right-side">
  <!-- SMS Settings Icon Button - Top Right -->
                    <div class="flex justify-end mb-4 gap-2">
                                                 <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=hezarfen&section=hepsijet_integration' ) ); ?>" 
                        class="hezarfen-responsive-btn inline-flex items-center px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 transition-all duration-200 group" 
                        target="_blank"
                        title="<?php esc_attr_e( 'Configure Hepsijet Integration Settings', 'hezarfen-for-woocommerce' ); ?>">
                            <svg class="w-4 h-4 mr-2 group-hover:animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <?php esc_html_e( 'Hepsijet Integration Settings', 'hezarfen-for-woocommerce' ); ?>
                           <svg style="max-height: 50px" class="w-3 h-3 ml-1 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                           </svg>
                       </a>
                       <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=hezarfen&section=sms_settings' ) ); ?>" 
                       class="hezarfen-responsive-btn inline-flex items-center px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 transition-all duration-200 group" 
                       target="_blank"
                       title="<?php esc_attr_e( 'Configure SMS automation for order status changes', 'hezarfen-for-woocommerce' ); ?>">
                           <svg style="max-height: 50px" class="w-4 h-4 mr-2 group-hover:animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                           </svg>
                           <?php esc_html_e( 'SMS Settings', 'hezarfen-for-woocommerce' ); ?>
                           <svg style="max-height: 50px" class="w-3 h-3 ml-1 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                           </svg>
                       </a>
                       <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=hezarfen' ) ); ?>" 
                       class="hezarfen-responsive-btn inline-flex items-center px-3 py-2 text-sm font-medium text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1 transition-all duration-200 group" 
                       target="_blank"
                       title="<?php esc_attr_e( 'Watch training videos to learn how to use Hezarfen', 'hezarfen-for-woocommerce' ); ?>">
                           <svg style="max-height: 50px" class="w-4 h-4 mr-2 group-hover:animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                           </svg>
                           <?php esc_html_e( 'Training Videos', 'hezarfen-for-woocommerce' ); ?>
                           <svg style="max-height: 50px" class="w-3 h-3 ml-1 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                           </svg>
                       </a>
                   </div>
                    <?php
                    // Get Hepsijet shipments from encapsulated meta data
                    $order = wc_get_order($order_id);
                    $all_meta = $order->get_meta_data();
                    $integration_shipments = array();
                    $manual_shipments = Helper::get_all_shipment_data($order_id);

                    // Find Hepsijet shipments in meta data
                    foreach ($all_meta as $meta) {
                        if (strpos($meta->key, '_hezarfen_hepsijet_shipment_') === 0) {
                            $meta_value = $meta->value;
                            if (is_array($meta_value) && isset($meta_value['delivery_no'])) {
                                // Create a shipment object for display
                                $shipment = new stdClass();
                                $shipment->meta_id = $meta->id;
                                $shipment->tracking_num = $meta_value['delivery_no'];
                                $shipment->courier_id = 'hepsijet-entegrasyon';
                                $shipment->courier_title = 'Hepsijet Entegrasyon';
                                $shipment->shipment_details = $meta_value;
                                
                                $integration_shipments[] = $shipment;
                            }
                        }
                    }

                    $has_integration_shipments = count($integration_shipments) > 0;
                    $has_manual_shipments = count($manual_shipments) > 0;

                    if (!$has_integration_shipments && !$has_manual_shipments) :
                    ?>
                        <div class="border-dashed border-2 border-gray-2 p-4 flex justify-center min-h-48">
                            <div id="no-shipments" class="w-9/12 flex justify-center flex-col font-medium items-center">
                                <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 20">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m13 19-6-5-6 5V2a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v17Z" />
                                </svg>
                                <p class="text-black text-lg text-center"><?php esc_html_e('Nothing to Track Yet', 'hezarfen-for-woocommerce'); ?></p>
                                <div class="text-center">
                                    <p class="text-gray-1 font-light"><?php esc_html_e('There are no tracking numbers added to the tracking list.', 'hezarfen-for-woocommerce'); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php
                    else :
                    ?>
                        <div class="space-y-6" data-order_id="<?php echo esc_attr($order_id); ?>">
                            <!-- Integration Shipments Table -->
                            <?php if ($has_integration_shipments) : ?>
                                <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                                    <div class="bg-orange-50 px-4 py-2 border-b">
                                        <h4 class="text-sm font-medium text-orange-800"><?php esc_html_e('Integration Shipments', 'hezarfen-for-woocommerce'); ?></h4>
                                    </div>
                                    <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                            <tr>
                                                <th scope="col" class="px-6 py-3"><?php esc_html_e('Courier', 'hezarfen-for-woocommerce'); ?></th>
                                                <th scope="col" class="px-6 py-3"><?php esc_html_e('Koli/Desi', 'hezarfen-for-woocommerce'); ?></th>
                                                <th scope="col" class="px-6 py-3"><?php esc_html_e('Actions', 'hezarfen-for-woocommerce'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($integration_shipments as $shipment_args):
                                                // Get shipment details directly from the shipment object
                                                $shipment_details = $shipment_args->shipment_details;
                                                
                                                // Extract data from encapsulated JSON meta
                                                $packages = $shipment_details['packages'] ?? null;
                                                $package_count = $shipment_details['package_count'] ?? null;
                                                $desi = $shipment_details['desi'] ?? null;
                                                $delivery_no = $shipment_details['delivery_no'] ?? null;
                                                $cancelled_at = $shipment_details['cancelled_at'] ?? null;
                                                $cancel_reason = $shipment_details['cancel_reason'] ?? null;
                                                $status = $shipment_details['status'] ?? 'active';
                                                $is_return = $shipment_details['is_return'] ?? false;
                                                $planned_pickup_date = $shipment_details['planned_pickup_date'] ?? null;
                                                
                                                // Use tracking number as fallback for delivery_no
                                                $effective_delivery_no = $delivery_no ?: $shipment_args->tracking_num;
                                                $is_cancelled = ($status === 'cancelled' || !empty($cancelled_at));
                                                $is_shipped = ($status === 'shipped');
                                                $is_delivered = ($status === 'delivered');
                                            ?>
                                                <tr data-delivery_no="<?php echo esc_attr($effective_delivery_no); ?>" data-order_id="<?php echo esc_attr($order_id); ?>" class="<?php echo $is_cancelled ? 'bg-gray-100 opacity-60' : 'bg-white'; ?> border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                                    <th scope="row" class="px-6 py-4 font-medium <?php echo $is_cancelled ? 'text-gray-500 line-through' : 'text-gray-900'; ?> whitespace-nowrap dark:text-white">
                                                        <?php echo esc_html($shipment_args->courier_title); ?>
                                                        <?php if ($is_return): ?>
                                                            <span class="ml-2 px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full"><?php esc_html_e('Return by appointment', 'hezarfen-for-woocommerce'); ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($is_cancelled): ?>
                                                            <span class="ml-2 px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full"><?php esc_html_e('Cancelled', 'hezarfen-for-woocommerce'); ?></span>
                                                        <?php elseif ($is_delivered): ?>
                                                            <span class="ml-2 px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full"><?php esc_html_e('Delivered', 'hezarfen-for-woocommerce'); ?></span>
                                                        <?php elseif ($is_shipped): ?>
                                                            <span class="ml-2 px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full"><?php esc_html_e('Shipped', 'hezarfen-for-woocommerce'); ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($is_return && $planned_pickup_date): ?>
                                                            <div class="text-xs text-purple-600 mt-1">
                                                                <?php esc_html_e('Planned Pickup:', 'hezarfen-for-woocommerce'); ?> <?php echo esc_html(date('d.m.Y', strtotime($planned_pickup_date))); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </th>
                                                    <td class="px-6 py-4">
                                                        <?php if ($is_cancelled): ?>
                                                            <div class="text-xs text-gray-500">
                                                                <?php if (!empty($packages) && is_array($packages)): ?>
                                                                    <!-- Yeni format: Her koli için ayrı desi -->
                                                                    <?php foreach ($packages as $index => $package): ?>
                                                                        <div class="line-through">
                                                                            <?php printf(esc_html__('Koli %d:', 'hezarfen-for-woocommerce'), $index + 1); ?> 
                                                                            <?php echo esc_html(number_format($package['desi'], 2)); ?> desi
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                <?php else: ?>
                                                                    <!-- Eski format: Toplam göster -->
                                                                    <div class="line-through"><?php esc_html_e('Koli:', 'hezarfen-for-woocommerce'); ?> <?php echo esc_html($package_count ?: 'N/A'); ?></div>
                                                                    <div class="line-through"><?php esc_html_e('Desi:', 'hezarfen-for-woocommerce'); ?> <?php echo esc_html($desi ?: 'N/A'); ?></div>
                                                                <?php endif; ?>
                                                                <div class="text-red-600 font-medium mt-1"><?php esc_html_e('Cancelled:', 'hezarfen-for-woocommerce'); ?> <?php echo esc_html(date('d/m/Y H:i', strtotime($cancelled_at))); ?></div>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="text-xs">
                                                                <?php if (!empty($packages) && is_array($packages)): ?>
                                                                    <!-- Yeni format: Her koli için ayrı desi -->
                                                                    <?php foreach ($packages as $index => $package): ?>
                                                                        <div class="mb-0.5">
                                                                            <span class="font-medium"><?php printf(esc_html__('Koli %d:', 'hezarfen-for-woocommerce'), $index + 1); ?></span> 
                                                                            <?php echo esc_html(number_format($package['desi'], 2)); ?> desi
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                <?php else: ?>
                                                                    <!-- Eski format: Toplam göster -->
                                                                    <div><?php esc_html_e('Koli:', 'hezarfen-for-woocommerce'); ?> <?php echo esc_html($package_count ?: 'N/A'); ?></div>
                                                                    <div><?php esc_html_e('Desi:', 'hezarfen-for-woocommerce'); ?> <?php echo esc_html($desi ?: 'N/A'); ?></div>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="actions px-6 py-4">
                                                        <?php if ($is_return): ?>
                                                            <!-- Return shipment: show copyable barcode number instead of barcode button -->
                                                            <div class="flex flex-col gap-2">
                                                                <div class="flex items-center gap-2 bg-purple-50 border border-purple-200 px-3 py-2 rounded">
                                                                    <span class="font-mono text-sm font-semibold text-purple-800"><?php echo esc_html($effective_delivery_no); ?></span>
                                                                    <button type="button" class="copy-delivery-no cursor-pointer hover:text-purple-600 text-purple-500" data-delivery_no="<?php echo esc_attr($effective_delivery_no); ?>" title="<?php esc_attr_e('Copy', 'hezarfen-for-woocommerce'); ?>">
                                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                                                        </svg>
                                                                    </button>
                                                                </div>
                                                                <p class="text-xs text-gray-500 italic"><?php esc_html_e('Share this code with your customer', 'hezarfen-for-woocommerce'); ?></p>
                                                                <div class="flex gap-1 flex-wrap">
                                                                    <button type="button" data-delivery_no="<?php echo esc_attr($effective_delivery_no); ?>" data-order_id="<?php echo esc_attr($order_id); ?>" class="check-hepsijet-details cursor-pointer focus:outline-none hover:opacity-80 bg-blue-600 text-white px-2 py-1 rounded text-xs">
                                                                        <?php esc_html_e('Details', 'hezarfen-for-woocommerce'); ?>
                                                                    </button>
                                                                    <?php if (!$is_cancelled): ?>
                                                                        <button type="button" data-delivery_no="<?php echo esc_attr($effective_delivery_no); ?>" data-order_id="<?php echo esc_attr($order_id); ?>" class="cancel-hepsijet-shipment cursor-pointer focus:outline-none hover:opacity-80 bg-red-600 text-white px-2 py-1 rounded text-xs">
                                                                            <?php esc_html_e('Cancel', 'hezarfen-for-woocommerce'); ?>
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php elseif ($is_cancelled): ?>
                                                            <div class="flex gap-1 flex-wrap">
                                                                <span class="px-2 py-1 bg-gray-300 text-gray-600 rounded text-xs cursor-not-allowed">
                                                                    <?php esc_html_e('Barcode', 'hezarfen-for-woocommerce'); ?>
                                                                </span>
                                                                <button type="button" data-delivery_no="<?php echo esc_attr($effective_delivery_no); ?>" data-order_id="<?php echo esc_attr($order_id); ?>" class="check-hepsijet-details cursor-pointer focus:outline-none hover:opacity-80 bg-blue-600 text-white px-2 py-1 rounded text-xs">
                                                                    <?php esc_html_e('Details', 'hezarfen-for-woocommerce'); ?>
                                                                </button>
                                                                <span class="px-2 py-1 bg-gray-300 text-gray-600 rounded text-xs cursor-not-allowed">
                                                                    <?php esc_html_e('Cancelled', 'hezarfen-for-woocommerce'); ?>
                                                                </span>
                                                            </div>
                                                        <?php elseif ($is_delivered): ?>
                                                            <div class="flex gap-1 flex-wrap">
                                                                <button type="button" data-delivery_no="<?php echo esc_attr($effective_delivery_no); ?>" data-order_id="<?php echo esc_attr($order_id); ?>" class="get-hepsijet-barcode cursor-pointer focus:outline-none hover:opacity-80 bg-green-600 text-white px-2 py-1 rounded text-xs">
                                                                    <?php esc_html_e('Barcode', 'hezarfen-for-woocommerce'); ?>
                                                                </button>
                                                                <button type="button" data-delivery_no="<?php echo esc_attr($effective_delivery_no); ?>" data-order_id="<?php echo esc_attr($order_id); ?>" class="check-hepsijet-details cursor-pointer focus:outline-none hover:opacity-80 bg-blue-600 text-white px-2 py-1 rounded text-xs">
                                                                    <?php esc_html_e('Details', 'hezarfen-for-woocommerce'); ?>
                                                                </button>
                                                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">
                                                                    <?php esc_html_e('Delivered', 'hezarfen-for-woocommerce'); ?>
                                                                </span>
                                                            </div>
                                                        <?php elseif ($is_shipped): ?>
                                                            <div class="flex gap-1 flex-wrap">
                                                                <button type="button" data-delivery_no="<?php echo esc_attr($effective_delivery_no); ?>" data-order_id="<?php echo esc_attr($order_id); ?>" class="get-hepsijet-barcode cursor-pointer focus:outline-none hover:opacity-80 bg-green-600 text-white px-2 py-1 rounded text-xs">
                                                                    <?php esc_html_e('Barcode', 'hezarfen-for-woocommerce'); ?>
                                                                </button>
                                                                <button type="button" data-delivery_no="<?php echo esc_attr($effective_delivery_no); ?>" data-order_id="<?php echo esc_attr($order_id); ?>" class="check-hepsijet-details cursor-pointer focus:outline-none hover:opacity-80 bg-blue-600 text-white px-2 py-1 rounded text-xs">
                                                                    <?php esc_html_e('Details', 'hezarfen-for-woocommerce'); ?>
                                                                </button>
                                                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">
                                                                    <?php esc_html_e('Shipped', 'hezarfen-for-woocommerce'); ?>
                                                                </span>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="flex gap-1 flex-wrap">
                                                                <button type="button" data-delivery_no="<?php echo esc_attr($effective_delivery_no); ?>" data-order_id="<?php echo esc_attr($order_id); ?>" class="get-hepsijet-barcode cursor-pointer focus:outline-none hover:opacity-80 bg-green-600 text-white px-2 py-1 rounded text-xs">
                                                                    <?php esc_html_e('Barcode', 'hezarfen-for-woocommerce'); ?>
                                                                </button>
                                                                <button type="button" data-delivery_no="<?php echo esc_attr($effective_delivery_no); ?>" data-order_id="<?php echo esc_attr($order_id); ?>" class="check-hepsijet-details cursor-pointer focus:outline-none hover:opacity-80 bg-blue-600 text-white px-2 py-1 rounded text-xs">
                                                                    <?php esc_html_e('Details', 'hezarfen-for-woocommerce'); ?>
                                                                </button>
                                                                <button type="button" data-delivery_no="<?php echo esc_attr($effective_delivery_no); ?>" data-order_id="<?php echo esc_attr($order_id); ?>" class="cancel-hepsijet-shipment cursor-pointer focus:outline-none hover:opacity-80 bg-red-600 text-white px-2 py-1 rounded text-xs">
                                                                    <?php esc_html_e('Cancel', 'hezarfen-for-woocommerce'); ?>
                                                                </button>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <!-- Manual Tracking Table -->
                            <?php if ($has_manual_shipments) : ?>
                                <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                                    <div class="bg-gray-50 px-4 py-2 border-b">
                                        <h4 class="text-sm font-medium text-gray-800"><?php esc_html_e('Manual Tracking Numbers', 'hezarfen-for-woocommerce'); ?></h4>
                                    </div>
                                    <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                            <tr>
                                                <th scope="col" class="px-6 py-3"><?php esc_html_e('Courier Company', 'hezarfen-for-woocommerce'); ?></th>
                                                <th scope="col" class="px-6 py-3"><?php esc_html_e('Tracking Number', 'hezarfen-for-woocommerce'); ?></th>
                                                <th scope="col" class="px-6 py-3"><?php esc_html_e('SMS', 'hezarfen-for-woocommerce'); ?></th>
                                                <th scope="col" class="px-6 py-3"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($manual_shipments as $shipment_args): ?>
                                                <tr data-meta_id="<?php echo esc_attr(strval($shipment_args->meta_id)); ?>" class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                                    <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                                        <?php echo esc_html($shipment_args->courier_title); ?>
                                                    </th>
                                                    <td class="px-6 py-4">
                                                        <?php echo esc_html($shipment_args->tracking_num); ?>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <?php if ((bool) $shipment_args->sms_sent) : ?>
                                                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                <path fill-rule="evenodd" clip-rule="evenodd" d="M10 17.5C10.9849 17.5 11.9602 17.306 12.8701 16.9291C13.7801 16.5522 14.6069 15.9997 15.3033 15.3033C15.9997 14.6069 16.5522 13.7801 16.9291 12.8701C17.306 11.9602 17.5 10.9849 17.5 10C17.5 9.01509 17.306 8.03982 16.9291 7.12987C16.5522 6.21993 15.9997 5.39314 15.3033 4.6967C14.6069 4.00026 13.7801 3.44781 12.8701 3.0709C11.9602 2.69399 10.9849 2.5 10 2.5C8.01088 2.5 6.10322 3.29018 4.6967 4.6967C3.29018 6.10322 2.5 8.01088 2.5 10C2.5 11.9891 3.29018 13.8968 4.6967 15.3033C6.10322 16.7098 8.01088 17.5 10 17.5ZM9.80667 13.0333L13.9733 8.03333L12.6933 6.96667L9.11 11.2658L7.25583 9.41083L6.0775 10.5892L8.5775 13.0892L9.2225 13.7342L9.80667 13.0333Z" fill="#008000" />
                                                            </svg>
                                                        <?php else : ?>
                                                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                <path d="M10.0003 18.3334C5.39783 18.3334 1.66699 14.6026 1.66699 10.0001C1.66699 5.39758 5.39783 1.66675 10.0003 1.66675C14.6028 1.66675 18.3337 5.39758 18.3337 10.0001C18.3337 14.6026 14.6028 18.3334 10.0003 18.3334ZM10.0003 8.82175L7.64366 6.46425L6.46449 7.64341L8.82199 10.0001L6.46449 12.3567L7.64366 13.5359L10.0003 11.1784L12.357 13.5359L13.5362 12.3567L11.1787 10.0001L13.5362 7.64341L12.357 6.46425L10.0003 8.82175Z" fill="#FF2222" />
                                                            </svg>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="actions px-6 py-4 flex gap-2">
                                                        <a href="<?php echo esc_url($shipment_args->tracking_url); ?>" target="_blank" class="cursor-pointer focus:outline-none hover:opacity-80">
                                                            <svg width="30" height="24" viewBox="0 0 30 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                <rect width="30" height="24" rx="6" fill="black" />
                                                                <path d="M18.5179 14.6933L21.0163 17.1912L20.1909 18.0166L17.6931 15.5182C16.7637 16.2632 15.6077 16.6684 14.4165 16.6667C11.5185 16.6667 9.1665 14.3147 9.1665 11.4167C9.1665 8.51875 11.5185 6.16675 14.4165 6.16675C17.3145 6.16675 19.6665 8.51875 19.6665 11.4167C19.6682 12.6079 19.263 13.7639 18.5179 14.6933ZM17.3478 14.2605C18.0881 13.4992 18.5015 12.4787 18.4998 11.4167C18.4998 9.161 16.6723 7.33341 14.4165 7.33341C12.1608 7.33341 10.3332 9.161 10.3332 11.4167C10.3332 13.6725 12.1608 15.5001 14.4165 15.5001C15.4784 15.5018 16.4989 15.0883 17.2603 14.348L17.3478 14.2605Z" fill="white" />
                                                            </svg>
                                                        </a>
                                                        <a class="remove-shipment-data hover:opacity-80 focus:outline-none cursor-pointer">
                                                            <svg width="32" height="24" viewBox="0 0 32 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                <rect x="0.5" y="0.5" width="31" height="23" rx="5.5" stroke="#FF2222" />
                                                                <path d="M19.3335 8.00016H22.6668V9.3335H21.3335V18.0002C21.3335 18.177 21.2633 18.3465 21.1382 18.4716C21.0132 18.5966 20.8436 18.6668 20.6668 18.6668H11.3335C11.1567 18.6668 10.9871 18.5966 10.8621 18.4716C10.7371 18.3465 10.6668 18.177 10.6668 18.0002V9.3335H9.3335V8.00016H12.6668V6.00016C12.6668 5.82335 12.7371 5.65378 12.8621 5.52876C12.9871 5.40373 13.1567 5.3335 13.3335 5.3335H18.6668C18.8436 5.3335 19.0132 5.40373 19.1382 5.52876C19.2633 5.65378 19.3335 5.82335 19.3335 6.00016V8.00016ZM20.0002 9.3335H12.0002V17.3335H20.0002V9.3335ZM14.0002 11.3335H15.3335V15.3335H14.0002V11.3335ZM16.6668 11.3335H18.0002V15.3335H16.6668V11.3335ZM14.0002 6.66683V8.00016H18.0002V6.66683H14.0002Z" fill="#FF2222" />
                                                            </svg>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php
                    endif;
                    ?>
                </div>
            </div>
            <!-- Modern Confirmation Modal -->
            <div id="modal-body" class="hez-modal-overlay hidden inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center" style="position: fixed" role="dialog" aria-modal="true" aria-labelledby="modal-title" aria-describedby="modal-description">
                <div class="hez-modal-content bg-white rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0">
                    <div class="p-6">
                        <!-- Modal Header -->
                        <div class="flex items-center mb-4">
                            <div class="flex-shrink-0 w-10 h-10 bg-red-100 rounded-full flex items-center justify-center mr-3">
                                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 id="modal-title" class="text-lg font-semibold text-gray-900">
                                    <?php esc_html_e('Remove shipment data?', 'hezarfen-for-woocommerce'); ?>
                                </h3>
                            </div>
                            <button type="button" class="hez-modal-close text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 rounded-md p-1" aria-label="<?php esc_attr_e('Close modal', 'hezarfen-for-woocommerce'); ?>">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Modal Body -->
                        <div class="mb-6">
                            <p id="modal-description" class="text-sm text-gray-600 leading-relaxed">
                                <?php esc_html_e('Are you sure you want to remove this shipment data? This action cannot be undone and the tracking information will be permanently deleted.', 'hezarfen-for-woocommerce'); ?>
                            </p>
                        </div>
                        
                        <!-- Modal Actions -->
                        <div class="flex flex-col sm:flex-row gap-3 sm:gap-2 sm:justify-end">
                            <button type="button" class="hez-modal-cancel w-full sm:w-auto px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors duration-200">
                                <?php esc_html_e('Cancel', 'hezarfen-for-woocommerce'); ?>
                            </button>
                            <button type="button" class="hez-modal-confirm w-full sm:w-auto px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors duration-200">
                                <?php esc_html_e('Remove', 'hezarfen-for-woocommerce'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hepsijet Shipment Details Modal -->
            <div id="hepsijet-details-modal" class="hez-modal-overlay hidden inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center" style="position: fixed" role="dialog" aria-modal="true">
                <div class="hez-modal-content bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 transform transition-all duration-300 scale-95 opacity-0">
                    <div class="p-6">
                        <!-- Modal Header -->
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <?php esc_html_e('Shipment Details', 'hezarfen-for-woocommerce'); ?>
                            </h3>
                            <button type="button" class="hez-details-modal-close text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded-md p-1">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Modal Body -->
                        <div id="hepsijet-details-content" class="mb-6">
                            <p class="text-sm text-gray-600">
                                <?php esc_html_e('Loading shipment details...', 'hezarfen-for-woocommerce'); ?>
                            </p>
                        </div>
                        
                        <!-- Modal Actions -->
                        <div class="flex justify-end">
                            <button type="button" class="hez-details-modal-close px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                <?php esc_html_e('Close', 'hezarfen-for-woocommerce'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hepsijet Barcode Label Modal -->
            <div id="hepsijet-barcode-modal" class="hez-modal-overlay hidden inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center" style="position: fixed" role="dialog" aria-modal="true">
                <div class="hez-modal-content bg-white rounded-lg shadow-xl max-w-7xl w-full h-5/6 mx-4 transform transition-all duration-300 scale-95 opacity-0">
                    <div class="p-6 h-full">
                        <!-- Modal Header -->
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <?php esc_html_e('Shipment Label PDF', 'hezarfen-for-woocommerce'); ?>
                            </h3>
                            <button type="button" class="hez-barcode-modal-close text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded-md p-1">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <!-- PDF Content Container -->
                        <div id="hepsijet-barcode-content" class="flex-1 h-full">
                            <!-- Initial loading state, will be replaced by JS -->
                            <div class="w-full h-full flex justify-center">
                                <div class="text-sm text-gray-600">PDF hazırlanıyor...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php if (defined('HEZARFEN_PRO_VERSION')) : ?>
        <div class="<?php if (!defined('HEZARFEN_PRO_VERSION')) : ?>hidden<?php endif; ?> rounded-lg" id="hezarfen-pro" role="tabpanel" aria-labelledby="hezarfen-pro-tab">
            <?php do_action('hez_admin_order_edit_shipment_edits', $order_id); ?>
        </div>
        <?php endif; ?>

        <?php
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        if (is_plugin_active('intense-kargo-takip-for-woocommerce/intense-kargo-takip-for-woocommerce.php') || is_plugin_active('kargo-takip-turkiye/kargo-takip-turkiye.php')) {
            ?>
            <div class="hez-shipment-migrate mt-6 mb-4">
                <div class="p-4 mb-4 text-sm text-blue-800 rounded-lg bg-blue-50 dark:bg-gray-800 dark:text-blue-400" role="alert">
                    <span class="font-medium">
                        <?php esc_html_e( "Easy Data Recognition - We've detected that you're using the Intense Kargo Takip Plugin or Kargo Takip Türkiye plugin.", "hezarfen-for-woocommerce" ); ?>
                    </span>
                    <?php  esc_html_e( "Hezarfen can automatically recognize the shipment data of your previous orders with just one click. Moreover, this data remains accessible in Hezarfen even if you deactivate the Kargo Takip Türkiye plugin later on. You can start doing it on the Hezarfen Settings screen. In Hezarfen Manual Shipment Tracking Settings -> Advanced -> Recognize third party plugins' data -> Recognition type -> click 'Desteklenen eklentilerin verilerini algıla: (Intense Kargo Takip, Kargo Takip Turkiye)'", 'hezarfen-for-woocommerce' ); ?>
                </div>

                <a target="_blank" class="text-white bg-green-700 hover:bg-green-800 focus:outline-none focus:ring-4 focus:ring-green-300 font-medium rounded-full text-sm px-5 py-2.5 text-center me-2 mb-2 dark:bg-green-600 dark:hover:bg-green-700 dark:focus:ring-green-800" href="<?php echo esc_html( admin_url( 'admin.php?page=wc-settings&tab=hezarfen&section=manual_shipment_tracking' ) ); ?>"><?php esc_html_e( 'Visit Hezarfen Shipment Settings', 'hezarfen-for-woocommerce' ); ?></a>
                <a target="_blank" class="text-white bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-300 font-medium rounded-full text-sm px-5 py-2.5 text-center me-2 mb-2 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800" href="https://wordpress.org/support/plugin/hezarfen-for-woocommerce/"><?php esc_html_e( 'Free Support on wordpress.org', 'hezarfen-for-woocommerce' ); ?></a>
            </div>
            <?php
        }
        ?>
    </div>
</div>

<script type="text/javascript">
jQuery(function($) {
    'use strict';
    
    const $warehouse = $('#hepsijet-warehouse');
    const serverSideData = $warehouse.attr('data-loaded-server-side');
    
    if (serverSideData === 'true' && $warehouse.find('option').length > 0) {
        // Save the server-side HTML
        const serverSideHTML = $warehouse.html();
        const serverSideOptions = $warehouse.find('option').length;
        
        // Watch for changes and restore if needed
        let checkCount = 0;
        const checkInterval = setInterval(function() {
            checkCount++;
            
            const currentOptions = $warehouse.find('option').length;
            
            // If dropdown was emptied or options changed significantly
            if (currentOptions < serverSideOptions) {
                $warehouse.html(serverSideHTML);
                $warehouse.attr('data-loaded-server-side', 'true');
            }
            
            // Stop checking after 5 seconds
            if (checkCount > 50) {
                clearInterval(checkInterval);
            }
        }, 100);
    }
});
</script>