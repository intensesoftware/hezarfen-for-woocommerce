---
id: sales-contract
title: Mesafeli Satış Sözleşmesi & Ön Bilgilendirme Formu (MSS/ÖBF)
status: stable
since: 2.0.0
owner: hezarfen-core
entry_files:
  - includes/contracts/class-contracts-integration.php
  - includes/contracts/core/class-contract-renderer.php
  - includes/contracts/core/class-template-processor.php
  - includes/contracts/core/class-contract-validator.php
  - includes/contracts/core/class-post-order-processor.php
  - includes/contracts/admin/class-contracts-settings.php
  - includes/contracts/admin/class-order-agreements.php
  - includes/contracts/frontend/class-customer-agreements.php
  - includes/Hezarfen_Install.php
depends_on: [woocommerce]
related: [invoice-fields, my-account, checkout-customization]
---

## Amaç

Checkout sayfasında Türkçe **Mesafeli Satış Sözleşmesi (MSS)** ve **Ön Bilgilendirme Formu (ÖBF)** ile sınırlı kalmadan, admin'in tanımlayabileceği **sınırsız sayıda** sözleşmeyi WordPress sayfaları üzerinden şablonlamak, müşteri kabulünü IP + user-agent ile birlikte kayıt altına almak ve KVKK uyumlu saklamak.

## Kapsam

