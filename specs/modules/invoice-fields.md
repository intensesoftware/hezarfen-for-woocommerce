---
id: invoice-fields
title: Fatura Alanları (Bireysel/Kurumsal, TC No, Vergi)
status: stable
since: 1.0.0
owner: hezarfen-core
entry_files:
  - includes/Checkout.php
  - includes/InvoiceInfo.php
  - includes/Data/PostMetaEncryption.php
  - includes/Data/Abstracts/Abstract_Encryption.php
  - includes/admin/order/OrderDetails.php
  - includes/admin/order/OrderListColumns.php
  - includes/admin/settings/class-hezarfen-settings-hezarfen.php
  - includes/class-hezarfen.php
depends_on: [woocommerce]
related: [checkout-customization, sales-contract]
---

## Amaç

Checkout'a Türkiye'ye özgü fatura alanlarını eklemek: **bireysel/kurumsal** seçimi, **TC kimlik numarası** (KVKK uyumlu **AES-128-CBC** şifreleme ile saklanır), **vergi numarası**, **vergi dairesi**. Admin sipariş ekranında ve sözleşmelerde bu bilgilerin kullanılmasını sağlamak.

## Kapsam

- Tek master switch: `hezarfen_show_hezarfen_checkout_tax_fields` (yes/no). Kapalıysa hiçbir alan render edilmez.
- TC alanı opsiyoneldir ve **encryption key olmadan görünmez** (`is_show_identity_field_on_checkout`).
- `invoice_type` değerine göre TC ↔ vergi alanları JS ile koşullu görünür/gizlenir; backend'de de aynı koşula göre required toggle.
- TC numarası **şifreli** saklanır, vergi no/dairesi **plain text**.
- REST API `WC_Order` çıktısı için TC otomatik decrypt edilir (`class-hezarfen.php:186-246`).

## Veri Modeli

### Order Meta
- `_billing_hez_invoice_type` — `person` | `company` | `""`
- `_billing_hez_TC_number` — **base64(IV + HMAC-SHA256 + ciphertext)** AES-128-CBC sonucu, ya da `"******"` (encryption başarısız fallback).
- `_billing_hez_tax_number` — düz metin, max 11 hane.
- `_billing_hez_tax_office` — düz metin.
- `_billing_company` — WC standart şirket alanı; kurumsal faturada zorunlu.

### Options
- `hezarfen_show_hezarfen_checkout_tax_fields` — modül master switch.
- `hezarfen_checkout_show_TC_identity_field` — TC alanını göster.
- `hezarfen_checkout_is_TC_identity_number_field_required` — TC zorunlu mu.
- `hezarfen_encryption_key_generated` — `yes` olunca constant yaratıldı kabul edilir.
- `hezarfen_encryption_key_recovery_log` — anahtar yenileme tarihleri (audit).

### Encryption
- Anahtar `wp-config.php` içinde **`HEZARFEN_ENCRYPTION_KEY`** sabiti olarak duracak; admin ayarlardan rastgele 64-byte (`openssl_random_pseudo_bytes`) anahtar üretip kopyalanması beklenir.
- Cipher: `AES-128-CBC`, random IV (16 byte/encrypt).
- Bütünlük: HMAC-SHA256 (ciphertext üzerinde), decrypt sırasında doğrulanır.
- OpenSSL extension yoksa: plaintext döner (degraded mode).
- Health check: `decrypt(known_ciphertext)` "Istanbul" döndüğünde key sağlam kabul edilir.

## Davranışlar

### Senaryo: Bireysel fatura, TC alanı zorunlu
- **Given** `hezarfen_show_hezarfen_checkout_tax_fields = yes`, `hezarfen_checkout_show_TC_identity_field = yes`, `hezarfen_checkout_is_TC_identity_number_field_required = yes`, encryption key sağlam
- **When** müşteri checkout'ta `invoice_type = person` seçer
- **Then** TC alanı görünür, vergi alanları gizlenir (JS)
- **And** TC boş bırakılırsa checkout reddedilir
- **And** TC numerik değilse veya 11 hane değilse "TC ID number is not valid" notice'i çıkar

### Senaryo: Kurumsal fatura
- **When** müşteri `invoice_type = company` seçer
- **Then** TC alanı gizlenir
- **And** `billing_company`, `billing_hez_tax_number`, `billing_hez_tax_office` görünür ve zorunludur
- **And** `billing_hez_tax_number` 10 veya 11 hane olmalı; aksi halde "Tax number is not valid"

