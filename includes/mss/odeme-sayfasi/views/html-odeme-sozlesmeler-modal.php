<?php
/**
 * Ödeme ekranında sözleşme içeriklerinin modal olarak gösteirlmesini sağlar.
 *
 * @package Intense\MSS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gosterilmeyecek_sozlesmeler = array_key_exists( 'gosterilmeyecek_sozlesmeler', $ayarlar ) ? $ayarlar['gosterilmeyecek_sozlesmeler'] : array();

?>

<div id="checkout-sozlesmeler">
	<?php if ( ! in_array( 'obf', $gosterilmeyecek_sozlesmeler, true ) && $ayarlar['obf_taslak_id'] > 0 ) { ?>
		<div id="in-obf-icerik-" class="white-popup mfp-hide">
			<?php echo wp_kses_post( $obf_content ); ?>
		</div>
	<?php } ?>

	<?php if ( ! in_array( 'mss', $gosterilmeyecek_sozlesmeler, true ) && $ayarlar['mss_taslak_id'] > 0 ) { ?>
		<div id="in-mss-icerik-" class="white-popup mfp-hide">
			<?php echo wp_kses_post( $mss_content ); ?>
		</div>
	<?php } ?>

	<?php if ( ! in_array( 'custom_1', $gosterilmeyecek_sozlesmeler, true ) && $ayarlar['ozel_sozlesme_1_taslak_id'] > 0 ) { ?>
		<div id="in-ozel-sozlesme-1-icerik-" class="white-popup mfp-hide">
		<?php echo wp_kses_post( $ozel_sozlesme_1_content ); ?>
		</div>
	<?php } ?>

	<?php if ( ! in_array( 'custom_2', $gosterilmeyecek_sozlesmeler, true ) && $ayarlar['ozel_sozlesme_2_taslak_id'] > 0 ) { ?>
		<div id="in-ozel-sozlesme-2-icerik-" class="white-popup mfp-hide">
		<?php echo wp_kses_post( $ozel_sozlesme_2_content ); ?>
		</div>
	<?php } ?>
</div>

<script>
	/** Default ödeme yöntemi **/
	var defaultPaymentMethod_ID = jQuery(".payment_methods input[name=payment_method]").attr('id');
	var defaultPaymentMethod_label = jQuery("label[for='"+ defaultPaymentMethod_ID +"']").text();
	var defaultPaymentMethod = jQuery.trim(defaultPaymentMethod_label);

	jQuery(".obf_mss_payment_method").replaceWith('<span class="obf_mss_payment_method">'+ defaultPaymentMethod +'</span>');

	jQuery( document ).ready(function() {

		jQuery('.open-popup-link').magnificPopup({
			type: 'inline',
			midClick: true,
			mainClass: 'mfp-fade'
		});

	});
</script>
