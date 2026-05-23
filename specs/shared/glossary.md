# Glossary / Sözlük

Hezarfen ve Türkiye e-ticaret bağlamında sık geçen terimler.

## Türkçe terimler

| Terim | Açıklama |
|---|---|
| **MSS** | Mesafeli Satış Sözleşmesi. 6502 sayılı Tüketicinin Korunması Hakkında Kanun çerçevesinde, internet üzerinden satılan ürün/hizmet için satıcı ile alıcı arasında akdedilen sözleşme. |
| **ÖBF** | Ön Bilgilendirme Formu. MSS'den önce müşteriye sunulması zorunlu form; ürün, fiyat, cayma hakkı vb. bilgileri içerir. |
| **KVKK** | Kişisel Verilerin Korunması Kanunu (6698). TC kimlik no, telefon, adres gibi kişisel verilerin işlenmesi ve saklanmasını düzenler. Hezarfen TC numarasını AES-128-CBC ile şifreleyerek bu kanuna uyum sağlar. |
| **IYS** | İleti Yönetim Sistemi. Ticari elektronik ileti (SMS, e-posta) gönderiminde alıcının açık rızasının kayıt altına alındığı merkezi sistem. NetGSM gönderimlerinde `iysfilter` parametresi: `0`=bilgilendirme, `11`=ticari bireysel, `12`=ticari kurumsal. |
| **TC Kimlik No** | T.C. vatandaşları için 11 haneli benzersiz kimlik numarası. Hezarfen bireysel fatura için ister; KVKK gereği şifrelenerek saklanır. |
| **Vergi No / VKN** | Şirketler için 10 haneli vergi kimlik numarası (kişiler için 11 haneli TC ile aynı). |
| **Vergi Dairesi** | Mükellefin bağlı bulunduğu vergi dairesi adı. Kurumsal faturada zorunlu. |
| **Bireysel fatura** | `invoice_type=person`. Şahıs adına kesilen fatura; TC kimlik gerektirir. |
| **Kurumsal fatura** | `invoice_type=company`. Şirket adına kesilen fatura; şirket adı + vergi no + vergi dairesi gerektirir. |
| **Mahalle** | Türkiye'nin en küçük resmi idari birimi. Hezarfen mahalleyi `address_1` alanına bağlar. |
| **İlçe** | Şehrin alt idari birimi. Hezarfen ilçeyi `city` alanına bağlar (label: "Town / City"). |
| **İl** | Türkiye'nin 81 üst idari birimi. WC `state` alanı, `TR01..TR81` plaka kodlarıyla. |
| **Mahalle Local** | Hezarfen'in dahili statik Türkiye il/ilçe/mahalle veri kümesi. |
| **MBGB** | Mahalle Bazlı Gönderim Bedeli. Hezarfen'in opsiyonel addon'u — mahalleye göre kargo ücreti farklılaştırır. Min sürüm `WC_HEZARFEN_MIN_MBGB_VERSION = 0.6.1`. |
| **Kargoya Verildi** | Custom WC order status `wc-hezarfen-shipped`. Manuel kargo takip kaydı eklendiğinde sipariş bu statüye geçer. |
| **Desi** | Kargo firmalarının ücretlendirmede kullandığı hacim-ağırlık birimi (uzunluk×genişlik×yükseklik÷3000). |
| **Cayma hakkı** | Tüketicinin sözleşmenin kurulmasından itibaren 14 gün içinde gerekçe göstermeden cayma hakkı. Hezarfen sözleşmeleri ile belgelenir. |

## Teknik terimler

| Terim | Açıklama |
|---|---|
| **HPOS** | High-Performance Order Storage. WC 8.0+ ile gelen yeni sipariş saklama mekanizması (`wp_wc_orders` ve `wp_wc_orders_meta` tabloları). Hezarfen `WC_HEZARFEN_HPOS_ENABLED` constant'ı üzerinden uyumludur. |
| **WC Blocks Checkout** | Gutenberg block tabanlı yeni checkout. Hezarfen şu an **desteklemiyor**; classic shortcode checkout gerekir. |
| **CFE** | Checkout Field Editor for WooCommerce. ThemeHigh'in eklentisi. Hezarfen onu tespit edip çakışan field manipülasyonlarını ona bırakır. |
| **NetGSM** | Türkiye merkezli toplu SMS sağlayıcısı. Hezarfen'in native entegrasyonu. |
| **PandaSMS** | Alternatif SMS sağlayıcı eklentisi. Hezarfen sadece "kargoya verildi" event'inde tetikler. |
| **HepsiJET** | Hepsiburada'nın kargo birimi. Hezarfen API entegrasyonuyla gönderi oluşturma, takip, iade barkodu vs. sağlar. |
| **Action Scheduler** | WC'nin background job kuyruğu. Hezarfen SMS gönderimi için **kullanmaz** (senkron). |
| **Composer autoload** | `vendor/autoload.php` ile yüklenen 3rd-party bağımlılıkları (örn. TCPDF — şu an kullanılmıyor ama yüklü). |

## Plugin sürüm sabitleri

`hezarfen-for-woocommerce.php` içinde:

| Sabit | Değer | Açıklama |
|---|---|---|
| `WC_HEZARFEN_VERSION` | `2.11.4` | Plugin sürümü |
| `WC_HEZARFEN_MIN_WC_VERSION` | `6.9.0` | Minimum WooCommerce |
| `WC_HEZARFEN_MIN_MBGB_VERSION` | `0.6.1` | Minimum MBGB addon |
| `WC_HEZARFEN_FILE` | `__FILE__` | Plugin entry path |
| `WC_HEZARFEN_UYGULAMA_YOLU` | `plugin_dir_path()` | Plugin dizini |
| `WC_HEZARFEN_UYGULAMA_URL` | `plugin_dir_url()` | Plugin URL |
| `WC_HEZARFEN_NEIGH_API_URL` | `api/get-mahalle-data.php` URL'i | Frontend mahalle endpoint'i |
| `WC_HEZARFEN_HPOS_ENABLED` | bool | Runtime'da WC HPOS durumu (init'te tanımlanır) |
| `HEZARFEN_ENCRYPTION_KEY` | base64 string | `wp-config.php`'de admin tarafından tanımlanır; TC encryption anahtarı |
| `HEZARFEN_FORCE_SHOW_UPGRADE` | bool | Geliştirici override; Pro tespiti olsa bile upgrade UI'ı gösterir |