- WP **sayfaları** (`post_type=page`) şablon kaynağı olarak kullanılır; ayrı bir custom post type yok.
- Sözleşmeler `wp_hezarfen_contracts` özel tablosunda saklanır (`Hezarfen_Install.php:156-183`).
- 39+ dinamik değişken `{{...}}` ile şablona enjekte edilir.
- Checkout'ta **inline** veya **modal** olarak gösterilir.
- Sipariş tamamlandıktan sonra (timing'e göre `new_order` veya `processing`) sözleşme HTML'i müşteriye e-postada gönderilir, admin'e bilgi maili atılabilir.
- "Hesabım > Sipariş Detayı" ve admin sipariş ekranında sözleşmeler tab-modal ile gösterilir.

## Veri Modeli

### Custom Tablo: `wp_hezarfen_contracts`

```sql
id              BIGINT UNSIGNED AUTO_INCREMENT
order_id        BIGINT UNSIGNED NOT NULL          -- KEY
contract_name   VARCHAR(255) NOT NULL
contract_content LONGTEXT NOT NULL                -- değişkenleri işlenmiş HTML
ip_address      VARCHAR(45) NOT NULL              -- IPv4/IPv6
user_agent      VARCHAR(500)
created_at      DATETIME DEFAULT CURRENT_TIMESTAMP -- KEY
updated_at      DATETIME ON UPDATE CURRENT_TIMESTAMP
```

### Options

- `hezarfen_contracts_enabled` — `yes|no`, modül master switch.
- `hezarfen_mss_settings` — array:
  - `contracts` — sözleşme tanımları listesi `[ {id, name, page_id, enabled, show_in_checkbox} ]`
  - `agreement_creation_timing` — `processing` (default) | `new_order`
  - `odeme_sayfasinda_sozlesme_gosterim_tipi` — `inline` (default) | `modal`
  - `yonetici_sozlesme_saklama_eposta_adresi` — admin bilgi maili adresi (opsiyonel)
- `hezarfen_db_version` — şema migration takibi.

### Order Meta

- `_in_mss_eposta_gonderildi_mi` — `0|1`, müşteri e-postasında sözleşme ekinin tekrar dahil edilmesini engellemek için.

## Şablon Değişkenleri (özet)

Tam liste için `includes/contracts/core/class-template-processor.php:104-119` ve `class-contracts-settings.php:491-553`.

- **Sipariş**: `{{siparis_no}}`, `{{siparis_tarihi}}`, `{{siparis_saati}}`, `{{toplam_tutar}}`, `{{ara_toplam}}`, `{{toplam_vergi_tutar}}`, `{{kargo_ucreti}}`, `{{urunler}}` (HTML table), `{{odeme_yontemi}}`, `{{indirim_toplami}}`
- **Fatura adresi**: `{{fatura_adi}}`, `{{fatura_soyadi}}`, `{{fatura_sirket}}`, `{{fatura_adres_1|2}}`, `{{fatura_ilce}}`, `{{fatura_sehir}}`, `{{fatura_posta_kodu}}`, `{{fatura_ulke}}`, `{{fatura_telefon}}`, `{{fatura_eposta}}`
- **Teslimat adresi**: `{{teslimat_*}}` (fatura ile aynı alanlar; "farklı adrese gönder" işaretliyse, değilse fatura'ya fallback)
- **Site**: `{{site_adi}}`, `{{site_url}}`
- **Tarih**: `{{bugunun_tarihi}}`, `{{su_an}}` (checkout'ta placeholder; sipariş sonrası gerçek değer)
- **Hezarfen fatura**: `{{hezarfen_kurumsal_vergi_daire}}`, `{{hezarfen_kurumsal_vergi_no}}` (sadece kurumsal), `{{hezarfen_bireysel_tc}}` (sadece bireysel; `PostMetaEncryption` ile decrypt) — bkz. `./invoice-fields.md`

## Davranışlar

### Senaryo: Checkout'ta sözleşme gösterimi (inline)
- **Given** `hezarfen_contracts_enabled = yes` ve gösterim tipi `inline`
- **When** müşteri checkout sayfasını açar
- **Then** her aktif sözleşme `woocommerce_checkout_before_terms_and_conditions` hook'unda HTML olarak inline render edilir
- **And** her sözleşme için kabul checkbox'ı `woocommerce_checkout_after_terms_and_conditions` hook'unda gösterilir

### Senaryo: Modal gösterim
- **Given** gösterim tipi `modal`
- **When** müşteri checkout'a girer
- **Then** sözleşmeler link olarak gösterilir, tıklayınca popup modal açılır

### Senaryo: Onay zorunlu, kabul edilmedi
- **Given** sözleşmenin `show_in_checkbox = true`
- **When** müşteri kabul kutusunu işaretlemeden "Sipariş ver" tıklar
- **Then** `Contract_Validator::validate_checkout_contracts()` (`woocommerce_checkout_process` hook) hata fırlatır
- **And** WC notice "X sözleşmesini kabul etmelisiniz" gösterilir
- **And** sipariş oluşturulmaz

### Senaryo: Çoklu sözleşme tek checkbox ile
- **Given** birden fazla zorunlu sözleşme var, settings'te tek combined checkbox seçili
- **When** müşteri tek bir kutuyu işaretler
- **Then** `contract_combined_checkbox` üzerinden hepsi onaylanmış sayılır

### Senaryo: Sipariş işlendiğinde sözleşmenin kalıcılaşması
- **Given** `agreement_creation_timing = processing` (varsayılan)
- **When** sipariş `processing` durumuna geçer (`woocommerce_order_status_processing`)
- **Then** her aktif sözleşme için değişkenler işlenir
- **And** sonuç HTML'i `wp_hezarfen_contracts` tablosuna `order_id, contract_name, contract_content, ip_address, user_agent, created_at` ile yazılır
- **And** müşteriye `processing` e-postasında sözleşmeler eklenir

### Senaryo: Sipariş onayı (new_order) zamanlamasıyla kalıcılaşma
- **Given** `agreement_creation_timing = new_order`
- **When** `woocommerce_checkout_order_processed` tetiklenir
- **Then** sözleşmeler aynı şekilde tabloya yazılır
- **And** "New Order" e-postasına sözleşmeler eklenir
- **Edge** ödeme başarısızsa (siparişe `failed` denir) yine yazılır — admin kontrol etmelidir

### Senaryo: AJAX güncellemesi sırasında değişken yenileme
- **Given** müşteri checkout formunda fatura adresi veya ödeme yöntemini değiştirir
- **When** `woocommerce_update_order_review` AJAX'ı tetiklenir
- **Then** `get_contract_fragments` ile inline sözleşme içerikleri yeni cart/billing değişkenleriyle yeniden render edilir

### Senaryo: Hesabım > Sipariş Detayı
- **Given** sipariş tamamlanmış ve sözleşmeleri saklanmış
- **When** müşteri "Hesabım > Sipariş Detayı" sayfasını açar
- **Then** "Sözleşmeleri Görüntüle" butonu sözleşme adı, kabul tarihi, IP ile birlikte modal tab içinde listeler

### Senaryo: Admin sipariş ekranı meta box
- **Given** admin sipariş edit ekranında
- **When** "Hezarfen Sözleşmeleri" meta box render edilir
- **Then** tab başlıklı modal sözleşme adı + tam HTML + kabul zamanı + IP'yi gösterir
- **Edge** tablo henüz oluşmamışsa "Henüz sözleşme yok" mesajı gösterilir

### Senaryo: Dummy sipariş (preview) sözleşme yazmaz
- **Given** WC preview order (id=12345 veya transaction_id=999999999)
- **When** mail tetikleyici çağrılır
- **Then** sözleşme dahil edilmez (`Post_Order_Processor::include_contracts_in_email:104-107`)

### Senaryo: Bireysel/Kurumsal koşullu blok (tanımlı, henüz aktif değil)
- **Status**: Pattern'ler `Post_Order_Processor.php:20-21` içinde tanımlı (`@IF_HEZARFEN_FAT_BIREYSEL ... @END_HEZARFEN_FAT_BIREYSEL`, `@IF_HEZARFEN_FAT_KURUMSAL ...`) ama mevcut sürümde **regex işleme bağlanmamış**.
- **How to apply**: Yeni özellik geliştirilirken bu bloğu aktif etmeden önce `Template_Processor::process_variables`'da regex eval'ı eklenmeli.

## Edge Cases

- **PDF üretimi yok** — sözleşmeler sadece HTML olarak saklanır/iletilir. (Composer'da TCPDF yüklü ama bu modülde kullanılmıyor.)
- **KVKK saklama**: otomatik temizleme yok. IP + UA + içerik süresiz kalır; manuel silme admin sorumluluğunda. Türk e-ticaret regülasyonu için 7 yıllık saklama beklentisini karşılar.
- **Türkçe gramer eki**: `get_ek()` (`class-contract-renderer.php:262-285`) — `tr_*` locale'inde sözleşme adına -i/-yi accusative eki ekler. Diğer locale'lerde devre dışı.
- **Blocks Checkout (WC Blocks)**: desteklenmiyor; ana plugin `class-hezarfen.php:148-161`'de admin'i uyarır.
- **WordPress sayfası publish değilse**: dropdown'da görünmez, şablon olarak seçilemez.
- **wpautop()** içerik üzerinde uygulanır; Gutenberg block içerikleri ve shortcode'lar desteklenir.

## UI Lokasyonları

- **Admin > WooCommerce > Ayarlar > Hezarfen > Sözleşmeler** — `?page=wc-settings&tab=hezarfen&section=contracts_settings`
- **Admin > Sipariş edit** — "Hezarfen Sözleşmeleri" meta box (normal priority)
- **Frontend > Checkout** — terms section öncesi inline veya modal trigger
- **Frontend > Thank You sayfası** — sözleşme listesi + view modal
- **Frontend > Hesabım > Sipariş Detayı** — sözleşme listesi + view modal
- **E-posta** — `woocommerce_email_customer_details` üzerinden HTML body içine eklenir

## Hooks

### Filters
- `hezarfen_contracts_include_item_meta` — sözleşme ürün tablosunda item meta dahil edilsin mi. Params: `(bool $should_include, string $key, array $meta_data, WC_Order_Item $item)`. Default `true`.
- `hezarfen_mss_include_agreements_in_customer_email` — müşteri mailine sözleşmeleri dahil et/etme. Params: yok, return `bool`. Default `true`.

### Bağlandığı WP/WC Hooks (modify only)
- `woocommerce_checkout_before_terms_and_conditions` (render)
- `woocommerce_checkout_after_terms_and_conditions` (checkbox)
- `woocommerce_checkout_process` (validate)
- `woocommerce_checkout_order_processed` (save — timing=new_order)
- `woocommerce_order_status_processing` (save — timing=processing)
- `woocommerce_email_customer_details` (mail ek)
- `woocommerce_update_order_review_fragments` (AJAX refresh)

## Sınama Notları

- Yeni kurulumda `wp_hezarfen_contracts` tablosunun activation hook'unda oluştuğunu doğrula.
- WP page'i taslakta bırakıp dropdown'da görünmediğini doğrula.
- Checkbox işaretlenmediğinde checkout'un blok olduğunu doğrula.
- `processing` ve `new_order` timing'lerinin ikisi için tabloya yazma testi.
- `{{hezarfen_bireysel_tc}}` ile bireysel + TC dolu sipariş; KVKK için decryption gözle.
- WC Blocks Checkout aktifken admin notice çıktığını doğrula.
- Inline → modal switch'inde frontend render'ın değiştiğini doğrula.
- AJAX refresh'te değişkenlerin gerçekten güncellendiğini DOM'da doğrula.
