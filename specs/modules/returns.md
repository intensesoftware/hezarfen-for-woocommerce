---
id: returns
title: İade Yönetimi
status: beta
since: 2.15.0
owner: hezarfen-core
entry_files:
  - includes/returns/class-returns-module.php
  - includes/returns/core/class-return-service.php
  - includes/returns/core/class-return-eligibility.php
  - includes/returns/frontend/class-my-account-returns.php
  - includes/returns/admin/class-returns-admin.php
depends_on: [woocommerce]
optional_deps: [manual-shipment-tracking]
related: [my-account, shipment-tracking, admin-menu]
---

## Amaç

Müşterinin satın aldığı ürünü iade etmek için mağazaya e-posta atması ya da telefon
etmesi gerekmesin. Müşteri, hesabındaki siparişin detay sayfasından iade talebi
açar ve süreci adım adım takip eder; mağaza da talepleri tek bir ekrandan onaylar,
reddeder, ek bilgi ister ve kargo bilgisini görür.

Akış **hesaba bağlıdır**: talep yalnızca siparişin sahibi olan müşteri
tarafından, kendi sipariş detay sayfasından açılabilir. Bu, Amazon ve
Trendyol gibi pazaryerlerinin izlediği yolla aynıdır; üyeliksiz sorgulama
bilerek yoktur.

Hesabım menüsüne ayrı bir "İadelerim" sekmesi **eklenmez**: iade siparişe
aittir, dolayısıyla hem başlatma hem takip o siparişin sayfasında olur.

Modül **varsayılan olarak kapalıdır**; WooCommerce → Ayarlar → Hezarfen → İade
Yönetimi bölümünden açılır.

## Kapsam

- Tek giriş noktası: Hesabım → Siparişler → sipariş detayındaki iade paneli; aynı panel o siparişin açık taleplerini de listeler (`includes/returns/frontend/class-my-account-returns.php`)
- Talep detayı ve durum takibi (`/{hesabım}/iadelerim/{talep id}/`)
- Ürün/adet seçimi ve kısmi iade (`includes/returns/core/class-return-eligibility.php`)
- Hazır iade sebepleri + "Diğer" seçeneğinde zorunlu açıklama (`includes/returns/core/class-default-reason-provider.php`)
- Global iade süresi ve süre başlangıcı ayarı (`includes/returns/core/class-global-return-policy-provider.php`)
- Tek iade adresi (`includes/returns/core/class-return-settings.php`)
- Gönderim yöntemi: müşteri kendi gönderir (manuel takip no) veya Kargokit iade barkodu — müşteri onaydan sonra kargo alım gününü kendi seçer (`includes/returns/shipping/`)
- Admin liste + detay, manuel onay/red, basit ek bilgi isteme, dahili/müşteriye açık notlar (`includes/returns/admin/`)
- Temel timeline ve standart WooCommerce e-postaları (`includes/returns/emails/`)
- Tamamlanan talebi WooCommerce'in **manuel** iade kaydına yazma ve isteğe bağlı stok geri ekleme (`includes/returns/core/class-return-refunds.php`)
- Talebin kilometre taşlarını sipariş notu olarak siparişe düşme (`includes/returns/core/class-return-order-sync.php`)

### Kapsam dışı (kasıtlı)

Aşağıdakiler bu modülde **yoktur**; modül bunlar için hazır uzantı noktaları
sunar (bkz. "Uzantı Noktaları"), böylece bir eklenti çekirdek koda dokunmadan
ekleyebilir:

- Üyeliksiz (guest) iade talebi
- Mağazanın kendi tanımladığı iade sebepleri
- Talebe fotoğraf/video ekleme ve kanıt kuralları
- E-posta içeriği özelleştirme
- Ürün/kategori bazlı iade politikaları
- Çoklu iade adresi / depo
- Mağazanın kendi kargo anlaşmasıyla otomatik barkod
- Talep içi mesajlaşma, değişim (exchange), mağaza kredisi
- Kural motoru, otomatik onay, toplu işlem, SLA hatırlatmaları, analitik, CSV/API/webhook
- **Paranın gerçekten iade edilmesi.** Modül ödeme altyapısına iade isteği
  göndermez; sadece WooCommerce'e "bu kadarı iade edildi" der. Transferi
  mağaza kendi kanalından yapar. Gateway üzerinden otomatik iade, bu
  sözleşmenin (`hezarfen_return_refund_created`) üstüne bir eklentinin işidir.

