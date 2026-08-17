# Hooks Reference

Hezarfen'in **dışa açtığı** action ve filter hook'ları. Üçüncü-parti entegrasyonlar burada listelenenleri kullanmalıdır — başka isimlere bağlanmak breaking change riski taşır.

WP/WC core hook'larına `add_filter`/`add_action` yapan ama kendi adıyla yeni bir hook tanımlamayan kullanımlar listede yer almaz; ilgili modül spec'ine bakın.

---

## Actions (do_action)

### Sipariş Yaşam Döngüsü

| Hook | Modül | Ne Zaman | Params |
|---|---|---|---|
| `hezarfen_mst_shipment_data_saved` | shipment-tracking | Bir takip kaydı kaydedildiğinde (manuel veya HepsiJET API üzerinden) | `(WC_Order $order, array $shipment_data)` |
| `hezarfen_mst_order_shipped` | shipment-tracking | Kargo eventi sonrası: status güncellendi + e-posta/SMS denendi | `(WC_Order $order, array $shipment_data)` |
| `hezarfen_order_shipped` | sms-automation | Kargo SMS'i işlendikten sonra (sms modülünden zincirleme) | `(WC_Order $order, array $shipment_data)` |
| `hezarfen_checkout_neighborhood_changed` | neighborhood-selection | Müşteri checkout'ta mahalle değiştirdi (AJAX) | Yok (POST verilerine erişim açık) |

### İade Yönetimi

| Hook | Modül | Ne Zaman | Params |
|---|---|---|---|
| `hezarfen_returns_loaded` | returns | Modül ayağa kalktığında; sağlayıcılar burada kaydedilmeli | `(Returns_Module $module)` |
| `hezarfen_return_created` | returns | Yeni iade talebi kaydedildikten sonra | `(Return_Request $request, WC_Order $order)` |
| `hezarfen_return_status_changed` | returns | Talep durumu değiştiğinde | `(Return_Request $request, string $old_status, string $new_status)` |
| `hezarfen_return_status_{status}` | returns | Talep belirli bir duruma geçtiğinde (ör. `hezarfen_return_status_approved`) | `(Return_Request $request, string $old_status)` |
| `hezarfen_returns_event_added` | returns | Timeline'a kayıt eklendiğinde | `(int $event_id, Return_Event $event)` |

### HTTP / Webhook entry

| Hook | Modül | Açıklama |
|---|---|---|
| `woocommerce_api_hez_ordermigo_shipment_status` | shipment-tracking | HepsiJET webhook callback URL'i (`?wc-api=hez_ordermigo_shipment_status`) |

---

## Filters (apply_filters)

### Shipment Tracking

| Hook | Default | Params | Amaç |
|---|---|---|---|
| `hezarfen_mst_new_order_status` | `"wc-hezarfen-shipped"` | `(string $status, WC_Order $order, string $courier_id, string $tracking_num)` | Takip girilince geçilecek WC status'unu özelleştir |
| `hezarfen_mst_courier_companies` | array (26 firma) | `(array $couriers)` | Kargo firma listesini değiştir/ekle |
| `hezarfen_mst_get_shipment_data` | meta'dan parse'lanmış | `(array $data, int $order_id)` | Üçüncü parti shipment verisini inject et |
| `hezarfen_shop_order_no_shipment_found_msg` | "—" | `(string $message, int $order_id)` | Siparişler tablosundaki "kargo yok" mesajı |

### Sales Contract

| Hook | Default | Params | Amaç |
|---|---|---|---|
| `hezarfen_contracts_include_item_meta` | `true` | `(bool $should_include, string $key, array $meta_data, WC_Order_Item $item)` | Sözleşme ürün tablosunda item meta'yı dahil etme/etmeme |
| `hezarfen_mss_include_agreements_in_customer_email` | `true` | – return `bool` | Müşteri e-postalarına sözleşme HTML ekini dahil etme/etmeme |

### Neighborhood

