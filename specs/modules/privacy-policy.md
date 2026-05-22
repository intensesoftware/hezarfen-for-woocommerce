---
id: privacy-policy
title: Gizlilik Politikası Entegrasyonu (WP Privacy Policy Guide)
status: stable
since: 2.0.0
owner: hezarfen-core
entry_files:
  - includes/class-privacy-policy.php
depends_on: [woocommerce]
related: [invoice-fields, sales-contract]
---

## Amaç

WordPress'in **Privacy Policy Guide** ekranına (Settings → Privacy → Policy Guide) Hezarfen'e ait önerilen metni eklemek. Site sahibi privacy policy sayfasını oluştururken Hezarfen'in topladığı veriler ve Intense Yazılım'ın gizlilik politikası linki tek tıkla kopyalanabilir hale gelir. KVKK uyumluluğunun dokümantasyon ayağı.

## Kapsam

- Yalnızca **suggested text** ekler — privacy policy sayfasını otomatik oluşturmaz veya değiştirmez.
- Kullanıcı verisi okumaz/yazmaz; tablo yok, option yok.
- `wp_add_privacy_policy_content` API'sini kullanır; WP 4.9.6 (Privacy Policy özelliğinin geldiği sürüm) altında sessiz no-op olur.
- Türkçe + İngilizce çift dilli içerik.
- `https://intense.com.tr/yasal/gizlilik-politikasi/` adresine dış link içerir.

## Davranışlar

### Senaryo: WP 4.9.6+ admin sayfası açar
- **Given** WP sürümü Privacy Policy özelliğini destekliyor (`wp_add_privacy_policy_content` mevcut)
- **When** admin `admin_init` aşamasını tetikler
- **Then** "Hezarfen - WooCommerce Kargo Entegrasyonu" başlıklı suggested text registry'e eklenir
- **And** Settings → Privacy → Policy Guide ekranında "Hezarfen" bölümü görünür
- **And** Kullanıcı "Copy suggested policy text" diyebilir

### Senaryo: WP < 4.9.6
- **Given** `wp_add_privacy_policy_content` fonksiyonu yok
- **When** sınıf constructor'ı çağrılır
- **Then** early return; hiçbir şey eklenmez, hata fırlamaz
- **Edge** Hezarfen `WC_HEZARFEN_MIN_WC_VERSION = 6.9.0` zaten WP 5.7+ gerektiriyor; pratikte bu fallback erişilemez kalır

### Senaryo: Locale Türkçe değil
- **Given** site `en_US` locale'inde
- **When** suggested text yüklenir
- **Then** içerik İngilizce paragraflarla görünür
- **And** Türkçe paragraflar yine altta görünür (içerik bilingual hardcoded; gettext çevirisi başlığı çevirebilir ama metin gövdesi statiktir)

## Edge Cases

- **GDPR personal data export/erasure API'leri**: Bu modül `wp_privacy_personal_data_exporters` veya `wp_privacy_personal_data_erasers` filter'larına bağlanmaz. TC numarası, sözleşme kayıtları ve SMS log'ları **otomatik export/erasure'da yer almaz** — admin gerekirse manuel temizlemeli.
- **Suggested text vs. yayınlanmış policy**: Kullanıcı suggested text'i privacy policy sayfasına kopyalamadığı sürece sitede görünür hale gelmez; sadece admin tarafında öneri olarak durur.
- **Çoklu plugin**: Diğer plugin'ler de aynı API'yi kullanır; Hezarfen'in bölümü kendi başlığı altında ayrı görünür.
- **KVKK ≠ GDPR**: WP Privacy API teknik olarak GDPR'a göre tasarlanmış; Hezarfen Türkçe KVKK içeriğini aynı kanaldan iletir. Site sahibinin nihai sorumluluğu metni KVKK gereksinimlerine göre uyarlamaktır.

## UI Lokasyonları

- **Admin > Settings > Privacy > Policy Guide** — "Hezarfen - WooCommerce Kargo Entegrasyonu" bölümü.
- Dış link: `https://intense.com.tr/yasal/gizlilik-politikasi/`.

## Hooks

Modül kendi public hook'u expose etmez. Bağlandığı:
- `admin_init` → suggested text register.

## Sınama Notları

- WP admin "Privacy" menüsünden "Policy Guide" ekranını aç; Hezarfen bölümünün göründüğünü doğrula.
- "Copy suggested policy text" butonu ile metnin clipboard'a alındığını doğrula.
- Türkçe ve İngilizce paragrafların ikisinin de göründüğünü doğrula.
- TC/sözleşme verilerinin WP personal data export tool'una **dahil olmadığını** doğrula (bilinen tasarım, not olarak akılda kalsın).