## Veri Modeli

### Tablolar

Şema `hezarfen_returns_db_version` option'ı ile ayrı sürümlenir; plugin sürümü
artmadan da kurulabilsin diye `hezarfen_db_version`'a bağlı değildir
(`includes/returns/core/class-returns-schema.php`).

- `{prefix}hezarfen_returns` — talep başlığı. `return_number`, `order_id`,
  `customer_id`, `customer_email`, `status`, `shipping_method`, `courier`,
  `tracking_number`, `pickup_date`, `return_address_id`, `refund_id`,
  `pickup_address`, `customer_note`, `refund_amount`, `currency`,
  `created_at`, `updated_at`.
- `{prefix}hezarfen_return_items` — talebe dahil satırlar. `return_id`,
  `order_item_id`, `product_id`, `variation_id`, `product_name`, `sku`,
  `quantity`, `line_total`, `reason_key`, `reason_note`.
- `{prefix}hezarfen_return_events` — timeline. `return_id`, `type`, `actor_type`,
  `actor_id`, `actor_name`, `from_status`, `to_status`, `message`,
  `is_customer_visible`, `created_at`.

### Durumlar

| Key | Admin etiketi | Açıklama |
|---|---|---|
| `pending` | Beklemede | Yeni talep, inceleme bekliyor |
| `info-required` | Ek bilgi bekleniyor | Mağaza müşteriden ayrıntı istedi |
| `approved` | Onaylandı | Ürünlerin yola çıkması bekleniyor; kurye ile alım yapılan yöntemlerde müşterinin alım günü seçmesi beklenir |
| `shipped` | Kargoya verildi | Takip numarası girildi |
| `received` | Tarafımıza ulaştı | Ürünler mağazaya ulaştı |
| `completed` | Tamamlandı | Süreç kapandı (terminal) |
| `rejected` | Reddedildi | Talep kabul edilmedi (terminal) |
| `cancelled` | İptal edildi | Müşteri veya mağaza iptal etti (terminal) |

İzinli geçişler `Return_Status::get_transitions()` içinde tanımlıdır ve
filtrelenebilir. `rejected` ve `cancelled` talepler sipariş satırında ayırdıkları
adedi geri bırakır; diğer tüm durumlar adedi tutar.

## Davranışlar

### Senaryo: Müşteri kısmi iade talebi açar
- **Given** modül açık ve siparişin durumu izinli durumlar arasında
- **And** iade süresi dolmamış
- **When** müşteri Hesabım → Siparişler → sipariş detayı → "İade talebi oluştur" yolunu izleyip bir satırdan 2 adetten 1'ini seçer, sebep belirtir ve formu gönderir
- **Then** `pending` durumunda bir talep oluşur, `IADE-{sipariş no}-{sıra}` referansı atanır
- **And** müşteriye onay, mağazaya bilgi e-postası gider
- **And** aynı satır için kalan 1 adet hâlâ iade edilebilir görünür

### Senaryo: "Diğer" sebebi açıklama olmadan gönderilir
- **Given** müşteri bir satır seçmiş ve sebep olarak "Diğer" işaretlemiş
- **When** açıklama alanı boş bırakılıp form gönderilir
- **Then** talep oluşmaz ve "açıklama yazmanız gerekiyor" hatası gösterilir
- **And** bu kural JavaScript kapalıyken de sunucu tarafında uygulanır

### Senaryo: Başka bir müşterinin siparişi denenir
- **Given** giriş yapmış bir müşteri
- **When** kendisine ait olmayan bir siparişin iade formu URL'i açılır
- **Then** form render edilmez, "iade talebi oluşturamazsınız" hatası gösterilir

### Senaryo: Üye olmayan ziyaretçi
- **Given** oturum açmamış bir ziyaretçi
- **When** iade formu veya talep detayı URL'i açılır
- **Then** WooCommerce hesabım akışı devreye girer; giriş yapılmadan hiçbir talep görülemez

### Senaryo: İade süresi dolmuş sipariş
- **Given** global iade süresi 14 gün ve sipariş 30 gün önce tamamlanmış
- **When** iade formu açılmak istenir
- **Then** "iade süresi ... doldu" hatası gösterilir ve form render edilmez

