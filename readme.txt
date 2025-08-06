=== Hezarfen - Türkiye Kargo Entegratör - WooCommerce Kargo Takip ve Yönetim Eklentisi For WooCommerce ===
Contributors: intenseyazilim, mskapusuz
Tags: kargo, kargo takip, türkiye, woocommerce, fatura
Requires at least: 5.3
Tested up to: 6.8
Requires PHP: 7.0
License: GPL2
Stable tag: 2.3.2

Türkiye'nin sevilen WooCommerce kargo eklentisi! Aras kargo, MNG kargo, Yurtiçi kargo, PTT kargo, Sürat kargo ve 25+ yerli kargo firması için manuel kargo takibi. Kargo numarası girme, kargo durumu takibi, kargo SMS bildirimleri ve kapsamlı kargo yönetimi özelliği. Türk WooCommerce e-ticaret sitelerine özel geliştirilmiş kargo çözümü.

== Description ==
Türkiye'nin sevilen WooCommerce kargo eklentisi! Manuel kargo takip özelliği ile 26 farklı kargo firmasını destekler. Kargo numarası girme, kargo durumu takibi, kargo SMS bildirimleri ve kapsamlı kargo yönetimi özelliği. Türk WooCommerce e-ticaret sitelerine özel geliştirilmiş kargo çözümü. Hezarfen, WooCommerce e-ticaret altyapısını Türkiye için daha kullanılabilir kılmayı amaçlar. Ücretsiz olarak manuel kargo takip özelliği, ilçe/mahalle seçim alanları, kurumsal/bireysel fatura bilgileri ve T.C. kimlik no alanı gibi özellikler sunar.

**Desteklenen Kargo Firmaları (Manuel Kargo Takip):**
• Aras Kargo
• Birgünde Kargo
• Brinks Kargo
• CDEK
• DHL
• FedEx
• Gelal
• hepsiJET
• Horoz Lojistik
• Jetizz
• Kargo Türk
• Kargoist
• Kolay Gelsin
• Kurye
• MNG Kargo
• PackUpp
• PTT Kargo
• Scotty
• Sendeo Kargo
• Sürat Kargo
• TNT
• Trendyol Express
• UPS Kargo
• Yurtiçi Kargo

= ÖZELLİKLER =
- Ücretsiz manuel kargo takibi özelliği (takip numarası girebilme, "kargoya verildi" sipariş durumu, müşteri ekranında kargo detaylarının gösterimi, NetGSM ve PandaSMS entegrasyonu, sipariş kargoya verildi maili.)
- Ödeme ekranına Türkiye için mahalle alanlarının eklenmesi (ücretsiz, mahalle verileri eklenti içerisinde saklanır.)
- Ödeme ekranında vergi bilgileri (kurumsal ve bireysel fatura tercihine göre)
- Encrypt edilebilir T.C. kimlik no alanı (T.C. no verileri veritabanına encrypt edilerek yazılır.)
- Posta kodu alanını ödeme ekranından tek tuşla kaldırabilme
- Ödeme formundaki alanları tek tuşla, Türkiye için otomatik sıralayabilme

= PRO ÖZELLİKLER =
- **5 Kargo Entegrasyonu Tek Pakette**: Yurtiçi, DHL E-Commerce (MNG), Aras, Kolay Gelsin, Hepsijet kargolarıyla entegre olun. Tüm kargo firmalarını aynı anda kullanabilirsiniz, kargo firması başına ayrı ücret ödemezsiniz.
- **Otomatik Kargo Entegrasyonu**: Barkod oluşturun, kargo takip numaraları otomatik girilsin, otomatik olarak firmanızın adına SMS ve e-posta gönderilsin, sipariş durumları otomatik olarak kargoya verildi veya tamamlandı yapılsın.
- **Paraşüt Entegrasyonu**: Paraşüt ile siparişlerinizi otomatik olarak faturalandırıp resmileştirin.
- **Otomatik Ürün Gönderimi**: Paraşüt'e ürünlerinizi otomatik olarak gönderin.

**Hezarfen Pro'yu satın almak için:** [https://intense.com.tr/urun/hezarfen-pro/](https://intense.com.tr/urun/hezarfen-pro/)

== Frequently Asked Questions ==

= Kargo takip özelliğinin ayarlarını nasıl yapabilirim? =
Eklentiyi aktifleştirdikten sonra, WooCommerce -> Ayarlar -> Hezarfen -> Manuel Kargo Takip menüsünden ilgili ayarları yapabilirsiniz.

= T.C. Kimlik numarası alanı neden görünmüyor? =
T.C. Kimlik numarası alanının görünmesi için şifreleme (encryption) özelliğinin aktif edilmesi gerekir. WooCommerce -> Ayarlar -> Hezarfen -> Şifreleme (Encryption) menüsünden bu özelliği aktifleştirebilirsiniz.

