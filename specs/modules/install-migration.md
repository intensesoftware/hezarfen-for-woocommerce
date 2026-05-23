---
id: install-migration
title: Kurulum, Aktivasyon ve Sürüm Migrasyonları
status: stable
since: 1.0.0
owner: hezarfen-core
entry_files:
  - hezarfen-for-woocommerce.php
  - includes/Hezarfen_Install.php
  - includes/class-hezarfen.php
depends_on: [woocommerce]
related: [sales-contract, sms-automation, invoice-fields]
---

## Amaç

Plugin'in aktivasyon ve `init` aşamasında çalışan kurulum işleri: gerekli DB tablolarının oluşturulması, legacy ayarların yeni şemaya migrate edilmesi, sürüm bayrağının ileri taşınması, WC sürüm uyumluluğunun zorlanması, HPOS uyumluluğunun WC'ye deklare edilmesi.

## Kapsam

- Plugin entry: `hezarfen-for-woocommerce.php`.
- WC aktif değilse veya minimum sürüm sağlanmıyorsa **plugin yüklenmez** (`plugins_loaded:8` öncesi guard).
- Aktivasyon hook'u + `init` hook'unun **ikisinde** de `Hezarfen_Install::install()` çağrılır. Bu sayede plugin upload edilip activate edilmeden update edilen kurulumlarda da migration koşulur.
- HPOS uyumluluğu `before_woocommerce_init` üzerinden WC'ye deklare edilir.

## Sürüm Sabitleri

| Sabit | Değer | Yer | Amaç |
|---|---|---|---|
| `WC_HEZARFEN_VERSION` | `2.11.4` | `hezarfen-for-woocommerce.php` | Plugin sürümü |
| `WC_HEZARFEN_MIN_WC_VERSION` | `6.9.0` | aynı | Min WC; düşükse admin notice + abort |
| `WC_HEZARFEN_MIN_MBGB_VERSION` | `0.6.1` | aynı | MBGB addon min sürüm |
| `WC_HEZARFEN_HPOS_ENABLED` | bool | runtime, `init` | WC HPOS açık mı |

## Veri Modeli

### Options
- `hezarfen_version` — yüklü plugin sürümü.
- `hezarfen_db_version` — DB şema sürümü (migration kuyruğu kontrolü için).
- `hezarfen_sms_migration_completed` — 2.5.0 SMS migration bayrağı.
- `hezarfen_pro_db_version` — Pro plugin yüklü olduğunda Pro yazar (tespit için kullanılır).
- Migration sırasında dolan modül-spesifik option'lar: `hezarfen_sms_rules`, `hezarfen_sms_automation_enabled`, `hezarfen_mss_settings`.

### Custom Tablo
- `wp_hezarfen_contracts` — bkz. [sales-contract.md](./sales-contract.md). v2.5.0+ kurulumlarında `dbDelta` ile oluşturulur.

## Plugin Boot Sırası

`hezarfen-for-woocommerce.php`:

1. `ABSPATH` guard.
2. `active_plugins` içinde `woocommerce/woocommerce.php` var mı? Yoksa **silent return**.
3. Sabitler tanımlanır.
4. `plugins_loaded` priority **8** ile `hezarfen_init_plugin()` register edilir.
5. `before_woocommerce_init` ile HPOS uyumluluğu deklare edilir (`custom_order_tables`).
6. Plugin actions linklerine "Settings" linki eklenir.

`hezarfen_init_plugin()`:
1. `WC()` fonksiyonu var mı?
2. WC sürümü `>= 6.9.0` mı? Değilse admin notice + abort.
3. Textdomain yükle (`languages/`).
4. `class-privacy-policy.php` require (bkz. [privacy-policy.md](./privacy-policy.md)).
5. Composer autoload (varsa).
6. `includes/Autoload.php` require — modüller buradan boot olur.

## Davranışlar

### Senaryo: Temiz kurulum (ilk aktivasyon)
- **Given** plugin ilk kez aktive ediliyor, `hezarfen_version` option yok
- **When** `register_activation_hook` veya `init` hook'u `install()` çağırır
- **Then** version compare `'< current_version'` true döner
- **And** `hezarfen_version` ve `hezarfen_db_version` güncel sürüme set edilir
- **And** SMS migration koşulur (boş, no-op)
- **And** `wp_hezarfen_contracts` tablosu `dbDelta` ile oluşturulur (v2.5.0+ koşulu sağlanıyor)
- **And** default option'lar yazılmaz — özellik bazlı lazy creation tercih edilir

### Senaryo: WC yüklü değil
- **Given** WooCommerce aktif değil
- **When** Hezarfen aktive edilmeye çalışılır
- **Then** plugin entry dosyası `return` ile sessiz çıkar
- **And** Hiçbir hook register edilmez, hiçbir sabit tanımlanmaz

### Senaryo: WC sürümü düşük
- **Given** WC `< 6.9.0`
- **When** `hezarfen_init_plugin` çalışır
- **Then** plugin ileri yüklenmez (Autoload require edilmez)
- **And** `admin_notices`'a uyarı eklenir: "Hezarfen requires WC X+, you have Y"
- **Edge** Eklenti listesinde aktif görünür ama hiçbir özelliği çalışmaz

