---
id: returns
title: İade Yönetimi
status: beta
since: 2.15.0
owner: hezarfen-core
entry_files:
  - includes/returns/class-returns-module.php
  - includes/returns/core/class-return-service.php
  - includes/returns/core/class-return-eligibility.php
  - includes/returns/frontend/class-my-account-returns.php
  - includes/returns/frontend/class-guest-returns.php
  - includes/returns/admin/class-returns-admin.php
depends_on: [woocommerce]
optional_deps: [manual-shipment-tracking]
related: [my-account, shipment-tracking, admin-menu]
---

## Amaç

Müşterinin satın aldığı ürünü iade etmek için mağazaya e-posta atması ya da telefon
etmesi gerekmesin. Müşteri hesabından (veya üye değilse sipariş no + e-posta ile)
iade talebi açar, süreci adım adım takip eder; mağaza da talepleri tek bir ekrandan
onaylar, reddeder, ek bilgi ister ve kargo bilgisini görür.

Modül **varsayılan olarak kapalıdır**; WooCommerce → Ayarlar → Hezarfen → İade
Yönetimi bölümünden açılır.

## Kapsam

- Hesabım altında "İadelerim" listesi ve talep detayı (`includes/returns/frontend/class-my-account-returns.php`)
- Üyeliksiz (guest) iade: sipariş no + fatura e-postası doğrulaması (`includes/returns/frontend/class-guest-returns.php`)
- Ürün/adet seçimi ve kısmi iade (`includes/returns/core/class-return-eligibility.php`)
- Hazır iade sebepleri + "Diğer" seçeneğinde zorunlu açıklama (`includes/returns/core/class-default-reason-provider.php`)
- Global iade süresi ve süre başlangıcı ayarı (`includes/returns/core/class-global-return-policy-provider.php`)
- Tek iade adresi (`includes/returns/core/class-return-settings.php`)
- Gönderim yöntemi: müşteri kendi gönderir (manuel takip no) veya Kargokit otomatik iade barkodu (`includes/returns/shipping/`)
- Admin liste + detay, manuel onay/red, basit ek bilgi isteme, dahili/müşteriye açık notlar (`includes/returns/admin/`)
- Temel timeline ve standart WooCommerce e-postaları (`includes/returns/emails/`)

### Kapsam dışı (kasıtlı)

Aşağıdakiler bu modülde **yoktur**; modül bunlar için hazır uzantı noktaları
sunar (bkz. "Uzantı Noktaları"), böylece bir eklenti çekirdek koda dokunmadan
ekleyebilir:

- Mağazanın kendi tanımladığı iade sebepleri
- Talebe fotoğraf/video ekleme ve kanıt kuralları
- E-posta içeriği özelleştirme
- Ürün/kategori bazlı iade politikaları
- Çoklu iade adresi / depo
- Mağazanın kendi kargo anlaşmasıyla otomatik barkod
- Talep içi mesajlaşma, değişim (exchange), mağaza kredisi
- Kural motoru, otomatik onay, toplu işlem, SLA hatırlatmaları, analitik, CSV/API/webhook

## Veri Modeli

### Tablolar

Şema `hezarfen_returns_db_version` option'ı ile ayrı sürümlenir; plugin sürümü
artmadan da kurulabilsin diye `hezarfen_db_version`'a bağlı değildir
(`includes/returns/core/class-returns-schema.php`).

- `{prefix}hezarfen_returns` — talep başlığı. `return_number`, `order_id`,
  `customer_id`, `customer_email`, `status`, `shipping_method`, `courier`,
  `tracking_number`, `return_address_id`, `customer_note`, `refund_amount`,
  `currency`, `access_token`, `created_via`, `ip_address`, `created_at`,
  `updated_at`.
- `{prefix}hezarfen_return_items` — talebe dahil satırlar. `return_id`,
  `order_item_id`, `product_id`, `variation_id`, `product_name`, `sku`,
  `quantity`, `line_total`, `reason_key`, `reason_note`.
- `{prefix}hezarfen_return_events` — timeline. `return_id`, `type`, `actor_type`,
  `actor_id`, `actor_name`, `from_status`, `to_status`, `message`,
  `is_customer_visible`, `created_at`.

### Durumlar