### Senaryo: Mağaza talebi onaylar
- **Given** `pending` durumda bir talep
- **When** yönetici Hezarfen → İadeler → talep detayında "Onayla" der
- **Then** durum `approved` olur, timeline'a yönetici aktörlü bir kayıt düşer
- **And** müşteriye onay e-postası gider
- **And** gönderim yöntemi Kargokit ise barkod **oluşturulmaz**; onay yalnızca
  müşterinin randevu almasını açar

### Senaryo: Mağaza ek bilgi ister
- **Given** `pending` veya `info-required` dışı bir açık talep
- **When** yönetici "Müşteriden ek bilgi iste" alanına mesaj yazıp gönderir
- **Then** durum `info-required` olur, mesaj timeline'a `info-request` tipiyle düşer
- **And** müşteriye ek bilgi e-postası gider
- **When** müşteri talep sayfasındaki formdan yanıt yazar
- **Then** yanıt `info-response` olarak kaydedilir ve durum `pending`'e döner

### Senaryo: Müşteri iade kargo randevusunu alır
- **Given** gönderim yöntemi Kargokit ve talep `approved`, henüz barkodu yok
- **When** müşteri talep detayında Kargokit'in sunduğu günlerden birini seçip
  "İade kargomu oluştur" der
- **Then** seçilen gün için iade barkodu oluşturulur, barkod no ve alım günü
  talebe yazılır, timeline'a müşteriye açık bir `shipping` kaydı düşer
- **And** durum `approved` kalır: kargo, kurye ürünü teslim aldığında
  `shipped` olur
- **And** müşteri kargo kodunu ve alım gününü talep detayında görür

### Senaryo: Müşterinin seçtiği gün artık müsait değil
- **Given** müşteri formu açtıktan sonra kontenjan dolmuş bir gün gönderir
- **When** form gönderilir
- **Then** barkod oluşturulmaz, "seçtiğiniz gün artık müsait değil" hatası
  gösterilir ve güncel gün listesiyle form yeniden çizilir

### Senaryo: Randevusu alınmış bir talebe ikinci randevu denenir
- **Given** talebin barkodu zaten var **veya** talep `approved` değil
- **When** forma ait bir POST elle gönderilir
- **Then** kayıt reddedilir; mevcut barkod ve alım günü değişmez

### Senaryo: Müşteri kargo bilgisini girer
- **Given** gönderim yöntemi "müşteri kendi gönderir" ve talep `approved`
- **When** müşteri kargo firması ve takip numarasını kaydeder
- **Then** bilgi talebe yazılır, timeline'a `shipping` kaydı düşer
- **And** durum otomatik olarak `shipped` olur

### Senaryo: Müşteri kargo bilgisini giremeyeceği bir talebe yazmaya çalışır
- **Given** talep `pending`/`rejected`/`cancelled` durumda **veya** gönderim
  yöntemi takip numarasını mağaza adına üretiyor (Kargokit)
- **When** forma ait bir POST elle gönderilir
- **Then** kayıt reddedilir; mevcut takip numarası/barkod değişmez

### Senaryo: Kargokit iade barkodu oluşturulamaz
- **Given** gönderim yöntemi Kargokit ve entegrasyon eksik/hatalı, ya da
  siparişin Kargokit gönderi kaydı yok
- **When** müşteri gün seçip randevu almaya çalışır
- **Then** talep `approved` kalır ve barkodsuz kalır; hata müşteriye anlaşılır
  bir mesajla gösterilir, ayrıntısı yalnızca mağazanın gördüğü bir timeline
  kaydına yazılır
- **And** müsait gün listesi hiç alınamıyorsa form yerine aynı mesaj çıkar
- **And** mağaza talebe manuel takip numarası girerek devam edebilir

### Senaryo: Müşteri kargo randevusunu iptal eder
- **Given** barkodu alınmış bir talep ve alım gününün bir gün öncesi 23:59
  henüz geçmemiş
- **When** müşteri "Kargo randevusunu iptal et" der
- **Then** önce taşıyıcıya iptal geçilir; ancak orada serbest bırakıldıktan
  sonra barkod, kargo firması ve alım günü talepten silinir
