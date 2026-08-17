# Data Model

Plugin'in yazdığı/okuduğu tüm option key'leri, order meta'ları ve özel tabloların referansı. Yeni bir özellik eklerken **buraya işle**.

---

## wp_options

### Master / feature toggles

| Option | Tip | Modül | Açıklama |
|---|---|---|---|
| `hezarfen_version` | string | core | Yüklü plugin sürümü (install/migration takibi) |
| `hezarfen_db_version` | string | core | Şema migration sürümü |
| `hezarfen_sms_migration_completed` | bool | sms | Legacy SMS setting'lerinden geçişin tamamlandığı bayrağı |
| `hezarfen_show_hezarfen_checkout_tax_fields` | yes/no | invoice-fields | Fatura alanlarını çekirdek aç-kapa |
| `hezarfen_contracts_enabled` | yes/no | sales-contract | Sözleşme modülü master switch |
| `hezarfen_sms_automation_enabled` | yes/no | sms-automation | SMS modülü master switch |
| `hezarfen_enable_shipment_tracking` | yes/no | shipment-tracking | Kargo takip master switch |

### Checkout

| Option | Tip | Modül | Açıklama |
|---|---|---|---|
| `hezarfen_enable_district_neighborhood_fields` | yes/no | neighborhood | İlçe/mahalle alanlarını aktif et |
| `hezarfen_hide_checkout_postcode_fields` | yes/no | checkout | Posta kodunu gizle |
| `hezarfen_checkout_fields_auto_sort` | yes/no | checkout | TR sırasına diz |
| `hezarfen_checkout_show_TC_identity_field` | yes/no | invoice-fields | TC alanını göster |
| `hezarfen_checkout_is_TC_identity_number_field_required` | yes/no | invoice-fields | TC zorunlu |

### My Account

| Option | Tip | Modül | Açıklama |
|---|---|---|---|
| `hezarfen_sort_my_account_fields` | yes/no | my-account | Adres düzenle'de sıralama |
| `hezarfen_hide_my_account_postcode_fields` | yes/no | my-account | Posta kodunu Hesabım'da gizle |

### Encryption (TC)

| Option | Tip | Modül | Açıklama |
|---|---|---|---|
| `hezarfen_encryption_key_generated` | yes/no | invoice-fields | Anahtar üretildi bayrağı |
| `hezarfen_encryption_key_recovery_log` | array | invoice-fields | Anahtar yenileme audit log |

### SMS

| Option | Tip | Modül | Açıklama |
|---|---|---|---|
| `hezarfen_sms_rules` | array | sms-automation | Kural listesi |
| `hezarfen_global_netgsm_credentials` | array | sms-automation | `{ username, password, msgheader }` |

### Sözleşme

| Option | Tip | Modül | Açıklama |
|---|---|---|---|
| `hezarfen_mss_settings` | array | sales-contract | Sözleşmeler listesi + zamanlama + gösterim |

### Shipment Tracking

| Option | Tip | Modül | Açıklama |
|---|---|---|---|
| `hezarfen_mst_default_courier_company` | string | shipment-tracking | Ön seçili kargo firması slug'ı |
| `hezarfen_mst_show_shipment_tracking_column` | yes/no | shipment-tracking, my-account | Müşteri sipariş tablosu kolonu |
| `hezarfen_mst_enable_sms_notification` | yes/no | shipment-tracking (legacy) | Kargo SMS'i (legacy; modern akış SMS modülü) |
| `hezarfen_mst_notification_provider` | netgsm\|pandasms | shipment-tracking | Legacy SMS provider seçimi |
| `hezarfen_mst_courier_company_custom_meta` | string | shipment-tracking | "Custom" courier başlık meta key'i |
| `hezarfen_mst_tracking_num_custom_meta` | string | shipment-tracking | Üçüncü-parti takip no meta key'i |
| `hezarfen_mst_disabled_couriers` | array | shipment-tracking | Listeden gizlenecek courier id'ler |

### İade Yönetimi (returns)

