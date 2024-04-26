
<div id="hez-order-shipments" class="hez-ui">
    <div class="mb-4 border-gray-200 dark:border-gray-700">
        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="default-tab" data-tabs-toggle="#default-tab-content" role="tablist">
            <li class="w-1/2" role="presentation">
                <button class="h-16	w-full inline-block p-2 border-b-2 rounded-t-lg" id="profile-tab" data-tabs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="false"><?php esc_html_e( 'Manual Tracking', 'hezarfen-for-woocommerce' ); ?></button>
            </li>
            <li class="w-1/2" role="presentation">
                <button class="h-16	center flex justify-center items-center	 w-full gap-4 inline-block p-2 border-b-2 rounded-t-lg hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300" id="dashboard-tab" data-tabs-target="#dashboard" type="button" role="tab" aria-controls="dashboard" aria-selected="false">
                    <?php esc_html_e( 'Shipment Barcode', 'hezarfen-for-woocommerce' ); ?>
                    <span type="button" class="flex gap-2 bg-primary-color text-xs text-white border border-gray-200 focus:ring-4 focus:outline-none focus:ring-gray-100 font-medium rounded-lg text-sm px-5 py-1 text-center inline-flex items-center dark:focus:ring-gray-600 dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:bg-gray-700">
                        <svg width="13" height="12" viewBox="0 0 13 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9.25 4.00006H8.75V3.00006C8.75 1.62006 7.63 0.500061 6.25 0.500061C4.87 0.500061 3.75 1.62006 3.75 3.00006V4.00006H3.25C2.7 4.00006 2.25 4.45006 2.25 5.00006V10.0001C2.25 10.5501 2.7 11.0001 3.25 11.0001H9.25C9.8 11.0001 10.25 10.5501 10.25 10.0001V5.00006C10.25 4.45006 9.8 4.00006 9.25 4.00006ZM6.25 8.50006C5.7 8.50006 5.25 8.05006 5.25 7.50006C5.25 6.95006 5.7 6.50006 6.25 6.50006C6.8 6.50006 7.25 6.95006 7.25 7.50006C7.25 8.05006 6.8 8.50006 6.25 8.50006ZM4.75 4.00006V3.00006C4.75 2.17006 5.42 1.50006 6.25 1.50006C7.08 1.50006 7.75 2.17006 7.75 3.00006V4.00006H4.75Z" fill="white"/>
                        </svg>
                        Hezarfen Pro
                    </span>
                </button>
            </li>
        </ul>
    </div>
    <div id="default-tab-content">
        <div class="hidden p-4 rounded-lg bg-gray-50 dark:bg-gray-800" id="profile" role="tabpanel" aria-labelledby="profile-tab">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-lg text-black"><?php esc_html_e( 'Enter Tracking Information', 'hezarfen-for-woocommerce' ); ?></p>
                    <p class="text-gray-1 text-xs font-light"><?php esc_html_e( 'In order to track your shipment, please enter your tracking number and select courier from below and add it to your tracking list.', 'hezarfen-for-woocommerce' ); ?></p>

                    <div>
                        <?php
                            $courier_select_name  = sprintf( '%s[%s][%s]', self::DATA_ARRAY_KEY, $shipment_data->id, self::COURIER_HTML_NAME );
                        ?>
                        <div class="mb-5">
                            <label for="tracking-num-input" class="font-normal text-gray-1 block mb-2 text-sm font-medium dark:text-white font-light>Your email</label>
                            <input type="text" id="tracking-num-input" class="shadow-sm bg-gray-50 border border-gray-300 text-gray-1 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 dark:shadow-sm-light" required />
                        </div>
                        <div class="mb-5">
                            <label for="password" class="font-normal text-gray-1 block mb-2 text-sm font-medium dark:text-white font-light"><?php esc_html_e( 'Select a Courier Company', 'hezarfen-for-woocommerce' ); ?></label>
                            <ul id="shipping-companies" class="grid w-full gap-2 md:grid-cols-4 max-h-24 overflow-hidden transition-max-height duration-300 ease-in-out">
                                <?php foreach ( \Hezarfen\ManualShipmentTracking\Helper::courier_company_options() as $courier_id => $courier_label ) : if(empty($courier_id)) {continue;} ?>
                                    <li>
                                        <input type="radio" name="courier-company-select" value="<?php echo esc_attr( $courier_id ); ?>" class="hidden peer" required />
                                        <label for="courier-company-select" class="max-h-10 inline-flex items-center justify-between w-full p-4 text-gray-500 bg-white border border-gray-200 rounded-lg cursor-pointer dark:hover:text-gray-300 dark:border-gray-700 dark:peer-checked:text-blue-500 peer-checked:border-blue-600 peer-checked:text-blue-600 hover:text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:bg-gray-800 dark:hover:bg-gray-700">                           
                                            <div class="block">
                                                <div class="w-full">
                                                    <img src="<?php echo esc_attr( HEZARFEN_MST_COURIER_LOGO_URL . \Hezarfen\ManualShipmentTracking\Helper::get_courier_class( $courier_id )::$logo ); ?>" />
                                                </div>
                                            </div>
                                            <svg class="w-5 h-5 ms-3 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 10">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 5h12m0 0L9 1m4 4L9 9"/>
                                            </svg>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="flex justify-center">
                            <button type="button" class="h-expand" class="bg-blue-500 text-white px-4 py-2 mt-2 hover:bg-blue-700"><?php esc_html_e( 'Show more', 'hezarfen-for-woocommerce' ); ?></button>
                        </div>

                    </div>
                </div>
                <div class="border-dashed border-2 border-gray-2 p-4">
                    ....
                </div>
            </div>
        </div>
        <div class="hidden p-4 rounded-lg bg-gray-50 dark:bg-gray-800" id="dashboard" role="tabpanel" aria-labelledby="dashboard-tab">
            <p class="text-sm text-gray-500 dark:text-gray-400">This is some placeholder content the <strong class="font-medium text-gray-800 dark:text-white">Dashboard tab's associated content</strong>. Clicking another tab will toggle the visibility of this one for the next. The tab JavaScript swaps classes to control the content visibility and styling.</p>
        </div>
    </div>
</div>