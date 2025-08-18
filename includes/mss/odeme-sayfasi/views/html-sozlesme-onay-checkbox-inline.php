<?php
/**
 * Inline (popup olmayan) sözleşmeler için onay kutuları.
 *
 * @package Intense\MSS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$intense_mss_ayarlar = get_option( 'intense_mss_ayarlar', array() );

$sozlesme_onay_checkbox_varsayilan_durum = (int) $intense_mss_ayarlar['sozlesme_onay_checkbox_varsayilan_durum'];

$gosterilmeyecek_sozlesmeler = array_key_exists( 'gosterilmeyecek_sozlesmeler', $ayarlar ) ? $ayarlar['gosterilmeyecek_sozlesmeler'] : array();
?>

<div class="in-sozlesme-onay-checkboxes">
	<?php if( ! in_array( 'obf', $gosterilmeyecek_sozlesmeler, true ) ): ?>
	<p class="form-row in-sozlesme-onay-checkbox">
		<input type="checkbox" name="intense_obf_onay_checkbox"  
		<?php
		if ( 1 === $sozlesme_onay_checkbox_varsayilan_durum ) {
			echo 'checked';}
		?>
		/> <span><?php esc_html_e( 'I agree to the preliminary information form.', 'intense-mss-for-woocommerce' ); ?></span>
	</p>
	<?php endif; ?>
	<?php if( ! in_array( 'mss', $gosterilmeyecek_sozlesmeler, true ) ): ?>
	<p class="form-row in-sozlesme-onay-checkbox">
		<input type="checkbox" name="intense_mss_onay_checkbox" 
		<?php
		if ( 1 === $sozlesme_onay_checkbox_varsayilan_durum ) {
			echo 'checked';}
		?>
		/> <span><?php esc_html_e( 'I agree to the distance sale agreement.', 'intense-mss-for-woocommerce' ); ?></span>
	</p>
	<?php endif; ?>
	<?php if ( ! in_array( 'custom_1', $gosterilmeyecek_sozlesmeler, true ) && $ayarlar['ozel_sozlesme_1_taslak_id'] > 0 ) {
		$ozel_sozleme_1_post = get_post( apply_filters( 'wpml_object_id', $ayarlar['ozel_sozlesme_1_taslak_id'], 'mss', true ) );
	?>
		<p class="form-row in-sozlesme-onay-checkbox">
			<input type="checkbox" name="intense_ozel_sozlesme_1_onay_checkbox"  />
			<span><?php echo esc_html( sprintf( __( 'I agree to the %s.', 'intense-mss-for-woocommerce' ), $ozel_sozleme_1_post->post_title ) ); ?></span>
		</p>
	<?php } ?>
	<?php if ( ! in_array( 'custom_2', $gosterilmeyecek_sozlesmeler, true ) && $ayarlar['ozel_sozlesme_2_taslak_id'] > 0 ) {
		$ozel_sozleme_2_post = get_post( apply_filters( 'wpml_object_id', $ayarlar['ozel_sozlesme_2_taslak_id'], 'mss', true ) );
	?>
		<p class="form-row in-sozlesme-onay-checkbox">
			<input type="checkbox" name="intense_ozel_sozlesme_2_onay_checkbox"  />
			<span><?php echo esc_html( sprintf( __( 'I agree to the %s.', 'intense-mss-for-woocommerce' ), $ozel_sozleme_2_post->post_title ) ); ?></span>
		</p>
	<?php } ?>
</div>