### Senaryo: 2.5.0'a yükseltme — SMS migration
- **Given** kullanıcı önceki sürümlerde `hezarfen_mst_enable_sms_notification = yes` + NetGSM/PandaSMS resmi eklenti kurulu
- **When** plugin 2.5.0+ sürüme yükseltilir ve `init`'te `install()` koşar
- **Then** `hezarfen_sms_migration_completed = true` değilse legacy ayarlar okunur
- **And** Provider tespitine göre `hezarfen_sms_rules` array'ine `netgsm_legacy` veya `pandasms_legacy` action_type'lı kural eklenir
- **And** `hezarfen_sms_automation_enabled = yes` yazılır
- **And** Admin'e geçici notice gösterilir
- **And** `hezarfen_sms_migration_completed = true` bayrağı atılır — bir daha çalışmaz
- **Edge** Legacy ayar bulunamazsa migration sessiz tamamlanır

### Senaryo: 2.5.0'a yükseltme — sözleşme tablosu
- **Given** önceki sürümden gelen kurulumda `wp_hezarfen_contracts` tablosu yok
- **When** `install()` koşar
- **Then** `setup_mss_database()` `dbDelta` ile tabloyu oluşturur
- **And** `hezarfen_db_version` güncellenir

### Senaryo: Sürüm bayrağı zaten güncel
- **Given** `hezarfen_version >= current_version`
- **When** `install()` her `init`'te tekrar koşar
- **Then** version compare guard'ı `false` döner
- **And** Hiçbir migration tekrar koşmaz (idempotent)

### Senaryo: HPOS deklarasyonu
- **Given** WC 8.0+ HPOS özelliği mevcut
- **When** `before_woocommerce_init` tetiklenir
- **Then** `FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true)` çağrılır
- **And** WC settings'te plugin "HPOS compatible" işaretlenir
- **Edge** WC < 8.0'de `FeaturesUtil` class'ı yoksa guard ile atlanır

### Senaryo: Pro upgrade tespiti
- **Given** Hezarfen Pro yüklü ve aktif
- **When** `is_pro_installed()` çağrılır (`class-admin-menu.php:49`)
- **Then** `hezarfen_pro_db_version` option dolu olduğu için `true` döner
- **And** "Yükselt" submenu'sü gizlenir, upgrade banner'lar suppress edilir
- **Edge** `HEZARFEN_FORCE_SHOW_UPGRADE` constant'ı `true` ise Pro yüklü olsa bile upgrade UI gösterilir (dev debug)

## Edge Cases

- **Composer `vendor/autoload.php` yok**: Plugin yüklenir ama TCPDF gibi composer-only bağımlılıklar çalışmaz. Şu an MSS modülü PDF üretmediği için pratikte etkisiz.
- **Multisite**: Activation hook her site için ayrı koşmaz; network activate edilirse her site `init`'te bireysel migrate olur (idempotent guard yeterli).
- **WP cron disabled**: Bu modülde cron'a bağımlılık yok; migration her request'in `init`'inde idempotent çalışır.
- **Migration yarıda kalırsa**: Sürüm bayrağı yazılmadığı için sonraki request'te baştan koşar. Tablonun parça parça oluşması `dbDelta`'nın doğası gereği güvenlidir.
- **Plugin downgrade**: Yeni şemada yazılmış veri eski plugin sürümünde fonksiyonel olmayabilir; resmi olarak desteklenmez. `hezarfen_db_version` geri sarılmaz.

## UI Lokasyonları

Doğrudan UI yoktur. Etkileri:
- **Plugins listesi** — "Settings" action link.
- **WC > Settings > Advanced > Features** — HPOS uyumluluk işareti.
- **Admin notices** — WC sürümü düşükse uyarı, SMS migration sonrası bilgi banner'ı.

## Hooks

Bu modülün dışa açtığı public hook **yok**. Bağlandıkları:
- `register_activation_hook` → `install()`
- `init` → `install()` (idempotent guard ile)
- `plugins_loaded` (priority 8) → `hezarfen_init_plugin`
- `before_woocommerce_init` → HPOS compat declare
- `plugin_action_links_*` → Settings linki
- `admin_notices` → WC sürüm uyarısı, migration notice'ları

## Sınama Notları

- Temiz sandbox'ta plugin'i aktive et: `hezarfen_version`, `hezarfen_db_version` ve `wp_hezarfen_contracts` tablosunun oluştuğunu doğrula.
- WC pasif iken aktive et: hiçbir hook register edilmediğini doğrula (hata fırlamamalı).
- WC 6.8 ile aktive et: admin notice çıkmalı, plugin feature'ları çalışmamalı.
- Önceki sürüm dump'ından restore edilmiş site (`hezarfen_mst_enable_sms_notification = yes`): bir kez sayfa ziyaretinden sonra `hezarfen_sms_rules` dolmuş ve `hezarfen_sms_migration_completed = true` olmalı.
- Aynı request'te `install()` iki kez koşturulduğunda sadece bir kez DB yazımı gerçekleştiğini doğrula (idempotency).
- HPOS açık WC'de Plugin'in "HPOS compatible" işaretlendiğini doğrula.
- `HEZARFEN_FORCE_SHOW_UPGRADE = true` set edilince Pro yüklü olsa bile upgrade UI'nin geri geldiğini doğrula.
