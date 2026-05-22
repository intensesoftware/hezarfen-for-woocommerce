---
id: admin-menu
title: Admin Menü, Upgrade Akışı ve Bildirim Banner'ları
status: stable
since: 1.0.0
owner: hezarfen-core
entry_files:
  - includes/admin/class-admin-menu.php
  - includes/class-hezarfen.php
  - includes/class-feature-status.php
  - includes/admin/settings/class-hezarfen-settings-hezarfen.php
  - includes/admin/settings/class-hezarfen-roadmap-helper.php
depends_on: [woocommerce]
related: [install-migration]
---

## Amaç

Admin tarafındaki Hezarfen deneyimini bir araya getirir: üst seviye **"Hezarfen" menüsü**, **Pro upgrade** sayfası ve formu, WP admin'in dört bir yanına yayılan **review/roadmap/uyumluluk uyarı banner'ları**, ve plugin'in **gerçekten kullanılıp kullanılmadığını** ölçen feature-status cache'i.

## Kapsam

- Üst seviye admin menüsü: slug `hezarfen`, position 4, capability `manage_options`.
- Submenu'ler: "Ayarlar" (WC settings tab'ına yönlendirir), opsiyonel "Yükselt" (Pro yoksa).
- WC settings içinde "Hezarfen" tab'ı (`woocommerce_get_settings_pages`).
- Çoklu banner sistemi: review, roadmap voting, theme/host compat uyarıları, SMS migration notice, WC Blocks Checkout uyarısı.
- Feature-status: 24 saat transient ile aktif kullanım tespiti.

## Veri Modeli

### Options
- `hezarfen_roadmap_votes` — v3.0 oylama log'u.
- `hezarfen_pro_db_version` — Pro plugin yüklü/aktif bayrağı (Pro tarafından yazılır).
- `hezarfen_review_notice_*` — review banner state (impression sayacı, dismiss tarihi).
- `hezarfen_dismissed_notices` — dismiss edilmiş notice id'leri.

