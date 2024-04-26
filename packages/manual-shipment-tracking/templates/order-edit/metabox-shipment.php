
<div id="hez-order-shipments" class="hez-ui">
    <div class="mb-4 border-gray-200 dark:border-gray-700">
        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="default-tab" data-tabs-toggle="#default-tab-content" role="tablist">
            <li class="w-1/2" role="presentation">
                <button class="h-16	w-full inline-block p-2 border-b-2 rounded-t-lg" id="profile-tab" data-tabs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="false"><?php esc_html_e( 'Manual Tracking', 'hezarfen-for-woocommerce' ); ?></button>
            </li>
            <li class="w-1/2" role="presentation">
                <button class="h-16	center flex justify-center items-center	 w-full gap-4 inline-block p-2 border-b-2 rounded-t-lg hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300" id="dashboard-tab" data-tabs-target="#dashboard" type="button" role="tab" aria-controls="dashboard" aria-selected="false">
                    <?php esc_html_e( 'Shipment Barcode', 'hezarfen-for-woocommerce' ); ?>
                    <span type="button" class="flex gap-2 bg-primary-color text-xs text-white border border-gray-200 focus:ring-4 focus:outline-none focus:ring-gray-300 font-medium rounded-lg text-sm px-5 py-1 text-center inline-flex items-center dark:focus:ring-gray-600 dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:bg-gray-700">
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
            <div class="grid grid-cols-2 gap-8">
                <div>
                    <p class="text-lg text-black"><?php esc_html_e( 'Enter Tracking Information', 'hezarfen-for-woocommerce' ); ?></p>
                    <p class="text-gray-1 text-xs font-light"><?php esc_html_e( 'In order to track your shipment, please enter your tracking number and select courier from below and add it to your tracking list.', 'hezarfen-for-woocommerce' ); ?></p>

                    <div class="mt-6">
                        <?php
                            $courier_select_name  = sprintf( '%s[%s][%s]', self::DATA_ARRAY_KEY, $shipment_data->id, self::COURIER_HTML_NAME );
                        ?>
                        <div class="mb-5">
                            <label for="tracking-num-input" class="font-light text-gray-1 block mb-2 text-sm dark:text-white"><?php esc_html_e( 'Tracking Number', 'hezarfen-for-woocommerce' ); ?></label>
                            <input type="text" id="tracking-num-input" class="shadow-sm bg-gray-50 border border-gray-300 text-gray-3 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 dark:shadow-sm-light" required />
                        </div>
                        <div class="mb-5">
                            <label class="font-light text-gray-1 block mb-2 text-sm dark:text-white"><?php esc_html_e( 'Select a Courier Company', 'hezarfen-for-woocommerce' ); ?></label>
                            <ul id="shipping-companies" class="max-h-24 grid w-full gap-2 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 3xl:grid-cols-5 4xl:grid-cols-6 5xl:grid-cols-7 6xl:grid-cols-8 overflow-hidden transition-max-height duration-300 ease-in-out">
                                <?php foreach ( \Hezarfen\ManualShipmentTracking\Helper::courier_company_options() as $courier_id => $courier_label ) : if(empty($courier_id)) {continue;} ?>
                                    <li class="flex justify-center">
                                        <input type="radio" id="courier-company-select-<?php echo esc_attr( $courier_id ); ?>" name="courier-company-select" value="<?php echo esc_attr( $courier_id ); ?>" class="hidden peer" required />
                                        <label for="courier-company-select-<?php echo esc_attr( $courier_id ); ?>" class="flex justify-center h-12 items-center justify-between w-full p-5 text-gray-3 bg-white border border-gray-3 rounded-lg cursor-pointer dark:hover:text-gray-300 dark:border-gray-3 dark:peer-checked:text-blue-500 peer-checked:bg-orange-1 peer-checked:border-2 peer-checked:border-orange-2 peer-checked:text-blue-600 hover:text-gray-600 hover:bg-gray-300 dark:text-gray-400 dark:bg-gray-800 dark:hover:bg-gray-700">                           
                                            <img class="max-h-8" src="<?php echo esc_attr( HEZARFEN_MST_COURIER_LOGO_URL . \Hezarfen\ManualShipmentTracking\Helper::get_courier_class( $courier_id )::$logo ); ?>" />
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="flex justify-center">
                            <button type="button" class="h-expand" class="text-black px-4 py-2 mt-2" data-show-more-label="<?php esc_html_e( 'Show More', 'hezarfen-for-woocommerce' ); ?>" data-show-less-label="<?php esc_html_e( 'Show Less', 'hezarfen-for-woocommerce' ); ?>"><?php esc_html_e( 'Show More', 'hezarfen-for-woocommerce' ); ?></button>
                        </div>

                        <div class="flex justify-center mt-6">
                            <button type="button" class="w-full text-white bg-gray-800 hover:bg-gray-900 focus:outline-none focus:ring-4 focus:ring-gray-300 font-normal rounded-lg text-sm px-5 py-2.5 me-2 mb-2 dark:bg-gray-800 dark:hover:bg-gray-700 dark:focus:ring-gray-700 dark:border-gray-700"><?php esc_html_e( 'Add to Tracking List', 'hezarfen-for-woocommerce' ); ?></button>
                        </div>

                    </div>
                </div>
                <div class="border-dashed border-2 border-gray-2 p-4 flex justify-center">
                    <div id="no-shipments" class="w-9/12 flex justify-center flex-col font-medium items-center">
                        <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 20">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m13 19-6-5-6 5V2a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v17Z"/>
                        </svg>
                        <p class="text-black text-lg"><?php esc_html_e( 'Nothing to Track Yet', 'hezarfen-for-woocommerce' ); ?></p>
                        <div class="text-center">
                            <p class="text-gray-1 font-light"><?php esc_html_e( 'There are no tracking numbers added to the tracking list.', 'hezarfen-for-woocommerce' ); ?></p>
                            <p class="text-gray-1 font-light"><?php esc_html_e( 'Please add one or more tracking numbers to the list.', 'hezarfen-for-woocommerce' ); ?></p>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <div class="hidden p-4 rounded-lg bg-gray-50 dark:bg-gray-800" id="dashboard" role="tabpanel" aria-labelledby="dashboard-tab">
            <p class="text-sm text-gray-500 dark:text-gray-400">This is some placeholder content the <strong class="font-medium text-gray-800 dark:text-white">Dashboard tab's associated content</strong>. Clicking another tab will toggle the visibility of this one for the next. The tab JavaScript swaps classes to control the content visibility and styling.</p>
        </div>
    </div>
</div>