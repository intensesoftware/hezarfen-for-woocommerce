
<div class="hez-ui">
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
            <p class="text-sm text-gray-500 dark:text-gray-400">This is some placeholder content the <strong class="font-medium text-gray-800 dark:text-white">Profile tab's associated content</strong>. Clicking another tab will toggle the visibility of this one for the next. The tab JavaScript swaps classes to control the content visibility and styling.</p>
        </div>
        <div class="hidden p-4 rounded-lg bg-gray-50 dark:bg-gray-800" id="dashboard" role="tabpanel" aria-labelledby="dashboard-tab">
            <p class="text-sm text-gray-500 dark:text-gray-400">This is some placeholder content the <strong class="font-medium text-gray-800 dark:text-white">Dashboard tab's associated content</strong>. Clicking another tab will toggle the visibility of this one for the next. The tab JavaScript swaps classes to control the content visibility and styling.</p>
        </div>
    </div>
</div>