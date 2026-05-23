---
id: shipment-tracking
title: Kargo Takip & HepsiJET Entegrasyonu (Manual Shipment Tracking)
status: stable
since: 1.0.0
owner: hezarfen-core
entry_files:
  - packages/manual-shipment-tracking/manual-shipment-tracking.php
  - packages/manual-shipment-tracking/includes/class-manual-shipment-tracking.php
  - packages/manual-shipment-tracking/includes/admin/class-admin-orders.php
  - packages/manual-shipment-tracking/includes/admin/class-admin-ajax.php
  - packages/manual-shipment-tracking/includes/admin/class-settings.php
  - packages/manual-shipment-tracking/models/class-shipment-data.php
  - packages/manual-shipment-tracking/includes/courier-companies/
depends_on: [woocommerce]
optional_deps: [netgsm-plugin, pandasms-plugin]
related: [sms-automation, my-account]
---

## Amaç

Sipariş bazında kargo firması + takip numarası girilebilmesi; müşteriye takip bilgisinin "Kargoya Verildi" e-postası, SMS ve "Hesabım" sayfasında ulaştırılması. Manuel girişin yanında **HepsiJET** için API tabanlı otomatik gönderi oluşturma, barkod üretimi, webhook ile durum güncellemesi ve toplu işlem desteği.

## Kapsam

- Sipariş başına **birden fazla** takip kaydı girilebilir (tek sipariş, çoklu gönderi).
- 26 kargo firması; her biri `Courier_Company` abstract'ından türeyen ayrı sınıf, `packages/manual-shipment-tracking/includes/courier-companies/class-{id}.php` altında.
- Custom order status `wc-hezarfen-shipped` (Shipped / Kargoya Verildi); `wc-processing` sonrası listeye eklenir.
- "Sipariş listesi" admin tablosunda kargo bilgisi kolonu (`hezarfen_mst_shipment_info`).
- HPOS uyumludur; `WC_HEZARFEN_HPOS_ENABLED` sabiti üzerinden meta box ekran kimliği seçilir.

## Desteklenen Kargo Firmaları

Slug'lar (`Manual_Shipment_Tracking::COURIER_*` ve `class-manual-shipment-tracking.php:204-235`):

`aras`, `birgunde`, `brinks`, `cdek`, `custom`, `dhl`, `fedex`, `gelal`, `hepsijet`, `hepsijet-entegrasyon`, `horoz-lojistik`, `jetizz`, `kargo-turk`, `kargoist`, `kolay-gelsin`, `kurye`, `mng`, `packupp`, `ptt`, `scotty`, `sendeo`, `surat`, `tnt`, `trendyol-express`, `ups`, `yurtici`.

Liste `hezarfen_mst_courier_companies` filtresiyle genişletilebilir.

## Veri Modeli

### Order Meta

- `_hezarfen_mst_shipment_data` (string, **çoklu kayıt** — `get_meta(..., false)`): tek kayıt formatı **pipe-delimited**:
  `id||order_id||courier_id||courier_title||tracking_num||tracking_url||sms_sent`
- HepsiJET'e özel:
  - `_hezarfen_hepsijet_shipment_{delivery_no}` — API yanıtının cache'i
  - `_hezarfen_hepsijet_return_barcode_no`
  - `_hezarfen_hepsijet_return_barcode_print_date`
  - `_hezarfen_hepsijet_return_zpl_barcode`

### Settings (option key'ler)

- `hezarfen_mst_default_courier_company` — ön seçili kargo firması
- `hezarfen_mst_show_shipment_tracking_column` — "Hesabım > Siparişlerim" tablosunda takip kolonunu göster
- `hezarfen_mst_enable_sms_notification` — SMS açık/kapalı (legacy; modern akış için bkz. `./sms-automation.md`)
- `hezarfen_mst_notification_provider` — `netgsm` | `pandasms`
- `hezarfen_mst_courier_company_custom_meta` — "custom" kargo firması adının okunacağı meta key
- `hezarfen_mst_tracking_num_custom_meta` — üçüncü-parti içe aktarımdan takip no'sunun okunacağı meta key
- `hezarfen_mst_disabled_couriers` — dropdown'dan gizlenecek courier id dizisi
- `hezarfen_enable_shipment_tracking` — modül master switch