- **And** siparişteki gönderi kaydı da `cancelled` işaretlenir: sipariş
  ekranında iptal edilmiş bir randevu aktif görünmemelidir
- **And** talep `approved` kalır, gün seçici geri gelir

### Senaryo: Mağaza gönderiyi sipariş ekranından iptal eder
- **Given** barkodu alınmış bir talep
- **When** mağaza sipariş düzenleme ekranındaki gönderi kutusundan hepsiJET
  gönderisini iptal eder
- **Then** `hezarfen_hepsijet_shipment_cancelled` ile talebin de barkodu ve
  alım günü temizlenir, timeline'a kayıt düşer
- **And** müşteri artık geçersiz bir barkod görmez, yeni gün seçebilir

### Senaryo: Kapıda ödemeli siparişte Kargokit iadesi
- **Given** gönderim yöntemi Kargokit ve sipariş kapıda ödemeli (`cod`)
- **When** mağaza talebi onaylar
- **Then** Kargokit bu siparişe barkod üretemeyeceği için talep onay anında
  "müşteri kendi gönderir" yöntemine aktarılır
- **And** sebebi ve sonucu yalnızca mağazanın gördüğü tek bir timeline
  kaydında yazar
- **And** müşteri gün seçici yerine iade adresini ve takip numarası formunu
  görür — onay sonrası çıkmaz sokak oluşmaz

### Senaryo: Mağaza talebi tamamlar ve WooCommerce iadesi kaydeder
- **Given** `received` durumda bir talep ve "Tamamlandı olarak işaretle"
  aksiyonundaki iade kutusu işaretli
- **When** aksiyon çalıştırılır
- **Then** talep `completed` olur
- **And** iade edilen satır ve adetler için siparişe **manuel** bir
  WooCommerce iade kaydı (`refund_payment => false`) yazılır; ödeme
  altyapısına hiçbir istek gitmez, parayı mağaza kendi gönderir
- **And** kısmi iadede yalnızca dönen adet iade edilir; sipariş durumu
  değiştirilmez
- **And** siparişin tamamı iade edilmiş olursa siparişi `refunded` durumuna
  **WooCommerce kendisi** çeker — modül bu durumu hiç yazmaz
- **And** iade kaydının ID'si talebe yazılır, aynı talep ikinci kez iade
  yazamaz
- **And** stok yalnızca "stoğu geri ekle" ayarı açıkken geri eklenir; üstelik
  WooCommerce yalnızca o sipariş için gerçekten düşürdüğü stoğu geri koyar
  (`_reduced_stock`), elle oluşturulmuş siparişlerde bir şey olmaz

### Senaryo: İade kaydı siparişte kalan tutarı aşar
- **Given** siparişte bu modülün açmadığı bir iade var (ör. yalnızca kargo
  bedeli iade edilmiş), dolayısıyla kalan iade edilebilir tutar düşmüş
- **When** talep iade kutusu işaretli hâlde tamamlanır
- **Then** kayıt **kırpılmaz, hiç yazılmaz**: tutarı kısıp satır adetlerini
  tam bırakmak, WooCommerce'in parası iade edilmemiş adetleri iade edilmiş
  saymasına ve o satırın iade edilebilir adedinin düşmesine yol açardı
- **And** mağazaya hangi tutarların çakıştığı yazan bir hata gösterilir
- **And** talep yine `completed` olur

### Senaryo: Zaten elle iade edilmiş bir talep tamamlanır
- **Given** mağaza aynı satırı sipariş ekranından zaten iade etmiş
- **When** talep iade kutusu işaretli hâlde tamamlanır
- **Then** talep yine `completed` olur — mallar gerçekten döndü
- **And** ikinci bir iade kaydı yazılmaz; sebebi mağazaya bir uyarı olarak
  gösterilir ve iç timeline kaydına düşer

### Senaryo: Müşteri talebini iptal eder
- **Given** `pending` veya `info-required` durumda bir talep
- **When** müşteri "Talebi iptal et" der
- **Then** durum `cancelled` olur ve satırların ayırdığı adet serbest kalır

## Edge Cases