| Option | Tip | Modül | Açıklama |
|---|---|---|---|
| `hezarfen_returns_enabled` | yes/no | returns | İade modülü master switch (varsayılan `no`) |
| `hezarfen_returns_db_version` | string | returns | İade tablolarının şema sürümü (plugin sürümünden bağımsız) |
| `hezarfen_returns_window_days` | int | returns | Global iade süresi; `0` = sınırsız |
| `hezarfen_returns_window_reference` | completed\|paid\|created | returns | Sürenin sayılacağı sipariş tarihi |
| `hezarfen_returns_eligible_order_statuses` | array | returns | İade açılabilecek sipariş durumları (`wc-` önekli saklanır) |
| `hezarfen_returns_guest_enabled` | yes/no | returns | Üyeliksiz iade talebi |
| `hezarfen_returns_shipping_method` | string | returns | Aktif iade gönderim yöntemi anahtarı |
| `hezarfen_returns_instructions` | string | returns | Onay sonrası müşteriye gösterilen yönerge |
| `hezarfen_returns_page_id` | int | returns | `[hezarfen_iade]` kısa kodunu barındıran sayfanın ID'si |
| `hezarfen_returns_endpoints_version` | string | returns | Endpoint imzası; değişince rewrite flush tetiklenir |
| `hezarfen_returns_address_label` | string | returns | İade adresi başlığı |
| `hezarfen_returns_address_contact` | string | returns | İade adresi yetkili/firma adı |
| `hezarfen_returns_address_phone` | string | returns | İade adresi telefonu |
| `hezarfen_returns_address_line` | string | returns | İade adresi açık adres |
| `hezarfen_returns_address_neighborhood` | string | returns | İade adresi mahalle |
| `hezarfen_returns_address_district` | string | returns | İade adresi ilçe |
| `hezarfen_returns_address_city` | string | returns | İade adresi il |
| `hezarfen_returns_address_postcode` | string | returns | İade adresi posta kodu |

### Notice & feedback state

| Option | Tip | Modül | Açıklama |
|---|---|---|---|
| `hezarfen_roadmap_votes` | array | core | v3.0 roadmap oy log'u |
| `hezarfen_pro_db_version` | string | core | Pro plugin yüklü/aktif bayrağı |

### Transients

| Transient | Süre | Modül | Açıklama |
|---|---|---|---|
| `hezarfen_benefit` | 24h | feature-status | "Eklenti aktif olarak kullanılıyor mu" cache'i |
| HepsiJET barcode bulk cache | per request | shipment-tracking | Toplu barkod üretimi geçici cache |

---

## Order Meta

### Fatura (invoice-fields)

| Meta key | Tip | Şifreli | Açıklama |
|---|---|---|---|
| `_billing_hez_invoice_type` | string | hayır | `person` \| `company` \| `""` |
| `_billing_hez_TC_number` | string | **evet (AES-128-CBC + HMAC)** | TC kimlik no, fallback `"******"` |
| `_billing_hez_tax_number` | string | hayır | Vergi numarası |
| `_billing_hez_tax_office` | string | hayır | Vergi dairesi |

### Kargo (shipment-tracking)

| Meta key | Tip | Açıklama |
|---|---|---|
| `_hezarfen_mst_shipment_data` | string (çoklu) | Pipe-delimited: `id\|\|order_id\|\|courier_id\|\|courier_title\|\|tracking_num\|\|tracking_url\|\|sms_sent` |
| `_hezarfen_hepsijet_shipment_{delivery_no}` | string | HepsiJET API yanıtı cache'i |
| `_hezarfen_hepsijet_return_barcode_no` | string | İade barkodu |
| `_hezarfen_hepsijet_return_barcode_print_date` | string | İade barkodu yazdırma tarihi |
| `_hezarfen_hepsijet_return_zpl_barcode` | string | İade barkod ZPL içeriği |

### Sözleşme (sales-contract)

| Meta key | Tip | Açıklama |
|---|---|---|
| `_in_mss_eposta_gonderildi_mi` | 0\|1 | Müşteri e-postasına sözleşme eklendi mi (tekrar gönderim koruması) |

### SMS log (sms-automation)

| Meta key | Tip | Açıklama |
|---|---|---|
| `_hezarfen_sms_sent_{status}` | yes | Bu status için SMS gönderildi |
| `_hezarfen_sms_sent_time_{status}` | int | Unix timestamp |
| `_hezarfen_sms_jobid_{status}` | string | NetGSM job id |
| `_hezarfen_sms_log_*` | array | Full log entry |

---

## Custom Tablolar

### `wp_hezarfen_contracts`

Modül: sales-contract. `Hezarfen_Install.php:156-183` içinde `dbDelta` ile oluşturulur.

