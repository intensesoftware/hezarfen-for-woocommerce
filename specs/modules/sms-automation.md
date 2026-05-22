---
id: sms-automation
title: SMS Otomasyonu (NetGSM Native + Legacy Providers)
status: stable
since: 2.5.0
owner: hezarfen-core
entry_files:
  - includes/class-sms-automation.php
  - includes/class-notification-provider.php
  - includes/admin/settings/class-hezarfen-settings-hezarfen.php
  - assets/admin/js/sms-settings.js
  - packages/manual-shipment-tracking/includes/notification-providers/class-netgsm.php
  - packages/manual-shipment-tracking/includes/notification-providers/class-pandasms.php
depends_on: [woocommerce]
optional_deps: [netgsm-official-plugin, pandasms-official-plugin]
related: [shipment-tracking]
---

## Amaç

Sipariş durumu değişimleri ve "kargoya verildi" eventinde müşteriye otomatik SMS gönderimi. Yerleşik **NetGSM** entegrasyonu (önerilen) ile birlikte legacy "NetGSM resmi eklentisi" ve "PandaSMS resmi eklentisi" üzerinden geriye uyumlu çalışma. Kural bazlı; admin istediği kadar rule tanımlayabilir.

## Kapsam

- Rule-based: her rule bir tetikleyici (status veya shipment) + sağlayıcı + telefon kaynağı + şablon kombinasyonu.
- 40+ değişken `{variable}` veya legacy `[variable]` syntax'ıyla.
- Senkron gönderim — wp-cron / Action Scheduler kullanılmaz.
- IYS (İleti Yönetim Sistemi) flag'i desteklenir.
- Multilingual değişken normalleştirme: TR locale'inde İngilizce değişkenler Türkçe karşılıklarına maplenir.

## Sağlayıcılar

| `action_type` | Tanım |
|---|---|
| `netgsm` | Native — `hezarfen_global_netgsm_credentials` option'unda saklı user/pass/msgheader ile direkt REST API. Önerilen. |
| `netgsm_legacy` | NetGSM resmi eklentisi (`\Hezarfen\ManualShipmentTracking\Netgsm`) üzerinden. **Deprecated.** |
| `pandasms_legacy` | PandaSMS eklentisi (`pandasms-for-woocommerce`). Sadece `hezarfen_mst_order_shipped` trigger'ında çalışır. |

### NetGSM Native API
- Send: `POST https://api.netgsm.com.tr/sms/rest/v2/send`
- Headers: `POST https://api.netgsm.com.tr/sms/rest/v2/msgheader` (gönderici başlık listesi)
- Auth: Basic Auth (`user:pass`)
- Timeout: 30s (`wp_remote_post`)
- Başarı kodları: `00`, `01`, `02`.

## Veri Modeli

### Options
- `hezarfen_sms_automation_enabled` — `yes|no`.
- `hezarfen_sms_rules` — rule array.
- `hezarfen_global_netgsm_credentials` — `{ username, password, msgheader }`.

### Rule Yapısı
```php
[
  'condition_status' => 'wc-completed' | 'hezarfen_order_shipped' | 'wc-processing' | ...,
  'action_type'      => 'netgsm' | 'netgsm_legacy' | 'pandasms_legacy',
  'phone_type'       => 'billing' | 'shipping' | 'shipping_or_billing',
  'message_template' => 'Sayın {customer_name}, {order_number} numaralı siparişiniz {order_status}.',
  'iys_status'       => '0' | '11' | '12',
]
```

### Order Meta (log)
- `_hezarfen_sms_sent_{status}` — `yes` (gönderildi flag).
- `_hezarfen_sms_sent_time_{status}` — timestamp.
- `_hezarfen_sms_jobid_{status}` — NetGSM job id.
- `_hezarfen_sms_log_*` — full log entry (timestamp, success, response).

## Tetikleyici Eventler