- **Dijital ürünler**: sanal/indirilebilir satırlar iade listesine hiç girmez.
- **WooCommerce iadesi**: mağaza WC üzerinden adet iade ettiyse, o adet iade
  edilebilir miktardan düşülür. Tamamlanmış bir talep zaten aynı adedi
  düşürdüğü için ikisi toplanmaz; büyük olan geçerlidir. Açık talepler
  bunun üzerine ayrıca rezerve eder.
- **Para transferi**: modül hiçbir zaman ödeme altyapısına iade isteği
  göndermez. Yazdığı şey WooCommerce'in manuel iade kaydıdır; parayı mağaza
  kendi kanalından gönderir. Bu yüzden iade kaydı varsayılan olarak kapalıdır
  ve "Tamamlandı" aksiyonunda talep bazında onaylanır.
- **Sipariş durumu**: modül siparişin durumunu hiçbir akışta değiştirmez.
  `refunded` durumunu, toplam iade sipariş toplamına ulaştığında WooCommerce
  kendisi yazar; kısmi iadede sipariş durumu olduğu gibi kalır.
- **Sipariş notları**: talep açılması, onay, ret, iptal ve tamamlanma sipariş
  notu olarak da düşer. Ara kargo adımları düşmez; onlar talebin kendi
  timeline'ında kalır, yoksa sipariş notları boğulur.
- **İade adresi boş**: adres alanlarının hiçbiri zorunlu değil, dolayısıyla
  modül adres girilmeden açılabilir. Bu durumda "müşteri kendi gönderir"
  yönteminin yönergesi adres vaat etmez, müşteriyi mağazayla iletişime
  yönlendirir; mağaza da ayarlar ve iade ekranlarında bir uyarı görür.
- **Reddedilen form**: sunucu bir talebi geri çevirdiğinde form aynı istekte
  yeniden çizilir ve müşterinin yazdığı her şey — seçili satırlar, adetler,
  sebepler, satır notları, talep notu ve düzenlenmiş alım adresi — geri
  konur. Sekiz alanlık bir adresi hata başına yeniden yazdırmak, müşteriyi
  formu bırakıp mağazaya e-posta atmaya iten şeydir.
- **Zaman damgaları**: timeline ve talep tarihleri `get_gmt_from_date()` ile
  çevrilir. Anlık `gmt_offset` ile çevirmek, yaz saati uygulayan bir mağazada
  aynı kaydın yazın 14:00, kışın 15:00 görünmesine yol açıyordu.
- **İlerleme çubuğu**: `info-required` bir milat değil, mola; çubukta
  park edildiği adımı (`pending`) ödünç alır, o ana kadarki ilerleme silinmez.
- **Çift gönderim**: `return_number` benzersiz indekslidir; aynı sipariş için
  yarışan iki gönderimden yalnızca biri kaydolur, ikincisi "talebiniz az önce
  oluşturuldu" hatası alır.
- **Sayfa/endpoint çakışması**: `iadelerim` ve `iade-talebi` endpoint'leri
  `EP_ROOT` ile kaydedilir; aynı slug'a sahip bir sayfa bu kural tarafından
  gölgelenip 404 verir.
- **Sahiplik**: talep, siparişin `customer_id`'sine bağlıdır. Müşterisi olmayan
  (üyeliksiz ödeme ile açılmış) siparişler için iade talebi açılamaz.
- **Süre 0**: iade süresi 0 girilirse zaman sınırı uygulanmaz.
- **Şema kurulumu**: tablolar `hezarfen_returns_db_version` ile ayrı sürümlenir,
  plugin sürümü artmasa da kurulur.

## UI Lokasyonları

- **Admin**: Hezarfen → İadeler (liste + detay, bekleyen sayısı rozetli);
  WooCommerce → Ayarlar → Hezarfen → İade Yönetimi; sipariş düzenleme ekranında
  "Hezarfen İade Talepleri" kutusu; WooCommerce → Ayarlar → E-postalar altında
  altı bildirim.
- **Frontend**: sipariş detay sayfasının altındaki iade paneli (tek giriş
  noktası); iade formu `/{hesabım}/iade-talebi/{sipariş id}/`; talep detayı
  `/{hesabım}/iadelerim/{talep id}/`. ID taşımayan `/{hesabım}/iadelerim/`
  adresi siparişler sayfasına yönlenir.