| Hook | Default | Params | Amaç |
|---|---|---|---|
| `hezarfen_checkout_neighborhood_changed_output_args` | `{update_checkout: true}` | `(array $args)` | AJAX yanıtını özelleştir |

### İade Yönetimi

Modül, ek yeteneklerin koda dokunmadan takılabilmesi için sağlayıcı tabanlı
çalışır: liste filtreleri bir arayüzü uygulayan nesne bekler.

| Hook | Default | Params | Amaç |
|---|---|---|---|
| `hezarfen_returns_enabled` | option değeri | `(bool $enabled)` | Modülü programatik aç/kapa |
| `hezarfen_returns_reason_providers` | `[Default_Reason_Provider]` | `(Return_Reason_Provider_Interface[] $providers)` | Mağazaya özel iade sebepleri ekle |
| `hezarfen_returns_reasons` | birleştirilmiş liste | `(array $reasons)` | Nihai sebep listesini düzenle |
| `hezarfen_returns_policy_providers` | `[Global_Return_Policy_Provider]` | `(Return_Policy_Provider_Interface[] $providers)` | Ürün/kategori bazlı iade politikası ekle |
| `hezarfen_returns_resolved_policy` | çözümlenen politika | `(Return_Policy $policy, WC_Order $order, WC_Order_Item $item)` | Satır politikasını son anda değiştir |
| `hezarfen_returns_shipping_methods` | `[Customer_Ships, Kargokit]` | `(Return_Shipping_Method_Interface[] $methods)` | Kendi kargo anlaşmanı yöntem olarak ekle |
| `hezarfen_returns_repository` | `Return_Repository` | `(Return_Repository_Interface $repository)` | Alternatif depolama katmanı |
| `hezarfen_returns_statuses` | 8 durum | `(array $statuses)` | Yeni talep durumu tanımla |
| `hezarfen_returns_status_transitions` | geçiş haritası | `(array $transitions)` | İzinli durum geçişlerini değiştir |
| `hezarfen_returns_progress_steps` | 5 adım | `(string[] $steps)` | Müşteriye gösterilen ilerleme adımları |
| `hezarfen_returns_order_eligibility` | `true` | `(true\|WP_Error $result, WC_Order $order)` | Sipariş düzeyinde ek kural |
| `hezarfen_returns_returnable_lines` | hesaplanan satırlar | `(array $lines, WC_Order $order)` | İade edilebilir satırları filtrele |
| `hezarfen_returns_is_cancellable_by_customer` | `pending`/`info-required` | `(bool $cancellable, Return_Request $request)` | Müşteri iptal hakkını değiştir |
| `hezarfen_returns_return_address` | tek adres | `(array $address)` | Çoklu depo desteği |
| `hezarfen_returns_return_number` | `IADE-{sipariş}-{n}` | `(string $number, WC_Order $order)` | Talep referans formatı |
| `hezarfen_returns_list_endpoint` | `iadelerim` | `(string $slug)` | Hesabım endpoint slug'ı |
| `hezarfen_returns_request_endpoint` | `iade-talebi` | `(string $slug)` | İade formu endpoint slug'ı |
| `hezarfen_returns_admin_columns` | 7 kolon | `(array $columns)` | Yönetim listesine kolon ekle |
| `hezarfen_returns_admin_column_content` | `''` | `(string $content, string $column, Return_Request $request)` | Eklenen kolonun içeriği |

---

## AJAX Action'ları

Tüm AJAX endpoint'leri WordPress `wp_ajax_{action}` (auth gerekli) ve gerekiyorsa `wp_ajax_nopriv_{action}` (guest) ile register edilir. Capability ve nonce kontrolleri ilgili modüle göre değişir.

### Frontend (priv + nopriv)

| Action | Modül | Capability / Nonce | Açıklama |
|---|---|---|---|
| `wc_hezarfen_neighborhood_changed` | neighborhood-selection | nonce `mahalle-io-get-data` | Checkout mahalle değişiminde shipping/total refresh |

### Admin (priv, capability `manage_woocommerce` veya `manage_options`)

