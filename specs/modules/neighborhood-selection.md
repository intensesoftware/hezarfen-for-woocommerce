---
id: neighborhood-selection
title: İl / İlçe / Mahalle Seçimi (Mahalle Local)
status: stable
since: 1.0.0
owner: hezarfen-core
entry_files:
  - includes/class-mahalle-local.php
  - includes/Checkout.php
  - includes/class-hezarfen-wc-helper.php
  - includes/Ajax.php
  - api/get-mahalle-data.php
  - includes/Data/mahalle/tr-cities.php
  - includes/Data/mahalle/tr-districts.php
  - includes/Data/mahalle/tr-neighborhoods/
  - assets/js/mahalle-helper.js
depends_on: [woocommerce]
optional_deps: [mbgb]
related: [checkout-customization, my-account]
---

## Amaç

Türkiye adresleri için checkout ve "Hesabım > Adres Düzenle" sayfalarında **il (state) → ilçe (city) → mahalle (address_1)** kademeli dropdown'ları sağlamak. WooCommerce'in `city` alanını "İlçe", `address_1` alanını "Mahalle" olarak yeniden anlamlandırır.

## Kapsam

- Türkiye için **81 il**, ilgili tüm ilçe ve mahalle verileri **statik PHP** dosyalarında bundle'lanır — dış API çağrısı yok.
- Frontend cascading akışı kendi REST-benzeri endpoint'ini kullanır: `api/get-mahalle-data.php`.
- Adres alanları yalnızca `TR` ülkesi için select'e dönüştürülür; başka ülkede normal text input kalır.
- Billing ve shipping bağımsız çalışır.
- `MBGB` (Mahalle Bazlı Gönderim Bedeli) eklentisi opsiyonel olarak mahalle verisini kullanır; bu modül onu gerektirmez.

## Veri Modeli

### Statik kaynaklar
- `includes/Data/mahalle/tr-cities.php` — 81 il, `TR01..TR81` plaka kodları → şehir adı.
- `includes/Data/mahalle/tr-districts.php` — plaka kodu → ilçe listesi.
- `includes/Data/mahalle/tr-neighborhoods/tr-neighborhood-TR{NN}.php` — il başına ayrı dosya; ilçe adı → `{ id => mahalle_adı }`.

### Order/Customer Meta
WC standardı kullanılır — özel meta key **yok**:
- `billing_state` / `shipping_state` — il (`TR##` plaka kodu)
- `billing_city` / `shipping_city` — **ilçe** adı (label: "Town / City" → "İlçe")
- `billing_address_1` / `shipping_address_1` — **mahalle** adı (label: "Address" → "Mahalle")
- `billing_address_2` / `shipping_address_2` — sokak/bina/daire (label: "Adres")

### Options
- `hezarfen_enable_district_neighborhood_fields` — `yes|no` (default `yes`); ilçe/mahalle özelliğini aç-kapa.
- `hezarfen_hide_checkout_postcode_fields` — `yes|no` (default `no`); posta kodunu gizle ve required'dan kaldır.
- `hezarfen_checkout_fields_auto_sort` — `yes|no`; checkout alanlarını Türkiye sıralamasına otomatik diz.
- `hezarfen_sort_my_account_fields` — `yes|no`; "Hesabım > Adres Düzenle" sayfasını da sırala.

## Alan Sıralaması (TR locale, `assign_priorities_to_locale_fields` — `class-hezarfen-wc-helper.php:73-116`)

| Field | Priority |
|---|---|
| state (il) | 50 |
| city (ilçe) | 60 |
| address_1 (mahalle) | 70 |
| address_2 (sokak/no) | 80 |
| invoice_type (varsa) | 81 |
| tax fields | 82–84 |
| postcode | 90 |

Telefon ve e-posta için ayrıca `assign_priorities_to_non_locale_fields` (`billing_phone=32`, `billing_email=34`, `shipping_company=5`).

## Davranışlar

### Senaryo: Türk müşteri checkout açar
- **Given** `billing_country = TR`, `hezarfen_enable_district_neighborhood_fields = yes`
- **When** checkout sayfası render edilir
- **Then** `billing_state` 81 il opsiyonu ile select
- **And** `billing_city` boş ilçe select'i (il seçilince doldurulur)
- **And** `billing_address_1` boş mahalle select'i (ilçe seçilince doldurulur)
- **And** `billing_address_2` görünür, label "Adresiniz", required

### Senaryo: İl seçimi → ilçe yüklemesi
- **Given** kullanıcı `billing_state` seçti
- **When** `change.hezarfen` event'i tetiklenir
- **Then** AJAX `api/get-mahalle-data.php?dataType=district&cityPlateNumber=TR{NN}` çağrısı yapılır
- **And** dönen JSON `billing_city` select'ine doldurulur
- **And** `billing_address_1` boşaltılır