### Tetikleyici 1: WooCommerce sipariş durumu değişimi
- Hook: `woocommerce_order_status_changed` (priority 10).
- Status normalize edilir: `wc-` prefix'i kaldırılır.
- Desteklenen statuslar: pending, processing, on-hold, completed, cancelled, refunded, failed, checkout-draft.

### Tetikleyici 2: Kargoya verildi
- Hook: `hezarfen_mst_shipment_data_saved` (bkz. `./shipment-tracking.md`).
- Eşleşen rule `condition_status = hezarfen_order_shipped` olmalı.
- SMS gönderildikten sonra `hezarfen_order_shipped` action'ı tekrar fırlatılır.

## Şablon Değişkenleri (özet)

Tam liste için `class-sms-automation.php:709-813` ve TR locale map `:457-496`.

- **Sipariş**: `{order_number}`, `{customer_name}`, `{order_status}`, `{order_total}`, `{order_date}`, `{order_time}`
- **Fatura/teslimat**: `{billing_first_name}`, `{billing_last_name}`, `{billing_phone}`, `{billing_email}`, `{billing_company}`, `{billing_address}`, `{billing_city}`, `{billing_country}`, `{shipping_first_name}`, … (paralel)
- **Shipment-only** (sadece `hezarfen_order_shipped` trigger'ında dolu): `{courier_company}`, `{tracking_number}`, `{tracking_url}`
- **Legacy Türkçe**: `[siparis_no]`, `[uye_adi]`, `[uye_soyadi]`, `[kullanici_adi]`, `[tarih]`, `[saat]`, `[kargo_firmasi]`, `[takip_kodu]`, `[takip_linki]` — ya da `{...}` syntax'ıyla.

## Davranışlar

### Senaryo: Sipariş tamamlandığında SMS
- **Given** rule: `condition_status=wc-completed, action_type=netgsm, phone_type=billing`
- **And** `hezarfen_sms_automation_enabled=yes`, NetGSM credentials sağlam
- **When** sipariş `wc-completed` durumuna geçer
- **Then** `handle_order_status_change` çalışır
- **And** şablon değişkenleri sipariş verisiyle doldurulur
- **And** NetGSM API'ye `wp_remote_post` çağrısı yapılır
- **And** Başarılıysa `_hezarfen_sms_sent_completed = yes` ve `_hezarfen_sms_jobid_completed` meta'sı yazılır
- **And** Siparişe order note eklenir

### Senaryo: Kargoya verildi SMS'i
- **Given** rule: `condition_status=hezarfen_order_shipped`
- **When** `hezarfen_mst_shipment_data_saved` fırlatılır
- **Then** `handle_order_shipped` çalışır
- **And** `{tracking_number}`, `{tracking_url}`, `{courier_company}` doldurulur
- **And** SMS gönderilir
- **And** `hezarfen_order_shipped` action'ı fırlatılır (zincirleme entegrasyonlar için)

### Senaryo: Telefon yok — sessiz atla
- **Given** rule `phone_type = billing`, sipariş billing telefonu boş
- **When** trigger çalışır
- **Then** SMS sessizce atlanır (hata fırlatılmaz)
- **And** log yazılmaz

### Senaryo: `shipping_or_billing` fallback
- **Given** rule `phone_type = shipping_or_billing`
- **When** shipping telefon boş, billing dolu
- **Then** billing telefon kullanılır

### Senaryo: NetGSM credential eksik
- **Given** `hezarfen_global_netgsm_credentials` boş veya geçersiz
- **When** rule çalışır
- **Then** SMS atılmaz; log yazılmaz; sipariş notesi yok

### Senaryo: NetGSM API hata kodu
- **Given** API yanıt kodu `00/01/02` dışında
- **When** response parse edilir
- **Then** sonuç `success=false` döner
- **And** order meta'ya başarısızlık log'u yazılır (`_hezarfen_sms_log_*`)
- **Edge** Order note yazılır mı yazılmaz mı: implementasyon başarısızlıkta sessiz; admin log'a bakmalı.

### Senaryo: Legacy NetGSM eklentisi rule'u
- **Given** rule `action_type=netgsm_legacy`
- **And** NetGSM resmi eklentisi aktif
- **When** trigger çalışır
- **Then** SMS legacy `Netgsm` sınıfı üzerinden gönderilir
- **Edge** Eklenti pasifse SMS atılmaz; admin'e uyarı yok

### Senaryo: PandaSMS sadece shipment trigger'ında
- **Given** rule `action_type=pandasms_legacy`, `condition_status=wc-completed`
- **When** sipariş `wc-completed` olur
- **Then** PandaSMS tetiklenmez (sadece shipment event'inde çalışır)

### Senaryo: Legacy bracket variable
- **Given** şablon `Sn [uye_adi], [siparis_no] numaralı siparişiniz hazır.`
- **When** template processor çalışır
- **Then** `[uye_adi]` ve `[siparis_no]` aynen `{customer_name}` ve `{order_number}` gibi değiştirilir (geriye uyum)

## Edge Cases

- **Senkron gönderim**: API yavaşsa checkout/order işleme süresi uzar. Throttle/queue yok.
- **Uluslararası numara format'ı**: validation yok; NetGSM tarafa bırakılır.
- **Tekrar gönderme koruması**: `_hezarfen_sms_sent_{status}` flag'i tanımlı ama trigger handler'ında **aktif kullanılmıyor**. Aynı statuse tekrar geçilirse SMS tekrar atılabilir. (Bilinen davranış.)
- **Sandbox/test mode**: yok — her gönderim canlı.
- **Migration**: 2.5.0 öncesi `hezarfen_mst_enable_sms_notification` setting'i otomatik olarak yeni rule formatına dönüştürülür (`Hezarfen_Install.php:65-149`).
- **IYS**: `iysfilter` parametresi NetGSM'e iletilir. `0`=bilgilendirme, `11`=ticari bireysel, `12`=ticari kurumsal. Yanlış değer hukuki risk doğurabilir.

## UI Lokasyonları

- **Admin > WooCommerce > Ayarlar > Hezarfen > SMS Settings** — `?section=sms_settings`
  - Bağlantı durumu (NetGSM)
  - SMS otomasyonu master switch
  - Rule listesi: ekle/düzenle/sil
  - Rule form: tetikleyici, sağlayıcı, telefon tipi, şablon, IYS
  - NetGSM credentials modal (test connection + msgheader fetch)
- **Admin > Sipariş edit > Order Notes** — gönderim notu olarak görünür.

## Hooks

### Actions (emit)
- `hezarfen_order_shipped` — kargo SMS'i işlendikten sonra. Params: `(WC_Order $order, array $shipment_data)`.

### Actions (consume)
- `woocommerce_order_status_changed`
- `hezarfen_mst_shipment_data_saved`

### Filters
- Modül kendi public filter'ını expose etmez.

### AJAX (admin, capability `manage_woocommerce`)
- `hezarfen_save_sms_rules`
- `hezarfen_get_sms_rules`
- `hezarfen_save_netgsm_credentials`
- `hezarfen_get_netgsm_credentials`
- `hezarfen_get_netgsm_senders`

## Sınama Notları

- Native NetGSM ile end-to-end gönderim (test telefon hattıyla).
- Geçersiz credential ile API çağrısı: log'da sadece başarısızlık görünmeli, exception sızmamalı.
- Hem `{var}` hem `[var]` syntax'lı şablonların doğru render edildiğini doğrula.
- Aynı sipariş 2 kez aynı statusa geçtiğinde SMS'in tekrar gittiğini doğrula (mevcut tasarım).
- IYS değerinin API'ye doğru iletildiğini doğrula (`iysfilter`).
- Legacy migration: 2.5.0 öncesi setup'ı simüle edip `hezarfen_sms_rules` array'inin oluştuğunu doğrula.
- TR ↔ EN locale geçişinde değişken çevirisi.
- Telefon kaynağı `shipping_or_billing` priority sırasını doğrula.
