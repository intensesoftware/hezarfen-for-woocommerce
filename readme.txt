=== Hezarfen for WooCommerce - Ücretsiz ilçe/mahalle ve kurumsal/bireysel vergi ödeme alanları ===
Contributors: intenseyazilim
Tags: vergi alanları, posta kodu, il ilçe, mahalle, Türkiye, Turkish, Turkey, intense, hezerfen, kargo, vergi, fatura, ödeme alanları
Donate link: https://www.intense.com.tr
Requires at least: 5.3
Tested up to: 6.4
Requires PHP: 7.0
License: GPL2
Stable tag: 1.6.7

Hezarfen, WooCommerce e-ticaret altyapısını Türkiye için daha kullanılabilir kılmayı amaçlar.

== Description ==
Hezarfen, WooCommerce e-ticaret altyapısını Türkiye için daha kullanılabilir kılmayı amaçlar.

= ÖZELLİKLER =
- Ödeme ekranına Türkiye için mahalle alanlarının eklenmesi (ücretsiz, mahalle verileri eklenti içerisinde saklanır.)
- Ödeme ekranında vergi bilgileri (kurumsal ve bireysel fatura tercihine göre)
- Encrypt edilebilir T.C. kimlik no alanı (T.C. no verileri veritabanına encrypt edilerek yazılır.)
- Posta kodu alanını ödeme ekranından tek tuşla kaldırabilme
- Ödeme formundaki alanları tek tuşla, Türkiye için otomatik sıralayabilme

== Installation ==
Eklentiyi aktifleştirdikten sonra, WooCommerce -> ayarlar ekranına giderek Hezarfen menüsünden eklentinin ayarlarını kontrol edebilirsiniz.

== Screenshots ==
1. Ödeme formu ayarlar ekranı
3. Opsiyonel mahalle.io servisi aktifken ödeme ekranı
4. Opsiyonel mahalle.io servisi kullanılmadığında ödeme ekranı
5. Kurumsal vergi bilgileri
6. Bireysel vergi bilgileri

== Changelog ==
= 1.6.7 - 2023-12-06 =
* Ödeme ekranında vergi no alanın maks 11 karakter yazılabilmesi sağlandı.

= 1.6.6 - 2023-11-22 =
* Hata giderme: Ödeme ekranında oluşabilen PHP uyarısının düzeltilmesi

= 1.6.5 - 2023-11-19 =
* Hata giderme: Ödeme ekranında ilçe ve mahalle alanlarının HTML class değerlerini miras al.

= 1.6.3 - 2023-11-05 =
* Hata giderme: WP Admin sipariş düzenleme ekranından sipariş düzenlendikten sonra, fatura tipi bilgisinin değişmesi (kurumsal'ken bireysele dönmesi) problemi giderildi.

= 1.6.2 - 2023-06-07 =
* Hata giderme: Bazı yazım hatalarının giderilmesi.

= 1.6.1 - 2023-04-30 =
* Hata giderme: "jQuery.fn.change() event shorthand is deprecated" uyarısı giderildi.
* İyileştirme: Checkout Field Editor for Woocommerce eklentisi aktifken, Hezarfen otomatik ödeme alanları sıralanması ve posta kodunun gizlenmesi özelliklerinin devre dışı bırakılması sağlandı.
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
* Hata giderme: T.C. Kimlik Zorunluysa ve alan boşsa sipariş oluşturabilme sorunun giderilmesi
* Checkout Field Editor eklenti uyumluluğu
* Hata giderme: Tema uyumluluğu

= 1.4.3 - 2022-04-01 =
* Hata giderme: Ödeme ekranında sayfa yüklendiğinde il alanı boşsa, il seçildikten sonra mahalle ve ilçe alanlarının içeriğinin boş görünmesi problemi giderildi.

= 1.4.2 - 2022-03-26 =
* Hata giderme: Ödeme ekranında mahalle/ilçe alanlarında sınırlı kullanıcıda yaşanan sorun giderildi.

= 1.4.1 - 2022-03-24 =
* readme.txt güncellendi.

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