| Action | Modül | Açıklama |
|---|---|---|
| `hezarfen_save_sms_rules` | sms-automation | Rule array'ini kaydet |
| `hezarfen_get_sms_rules` | sms-automation | Mevcut kuralları getir |
| `hezarfen_save_netgsm_credentials` | sms-automation | NetGSM user/pass/msgheader kaydet |
| `hezarfen_get_netgsm_credentials` | sms-automation | Bağlantı durumunu kontrol et |
| `hezarfen_get_netgsm_senders` | sms-automation | NetGSM'den msgheader listesi çek |
| `hezarfen_mst_create_hepsijet_shipment` | shipment-tracking | HepsiJET API: gönderi oluştur |
| `hezarfen_mst_track_hepsijet_shipment` | shipment-tracking | HepsiJET API: takip durumu |
| `hezarfen_mst_cancel_hepsijet_shipment` | shipment-tracking | HepsiJET API: gönderi iptali |
| `hezarfen_mst_get_hepsijet_barcode` | shipment-tracking | HepsiJET API: barkod (ZPL/PNG) |
| `hezarfen_mst_generate_hepsijet_pdf` | shipment-tracking | HepsiJET API: barkod PDF |
| `hezarfen_submit_demand` | admin/upgrade | Pro paket talep formu |
| `hezarfen_dismiss_notice_*` | admin notices | Banner kapatma |

---

## REST API Etkileri

| Hook | Modül | Etki |
|---|---|---|
| `woocommerce_rest_prepare_shop_order_object` | invoice-fields | Bireysel siparişlerde `_billing_hez_TC_number` decrypt edilip response meta_data'ya eklenir |

---

## WC Core Hook'larına Bağlanan Modüller (referans)

Çakışma analizi için aşağıdaki WC hook'larına Hezarfen bağlanır:

| WC Hook | Hezarfen modülleri |
|---|---|
| `woocommerce_checkout_fields` | invoice-fields, checkout-customization, neighborhood-selection |
| `woocommerce_checkout_process` | invoice-fields, sales-contract |
| `woocommerce_checkout_order_processed` | sales-contract |
| `woocommerce_order_status_processing` | sales-contract |
| `woocommerce_order_status_changed` | sms-automation |
| `woocommerce_email_customer_details` | sales-contract |
| `woocommerce_update_order_review_fragments` | sales-contract |
| `woocommerce_get_country_locale` | checkout-customization, invoice-fields, neighborhood-selection |
| `woocommerce_default_address_fields` | checkout-customization |
| `woocommerce_admin_billing_fields` | invoice-fields |
| `woocommerce_admin_order_data_after_billing_address` | invoice-fields |
| `woocommerce_rest_prepare_shop_order_object` | invoice-fields |
| `woocommerce_address_to_edit` | my-account, neighborhood-selection |
| `woocommerce_after_save_address_validation` | my-account |
| `woocommerce_my_account_my_orders_columns` | shipment-tracking |
| `woocommerce_order_details_after_order_table` | shipment-tracking, sales-contract, returns |
| `woocommerce_account_menu_items` | returns |
| `woocommerce_my_account_my_orders_actions` | returns |
| `woocommerce_get_sections_hezarfen` / `woocommerce_get_settings_hezarfen` | returns |
| `woocommerce_email_classes` | shipment-tracking, returns |
| `woocommerce_thankyou` | sales-contract |
| `wc_order_statuses` | shipment-tracking (`wc-hezarfen-shipped` ekler) |
| `add_meta_boxes` | shipment-tracking, sales-contract, invoice-fields |
| `plugins_loaded` | core (bootstrap) |
| `before_woocommerce_init` | core (HPOS uyumluluk deklarasyonu) |

---

## Versiyonlama Politikası

- **Public action/filter'lar** semver'a tabi: minör sürümlerde signature değişmez.
- **AJAX action isimleri** semver'a tabi.
- **WC core hook bağlantıları** Hezarfen iç uygulamasıdır; değişebilir.
- Deprecation: `apply_filters_deprecated` ile en az bir minor sürüm uyarısı verilir.