### Senaryo: TC kaydederken şifreleme
- **Given** valid TC girildi, encryption key var
- **When** sipariş işlenir (`override_posted_data` hook'u)
- **Then** TC `PostMetaEncryption::encrypt()` ile şifrelenir
- **And** `_billing_hez_TC_number` meta'sı base64-encoded ciphertext olarak yazılır
- **Edge** OpenSSL yoksa plain text saklanır
- **Edge** Anahtar yoksa veya health check başarısızsa `"******"` placeholder yazılır

### Senaryo: Admin sipariş ekranında TC görme
- **Given** sipariş kayıtlı, TC şifreli
- **When** admin sipariş edit ekranını açar
- **Then** TC decrypt edilip "Customer billing details" altında görünür (`OrderDetails.php:76-90`)
- **Edge** Decrypt başarısızsa ciphertext / boş gösterilir; hata mesajı çıkartılmaz

### Senaryo: Admin siparişler listesinde fatura tipi kolonu
- **Given** modül aktif
- **Then** "Siparişler" tablosunda Total'dan sonra "Invoice Type" kolonu eklenir
- **And** her satır "Personal" / "Company" / "—" gösterir

### Senaryo: REST API çıktısı
- **Given** `WC_Order` REST endpoint'inden okunuyor
- **When** order `invoice_type = person`
- **Then** response meta_data'sında TC decrypted halde döner
- **And** Encryption şeffaftır, tüketici client'ın anahtara ihtiyacı yok

### Senaryo: Sözleşmelerde değişken kullanımı
- **Given** sözleşme şablonu `{{hezarfen_bireysel_tc}}` içeriyor
- **When** sipariş bireysel ve TC dolu
- **Then** değişken decrypt edilmiş TC ile değiştirilir
- **And** kurumsal siparişte `{{hezarfen_kurumsal_vergi_no}}` ve `{{hezarfen_kurumsal_vergi_daire}}` doldurulur
- (Bkz. `./sales-contract.md`)

### Senaryo: Encryption key kurtarma (recovery)
- **Given** site taşındı, `HEZARFEN_ENCRYPTION_KEY` `wp-config.php`'de eksik ama `hezarfen_encryption_key_generated = yes`
- **When** admin "Encryption Recovery" panelini açar
- **Then** yeni anahtar üretme seçeneği gösterilir
- **And** "Yeni anahtar üretirsen eski TC verileri okunamayacak" uyarısı çıkar
- **And** onay sonrası eylem `hezarfen_encryption_key_recovery_log` option'una zaman damgalı kaydedilir

## Edge Cases

- **Validation algoritması**: TC için sadece **uzunluk + numerik** kontrolü yapılır. **Resmi 11-haneli TC kontrol algoritması (mod 10 / 11)** uygulanmaz. Geliştirici notu: bu bilinen bir tasarım kararı; ürün ekibi kararıyla daha sıkı validation eklenebilir.
- **TC key rotation otomatik değil**: yeni anahtarla eski verinin migration'ı yapılmaz; bilinçli tasarım.
- **Vergi no encryption'sız**: KVKK perspektifinden vergi no kişisel veri sayılmadığı için düz saklanır.
- **TR olmayan ülkelerde fatura alanları**: master switch açıksa yine gösterilir; ürün ekibi isterse ülkeye göre koşullamalı.
- **Bireysel sipariş + sonradan kurumsal'a çevirme**: admin order edit'te tip değiştirilebilir; TC ve vergi meta'ları boşaltılmaz, sadece görünürlük değişir.
- **`"******"` placeholder**: encrypt başarısız siparişlerde gösterim sırasında bunu fark etmek için kullanılır; "decrypt failed" sinyali olarak değil olduğu gibi gösterilir.
- **Cartzilla teması**: alanlara Bootstrap class'ları enjekte edilir (`class-compatibility.php`).

## UI Lokasyonları

- **Frontend > Checkout** — billing bölümünde invoice_type select + koşullu TC/vergi alanları (priority 81-84).
- **Admin > Siparişler** — listede "Invoice Type" kolonu.
- **Admin > Sipariş edit** — billing fields altında vergi/TC; TC decrypted gösterimi.
- **Admin > WooCommerce > Ayarlar > Hezarfen**:
  - Genel sekmesi: master switch.
  - Checkout Tax Fields: TC göster/zorunlu.
  - Encryption: anahtar üretme & onay.
  - Encryption Recovery: anahtar eksikse yenileme.

## Hooks

Modül ağırlıklı olarak WC core hook'larına bağlanır; özel public action/filter dışa verilmez:
- `woocommerce_checkout_fields` (modify)
- `woocommerce_admin_billing_fields` (modify)
- `woocommerce_admin_order_data_after_billing_address` (render TC)
- `woocommerce_get_country_locale` (priority + label)
- `woocommerce_rest_prepare_shop_order_object` (REST decrypt)
- `override_posted_data` (encrypt on save)

## Sınama Notları

- TC validation: `12345678901`, `1234567890` (10 hane), `abcdefghijk` (numerik değil).
- Bireysel → kurumsal toggle'da JS koşullu görünürlüğü doğrula.
- Encryption health check: anahtarı silip checkout'ta TC alanının kaybolduğunu doğrula.
- OpenSSL kapatılmış PHP build'inde plaintext fallback gerçekten devreye giriyor mu (degraded mode).
- REST `GET /wc/v3/orders/{id}` ile TC'nin düz metin döndüğünü doğrula.
- Recovery flow: anahtarı `wp-config.php`'den sil, admin ayara gir, yeni anahtar üretilip eski TC'lerin `"******"` olduğunu gör.
- Sözleşme şablonunda `{{hezarfen_bireysel_tc}}` ve `{{hezarfen_kurumsal_vergi_no}}` değişimi.
- Audit log: `hezarfen_encryption_key_recovery_log` content'ini incele.