= Kaybolan şifreleme anahtarını nasıl yeniden oluşturabilirim? =
Şifreleme anahtarınızı kaybettiyseniz, WooCommerce -> Ayarlar -> Hezarfen -> Şifreleme Anahtarı Kurtarma bölümünden yeni bir anahtar oluşturabilirsiniz. Oluşturulan anahtarı wp-config.php dosyanıza `define( 'HEZARFEN_ENCRYPTION_KEY', 'örnek anahtar değeri' );` şeklinde eklemeniz gerekir. Bu işlem öncesinde mutlaka sitenizin tam yedeğini alın. Unutmayın ki, yeni anahtar oluşturduğunuzda eski siparişlerdeki şifrelenmiş T.C. Kimlik numarası verilerine erişemezsiniz. Not: "Şifreleme Anahtarı Kurtarma" bölümü sadece wp-config.php dosyasında encryption key tanımlanmadığında ve geçmişte başarılı bir şifreleme anahtarı oluşturma işlemi yaptıysanız görünür.

= İlçe ve mahalle verileri nereden geliyor? =
İlçe ve mahalle verileri eklenti içerisinde yer almaktadır, herhangi bir harici servise ihtiyaç duymaz.

= Kurumsal ve bireysel fatura seçeneğini nasıl aktifleştirebilirim? =
WooCommerce -> Ayarlar -> Hezarfen -> Ödeme Sayfası Ayarları menüsünden "Ödeme ekranında vergi alanlarını göster" seçeneğini aktifleştirmeniz yeterlidir.

= Hangi kargo firmalarını destekliyorsunuz? =
Hezarfen eklentisi aşağıdaki Türk kargo firmalarını desteklemektedir:

• Aras Kargo
• Birgünde Kargo
• Brinks Kargo
• CDEK
• DHL
• FedEx
• Gelal
• hepsiJET
• Horoz Lojistik
• Jetizz
• Kargo Türk
• Kargoist
• Kolay Gelsin
• Kurye
• MNG Kargo
• PackUpp
• PTT Kargo
• Scotty
• Sendeo Kargo
• Sürat Kargo
• TNT
• Trendyol Express
• UPS Kargo
• Yurtiçi Kargo

Bu kargo firmaları için manuel kargo takip numarası girişi, kargo durumu takibi ve müşteri ekranında kargo detaylarının gösterimi özellikleri mevcuttur.

= Müşterilere nasıl kargo SMS'i gönderilir? =
Hezarfen ücretsiz versiyonunda NetGSM ve PandaSMS entegrasyonları bulunmaktadır. WooCommerce -> Ayarlar -> Hezarfen -> Manuel Kargo Takip bölümünden kargoya verildi SMS şablonunu düzenleyebilirsiniz.

== Installation ==
Eklentiyi aktifleştirdikten sonra, WooCommerce -> Ayarlar ekranına giderek Hezarfen menüsünden eklentinin ayarlarını kontrol edebilirsiniz.

== Screenshots ==
1. Kargo takip no giriş alanı
2. Kargoya verildi maili
3. Müşteri hesabım sayfası kargo detayları
4. Ödeme formu ayarlar ekranı
5. Opsiyonel mahalle.io servisi aktifken ödeme ekranı
6. Opsiyonel mahalle.io servisi kullanılmadığında ödeme ekranı
7. Kurumsal vergi bilgileri
8. Bireysel vergi bilgileri
9. Kargo takip özellik ayarları
10. Sipariş liste ekranında kargo takip bilgilerinin görünmesi
11. Kargoya verildi özellik ayarları

== Changelog ==

= 2.3.2 - 2025-08-07 =
* PHP hatası giderildi.

= 2.3.1 - 2025-08-06 =
* WooCommerce uyumluluk versiyon bilgisi güncellendi.
* Eklenti banner ve logo güncellendi.

= 2.3.0 - 2025-03-12 =
* Özellik: Şifreleme anahtarı kurtarma özelliği eklendi.
* Hata giderme: Geçersiz T.C. Kimlik Numarası girildiğinde, ödeme ekranında otomatik temizleme özelliği eklendi.
* Hata giderme: Fatura tipi kurumsal seçildiğinde oluşan T.C. Kimlik Numarası doğrulama sorunu giderildi.
* Özellik: WooCommerce REST API için vergi alanları desteği eklendi (deneysel, beta).

= 2.1.2 - 2024-05-31 =
* WooCommerce admin sipariş düzenleme ekranında, indirilebilir ürünler alanının ve bazı alanların, Hezarfen tarafından bozulması için geçici düzeltme