## Davranışlar

### Senaryo: Manuel takip no girme
- **Given** admin sipariş düzenleme ekranında "Hezarfen Cargo Tracking & SMS Notifications" meta box'unda
- **When** bir kargo firması seçip takip no kaydederse
- **Then** order meta `_hezarfen_mst_shipment_data` yeni bir kayıtla güncellenir
- **And** `hezarfen_mst_shipment_data_saved` action'ı `(WC_Order $order, array $shipment_data)` ile fırlatılır
- **And** sipariş durumu `wc-hezarfen-shipped` olur (filtre `hezarfen_mst_new_order_status` ile değiştirilebilir)
- **And** SMS aktifse seçili sağlayıcı (NetGSM/PandaSMS) üzerinden müşteriye SMS gider
- **And** `Email_Order_Shipped` WC e-postası tetiklenir
- **And** "Hesabım > Sipariş Detayı" sayfasında takip linki görünür

### Senaryo: Tek siparişe ikinci gönderi ekleme
- **Given** sipariş zaten bir takip kaydına sahip
- **When** admin meta box'tan "Yeni gönderi ekle" ile ikinci kayıt girer
- **Then** `_hezarfen_mst_shipment_data` için **ikinci** ayrı meta satırı oluşturulur (üzerine yazılmaz)
- **And** "Siparişler" listesi kolonu "Shipment in pieces" şeklinde özetler

### Senaryo: HepsiJET API ile otomatik gönderi
- **Given** admin sipariş için kargo firması `hepsijet-entegrasyon` seçti
- **When** "Gönderiyi oluştur" butonuna tıklar (`hezarfen_mst_create_hepsijet_shipment` AJAX)
- **Then** HepsiJET API'sine istek gönderilir; dönen takip no otomatik kaydedilir
- **And** sipariş `wc-hezarfen-shipped` durumuna geçer
- **And** API yanıtı `_hezarfen_hepsijet_shipment_{delivery_no}` meta'sına cache'lenir

### Senaryo: HepsiJET webhook ile teslim güncellemesi
- **Given** HepsiJET kontrol panelinde webhook URL'i `?wc-api=hez_ordermigo_shipment_status` olarak tanımlı
- **When** HepsiJET `shipment.delivered` eventi push'lar
- **Then** imza HMAC-SHA256 ile doğrulanır
- **And** sipariş `wc-completed` durumuna geçirilir
- **Edge** imza eşleşmezse istek reddedilir, sipariş güncellenmez

### Senaryo: Toplu barkod üretimi (HepsiJET)
- **Given** admin siparişler listesinde birden fazla HepsiJET siparişi seçti
- **When** "Hezarfen Get Hepsijet Bulk Barcode" bulk action'ı çalıştırılır
- **Then** her sipariş için barkod oluşturulur; sonuç tek bir PDF olarak dönülür
- **And** geçici cache transient'lere yazılır

### Senaryo: Üçüncü parti veriyi tanıma
- **Given** sitede önceden "Intense Kargo Takip" veya "Kargo Takip Türkiye" eklentisi kullanılmış
- **When** sistem 25+ siparişte legacy meta tespit eder
- **Then** "Third party data support" otomatik aktifleşir
- **And** legacy meta'lar `hezarfen_mst_get_shipment_data` filtresi üzerinden read-only listelenir

### Senaryo: "Custom" kargo firması
- **Given** admin `custom` courier'ı seçti
- **When** sipariş kaydedilir
- **Then** courier başlığı `hezarfen_mst_courier_company_custom_meta` option'ında belirtilen meta key'den okunur (per-sipariş başlık)
- **And** `create_tracking_url()` boş döner — sadece takip no saklanır

### Senaryo: "Kurye" courier'ı
- **Given** courier `kurye` seçildi
- **When** sipariş kaydedilir
- **Then** takip URL'i üretilmez; takip linki gösterilmez

## Edge Cases

