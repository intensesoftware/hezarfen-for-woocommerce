<?php
/**
 * Ödeme ekranı sözleşme onay kutuları.
 *
 * @package Intense\MSS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hezarfen_mss_settings = get_option( 'hezarfen_mss_settings', array() );

$gosterilmeyecek_sozlesmeler = array_key_exists( 'gosterilmeyecek_sozlesmeler', $hezarfen_mss_settings ) ? $hezarfen_mss_settings['gosterilmeyecek_sozlesmeler'] : array();

$sozlesme_onay_checkbox_varsayilan_durum = isset($hezarfen_mss_settings['sozlesme_onay_checkbox_varsayilan_durum']) ? (int) $hezarfen_mss_settings['sozlesme_onay_checkbox_varsayilan_durum'] : 0;
$allowed_tags = [
	'a'=>[
		'href'=>true,
		'class'=>true
	],
];
?>

<div class="woocommerce-terms-and-conditions-wrapper in-sozlesme-onay-checkboxes">

	<?php if( ! in_array( 'obf', $gosterilmeyecek_sozlesmeler, true ) ): ?>
	<p class="form-row in-sozlesme-onay-checkbox validate-required">

		<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
			<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="intense_obf_onay_checkbox"  
			<?php
			if ( 1 === $sozlesme_onay_checkbox_varsayilan_durum ) {
				echo 'checked';}
			?>
			/>
			<span class="woocommerce-terms-and-conditions-checkbox-text">
				<?php echo wp_kses( sprintf( __('I agree to <a href="%s" class="%s">the preliminary information form</a>.', 'intense-mss-for-woocommerce'), '#in-obf-icerik-', 'checkout-sozlesme-modal-buton open-popup-link'), $allowed_tags ); ?>
			</span>
			<span class="required">*</span>
		</label>
	</p>
	<?php endif; ?>

	<?php if( ! in_array( 'mss', $gosterilmeyecek_sozlesmeler, true ) ): ?>
	<p class="form-row in-sozlesme-onay-checkbox validate-required">

		<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
			<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="intense_mss_onay_checkbox" 
			<?php
			if ( 1 === $sozlesme_onay_checkbox_varsayilan_durum ) {
				echo 'checked';}
			?>
			/>
			<span class="woocommerce-terms-and-conditions-checkbox-text">
				<?php echo wp_kses( sprintf( __('I agree to <a href="%s" class="%s">the distance sale agreement</a>.', 'intense-mss-for-woocommerce'), '#in-mss-icerik-', 'checkout-sozlesme-modal-buton open-popup-link'), $allowed_tags ); ?>
			</span>
			<span class="required">*</span>
		</label>
	</p>
	<?php endif; ?>

	<?php if ( ! in_array( 'custom_1', $gosterilmeyecek_sozlesmeler, true ) && $hezarfen_mss_settings['ozel_sozlesme_1_taslak_id'] > 0 ):
		$ozel_sozleme_1_post = get_post( apply_filters( 'wpml_object_id', $hezarfen_mss_settings['ozel_sozlesme_1_taslak_id'], 'intense_mss_form', true ) );
	?>
	<p class="form-row in-sozlesme-onay-checkbox validate-required">

		<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
			<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="intense_ozel_sozlesme_1_onay_checkbox" 
			<?php
			if ( 1 === $sozlesme_onay_checkbox_varsayilan_durum ) {
				echo 'checked';}
			?>
			/>
			<span class="woocommerce-terms-and-conditions-checkbox-text">
				<?php echo wp_kses( sprintf( __('I agree to the <a href="%s" class="%s">%s</a>.', 'intense-mss-for-woocommerce'), '#in-ozel-sozlesme-1-icerik-', 'checkout-sozlesme-modal-buton open-popup-link', $ozel_sozleme_1_post->post_title), $allowed_tags ); ?>
			</span>
			<span class="required">*</span>
		</label>

	</p>
	<?php
		endif;
	?>

	<?php if ( ! in_array( 'custom_2', $gosterilmeyecek_sozlesmeler, true ) && $hezarfen_mss_settings['ozel_sozlesme_2_taslak_id'] > 0 ):
		$ozel_sozleme_2_post = get_post( apply_filters( 'wpml_object_id', $hezarfen_mss_settings['ozel_sozlesme_2_taslak_id'], 'intense_mss_form', true ) );
	?>
	<p class="form-row in-sozlesme-onay-checkbox validate-required">

		<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
			<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="intense_ozel_sozlesme_2_onay_checkbox" 
			<?php
			if ( 1 === $sozlesme_onay_checkbox_varsayilan_durum ) {
				echo 'checked';}
			?>
			/>
			<span class="woocommerce-terms-and-conditions-checkbox-text">
				<?php echo wp_kses( sprintf( __('I agree to the <a href="%s" class="%s">%s</a>.', 'intense-mss-for-woocommerce'), '#in-ozel-sozlesme-2-icerik-', 'checkout-sozlesme-modal-buton open-popup-link', $ozel_sozleme_2_post->post_title), $allowed_tags ); ?>
			</span>
			<span class="required">*</span>
		</label>

	</p>
	<?php
		endif;
	?>

</div>
