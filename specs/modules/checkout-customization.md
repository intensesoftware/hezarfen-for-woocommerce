---
id: checkout-customization
title: Checkout Özelleştirme (Sıralama, Address 2, Compat)
status: stable
since: 1.0.0
owner: hezarfen-core
entry_files:
  - includes/Checkout.php
  - includes/class-hezarfen-wc-helper.php
  - includes/class-compatibility.php
  - includes/class-hezarfen.php
depends_on: [woocommerce]
related: [neighborhood-selection, invoice-fields, my-account]
---

## Amaç

Türkiye'ye uygun bir checkout deneyimi için adres alanlarını yeniden etiketlemek, **Türkiye sıralamasıyla** otomatik dizmek, **`address_2`** alanını silent şekilde zorla aktif etmek, **postal code** alanını kaldırmak/opsiyonel yapmak ve **WC Blocks Checkout** / **Checkout Field Editor** / popüler tema çakışmaları için uyumluluk shim'leri sağlamak.

## Kapsam

- TR locale modifikasyonu: `city` → "Town / City" (anlam: İlçe), `address_1` → "Neighborhood" (anlam: Mahalle).
- `address_2` opsiyonel olabilen WC alanını silent enable eder; tema/setting gizlemiş olsa bile gösterir.
- TR sırasına göre alan priority'leri uygular (bkz. `./neighborhood-selection.md` tablosu).
- Posta kodu Türkiye için gizlenebilir ve required'dan çıkarılır.
- "Checkout Field Editor for WooCommerce" varsa sıralama/postcode override'larını CFE'ye bırakır.
- WC Blocks Checkout (Gutenberg) kullanımı tespit edilir ve admin'e desteklenmediği bildirilir.
- Cartzilla teması için Bootstrap class'ları enjekte edilir.

## Veri Modeli

### Options
- `hezarfen_enable_district_neighborhood_fields` — ilçe/mahalle özelliği (bkz. `./neighborhood-selection.md`).
- `hezarfen_hide_checkout_postcode_fields` — `yes|no`.
- `hezarfen_checkout_fields_auto_sort` — `yes|no`.
- `hezarfen_sort_my_account_fields` — `yes|no` ("Hesabım > Adres Düzenle"yi de sırala).
- `hezarfen_show_hezarfen_checkout_tax_fields` — fatura alanları (bkz. `./invoice-fields.md`).

Bu modül kendi başına ek order meta yazmaz; WC standart alanlarını manipüle eder.

## Davranışlar

### Senaryo: TR locale modifikasyonu
- **Given** müşteri country = TR
- **When** checkout render edilir
- **Then** `city` label'i "Town / City" → "İlçe" anlamıyla, `address_1` label'i "Neighborhood" → "Mahalle" anlamıyla render edilir
- **And** `address_2` label'i "Adres" anlamıyla görünür ve required olur

### Senaryo: Otomatik alan sıralama
- **Given** `hezarfen_checkout_fields_auto_sort = yes`
- **When** WC checkout fields render edilir
- **Then** field priority'leri TR sırasına göre uygulanır (state=50, city=60, address_1=70, address_2=80, invoice/tax=81-84, postcode=90)
- **And** billing_phone=32, billing_email=34, shipping_company=5

### Senaryo: Posta kodu gizleme
- **Given** `hezarfen_hide_checkout_postcode_fields = yes`, country = TR
- **When** checkout render edilir
- **Then** `billing_postcode` / `shipping_postcode` `hidden=true, required=false`
- **And** validation'da postcode zorlanmaz

### Senaryo: `address_2` zorla aktif
- **Given** tema veya başka eklenti `woocommerce_checkout_address_2_field = false` yapmış
- **When** Hezarfen `plugins_loaded` üzerinden `force_enable_address2_field()` çalışır
- **Then** `address_2` yeniden enable edilir (silent — kullanıcıya bildirim verilmez)

### Senaryo: Checkout Field Editor varlığı
- **Given** "Checkout Field Editor for WooCommerce" aktif
- **When** Hezarfen settings'inde sort/postcode toggle'ları kontrol edilir
- **Then** ayarlar UI'da görünür ama efektif olarak **devre dışı** — CFE'nin field tanımlarını override etmesine izin verilir
- **And** edit-address sayfasında CFE'nin priority/label/placeholder/class filter'ları disable edilir (bkz. `class-compatibility.php`)

### Senaryo: WC Blocks Checkout tespiti
- **Given** site `Cart`/`Checkout` blocks kullanıyor (`is_checkout_block_default()`)
- **When** admin dashboard'a girer
- **Then** admin notice "Hezarfen şu anda Blocks Checkout ile çalışmıyor, classic shortcode'u kullanın" mesajı gösterir
- **And** plugin pasifleşmez; sadece uyarı gösterir

### Senaryo: Cartzilla theme support
- **Given** aktif tema Cartzilla
- **When** invoice/TC/tax field'ları render edilir
- **Then** `col-sm-12` / `col-sm-6` / `form-control` Bootstrap class'ları field wrapper'a eklenir

### Senaryo: Woodmart / SiteGround / Cloudways
- **Given** Woodmart teması veya SG/Cloudways host'u tespit edilir
- **When** admin dashboard'a girer
- **Then** ilgili compatibility/perf uyarı banner'ı gösterilir
- **Source**: `class-hezarfen.php:538-545` ve `class-hezarfen.php` host detection.

## Edge Cases

- **TR olmayan müşteri**: sıralama ve posta kodu ayarları sadece TR locale'inde tetiklenir; başka ülkelerde WC default davranışı korunur.
- **Multilingual**: site dilinin Türkçe olması zorunlu değil — country=TR yeterli.
- **CFE + sort_my_account_fields**: edit-address sayfasında CFE filter'ları suppress edilir, Hezarfen sıralaması uygulanır.
- **Tema custom checkout template**: tema `form-checkout.php`'i kendi başına yazıyorsa Hezarfen modifikasyonları render aşamasından sonra geçmediği sürece etkisiz kalabilir.
- **Hez Pro varlığı**: Pro pluginin yüklü olup olmadığı checkout layout'unu değiştirmez; sadece menü ve upgrade buton görünürlüğü farklılaşır.

## UI Lokasyonları

- **Frontend > Checkout** — billing & shipping bölümleri.
- **Frontend > Hesabım > Adres Düzenle** — opt-in sort/postcode.
- **Admin > WooCommerce > Ayarlar > Hezarfen > Checkout Page Settings** — `class-hezarfen-settings-hezarfen.php:253-302`.
- **Admin Dashboard** — blocks checkout / theme / hosting compatibility notices.

## Hooks

Modül kendi public hook'unu expose etmez; WC core hook'larına yaslanır:
- `woocommerce_get_country_locale` (max priority)
- `woocommerce_checkout_fields`
- `woocommerce_default_address_fields`
- `woocommerce_form_field_args`
- `admin_notices`

## Sınama Notları

- TR, DE, US country'leri için sırasıyla checkout render edip değişen alanları doğrula.
- `hezarfen_checkout_fields_auto_sort = yes` ile alan sırasının priority tablosuna uyduğunu doğrula.
- `address_2` field'ını başka bir plugin'le devre dışı bırak, checkout'ta yine göründüğünü doğrula.
- CFE aktifken Hezarfen sort'unun çalışmadığını ve CFE field'larının bozulmadığını doğrula.
- WC Blocks Checkout aktifken admin notice'ın çıktığını ve plugin'in pasifleşmediğini doğrula.
- Cartzilla teması ile invoice alanlarının Bootstrap class'larıyla render olduğunu doğrula.