| Key | Admin etiketi | Açıklama |
|---|---|---|
| `pending` | Beklemede | Yeni talep, inceleme bekliyor |
| `info-required` | Ek bilgi bekleniyor | Mağaza müşteriden ayrıntı istedi |
| `approved` | Onaylandı | Ürünlerin yola çıkması bekleniyor |
| `shipped` | Kargoya verildi | Takip numarası girildi |
| `received` | Tarafımıza ulaştı | Ürünler mağazaya ulaştı |
| `completed` | Tamamlandı | Süreç kapandı (terminal) |
| `rejected` | Reddedildi | Talep kabul edilmedi (terminal) |
| `cancelled` | İptal edildi | Müşteri veya mağaza iptal etti (terminal) |

İzinli geçişler `Return_Status::get_transitions()` içinde tanımlıdır ve
filtrelenebilir. `rejected` ve `cancelled` talepler sipariş satırında ayırdıkları
adedi geri bırakır; diğer tüm durumlar adedi tutar.

## Davranışlar

### Senaryo: Hesabı olan müşteri kısmi iade talebi açar
- **Given** modül açık ve siparişin durumu izinli durumlar arasında
- **And** iade süresi dolmamış
- **When** müşteri Hesabım → İadelerim → İade et yolunu izleyip bir satırdan 2 adetten 1'ini seçer, sebep belirtir ve formu gönderir
- **Then** `pending` durumunda bir talep oluşur, `IADE-{sipariş no}-{sıra}` referansı atanır
- **And** müşteriye onay, mağazaya bilgi e-postası gider
- **And** aynı satır için kalan 1 adet hâlâ iade edilebilir görünür

### Senaryo: "Diğer" sebebi açıklama olmadan gönderilir
- **Given** müşteri bir satır seçmiş ve sebep olarak "Diğer" işaretlemiş
- **When** açıklama alanı boş bırakılıp form gönderilir
- **Then** talep oluşmaz ve "açıklama yazmanız gerekiyor" hatası gösterilir
- **And** bu kural JavaScript kapalıyken de sunucu tarafında uygulanır

### Senaryo: Üyeliksiz müşteri siparişini sorgular
- **Given** üyeliksiz iade açık
- **When** müşteri iade sayfasında sipariş numarası ve fatura e-postasını girer
- **Then** eşleşme varsa sipariş anahtarından türetilmiş bir token ile iade formuna yönlendirilir
- **And** eşleşme yoksa her hata için aynı genel mesaj gösterilir (sipariş var mı yok mu sızdırılmaz)

### Senaryo: Üyeliksiz erişim tokensiz denenir
- **Given** var olan bir iade talebi
- **When** iade sayfası `?hezarfen_return={id}` ile tokensiz veya yanlış tokenla açılır
- **Then** talep detayı gösterilmez, sorgulama formuna düşülür

### Senaryo: İade süresi dolmuş sipariş
- **Given** global iade süresi 14 gün ve sipariş 30 gün önce tamamlanmış
- **When** iade formu açılmak istenir
- **Then** "iade süresi ... doldu" hatası gösterilir ve form render edilmez

### Senaryo: Mağaza talebi onaylar
- **Given** `pending` durumda bir talep
- **When** yönetici Hezarfen → İadeler → talep detayında "Onayla" der
- **Then** durum `approved` olur, timeline'a yönetici aktörlü bir kayıt düşer
- **And** müşteriye onay e-postası gider
- **And** gönderim yöntemi Kargokit ise iade barkodu oluşturulmaya çalışılır

### Senaryo: Mağaza ek bilgi ister
- **Given** `pending` veya `info-required` dışı bir açık talep
- **When** yönetici "Müşteriden ek bilgi iste" alanına mesaj yazıp gönderir
- **Then** durum `info-required` olur, mesaj timeline'a `info-request` tipiyle düşer
- **And** müşteriye ek bilgi e-postası gider
- **When** müşteri talep sayfasındaki formdan yanıt yazar
- **Then** yanıt `info-response` olarak kaydedilir ve durum `pending`'e döner

### Senaryo: Müşteri kargo bilgisini girer
- **Given** gönderim yöntemi "müşteri kendi gönderir" ve talep `approved`
- **When** müşteri kargo firması ve takip numarasını kaydeder
- **Then** bilgi talebe yazılır, timeline'a `shipping` kaydı düşer
- **And** durum otomatik olarak `shipped` olur

### Senaryo: Kargokit iade barkodu oluşturulamaz
- **Given** gönderim yöntemi Kargokit ve entegrasyon eksik/hatalı
- **When** yönetici talebi onaylar
- **Then** onay geri alınmaz; hata yalnızca mağazanın gördüğü bir timeline kaydına yazılır
- **And** mağaza manuel takip numarası girerek devam edebilir

