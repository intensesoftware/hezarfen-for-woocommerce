import { expect, test, type Page } from '@playwright/test';
import { addE2EProductToCart, waitForCheckoutIdle } from './helpers/checkout';
import { wp } from './helpers/wp-cli';

/**
 * Regression: the district/neighborhood feature must be fully switch-off-able.
 *
 * Checkout::is_district_neighborhood_enabled() gates every TR İlçe/Mahalle
 * transformation:
 *
 *   return 'yes' === apply_filters(
 *     'hezarfen_enable_district_neighborhood_fields',
 *     get_option( 'hezarfen_enable_district_neighborhood_fields', 'yes' )
 *   );
 *
 * When the `hezarfen_enable_district_neighborhood_fields` option is set to
 * 'no', Hezarfen must leave WooCommerce's stock fields alone: #billing_city
 * (İlçe) and #billing_address_1 (Mahalle) stay plain text <input>s and never
 * become Hezarfen <select> dropdowns. If a refactor ever runs the field
 * transformation unconditionally, a store that deliberately turned the
 * feature off would suddenly get the il-driven selects back — this spec
 * catches that.
 *
 * The whole suite otherwise runs with the option forced to 'yes' (see
 * global-setup.ts), so we restore that default on teardown.
 */
const OPTION = 'hezarfen_enable_district_neighborhood_fields';

async function expectPlainAddressInputs( page: Page ) {
	await page.goto( '/checkout/' );
	await waitForCheckoutIdle( page );

	// Default country = TR (set by global-setup); only the disabled gate, not
	// a non-TR country, should keep these as plain inputs here.
	await expect( page.locator( '#billing_country' ) ).toHaveValue( 'TR' );

	await expect( page.locator( 'input#billing_city' ) ).toBeAttached();
	await expect( page.locator( 'input#billing_address_1' ) ).toBeAttached();
	await expect( page.locator( 'select#billing_city' ) ).toHaveCount( 0 );
	await expect( page.locator( 'select#billing_address_1' ) ).toHaveCount( 0 );
}

test.describe( 'Hezarfen İlçe/Mahalle option ile kapalıyken', () => {
	test.beforeAll( () => {
		wp( [ 'option', 'update', OPTION, 'no' ] );
	} );

	test.afterAll( () => {
		// Restore the suite-wide default from global-setup.
		wp( [ 'option', 'update', OPTION, 'yes' ] );
	} );

	test.beforeEach( async ( { page } ) => {
		await addE2EProductToCart( page );
	} );

	test( 'city/address_1 düz input kalır, select olmaz (option=no)', async ( {
		page,
	} ) => {
		await expectPlainAddressInputs( page );
	} );
} );