```sql
CREATE TABLE wp_hezarfen_contracts (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id        BIGINT UNSIGNED NOT NULL,
  contract_name   VARCHAR(255) NOT NULL,
  contract_content LONGTEXT NOT NULL,
  ip_address      VARCHAR(45) NOT NULL,
  user_agent      VARCHAR(500) DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                  ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY order_id    (order_id),
  KEY created_at  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Yaşam döngüsü**: Sipariş işlendiğinde insert; KVKK uyumu için manuel silme dışında otomatik purge yok. Müşteri ve admin görüntüler.

### `wp_hezarfen_returns` / `wp_hezarfen_return_items` / `wp_hezarfen_return_events`

Modül: returns. `includes/returns/core/class-returns-schema.php` içinde `dbDelta` ile oluşturulur; `hezarfen_returns_db_version` option'ı ile ayrı sürümlenir.

```sql
CREATE TABLE wp_hezarfen_returns (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  return_number     VARCHAR(32)  NOT NULL DEFAULT '',
  order_id          BIGINT UNSIGNED NOT NULL,
  customer_id       BIGINT UNSIGNED NOT NULL DEFAULT 0,
  customer_email    VARCHAR(190) NOT NULL DEFAULT '',
  status            VARCHAR(32)  NOT NULL DEFAULT 'pending',
  shipping_method   VARCHAR(32)  NOT NULL DEFAULT '',
  courier           VARCHAR(64)  NOT NULL DEFAULT '',
  tracking_number   VARCHAR(100) NOT NULL DEFAULT '',
  return_address_id VARCHAR(64)  NOT NULL DEFAULT '',
  customer_note     TEXT NULL,
  refund_amount     DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
  currency          VARCHAR(10)  NOT NULL DEFAULT '',
  access_token      VARCHAR(64)  NOT NULL DEFAULT '',
  created_via       VARCHAR(32)  NOT NULL DEFAULT 'account',
  ip_address        VARCHAR(45)  NOT NULL DEFAULT '',
  created_at        DATETIME NOT NULL,
  updated_at        DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY order_id (order_id), KEY customer_id (customer_id),
  KEY status (status), KEY created_at (created_at),
  KEY customer_email (customer_email), KEY return_number (return_number)
);

CREATE TABLE wp_hezarfen_return_items (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  return_id     BIGINT UNSIGNED NOT NULL,
  order_item_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  product_id    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  variation_id  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  product_name  VARCHAR(255) NOT NULL DEFAULT '',
  sku           VARCHAR(100) NOT NULL DEFAULT '',
  quantity      INT(11) NOT NULL DEFAULT 0,
  line_total    DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
  reason_key    VARCHAR(64) NOT NULL DEFAULT '',
  reason_note   TEXT NULL,
  PRIMARY KEY (id), KEY return_id (return_id), KEY order_item_id (order_item_id)
);

CREATE TABLE wp_hezarfen_return_events (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  return_id           BIGINT UNSIGNED NOT NULL,
  type                VARCHAR(32) NOT NULL DEFAULT 'note',
  actor_type          VARCHAR(20) NOT NULL DEFAULT 'system',
  actor_id            BIGINT UNSIGNED NOT NULL DEFAULT 0,
  actor_name          VARCHAR(190) NOT NULL DEFAULT '',
  from_status         VARCHAR(32) NOT NULL DEFAULT '',
  to_status           VARCHAR(32) NOT NULL DEFAULT '',
  message             TEXT NULL,
  is_customer_visible TINYINT(1) NOT NULL DEFAULT 1,
  created_at          DATETIME NOT NULL,
  PRIMARY KEY (id), KEY return_id (return_id), KEY created_at (created_at)
);
```

**Yaşam döngüsü**: Müşteri talep açtığında insert; satırlar değişmez, timeline append-only. Deaktivasyon tabloları silmez.

---

## REST API Etkileri

`woocommerce_rest_prepare_shop_order_object` hook'u (`class-hezarfen.php:186-246`):
- Bireysel sipariş ise `_billing_hez_TC_number` decrypt edilip REST yanıtında düz metin olarak döner.
- Kurumsal sipariş ise vergi alanları olduğu gibi geçer.

---

## Statik Veri Dosyaları

`includes/Data/mahalle/`:
- `tr-cities.php` — 81 il, `[TR01..TR81] => İl Adı`
- `tr-districts.php` — `[TR##] => [İlçe1, İlçe2, ...]`
- `tr-neighborhoods/tr-neighborhood-TR{NN}.php` — 81 dosya, `[İlçe => [mahalle_id => Mahalle Adı]]`

Bu dosyalar dağıtım paketine **dahildir** (composer/build sonrası `wp-content/plugins/.../includes/Data/mahalle/`).