= 2.1.1 - 2024-05-30 =
* WooCommerce aktif olmadığında eklentinin çalışması engellendi.

= 2.1.0 - 2024-05-30 =
* Ödeme ekranında TCKN doğrulama özelliği genişletildi.
* Ödeme ekranında vergi no alanı için doğrulama eklendi.
* Ödeme ekranında ödeme blokları kullanılması durumunda, WP admin ekranında bilgilendirme uyarısı gösterilmesi sağlandı.

= 2.0.3 - 2024-05-14 =
* Hata giderme: v2.0.2 ile birlikte yapılan düzeltmelerin, manuel kargo takip özelliğinin çalışmasını engellemesi sorunu giderildi.

= 2.0.2 - 2024-05-14 =
* WordPress admin ekranında farklı eklentiler tarafından Bootstrap kütüphanelerinin kullanılması durumunda, Hezarfen eklentisinin etkilenmemesi sağlandı.

= 2.0.1 - 2024-04-15 =
* Kargo takip özelliği eklendi. (kargo takip numarası girebilme, kargoya verildi durumu, SMS entegrasyonu)

= 1.6.8 - 2024-04-15 =
* HPOS desteği eklendi.

= 1.6.7 - 2023-12-06 =
* Ödeme ekranında vergi no alanının maksimum 11 karakter yazılabilmesi sağlandı.

= 1.6.6 - 2023-11-22 =
* Hata giderme: Ödeme ekranında oluşabilen PHP uyarısının düzeltilmesi

= 1.6.5 - 2023-11-19 =
* Hata giderme: Ödeme ekranında ilçe ve mahalle alanlarının HTML class değerlerini miras alması sağlandı.

