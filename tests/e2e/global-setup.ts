import type { FullConfig } from '@playwright/test';
import { wp } from './helpers/wp-cli';

/**
 * Bring the LocalWP site into a known state for the e2e suite:
 *   - Storefront theme active
 *   - Hezarfen + WooCommerce plugins active
 *   - At least one published simple product available
 *   - Cash on Delivery (COD) gateway enabled
 *   - A flat-rate shipping method available for orders shipped to TR
 *   - Hezarfen contracts + TC ID requirement disabled so the checkout flow
 *     under test stays focused on il/ilçe/mahalle + order placement.
 */
export default async function globalSetup( _config: FullConfig ): Promise< void > {
	wp( [ 'theme', 'activate', 'storefront' ] );

	wp( [ 'plugin', 'activate', 'woocommerce', 'hezarfen-for-woocommerce' ] );

	wp( [ 'option', 'update', 'woocommerce_default_country', 'TR:TR06' ] );
	wp( [ 'option', 'update', 'woocommerce_currency', 'TRY' ] );
	wp( [ 'option', 'update', 'woocommerce_enable_guest_checkout', 'yes' ] );
	wp( [ 'option', 'update', 'woocommerce_enable_checkout_login_reminder', 'no' ] );
	wp( [ 'option', 'update', 'woocommerce_enable_signup_and_login_from_checkout', 'no' ] );
	// Make sure the storefront is publicly accessible during tests.
	wp( [ 'option', 'update', 'woocommerce_coming_soon', 'no' ] );
	wp( [ 'plugin', 'deactivate', 'coming-soon' ], { allowFailure: true } );

	wp( [ 'option', 'update', 'hezarfen_enable_district_neighborhood_fields', 'yes' ] );
	wp( [ 'option', 'update', 'hezarfen_contracts_enabled', 'no' ] );
	wp( [ 'option', 'update', 'hezarfen_checkout_show_TC_identity_field', 'no' ] );
	wp( [ 'option', 'update', 'hezarfen_checkout_is_TC_identity_number_field_required', 'no' ] );
	wp( [ 'option', 'update', 'hezarfen_show_hezarfen_checkout_tax_fields', 'no' ] );

	ensureCodEnabled();
	ensureFlatRateShipping();
	ensureTestProduct();
}

function ensureCodEnabled(): void {
	// Disable the other built-in offline gateways so COD is the sole
	// option on the checkout — that keeps the test deterministic and
	// avoids racing with whichever gateway WooCommerce decides to
	// pre-select.
	for ( const gw of [ 'bacs', 'cheque', 'paypal' ] ) {
		wp(
			[
				'wc',
				'payment_gateway',
				'update',
				gw,
				'--user=1',
				'--enabled=false',
			],
			{ allowFailure: true }
		);
	}
	wp( [
		'wc',
		'payment_gateway',
		'update',
		'cod',
		'--user=1',
		'--enabled=true',
		'--title=Cash on delivery (e2e)',
	] );
}

function ensureFlatRateShipping(): void {
	// One-shot setup: create / reuse an "E2E Türkiye" zone, attach country=TR,
	// add a flat_rate method, and write its settings. Doing this in a single
	// eval avoids parsing wc-cli output formats that vary across versions.
	wp( [
		'eval',
		`
			$zone = null;
			foreach ( WC_Shipping_Zones::get_zones() as $z ) {
				if ( $z['zone_name'] === 'E2E Türkiye' ) {
					$zone = new WC_Shipping_Zone( $z['zone_id'] );
					break;
				}
			}
			if ( ! $zone ) {
				$zone = new WC_Shipping_Zone();
				$zone->set_zone_name( 'E2E Türkiye' );
				$zone->set_zone_order( 1 );
				$zone->save();
			}
			$zone->set_locations( array( array( 'code' => 'TR', 'type' => 'country' ) ) );
			$zone->save();

			$instance_id = null;
			foreach ( $zone->get_shipping_methods( false, 'json' ) as $m ) {
				if ( $m->id === 'flat_rate' ) { $instance_id = $m->instance_id; break; }
			}
			if ( ! $instance_id ) {
				$instance_id = $zone->add_shipping_method( 'flat_rate' );
			}

			update_option(
				'woocommerce_flat_rate_' . $instance_id . '_settings',
				array( 'title' => 'Standart Kargo', 'cost' => '10', 'tax_status' => 'none' )
			);
			echo 'zone_id=' . $zone->get_id() . ' instance_id=' . $instance_id;
		`,
	] );
}

function ensureTestProduct(): void {
	const existing = wp( [
		'post',
		'list',
		'--post_type=product',
		'--name=hezarfen-e2e-product',
		'--field=ID',
		'--posts_per_page=1',
	] ).trim();
	if ( existing ) return;

	const productId = wp( [
		'post',
		'create',
		'--post_type=product',
		'--post_status=publish',
		'--post_title=Hezarfen E2E Product',
		'--post_name=hezarfen-e2e-product',
		'--porcelain',
	] ).trim();

	if ( ! productId ) {
		throw new Error( 'Failed to create e2e test product' );
	}

	wp( [ 'post', 'meta', 'update', productId, '_regular_price', '50' ] );
	wp( [ 'post', 'meta', 'update', productId, '_price', '50' ] );
	wp( [ 'post', 'meta', 'update', productId, '_stock_status', 'instock' ] );
	wp( [ 'post', 'meta', 'update', productId, '_virtual', 'no' ] );
	wp( [ 'post', 'meta', 'update', productId, '_downloadable', 'no' ] );
	wp( [ 'post', 'term', 'set', productId, 'product_type', 'simple' ] );
}
