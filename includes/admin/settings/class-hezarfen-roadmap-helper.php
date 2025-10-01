<?php
/**
 * Hezarfen Roadmap Helper
 * 
 * @package Hezarfen\Inc\Admin\Settings
 */

defined( 'ABSPATH' ) || exit();

/**
 * Helper class for roadmap feature lists
 */
class Hezarfen_Roadmap_Helper {
	
	/**
	 * Get free features list
	 *
	 * @return array
	 */
	public static function get_free_features() {
		return array(
			__( 'Yorum hatırlatma e-postası', 'hezarfen-for-woocommerce' ),
			__( 'Ürün tekrar stokta bildirimi', 'hezarfen-for-woocommerce' ),
			__( 'Cüzdan özelliği', 'hezarfen-for-woocommerce' ),
			__( 'Sözleşmelerin maillerde PDF olarak da gönderilmesi (şu anda e-postalarda yazı olarak gönderiliyor)', 'hezarfen-for-woocommerce' ),
			__( 'Giriş yaparken e-posta yerine e-posta veya telefon yazılabilmesi (yine şifreyle giriş yapılacak)', 'hezarfen-for-woocommerce' ),
			__( 'SMTP ayarlarını düzenleyebilme (harici eklenti kurmadan)', 'hezarfen-for-woocommerce' ),
			__( 'Checkout field editör özelliği (ödeme ekranında sürükle bırak ile yeni alanlar ekleme, mevcut alanların sırasını düzenleme, mahalle veya ilçe alanlarındaki Hezarfen özelliklerini kapatabilme)', 'hezarfen-for-woocommerce' ),
			__( 'Banka sanal posları tek çekim destekli (TEB, İşbank, Şekerbank, Halkbank, Finansbank, Ziraat)', 'hezarfen-for-woocommerce' ),
			__( 'Garanti sanal pos tek çekim destekli', 'hezarfen-for-woocommerce' ),
			__( 'ParamPos', 'hezarfen-for-woocommerce' ),
			__( 'Tosla', 'hezarfen-for-woocommerce' ),
			__( 'Tami', 'hezarfen-for-woocommerce' ),
			__( 'PayTR', 'hezarfen-for-woocommerce' ),
			__( 'Iyzico', 'hezarfen-for-woocommerce' ),
			__( 'Banka sanal pos - Kuveyt POS (tek çekim)', 'hezarfen-for-woocommerce' ),
			__( 'Banka sanal pos Akbank (tek çekim)', 'hezarfen-for-woocommerce' ),
			__( 'Banka sanal pos Vakıf Katılım (tek çekim)', 'hezarfen-for-woocommerce' ),
			__( 'Banka sanal pos Albaraka (tek çekim)', 'hezarfen-for-woocommerce' ),
			( current_time( 'timestamp' ) < strtotime( '2025-10-20' ) )
				? __( 'Hepsijet dışında farklı kargolarla da indirimli kargo anlaşması (şu anda Hepsijet&Intense işbirliğiyle 0-4 desi 69,99TL+KDV\'ye gönderim yapabiliyorsunuz, alt gönderim limiti olmadan ve kargoyla anlaşma yapmadan. Takip bilgileri ve sipariş durumu da otomatik güncelleniyor. Pro sürüm gerekmiyor. Diğer kargolarla da anlaşma yapılmasını istiyor musunuz?)', 'hezarfen-for-woocommerce' )
				: __( 'Hepsijet dışında farklı kargolarla da indirimli kargo anlaşması (şu anda Hepsijet&Intense işbirliğiyle gönderim yapabiliyorsunuz, alt gönderim limiti olmadan ve kargoyla anlaşma yapmadan. Takip bilgileri ve sipariş durumu da otomatik güncelleniyor. Pro sürüm gerekmiyor. Diğer kargolarla da anlaşma yapılmasını istiyor musunuz?)', 'hezarfen-for-woocommerce' ),
			__( 'Özel sipariş durumları tanımlayabilme', 'hezarfen-for-woocommerce' ),
			__( 'Kapıda ödemeye ek tutar tanımlayabilme', 'hezarfen-for-woocommerce' ),
			__( 'Dönüşüm odaklı ve kullanıcı dostu ödeme ekranı tasarımı', 'hezarfen-for-woocommerce' ),
			__( 'Verimor SMS entegrasyonu', 'hezarfen-for-woocommerce' ),
			__( 'İade Özelliği (Hesabım sayfasından müşterinin iade talebi oluşturabileceği alan)', 'hezarfen-for-woocommerce' ),
			__( 'Iletimerkezi SMS entegrasyonu', 'hezarfen-for-woocommerce' ),
			__( 'Diğer SMS entegrasyonu (detay kısmında istediğiniz SMS firmasını belirtiniz)', 'hezarfen-for-woocommerce' ),
			__( 'Kullanıcı dostu kargo takip ekranı', 'hezarfen-for-woocommerce' ),
		);
	}

	/**
	 * Get pro features list
	 *
	 * @return array
	 */
	public static function get_pro_features() {
		return array(
			__( 'Yorum hatırlatma e-postası için zamanlama (kargo teslim edildikten sonra istenilen saat sonra otomatik)', 'hezarfen-for-woocommerce' ),
			__( 'Yorum hatırlam bildiriminin SMS olarak da gönderilmesi', 'hezarfen-for-woocommerce' ),
			__( 'Yorum hatırlatma bildiriminde kupon verebilme', 'hezarfen-for-woocommerce' ),
			__( 'Sipariş sonrası cüzdana puan yüklenmesi', 'hezarfen-for-woocommerce' ),
			__( 'banka sanal posları taksit özelliği (Akbank, TEB, İşbank, Şekerbank, Halkbank, Finansbank, Ziraat)', 'hezarfen-for-woocommerce' ),
			__( 'Garanti sanal pos taksit özelliği', 'hezarfen-for-woocommerce' ),
			__( 'banka sanal pos taksit özelliği - Kuveyt POS', 'hezarfen-for-woocommerce' ),
			__( 'banka sanal pos taksit özelliği Akbank', 'hezarfen-for-woocommerce' ),
			__( 'banka sanal pos taksit özelliği Vakıf Katılım', 'hezarfen-for-woocommerce' ),
			__( 'banka sanal pos taksit özelliği Albaraka', 'hezarfen-for-woocommerce' ),
			__( 'banka sanal pos akıllı pos yönlendirme (belirli işlemlerin belirli poslardan geçmesi için kural tanımlayabilme ve bir pos başarısız olduğunda diğerinden deneme yapılması)', 'hezarfen-for-woocommerce' ),
			__( 'Hesabım sayfasının düzenlenmesi (Hesabım sayfasının daha kullanıcı dostu hale gelebilmesi)', 'hezarfen-for-woocommerce' ),
			__( 'Google ile giriş yap özelliği', 'hezarfen-for-woocommerce' ),
			__( 'IYS destekli toplu e-posta gönderimi (Euromessage Express Entegrasyonu)', 'hezarfen-for-woocommerce' ),
			__( 'Telefon ve SMS ile giriş yap özelliği (şifre istemeden)', 'hezarfen-for-woocommerce' ),
			__( 'Müşteri alışveriş yaptıktan sonra mevcut siparişine ek yapabilsin (eğer sipariş belirli durumlardaysa)', 'hezarfen-for-woocommerce' ),
		);
	}
}
