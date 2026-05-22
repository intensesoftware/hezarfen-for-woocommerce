---
id: my-account
title: Hesabım Sayfası Entegrasyonları
status: stable
since: 1.0.0
owner: hezarfen-core
entry_files:
  - includes/class-my-account.php
  - includes/contracts/frontend/class-customer-agreements.php
  - packages/manual-shipment-tracking/includes/class-my-account.php
  - assets/js/my-account-addresses.js
depends_on: [woocommerce]
related: [neighborhood-selection, shipment-tracking, sales-contract]
---

## Amaç

"Hesabım" (WC My Account) sayfasını Türkiye akışına uydurmak: adres düzenleme ekranında ilçe/mahalle select'leri, opsiyonel sıralama ve posta kodu gizleme; sipariş geçmişi ve detayında kargo takip + sözleşme erişimi.

## Kapsam

- Yeni endpoint veya tab **eklenmez**. Mevcut WC tab'larını zenginleştirir.
- `Edit Address` formunda `billing_city` ve `billing_address_1` select'e dönüştürülür (Türkiye için).
- "My Orders" tablosuna opsiyonel kargo takip kolonu.
- "View Order" / "Order Received" (thank you) sayfasında kargo takip + sözleşme modal'ları.

## Veri Modeli

Yeni meta veya option **yazılmaz**. Tüketici taraflıdır:
- Kargo: `_hezarfen_mst_shipment_data` (bkz. `./shipment-tracking.md`).
- Sözleşmeler: `wp_hezarfen_contracts` (bkz. `./sales-contract.md`).
- Adres: WC standart `billing_*` meta'ları.

İlgili options:
- `hezarfen_sort_my_account_fields`
- `hezarfen_hide_my_account_postcode_fields`
- `hezarfen_mst_show_shipment_tracking_column`

## Davranışlar

### Senaryo: Adres düzenle — TR seçili
- **Given** müşteri "Hesabım > Adres > Düzenle" ekranında, country = TR
- **When** sayfa render edilir
- **Then** `billing_city` select'e dönüştürülür (Mahalle_Local::get_districts ile doldurulur)
- **And** `billing_address_1` select'e dönüştürülür (mahalle listesi; il/ilçe seçimine göre AJAX'la yenilenir)
- **And** `my-account-addresses.js` enqueue edilir

### Senaryo: Müşteri kayıt sırasında validation hatası
- **Given** müşteri formu eksik gönderdi
- **When** WC validation hata verir
- **Then** müşteri objesi yine de save edilir (`class-my-account.php:73-76`) — kullanıcı yazdıklarını kaybetmez
- **And** error notice'ları gösterilir

### Senaryo: My Orders tablosunda takip kolonu
- **Given** `hezarfen_mst_show_shipment_tracking_column = yes`
- **When** müşteri "Hesabım > Siparişlerim" listesini açar
- **Then** her sipariş satırında "Kargo Takip" kolonu görünür
- **And** Takip no varsa linke tıklayınca courier'ın URL'ine yönlendirir

### Senaryo: Sipariş detayında kargo + sözleşme
- **Given** müşteri "Hesabım > Sipariş #123 > Görüntüle"de
- **When** sayfa render edilir
- **Then** ürün tablosu altında kargo takip kartı (varsa) gösterilir
- **And** "Sözleşmeleri Görüntüle" butonu (kayıt varsa) modal açar
- **And** modal her sözleşme için ayrı tab gösterir; başlık + kabul zamanı + IP + içerik

### Senaryo: Sipariş alındı (thank you) sayfası
- **Given** ödeme tamamlandı, müşteri "Sipariş Alındı" sayfasında
- **When** sayfa render edilir
- **Then** sözleşmeler listesi + view modal gösterilir
- **And** kargo bilgisi (varsa — genelde bu aşamada henüz yok) gösterilir

### Senaryo: Posta kodu Hesabım'da gizli
- **Given** `hezarfen_hide_my_account_postcode_fields = yes`
- **When** edit-address render edilir
- **Then** postcode `hidden`, required=false

## Edge Cases

- **Country=TR olmadan adres düzenleme**: select dönüşümü yapılmaz, normal text input kalır.
- **Sözleşmeler tablosu yoksa**: "Henüz sözleşme yok" mesajı, hata fırlatılmaz.
- **HPOS açık**: sözleşme verileri WP options'tan değil custom tablodan okunur, HPOS'tan etkilenmez.
- **Misafir checkout**: thank-you sayfası sözleşme modal'ını yine gösterir (login zorunlu değil); ancak "Hesabım > Sipariş" akışına ulaşamaz.
- **CFE ile çakışma**: `class-compatibility.php` edit-address sayfasında sort açıkken CFE filter'larını disable eder.

## UI Lokasyonları

- **Frontend > Hesabım > Adres > Düzenle (billing/shipping)**
- **Frontend > Hesabım > Siparişlerim** (tablo)
- **Frontend > Hesabım > Sipariş Detayı**
- **Frontend > Sipariş Alındı (thank-you)**

## Hooks

Kendi public hook'u expose etmez; aşağıdakilere bağlanır:
- `woocommerce_address_to_edit`
- `woocommerce_after_save_address_validation`
- `woocommerce_my_account_my_orders_columns`
- `woocommerce_my_account_my_orders_column_*`
- `woocommerce_order_details_after_order_table`
- `woocommerce_thankyou`

## Sınama Notları

- Misafir kullanıcının thank-you sayfasında sözleşme modal'ını görebildiğini doğrula.
- Validation hatası sonrası adres formundaki girişlerin korunduğunu doğrula.
- "Siparişlerim" tablosu kolon toggle'ının çalıştığını ve takip linkinin doğru URL'ye gittiğini doğrula.
- Sözleşmeler tablosu yokken sayfanın patlamadığını doğrula.
- Country değişiminde select ↔ text input dönüşümünün çalıştığını doğrula.
