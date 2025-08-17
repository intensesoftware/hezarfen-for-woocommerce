<?php
defined('ABSPATH') || exit;

use \Hezarfen\ManualShipmentTracking\Helper;
?>
<div id="hez-order-shipments" class="hez-ui">

    <?php
    // Get today's date in Turkish format
    $turkish_months = array(
        1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran',
        7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'
    );
    $turkish_days = array(
        'Monday' => 'Pazartesi', 'Tuesday' => 'Salı', 'Wednesday' => 'Çarşamba',
        'Thursday' => 'Perşembe', 'Friday' => 'Cuma', 'Saturday' => 'Cumartesi', 'Sunday' => 'Pazar'
    );
    
    $today_turkish = date('j') . ' ' . $turkish_months[date('n')] . ' ' . date('Y') . ' - ' . $turkish_days[date('l')];
    
    // Get the saved survey date from options
    $saved_survey_date = get_option('hez_survey_date', '');
    
    // If no saved date, save today's date
    if (empty($saved_survey_date)) {
        update_option('hez_survey_date', $today_turkish);
        $saved_survey_date = $today_turkish;
    }
    
    // Only show survey if today's date matches saved date
    if ($today_turkish === $saved_survey_date) :
    ?>
    <!-- Hezarfen User Survey -->
    <div style="margin-bottom: 1rem; padding: 1rem; background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; position: relative;">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="flex-shrink: 0;">
                <svg width="20" height="20" fill="#f59e0b" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <div style="flex: 1; min-width: 0;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                    <h4 style="font-size: 0.875rem; font-weight: 600; color: #92400e; margin: 0;">
                        Hezarfen Kullanıcı Anketi - <?php echo esc_html($today_turkish); ?>
                    </h4>
                    <span style="font-size: 0.75rem; color: #92400e; opacity: 0.8;">
                        Bu uyarı bugün gün boyunca burada gösterilecektir
                    </span>
                </div>
                <p style="font-size: 0.75rem; color: #92400e; margin: 0 0 0.5rem 0; line-height: 1.4;">
                    Hezarfen'i sizin için daha hızlı, pratik ve güçlü hale getirmemize yardımcı olun. Aşağıdaki formdan eklenmesini istediğiniz özellikleri veya yaşadığınız sorunları bize iletin — geri bildirimleriniz geliştirme ekibimiz tarafından dikkatle değerlendirilir.
                </p>
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <a href="https://intense.com.tr/hezarfen-kullanici-geri-bildirim-formu/" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; padding: 0.5rem 1rem; font-size: 0.75rem; font-weight: 500; color: white; background: #f59e0b; border: none; border-radius: 6px; text-decoration: none; transition: background-color 0.2s ease;" onmouseover="this.style.backgroundColor='#d97706';" onmouseout="this.style.backgroundColor='#f59e0b';">
                        💡 Anonim Fikrimi Paylaş
                    </a>
                    <span style="font-size: 0.7rem; color: #92400e; opacity: 0.8;">(anonimdir, iletişim bilgileriniz toplanmaz)</span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
                        <div class="mb-5">
                            <label for="tracking-num-input" class="font-light text-gray-1 block mb-2 text-sm dark:text-white"><?php esc_html_e('Tracking Number', 'hezarfen-for-woocommerce'); ?></label>
                            <input type="text" id="tracking-num-input" class="shadow-sm bg-gray-50 border border-gray-300 text-gray-3 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 dark:shadow-sm-light" />
                        </div>
                        
                        <!-- Smart Feedback Request Message -->
                        <div id="hezarfen-feedback-request" class="mb-4 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg shadow-sm" style="display: none;">
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-sm font-medium text-blue-900 mb-1">
                                        <?php esc_html_e('⭐ Love Hezarfen?', 'hezarfen-for-woocommerce'); ?>
                                    </h4>
                                    <p class="text-sm text-blue-700 mb-3">
                                        <?php esc_html_e('We noticed you\'re using our manual tracking feature! If Hezarfen has helped your store, please consider leaving a review on WordPress.org. Your feedback helps other store owners discover our plugin.', 'hezarfen-for-woocommerce'); ?>
                                    </p>
                                    <div class="flex items-center space-x-3">
                                        <a href="https://wordpress.org/support/plugin/hezarfen-for-woocommerce/reviews/#new-post" target="_blank" id="hezarfen-review-positive" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-green-700 bg-green-100 border border-green-200 rounded-md hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-green-500 transition-all duration-200">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"></path>
                                            </svg>
                                            <?php esc_html_e('Leave a Review', 'hezarfen-for-woocommerce'); ?>
                                        </a>
                                        <button type="button" id="hezarfen-feedback-dismiss" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 border border-gray-200 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-all duration-200">
                                            <?php esc_html_e('Maybe Later', 'hezarfen-for-woocommerce'); ?>
                                        </button>
                                    </div>
                                </div>
                                <button type="button" id="hezarfen-feedback-close" class="flex-shrink-0 text-gray-400 hover:text-gray-600 focus:outline-none">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-2">
                            <label class="font-light text-gray-1 block mb-2 text-sm dark:text-white"><?php esc_html_e('Select a Courier Company', 'hezarfen-for-woocommerce'); ?></label>
                            <ul id="shipping-companies" class="max-h-24 grid w-full gap-2 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 3xl:grid-cols-5 4xl:grid-cols-6 5xl:grid-cols-7 6xl:grid-cols-8 overflow-hidden transition-max-height duration-300 ease-in-out">
                                <?php foreach (Helper::courier_company_options() as $courier_id => $courier_label) : if (empty($courier_id)) {
                                        continue;
                                    } ?>
                                    <li class="flex justify-center">
                                        <input type="radio" id="courier-company-select-<?php echo esc_attr($courier_id); ?>" name="courier-company-select" value="<?php echo esc_attr($courier_id); ?>" class="hidden peer" />
                                        <label for="courier-company-select-<?php echo esc_attr($courier_id); ?>" class="flex justify-center h-12 items-center justify-between w-full p-5 text-gray-3 bg-white border border-gray-3 rounded-lg cursor-pointer dark:hover:text-gray-300 dark:border-gray-3 dark:peer-checked:text-blue-500 peer-checked:bg-orange-1 peer-checked:border-2 peer-checked:border-orange-2 peer-checked:text-blue-600 hover:text-gray-600 hover:bg-orange-1 dark:text-gray-400 dark:bg-gray-800 dark:hover:bg-gray-700">
                                            <img class="max-h-8" src="<?php echo esc_attr(HEZARFEN_MST_COURIER_LOGO_URL . Helper::get_courier_class($courier_id)::$logo); ?>" loading="lazy" alt="<?php echo esc_attr($courier_label); ?>" />
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

                        <div class="flex justify-center mt-2">
                            <button data-order_id="<?php echo esc_attr($order_id); ?>" id="add-to-tracking-list" type="button" class="w-full text-white bg-gray-800 hover:bg-gray-900 focus:outline-none focus:ring-4 focus:ring-gray-300 font-normal rounded-lg text-sm px-5 py-2.5 me-2 mb-2 dark:bg-gray-800 dark:hover:bg-gray-700 dark:focus:ring-gray-700 dark:border-gray-700"><?php esc_html_e('Add to Tracking List', 'hezarfen-for-woocommerce'); ?></button>
                        </div>

                    </div>
                </div>
                <?php
                $shipments_data = Helper::get_all_shipment_data($order_id);

                if (count($shipments_data) < 1) :
                ?>
                    <div class="border-dashed border-2 border-gray-2 p-4 flex justify-center">
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
                    <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
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
                                <?php
                                foreach ($shipments_data as $shipment_args):
                                ?>
                                    <tr data-meta_id="<?php echo esc_attr(strval($shipment_args->meta_id)); ?>" class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            <?php echo esc_html($shipment_args->courier_title); ?>
                                            </td>
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
                                <?php
                                endforeach;
                                ?>
                            </tbody>
                        </table>
                    </div>
                <?php
                endif;
                ?>
            </div>
            <!-- Modern Confirmation Modal -->
            <div id="modal-body" class="hez-modal-overlay hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center" role="dialog" aria-modal="true" aria-labelledby="modal-title" aria-describedby="modal-description">
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