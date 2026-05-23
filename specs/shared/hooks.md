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
| `woocommerce_order_details_after_order_table` | shipment-tracking, sales-contract |
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
