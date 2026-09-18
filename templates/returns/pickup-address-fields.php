<?php
/**
 * Pickup address fields.
 *
 * Shared by the request form and the address correction form on an approved
 * request, so the two can never drift apart.
 *
 * Override at yourtheme/hezarfen/returns/pickup-address-fields.php.
 *
 * @package Hezarfen\Inc\Returns
 *
 * @var array<string, string> $address Current values, already normalized.
 */

use Hezarfen\Inc\Mahalle_Local;

defined( 'ABSPATH' ) || exit();

$hez_cities        = Mahalle_Local::get_cities();
$hez_districts     = $address['city_code'] ? Mahalle_Local::get_districts( $address['city_code'] ) : array();
$hez_neighborhoods = $address['city_code'] && $address['district']
	? Mahalle_Local::get_neighborhoods( $address['city_code'], $address['district'], false )
	: array();
?>
<div class="hez-address-fields" data-hez-address-fields>
	<p class="hez-field hez-field--first-name">
		<label for="hez-pickup-first-name"><?php esc_html_e( 'Ad', 'hezarfen-for-woocommerce' ); ?></label>
		<input type="text" id="hez-pickup-first-name" class="hez-input" name="pickup_address[first_name]" value="<?php echo esc_attr( $address['first_name'] ); ?>" autocomplete="given-name" required>
	</p>

	<p class="hez-field hez-field--last-name">
		<label for="hez-pickup-last-name"><?php esc_html_e( 'Soyad', 'hezarfen-for-woocommerce' ); ?></label>
		<input type="text" id="hez-pickup-last-name" class="hez-input" name="pickup_address[last_name]" value="<?php echo esc_attr( $address['last_name'] ); ?>" autocomplete="family-name" required>
	</p>

	<p class="hez-field hez-field--phone">
		<label for="hez-pickup-phone"><?php esc_html_e( 'Cep telefonu', 'hezarfen-for-woocommerce' ); ?></label>
		<input type="tel" id="hez-pickup-phone" class="hez-input" name="pickup_address[phone]" value="<?php echo esc_attr( $address['phone'] ); ?>" autocomplete="tel" inputmode="tel" placeholder="5xx xxx xx xx" required>
		<span class="hez-field__hint"><?php esc_html_e( 'Kurye alım için bu numaradan arayacak.', 'hezarfen-for-woocommerce' ); ?></span>
	</p>

	<p class="hez-field hez-field--city">
		<label for="hez-pickup-city"><?php esc_html_e( 'İl', 'hezarfen-for-woocommerce' ); ?></label>
		<select id="hez-pickup-city" class="hez-input hez-select" name="pickup_address[city_code]" data-hez-address-city required>
			<option value=""><?php esc_html_e( 'İl seçin', 'hezarfen-for-woocommerce' ); ?></option>
			<?php foreach ( $hez_cities as $hez_code => $hez_name ) : ?>
				<option value="<?php echo esc_attr( $hez_code ); ?>" <?php selected( $address['city_code'], $hez_code ); ?>><?php echo esc_html( $hez_name ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>

	<p class="hez-field hez-field--district">
		<label for="hez-pickup-district"><?php esc_html_e( 'İlçe', 'hezarfen-for-woocommerce' ); ?></label>
		<select id="hez-pickup-district" class="hez-input hez-select" name="pickup_address[district]" data-hez-address-district required>
			<option value=""><?php esc_html_e( 'İlçe seçin', 'hezarfen-for-woocommerce' ); ?></option>
			<?php foreach ( $hez_districts as $hez_district ) : ?>
				<option value="<?php echo esc_attr( $hez_district ); ?>" <?php selected( $address['district'], $hez_district ); ?>><?php echo esc_html( $hez_district ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>

	<p class="hez-field hez-field--neighborhood">
		<label for="hez-pickup-neighborhood"><?php esc_html_e( 'Mahalle', 'hezarfen-for-woocommerce' ); ?></label>
		<select id="hez-pickup-neighborhood" class="hez-input hez-select" name="pickup_address[neighborhood]" data-hez-address-neighborhood required>
			<option value=""><?php esc_html_e( 'Mahalle seçin', 'hezarfen-for-woocommerce' ); ?></option>
			<?php foreach ( $hez_neighborhoods as $hez_neighborhood ) : ?>
				<option value="<?php echo esc_attr( $hez_neighborhood ); ?>" <?php selected( $address['neighborhood'], $hez_neighborhood ); ?>><?php echo esc_html( $hez_neighborhood ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>

	<p class="hez-field hez-field--address">
		<label for="hez-pickup-address"><?php esc_html_e( 'Açık adres', 'hezarfen-for-woocommerce' ); ?></label>
		<textarea id="hez-pickup-address" class="hez-input hez-textarea" name="pickup_address[address]" rows="2" maxlength="255" placeholder="<?php esc_attr_e( 'Cadde, sokak, bina ve daire no', 'hezarfen-for-woocommerce' ); ?>" required><?php echo esc_textarea( $address['address'] ); ?></textarea>
	</p>
</div>