### Transients
- `hezarfen_benefit` — 24h, feature-status cache:
  - true → plugin "aktif kullanılıyor" (en az birinde: invoice meta, sözleşme satırı, SMS gönderim log'u).
  - false → varlık yok / pasif.

### Constants
- `HEZARFEN_FORCE_SHOW_UPGRADE` — dev override; Pro yüklü olsa bile upgrade UI'ı zorla gösterir.

## Menü Yapısı

| Slug | Capability | Açıklama |
|---|---|---|
| `hezarfen` (top) | `manage_options` | "Hezarfen" üst seviye menü, position 4 |
| `hezarfen` → `Ayarlar` | `manage_options` | `?page=wc-settings&tab=hezarfen`'e redirect |
| `hezarfen` → `Yükselt` | `manage_options` | Sadece Pro yüklü değilse; özel upgrade sayfası |

WC settings tab'ı: `?page=wc-settings&tab=hezarfen` — settings page sınıfı `class-hezarfen-settings-hezarfen.php` (~73 KB; tüm modüllerin ayar bölümleri burada).

## Davranışlar

### Senaryo: Admin sol menüde Hezarfen'i görür
- **Given** kullanıcı `manage_options` cap'ine sahip
- **When** WP admin yüklenir
- **Then** sol sidebar'da "Hezarfen" (position 4) ve altında "Ayarlar" alt menüsü görünür
- **And** Pro yüklü değilse altında "Yükselt" alt menüsü de görünür
- **Edge** `HEZARFEN_FORCE_SHOW_UPGRADE = true` ise Pro yüklü olsa bile "Yükselt" görünür

### Senaryo: WC settings içinde Hezarfen tab'ı
- **Given** admin "WooCommerce > Ayarlar"a girer
- **When** tab'lar render edilir
- **Then** "Hezarfen" tab'ı görünür
- **And** Tab içinde bölüm/section ayrıştırması var: Genel, Checkout Page Settings, Checkout Tax Fields, Encryption, Encryption Recovery, Contracts, SMS Settings, Shipment Tracking
- **And** Hezarfen üst menüsünden gelinmişse parent/submenu highlighting bozulmadan tutulur

### Senaryo: Upgrade sayfası render
- **Given** Pro yüklü değil, admin "Hezarfen > Yükselt" tıklar
- **When** upgrade sayfası açılır
- **Then** paket listesi + fiyatlandırma + talep formu render edilir
- **And** Form Pro paketleri için "Demand" submit eder
- **And** Talep `hezarfen_submit_demand` AJAX'ı ile `info@intense.com.tr`'ye mail olarak gönderilir
- **Edge** AJAX nonce eksikse veya capability düşükse 403 ile reddedilir

### Senaryo: Review banner ilk gösterim
- **Given** plugin 7+ gündür yüklü, `feature-status` aktif kullanım tespit etti, review daha önce dismiss edilmemiş
- **When** admin herhangi bir sayfaya girer
- **Then** "Hezarfen'i seviyor musunuz? Yorum yazın" banner'ı gösterilir
- **And** Impression sayacı +1
- **And** Maksimum 3 impression sonrası 30 gün suppression
- **Edge** "Daha sonra hatırlat" → 30 gün ertelenir; "Hayır teşekkürler" → kalıcı dismiss

### Senaryo: Roadmap voting banner (≤ v2.7.40)
- **Given** plugin sürümü 2.7.40 ve altında
- **When** admin dashboard'a girer
- **Then** v3.0 özellik oylaması banner'ı gösterilir
- **And** Free/Pro feature listesi `class-hezarfen-roadmap-helper.php`'den okunur
- **And** Kullanıcı oy verir → AJAX `hezarfen_roadmap_vote_*`
- **And** Oy `hezarfen_roadmap_votes` option'una yazılır + `info@intense.com.tr`'ye mail olarak iletilir
- **Edge** Üst sürümlerde banner gösterilmez

### Senaryo: WC Blocks Checkout uyarısı
- **Given** site Cart/Checkout block'larını default olarak kullanıyor
- **When** admin dashboard'a girer
- **Then** "Hezarfen şu anda Blocks Checkout ile uyumlu değil, classic shortcode'a geçin" notice'ı gösterilir
- **Edge** Plugin pasifleşmez; sadece uyarı

### Senaryo: Theme/hosting uyarı banner'ları
- **Given** aktif tema Woodmart veya host SiteGround/Cloudways
- **When** admin dashboard'a girer
- **Then** ilgili tema/hosting özel performans veya uyumluluk notice'ı gösterilir
- **Edge** Cartzilla için ek class enjekte edilir (uyarı yok, otomatik compat) — bkz. [checkout-customization.md](./checkout-customization.md).

### Senaryo: SMS migration tamamlandı bilgilendirmesi
- **Given** install-migration sırasında SMS legacy ayarları yeni formata çevrildi
- **When** admin sonraki sayfa yüklemesinde
- **Then** "SMS kuralları otomatik dönüştürüldü, kontrol edin" notice'ı gösterilir (transient bazlı)
- **And** Tek seferlik; dismiss veya transient bitiminde kaybolur

### Senaryo: Feature-status cache hit
- **Given** `hezarfen_benefit` transient son 24 saat içinde set edilmiş
- **When** review/roadmap banner karar verici çağrılır
- **Then** DB sorgusu yapılmaz, transient'ten okunur
- **And** Süre dolduğunda yeniden hesaplanır:
  1. Order meta `_billing_hez_invoice_type` var mı?
  2. `wp_hezarfen_contracts` tablosunda satır var mı?
  3. Order meta `_hezarfen_sms_sent_*` var mı?
  - Üçünden biri true → `true`.
- **Edge** HPOS açıkken meta sorguları `wp_wc_orders_meta` üzerinden koşar.

## Edge Cases

- **Capability**: Tüm Hezarfen admin sayfaları `manage_options` veya `manage_woocommerce` ister. "Shop Manager" rolü Hezarfen menüsünü göremez (tasarım gereği).
- **Menü position 4**: WP dashboard'unda erken pozisyon; başka plugin'ler de aynı slot'u isteyebilir → WP otomatik kaydırır.
- **Pro var mı testi**: `hezarfen_pro_db_version` option'ı veya `HEZARFEN_FORCE_SHOW_UPGRADE` constant'ına bakar. Pro plugin pasifleştirilmiş ama option silinmemişse upgrade UI **gizli kalır** (silinmeyen option Pro'nun bir kez yüklenmiş olduğunu gösterir).
- **Review banner impression count**: Sayaç option'ları silinirse banner baştan döner; KVKK için kişiselleştirilmiş veri tutmaz, sadece global sayaç.
- **Roadmap mail bağımlılığı**: `wp_mail` çalışmıyorsa oy yine option'a yazılır ama Intense'e ulaşmayabilir; client-side hata gösterilmez.
- **Feature-status false negative**: Yeni kurulumda hiç sipariş yokken aktif kullanılıyor gözükmez; banner'lar sessiz kalır — beklenen.

## UI Lokasyonları

- **Sol sidebar (admin)** — "Hezarfen" üst menü + submenuler.
- **WC > Ayarlar > Hezarfen** — tüm modül ayarları, section'lar halinde.
- **Dashboard** — review banner, roadmap banner, compat notice'ları.
- **Plugins listesi** — "Settings" action link (`hezarfen_add_settings_link`).
- **Hezarfen > Yükselt** — `class-admin-menu.php:990-1042` upgrade page; paket grid + demand form.

## Hooks

### Actions (consume)
- `admin_menu` → menü/submenu register.
- `admin_init` → privacy policy register (bkz. [privacy-policy.md](./privacy-policy.md)), notice state hesaplama.
- `admin_notices` → tüm banner'lar.
- `woocommerce_get_settings_pages` → WC settings tab register.
- `parent_file` / `submenu_file` → menu highlighting fix.

### AJAX (admin, capability check'li, nonce'lu)
- `hezarfen_submit_demand` — Pro paket talebi.
- `hezarfen_roadmap_vote_*` — v3.0 oylama.
- `hezarfen_dismiss_notice_*` — banner kapatma.

Modül kendi public action/filter expose etmez.

## Sınama Notları

- `manage_options` cap olmayan kullanıcıyla giriş: Hezarfen menüsünün hiç görünmediğini doğrula.
- Pro yüklü ortamda "Yükselt" submenu'nün ve upgrade banner'ının kaybolduğunu doğrula.
- `HEZARFEN_FORCE_SHOW_UPGRADE = true` ile Pro yüklü olsa bile UI'nın geri geldiğini doğrula.
- Review banner: 3 impression sonrası 30 gün boyunca tekrar gösterilmediğini doğrula.
- Roadmap mail: SMTP olmayan ortamda oyun yine de option'a yazıldığını doğrula.
- Feature-status: invoice meta'sı olan bir test siparişi oluştur, transient'i temizle, banner mantığının doğru tetiklendiğini doğrula.
- HPOS açık ortamda `hezarfen_benefit` hesaplaması: `wp_wc_orders_meta` üzerinden sorgu koştuğunu profiler/query log ile doğrula.
- WC settings → Hezarfen tab → section'lar arası geçişte üst menü highlighting'in "Hezarfen"de kaldığını doğrula.