### Senaryo: İlçe seçimi → mahalle yüklemesi
- **Given** kullanıcı `billing_city` seçti
- **When** change event tetiklenir
- **Then** AJAX `?dataType=neighborhood&cityPlateNumber=TR{NN}&district={ad}` çağrısı yapılır
- **And** dönen `{id, name}` çiftleri mahalle select'ine doldurulur

### Senaryo: Mahalle seçildi (AJAX checkout refresh)
- **Given** kullanıcı mahalle seçti
- **When** `wc_hezarfen_neighborhood_changed` AJAX'ı tetiklenir (`includes/Ajax.php:24-37`, nonce `mahalle-io-get-data`)
- **Then** action `hezarfen_checkout_neighborhood_changed` fırlatılır
- **And** çıktı `{update_checkout: true}` (filter `hezarfen_checkout_neighborhood_changed_output_args` ile değiştirilebilir)
- **And** WC checkout review fragmanları yeniden hesaplanır — kargo, vergi vs. mahalleye göre değişebilir

### Senaryo: TR dışı ülke seçilir
- **Given** müşteri `country` alanını başka bir ülkeye değiştirir
- **When** JS country change handler tetiklenir (`mahalle-helper.js:115-129`)
- **Then** ilçe/mahalle select'leri normal text input'a dönüştürülür
- **And** AJAX event listener'ları kaldırılır

### Senaryo: Posta kodu gizleme
- **Given** `hezarfen_hide_checkout_postcode_fields = yes`, country = TR
- **When** checkout render edilir
- **Then** `billing_postcode` ve `shipping_postcode` `hidden` ve `required=false`
- **Edge** "Checkout Field Editor for WooCommerce" plugin'i aktifse bu setting devre dışı kalır (CFE'ye bırakılır)

### Senaryo: Hesabım > Adres Düzenle
- **Given** `hezarfen_sort_my_account_fields = yes` ve müşteri "Hesabım > Adres Düzenle"de
- **When** sayfa render edilir
- **Then** `billing_city` ve `billing_address_1` select'e dönüştürülür (`class-my-account.php:42-61`)
- **And** alan sıralaması TR priority'leriyle uygulanır

### Senaryo: Shipping kapalı
- **Given** WC shipping pasif
- **When** checkout render edilir
- **Then** sadece billing alanları select'e dönüştürülür; shipping alanlarına dokunulmaz

## Edge Cases

- **State'i el ile değiştirme** (geliştirici JS'i): her state change'inde city ve address_1 reset edilir.
- **Çoklu shipping methodu + mahalle bazlı ücret**: MBGB aktifse `wc_hezarfen_neighborhood_changed` çağrısı shipping cost'u tetikler. MBGB yoksa AJAX yine çalışır ama herhangi bir hesap değişmez.
- **Plaka kodu büyük/küçük harf**: API her zaman `TR##` formatı bekler.
- **Listede olmayan mahalle**: müşteri mahalleyi listede bulamazsa, en yakın mahalleyi seçip detayı `address_2`'ye yazması beklenir; özel "diğer" opsiyonu yok.
- **CFE çakışması**: `class-compatibility.php` CFE varsa `priority/label/placeholder/class` filter'larını disable eder.
- **Çoklu dil/locale**: Türkçe-spesifik veri; başka ülkeler için ekleme yok.

## UI Lokasyonları

- **Frontend > Checkout** — billing/shipping bölümleri
- **Frontend > Hesabım > Adres Düzenle**
- **Admin > Sipariş edit > Müşteri detayları** — il/ilçe/mahalle WC standart alanlarında görünür
- **Admin > WooCommerce > Ayarlar > Hezarfen > Genel** — toggle option'lar

## Hooks

### Actions
- `hezarfen_checkout_neighborhood_changed` — mahalle değiştiğinde AJAX içinde fırlatılır. Params: yok özel (POST verilerine erişilir).

### Filters
- `hezarfen_checkout_neighborhood_changed_output_args` — AJAX yanıtını değiştir. Default `{update_checkout: true}`.

### AJAX Action'ları
- `wc_hezarfen_neighborhood_changed` (priv + nopriv).

## Sınama Notları

- Country=TR ile checkout: 81 il, il→ilçe→mahalle cascading.
- Country=DE ile checkout: alanların text input olarak kaldığını doğrula.
- Hesabım > Adres Düzenle: select dönüşümü ve sıralama (sort option açıkken).
- Posta kodu gizle: checkout DOM'da `display:none` ve form submit'te validation hatası **yok**.
- CFE plugin aktifken sıralama/postcode setting'lerinin devre dışı kaldığını doğrula.
- AJAX'lar `mahalle-io-get-data` nonce'u olmadan reddedilmeli.
- Static data dosyalarının composer/build sırasında dist'e dahil edildiğini doğrula.