- **Talep detayında kargo bloğu iki farklı şey gösterir**: taşıyıcının ürettiği
  barkod, kuryeye okunacak bir kod olduğu için büyük, kopyalanabilir kartta
  (`.hez-code`) ve iptal penceresiyle birlikte çıkar; müşterinin kendi girdiği
  takip numarası ise sade bir etiket/değer çiftidir (`.hez-tracking`) ve
  düzeltilebilsin diye formu açık bırakır.

## Uzantı Noktaları

Modül, ek yeteneklerin **koda dokunmadan** takılabilmesi için şu sözleşmeleri
sunar:

| Sözleşme | Dosya | Ne için |
|---|---|---|
| `Return_Reason_Provider_Interface` | `core/interface-return-reason-provider.php` | Mağazaya özel iade sebepleri |
| `Return_Policy_Provider_Interface` | `core/interface-return-policy-provider.php` | Ürün/kategori bazlı iade politikaları |
| `Return_Shipping_Method_Interface` | `shipping/interface-return-shipping-method.php` | Kendi kargo anlaşmasıyla otomatik barkod; `requires_customer_booking()` + `get_booking_options()` + `book()` ile müşterinin randevu seçtiği akış, `cancel_booking()` ile iptali, `requires_pickup_address()` ile alım adresi. **Arayüzün tamamı zorunludur**; eksik uygulayan bir sınıf tanımlandığı anda fatal verir |
| `Return_Repository_Interface` | `core/interface-return-repository.php` | Alternatif depolama |

## Hooks

Tam liste için `specs/shared/hooks.md`. Öne çıkanlar:

- action: `hezarfen_return_created` — talep kaydedildikten sonra `(Return_Request $request, WC_Order $order)`
- action: `hezarfen_return_status_changed` — durum değişince `(Return_Request $request, string $old, string $new)`
- action: `hezarfen_return_status_{status}` — belirli bir duruma geçince `(Return_Request $request, string $old)`
- action: `hezarfen_return_shipment_booked` — müşteri iade kargo randevusunu alınca `(Return_Request $request, string $choice)`
- action: `hezarfen_return_refund_created` — tamamlanan talep için WooCommerce iade kaydı yazılınca `(Return_Request $request, WC_Order_Refund $refund)`
- action: `hezarfen_hepsijet_shipment_cancelled` — hepsiJET gönderisi iptal edilince; modül bunu dinleyip talebin randevusunu serbest bırakır `(int $order_id, string $delivery_no)`
- action: `hezarfen_returns_loaded` — modül ayağa kalkınca; sağlayıcılar burada kaydedilir `(Returns_Module $module)`
- filter: `hezarfen_returns_reason_providers` / `..._policy_providers` / `..._shipping_methods`
- filter: `hezarfen_returns_return_address` — talebin gönderileceği adres

## Sınama Notları

- E2E: `tests/e2e/returns-my-account.spec.ts`, `returns-admin.spec.ts`,
  `returns-eligibility.spec.ts`, `returns-settings.spec.ts`,
  `returns-emails.spec.ts`, `returns-booking.spec.ts`,
  `returns-refund.spec.ts`, `returns-photos.spec.ts`.
- İade kaydı specleri parayı gerçekten hareket ettirmez: `wc_create_refund`
  `refund_payment => false` ile çağrıldığı için ödeme altyapısı hiç devreye
  girmez. Ölçülen şey aritmetiktir — kısmi iade, elle iade edilmiş satırla
  çakışma, stok.
- Manuel: modülü açtıktan sonra kalıcı bağlantıları bir kez yenileyin
  (endpoint'ler `hezarfen_returns_endpoints_version` değişince otomatik flush
  olur; flush `shutdown`'a ertelenir, böylece geç kaydedilen kuralları düşürmez).
- HPOS açık/kapalı matrisinde sipariş düzenleme kutusu ayrı doğrulanmalı.
- Kargokit iade barkodu yalnızca hepsiJET entegrasyonu yapılandırılmış ve siparişin
  gönderi kaydı varken denenir; yapılandırma yoksa yöntem seçeneklerde çıkmaz.
- Müsait gün listesi il/ilçe başına 30 dakika transient'te tutulur
  (`hezarfen_returns_pickup_*`); randevu alındığında o ilçenin listesi düşürülür.
  Kargokit'i gerçekten çağıran testlerde bu transient'i temizlemek gerekir.
