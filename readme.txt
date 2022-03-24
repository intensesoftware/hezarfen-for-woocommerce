=== Hezarfen for WooCommerce ===
Contributors: intenseyazilim
Tags: vergi alanları, posta kodu, il ilçe, mahalle, Türkiye, Turkish, Turkey, intense, hezerfen, kargo, vergi
Donate link: https://www.intense.com.tr
Requires at least: 5.3
Tested up to: 5.9.2
Requires PHP: 7.0
License: GPL2
Stable tag: 1.4.0

Hezarfen, WooCommerce e-ticaret altyapısını Türkiye için daha kullanılabilir kılmayı amaçlar.

== Description ==
= ÖZELLİKLER =
- Opsiyonel olarak ödeme ekranına mahalle alanının eklenmesi (ücretsiz)
- Ödeme ekranında vergi bilgileri (kurumsal ve bireysel fatura tercihine göre)
- Encrypt edilebilir T.C. kimlik no alanı (T.C. no verileri veritabanına encrypt edilerek yazılır.)
- Posta kodu alanını ödeme ekranından tek tuşla kaldırabilme
- Ödeme formundaki alanları tek tuşla, Türkiye için otomatik sıralayabilme
- Mahalleye göre kurye ücretlendirmesi yapabilme (Ücretli: https://intense.com.tr/urun/hezarfen-mahalleye-gore-ucretlendirme-addon/)

= Geliştirmeye katkıda bulunmak istiyorum =
Hezarfen projesi Intense Yazılım ekibi tarafından geliştirilmektedir.

== Installation ==
Eklentiyi aktifleştirdikten sonra, WooCommerce -> ayarlar ekranına giderek Hezarfen menüsünden eklentinin ayarlarını kontrol edebilirsiniz.

== Screenshots ==
1. Ödeme formu ayarlar ekranı
3. Opsiyonel mahalle.io servisi aktifken ödeme ekranı
4. Opsiyonel mahalle.io servisi kullanılmadığında ödeme ekranı
5. Kurumsal vergi bilgileri
6. Bireysel vergi bilgileri

== Changelog ==
= 1.4.0 - 2022-03-24 =
* Hezarfen'e lokal mahalle desteği eklendi ve mahalle.io bağımlığı kaldırıldı. Artık ilçe/mahalle desteği ücretsiz ve varsayılan olarak sunuluyor.
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
* mahalle.io AJAX fonksiyonlarinda iyileştirmeler ( nonce ve sanization desteği )
* Genel iyileştirmeler

= 1.2.1 - 2020-11-10 =
* Özellik: Ödeme sayfasında yer alan Hezarfen select2 alanları için Türkçe dil desteği eklendi.
* Özellik: Ödeme sayfasında yer alan Hezarfen form alanları için WP filter desteği eklendi.
* İyileştirme: Ödeme sayfasında yer alan Hezarfen select2 alanları için tasarımsal iyileştirmeler

= 1.2.0 - 2020-11-10 =
* Ödeme sayfasında yer alan posta kodu alanlarının tek tuşla ödeme ekranından kaldırılabilmesi sağlandı.

= 1.1.0 - 2020-11-09 =
* Ödeme sayfasında, fatura bilgileri bölümündeki \'fatura firma adı\' alanının yalnızca \'fatura tipi\' kurumsal olarak seçildiğinde gösterilmesi sağlandı.

= 1.0.0 - 2020-11-07 =
* Opsiyonel olarak ödeme ekranına mahalle alanının eklenmesi
* Ödeme ekranında vergi bilgileri (kurumsal ve bireysel fatura tercihine göre)
* Encrypt edilebilir T.C. kimlik no alanı