### Senaryo: Müşteri talebini iptal eder
- **Given** `pending` veya `info-required` durumda bir talep
- **When** müşteri "Talebi iptal et" der
- **Then** durum `cancelled` olur ve satırların ayırdığı adet serbest kalır

## Edge Cases

- **Dijital ürünler**: sanal/indirilebilir satırlar iade listesine hiç girmez.
- **WooCommerce iadesi**: mağaza WC üzerinden adet iade ettiyse, o adet iade
  edilebilir miktardan düşülür.
- **Sayfa/endpoint çakışması**: herkese açık sayfanın slug'ı (`iade`) endpoint
  slug'larından (`iadelerim`, `iade-talebi`) farklıdır; aynı olsaydı `EP_ROOT`
  rewrite kuralı sayfayı gölgeleyip 404 üretirdi.
- **Guest bildirimi**: sepete hiç dokunmamış bir ziyaretçinin WooCommerce oturum
  çerezi yoktur; bildirim kaybolmasın diye çerez yönlendirmeden önce zorlanır.
- **Süre 0**: iade süresi 0 girilirse zaman sınırı uygulanmaz.
- **Şema kurulumu**: tablolar `hezarfen_returns_db_version` ile ayrı sürümlenir,
  plugin sürümü artmasa da kurulur.

## UI Lokasyonları

- **Admin**: Hezarfen → İadeler (liste + detay, bekleyen sayısı rozetli);
  WooCommerce → Ayarlar → Hezarfen → İade Yönetimi; sipariş düzenleme ekranında
  "Hezarfen İade Talepleri" kutusu; WooCommerce → Ayarlar → E-postalar altında
  altı bildirim.
- **Frontend**: Hesabım → İadelerim (`/{hesabım}/iadelerim/`, detay için
  `/{hesabım}/iadelerim/{id}/`); iade formu `/{hesabım}/iade-talebi/{sipariş id}/`;
  herkese açık sayfa `[hezarfen_iade]` kısa koduyla; sipariş detay sayfasının
  altında iade paneli.

## Uzantı Noktaları

Modül, ek yeteneklerin **koda dokunmadan** takılabilmesi için şu sözleşmeleri
sunar:

| Sözleşme | Dosya | Ne için |
|---|---|---|
| `Return_Reason_Provider_Interface` | `core/interface-return-reason-provider.php` | Mağazaya özel iade sebepleri |
| `Return_Policy_Provider_Interface` | `core/interface-return-policy-provider.php` | Ürün/kategori bazlı iade politikaları |
| `Return_Shipping_Method_Interface` | `shipping/interface-return-shipping-method.php` | Kendi kargo anlaşmasıyla otomatik barkod |
| `Return_Repository_Interface` | `core/interface-return-repository.php` | Alternatif depolama |

## Hooks

Tam liste için `specs/shared/hooks.md`. Öne çıkanlar:

- action: `hezarfen_return_created` — talep kaydedildikten sonra `(Return_Request $request, WC_Order $order)`
- action: `hezarfen_return_status_changed` — durum değişince `(Return_Request $request, string $old, string $new)`
- action: `hezarfen_return_status_{status}` — belirli bir duruma geçince `(Return_Request $request, string $old)`
- action: `hezarfen_returns_loaded` — modül ayağa kalkınca; sağlayıcılar burada kaydedilir `(Returns_Module $module)`
- filter: `hezarfen_returns_reason_providers` / `..._policy_providers` / `..._shipping_methods`
- filter: `hezarfen_returns_return_address` — talebin gönderileceği adres

## Sınama Notları

- E2E: `tests/e2e/returns-my-account.spec.ts`, `returns-guest.spec.ts`,
  `returns-admin.spec.ts`, `returns-eligibility.spec.ts`,
  `returns-settings.spec.ts`, `returns-emails.spec.ts`.
- Manuel: modülü açtıktan sonra kalıcı bağlantıları bir kez yenileyin
  (endpoint'ler `hezarfen_returns_endpoints_version` değişince otomatik flush olur).
- HPOS açık/kapalı matrisinde sipariş düzenleme kutusu ayrı doğrulanmalı.
- Kargokit iade barkodu yalnızca hepsiJET entegrasyonu yapılandırılmış ve siparişin
  gönderi kaydı varken denenir; yapılandırma yoksa yöntem seçeneklerde çıkmaz.