- **HPOS off / on matrisi**: meta box ekran kimliği `shop_order` veya `woocommerce_page_wc-orders`; `WC_HEZARFEN_HPOS_ENABLED` sabiti `class-hezarfen.php` içinde tanımlanır.
- **"Diğer / Other" courier**: takip URL'i hesaplanmaz.
- **Tek sipariş, farklı kalemler için farklı kargolar**: desteklenmiyor — granülerlik sipariş bütünü düzeyinde.
- **CSV ile toplu takip no içe aktarma**: desteklenmiyor; AJAX form veya HepsiJET API üzerinden tek tek.
- **Sipariş listesinden direkt düzenleme**: desteklenmiyor; sipariş detayı açılmalı.
- **SMS sağlayıcı eklentisi pasifken** `_hezarfen_mst_enable_sms_notification = yes` olsa bile SMS sessizce atlanır.
- **Meta string formatı pipe-delimited**: legacy karar; JSON'a migrate edilirken backward-compat için parse fallback'i unutulmamalı.

## UI Lokasyonları

- **Admin > WooCommerce > Siparişler > [sipariş]** — "Hezarfen Cargo Tracking & SMS Notifications" meta box
  - Template: `packages/manual-shipment-tracking/templates/order-edit/metabox-shipment.php`
- **Admin > WooCommerce > Siparişler** — `hezarfen_mst_shipment_info` kolonu
- **Admin > WooCommerce > Ayarlar > Hezarfen > Shipment Tracking** — `class-settings.php`
- **Frontend > Hesabım > Siparişler** — opsiyonel takip kolonu
- **Frontend > Hesabım > Sipariş Detayı** — takip kartı
- **E-posta**: `Email_Order_Shipped`, template:
  `packages/manual-shipment-tracking/templates/emails/email-order-shipped.php`
  (tema klasöründe `{theme}/hezarfen-for-woocommerce/emails/email-order-shipped.php` ile override edilir)

## Hooks

### Actions
- `hezarfen_mst_shipment_data_saved` — takip kaydı save sonrası. Params: `(WC_Order $order, array $shipment_data)`.
- `hezarfen_mst_order_shipped` — order status update + notifications tamamlandıktan sonra. Params: `(WC_Order $order, array $shipment_data)`.
- `woocommerce_api_hez_ordermigo_shipment_status` — HepsiJET webhook entry point.

### Filters
- `hezarfen_mst_new_order_status` — takip girildiğinde geçilecek sipariş durumu. Params: `(string $status, WC_Order $order, string $courier_id, string $tracking_num)`.
- `hezarfen_mst_courier_companies` — kargo firma listesini değiştir. Params: `(array $courier_companies)`.
- `hezarfen_mst_get_shipment_data` — üçüncü parti shipment data injekte et. Params: `(array $data, int $order_id)`.
- `hezarfen_shop_order_no_shipment_found_msg` — kolonda "kargo bulunamadı" mesajını özelleştir.

### AJAX Action'ları (admin, nonce'lu)
- `hezarfen_mst_create_hepsijet_shipment`
- `hezarfen_mst_track_hepsijet_shipment`
- `hezarfen_mst_cancel_hepsijet_shipment`
- `hezarfen_mst_get_hepsijet_barcode`
- `hezarfen_mst_generate_hepsijet_pdf`

## Sınama Notları

- WC mock order ile `Manual_Shipment_Tracking::save_tracking_info()` çağrısı; meta yazılımı + status değişimi doğrula.
- HPOS açık/kapalı: meta box görünürlüğü, kolon render'ı, bulk action.
- HepsiJET sandbox credentials ile create → track → cancel akışı.
- Webhook: geçerli ve geçersiz imza ile POST gönder; sipariş statüsünün sadece geçerli imzada değiştiğini doğrula.
- "custom" courier: `hezarfen_mst_courier_company_custom_meta` option'ı set edilmiş + ilgili order meta dolu iken başlığın doğru göründüğünü doğrula.
- Çoklu shipment: aynı sipariş için 3 takip no eklenip sırasıyla render edildiğini doğrula.
