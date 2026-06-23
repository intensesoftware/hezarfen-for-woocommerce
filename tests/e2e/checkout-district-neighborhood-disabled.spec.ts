import { expect, test, type Page } from '@playwright/test';
import { addE2EProductToCart, waitForCheckoutIdle } from './helpers/checkout';
import { deleteMuPlugin, writeMuPlugin } from './helpers/mu-plugin';
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
 * When it returns false Hezarfen must leave WooCommerce's stock fields alone:
 * #billing_city (İlçe) and #billing_address_1 (Mahalle) stay plain text
 * <input>s and never become Hezarfen <select> dropdowns. If a refactor ever
 * runs the field transformation unconditionally, a store that deliberately
 * turned the feature off would suddenly get the il-driven selects back — this
 * spec catches that.
 *
 * Both off-switches are covered because both feed the same gate:
 *   1. the `hezarfen_enable_district_neighborhood_fields` option set to 'no'
 *   2. a third-party `hezarfen_enable_district_neighborhood_fields` filter
 *      forcing 'no' while the option stays at its default 'yes'
 *
 * The whole suite otherwise runs with the option forced to 'yes' (see
 * global-setup.ts), so each block restores that default on teardown.
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

const FILTER_SLUG = 'hezarfen-e2e-disable-district-neighborhood-filter';
const FILTER_PHP = `<?php
/**
 * E2E fixture: a third-party plugin/theme disabling Hezarfen's TR
 * district/neighborhood fields purely via the public filter, leaving the
 * option at its default 'yes'. Exercises the filter arm of
 * Checkout::is_district_neighborhood_enabled().
 */
add_filter( 'hezarfen_enable_district_neighborhood_fields', function () {
	return 'no';
} );
`;

test.describe( 'Hezarfen İlçe/Mahalle filter ile kapatılınca', () => {
	test.beforeAll( () => {
		writeMuPlugin( FILTER_SLUG, FILTER_PHP );
	} );

	test.afterAll( () => {
		deleteMuPlugin( FILTER_SLUG );
	} );

	test.beforeEach( async ( { page } ) => {
		await addE2EProductToCart( page );
	} );

	test( 'city/address_1 düz input kalır, select olmaz (filter=no)', async ( {
		page,
	} ) => {
		await expectPlainAddressInputs( page );
	} );
} );