= 1.6.3 - 2023-11-05 =
* Hata giderme: WP Admin sipariş düzenleme ekranından sipariş düzenlendikten sonra, fatura tipi bilgisinin değişmesi (kurumsal'ken bireysele dönmesi) problemi giderildi.

= 1.6.2 - 2023-06-07 =
* Hata giderme: Bazı yazım hatalarının giderilmesi.

= 1.6.1 - 2023-04-30 =
* Hata giderme: "jQuery.fn.change() event shorthand is deprecated" uyarısı giderildi.
* İyileştirme: Checkout Field Editor for WooCommerce eklentisi aktifken, Hezarfen otomatik ödeme alanları sıralanması ve posta kodunun gizlenmesi özelliklerinin devre dışı bırakılması sağlandı.
* Hata giderme: Ödeme sayfasında varsayılan ülke Türkiye dışında bir ülkeyse ortaya çıkan sorun çözüldü.
* Hata giderme: Bazı stringlerin çeviri problemleri giderildi (varsayılan olarak Türkçe olup çeviri desteği bulunmayanlar)
* Hata giderme: Ödeme ekranında, il, ilçe, mahalle seçim alanlarındaki "no results found" uyarısına dil çeviri desteği eklendi.
* Hata giderme: Ödeme ekranında; adres 2 alanının etiketi (input label), yer tutucu (input placeholder) ve zorunluluk durum göstergelerinin görünmemesi problemleri Türkiye için giderildi. Artık bu alan, "Adresiniz" etiketiyle gösteriliyor.

= 1.6.0 - 2022-12-20 =
* Özellik: TC Kimlik No Alanı İçin 11 Karakter Doğrulaması Eklendi
* Hesabım > Adres düzenleme ekranında iyileştirmeler
* Genel altyapısal iyileştirmeler

= 1.5.0 - 2022-10-11 =
* Müşteri Hesabım ekranına, seçilebilir ilçe ve mahalle desteği eklendi.
* Ödeme ekranındaki bireysel/kurumsal seçim alanındaki select2 desteği kaldırıldı.
* Ödeme ekranında Hezarfen fonksiyonlarında iyileştirmeler yapıldı.

= 1.4.9 - 2022-09-24 =
* Readme.txt güncellendi.

= 1.4.8 - 2022-09-24 =
* Hata Giderme: Sipariş detay ekranında bazı koşullara bağlı olarak PHP uyarısı görünmesi problemi giderildi.
* Uyumluluk: Cartzilla teması için uyumluluk sorununun giderilmesi
* Hata Giderme: Ödeme ekranında il değiştiğinde mahalle alanının temizlenmemesi sorunu giderildi.

= 1.4.7 - 2022-09-15 =
* TC No/Vergi Dairesi vb. alanları için temalarla uyumluluğunun iyileştirilmesi

= 1.4.6 - 2022-09-04 =
* Düzeltme: Ödeme ekranında il, ilçe ve mahalle alanları, kelimenin ilk harfleri büyük harf olacak şekilde düzenlendi.
* Düzeltme: Ödeme ekranında, ilçe seçiminden sonra mahallelerin alfabetik sıralanmış olarak gösterilmesi sağlandı.

= 1.4.5 - 2022-08-13 =
* Hata Giderme: Ödeme ekranında başlıksız ve gereksiz ödeme alanlarının (text input) görünmesi problemi giderildi.
* İyileştirme: Checkout Field Editor ve benzeri eklentilerle uyumluluğun iyileştirilmesi
* Hezarfen Mahalle Addon v0.6.1 için uyumluluk

= 1.4.4 - 2022-07-03 =
* Hata giderme: Ödeme sayfasında Türkiye harici bir ülke seçildiğinde ortaya çıkan sorunların giderilmesi
* Hata giderme: T.C. Kimlik Zorunluysa ve alan boşsa sipariş oluşturabilme sorununun giderilmesi
* Checkout Field Editor eklenti uyumluluğu
* Hata giderme: Tema uyumluluğu

= 1.4.3 - 2022-04-01 =
* Hata giderme: Ödeme ekranında sayfa yüklendiğinde il alanı boşsa, il seçildikten sonra mahalle ve ilçe alanlarının içeriğinin boş görünmesi problemi giderildi.

= 1.4.2 - 2022-03-26 =
* Hata giderme: Ödeme ekranında mahalle/ilçe alanlarında sınırlı kullanıcıda yaşanan sorun giderildi.

= 1.4.1 - 2022-03-24 =
* readme.txt güncellendi.

= 1.4.0 - 2022-03-24 =
* Hezarfen'e lokal mahalle desteği eklendi ve mahalle.io bağımlılığı kaldırıldı. Artık ilçe/mahalle desteği ücretsiz ve varsayılan olarak sunuluyor.
* Performans iyileştirmeleri
* Ödeme ekranındaki ilçe ve mahalle alanlarının html element ID değerleri standartlara uygun hale getirildi.
* Ödeme ekranındaki ilçe ve mahalle bilgisinin kaydedilmesi sağlanarak, kayıtlı kullanıcıların her sefer mahalle ve ilçe seçme gereksinimi kaldırıldı.

= 1.3.4 - 2020-11-24 =
* İyileştirme: hezarfen_checkout_neighborhood_changed WP action için, tip (billing veya shipping) desteği 3.parametre olarak eklendi.

= 1.3.3 - 2020-11-14 =
* Düzeltme: Ödeme ekranındaki alanların otomatik sıralama özelliğinin aktif edilmesine rağmen, istisnai olarak bazı sistemlerde il, ilçe alanlarının sıralamasının yapılamaması problemi giderildi.

= 1.3.2 - 2020-11-14 =
* Düzeltme: Ödeme ekranında, encryption özelliği pasif olmasına rağmen T.C. no alanının gösterilmesi problemi giderildi.
* Düzeltme: Ödeme ekranında, fatura tipi: bireysel seçilmesine rağmen; kurumsal fatura tipine ait alanların zorunlu olması problemi giderildi.

= 1.3.1 - 2020-11-14 =
* Düzeltme: Ödeme ekranındaki Hezarfen vergi alanları kutucuklarının placeholder değerleri eklendi/güncellendi.

= 1.3.0 - 2020-11-13 =
* Özellik: Ödeme ekranındaki alanların otomatik sıralanması özelliği eklendi.
* Özellik: mahalle.io aktif edilirse, adres2 alanının otomatik olarak zorunlu yapılması sağlandı.

= 1.2.2 - 2020-11-11 =
* mahalle.io AJAX fonksiyonlarında iyileştirmeler ( nonce ve sanitization desteği )
* Genel iyileştirmeler

= 1.2.1 - 2020-11-10 =
* Özellik: Ödeme sayfasında yer alan Hezarfen select2 alanları için Türkçe dil desteği eklendi.
* Özellik: Ödeme sayfasında yer alan Hezarfen form alanları için WP filter desteği eklendi.
* İyileştirme: Ödeme sayfasında yer alan Hezarfen select2 alanları için tasarımsal iyileştirmeler

= 1.2.0 - 2020-11-10 =
* Ödeme sayfasında yer alan posta kodu alanlarının tek tuşla ödeme ekranından kaldırılabilmesi sağlandı.

= 1.1.0 - 2020-11-09 =
* Ödeme sayfasında, fatura bilgileri bölümündeki 'fatura firma adı' alanının yalnızca 'fatura tipi' kurumsal olarak seçildiğinde gösterilmesi sağlandı.

= 1.0.0 - 2020-11-07 =
* Opsiyonel olarak ödeme ekranına mahalle alanının eklenmesi
* Ödeme ekranında vergi bilgileri (kurumsal ve bireysel fatura tercihine göre)
* Encrypt edilebilir T.C. kimlik no alanı
