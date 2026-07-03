import { expect, test } from '@playwright/test';
import {
	expectCheckoutUpdate,
	fillTrAddressChain,
	pickFromSelect,
	TR_SAMPLE_ADDRESS,
	waitForCheckoutIdle,
} from './helpers/checkout';
import {
	applyOptions,
	restoreOptions,
	snapshotOptions,
} from './helpers/wp-options';
import { wp, wpBash } from './helpers/wp-cli';
import {
	fillTrBlockAddress,
	getLatestOrderHezData,
	placeBlockOrder,
	restoreCheckoutToClassic,
	setCheckoutToBlock,
	waitForBlockCheckoutReady,
} from './helpers/block-checkout';

/**
 * Cross-version coverage for Hezarfen on the WooCommerce block checkout page.
 *
 * The block checkout renders differently depending on the WooCommerce version,
 * and Hezarfen has to work on all of them:
 *
 *   - < 8.3   : the block checkout is not production-ready, so the page falls
 *               back to the classic checkout — Hezarfen's classic fields
 *               (added via `woocommerce_checkout_fields`) must still work.
 *   - 8.3–9.1 : the React block checkout renders; Hezarfen's block fields work
 *               (this is the band the WC-8.3 `setExtensionData` fix targets).
 *   - 9.2+    : same block fields, native field styling.
 *
 * For each band we assert (1) a healthy TR order can be placed with the
 * il/ilçe/mahalle + company invoice fields filled and persisted, and (2) the
 * il/ilçe/mahalle and invoice fields disappear when their features are turned
 * off.
 *
 * Each band installs its own WooCommerce version, so this suite is slow by
 * design; WooCommerce is restored to the latest version at the end.
 */

const FEATURE_OPTIONS = {
	hezarfen_enable_district_neighborhood_fields: 'yes',
	hezarfen_show_hezarfen_checkout_tax_fields: 'yes',
	hezarfen_checkout_show_TC_identity_field: 'yes',
	hezarfen_checkout_is_TC_identity_number_field_required: 'yes',
};

// Latest WooCommerce version to restore after the matrix; also the "9.2+" band.
const LATEST_WC = '10.9.1';

function installWooCommerce( version: string ): void {
	// Deactivate WooCommerce (and Hezarfen, which depends on it) before swapping
	// the files, so wp-cli doesn't bootstrap the half-replaced WooCommerce and
	// fatal on its autoloader mid-swap.
	wp(
		[ 'plugin', 'deactivate', 'hezarfen-for-woocommerce', 'woocommerce' ],
		{ allowFailure: true }
	);

	// Normal install: works on LocalWP and populates wp-cli's download cache.
	// Under wp-env this fails at the "remove old version" step because the
	// WooCommerce directory is a bind mount — allow that and swap in place below.
	wp( [ 'plugin', 'install', 'woocommerce', `--version=${ version }`, '--force' ], {
		allowFailure: true,
	} );

	const current = wp( [ 'plugin', 'get', 'woocommerce', '--field=version' ], {
		allowFailure: true,
	} ).trim();

	if ( current !== version ) {
		// Replace the contents of the (bind-mounted) WooCommerce dir from the
		// cached zip the install just downloaded, without removing the mount.
		wpBash(
			`set -e
			WC=/var/www/html/wp-content/plugins/woocommerce
			cd /tmp && rm -rf wc-swap && mkdir wc-swap
			unzip -qo "$HOME/.wp-cli/cache/plugin/woocommerce-${ version }.zip" -d wc-swap
			find "$WC" -mindepth 1 -delete
			cp -a wc-swap/woocommerce/. "$WC"/`
		);
	}

	wp( [ 'plugin', 'activate', 'woocommerce', 'hezarfen-for-woocommerce' ] );
}

const SCENARIOS = [
	{
		title: 'WooCommerce 8.2 (< 8.3 → classic checkout, the supported path)',
		wc: '8.2.2',
		mode: 'classic' as const,
	},
	{
		title: 'WooCommerce 8.3 (block checkout, legacy field styling)',
		wc: '8.3.1',
		mode: 'block' as const,
	},
	{
		title: 'WooCommerce 10.9 (9.2+, block checkout, native styling)',
		wc: LATEST_WC,
		mode: 'block' as const,
	},
];

// This spec swaps WooCommerce versions in place, which leaves the shared wp-env
// instance in a state the rest of the suite can't rely on. It is therefore
// opt-in: run it on its own (e.g. `npm run test:e2e:versions`) against a
// disposable environment, and recreate wp-env afterwards.
const RUN_MATRIX = process.env.HEZARFEN_E2E_VERSION_MATRIX === '1';

if ( ! RUN_MATRIX ) {
	test.skip(
		'WooCommerce version matrix (opt-in — set HEZARFEN_E2E_VERSION_MATRIX=1)',
		() => {}
	);
} else {
	// Restore WooCommerce to the latest version once the matrix is done.
	test.afterAll( () => {
		installWooCommerce( LATEST_WC );
	} );
}

for ( const scenario of RUN_MATRIX ? SCENARIOS : [] ) {
	test.describe( `Hezarfen block checkout — ${ scenario.title }`, () => {
		let snapshot: Record< string, string >;
		let productId: string;

		test.beforeAll( () => {
			installWooCommerce( scenario.wc );
			snapshot = snapshotOptions( Object.keys( FEATURE_OPTIONS ) );
			applyOptions( FEATURE_OPTIONS );
			// Block fields require WooCommerce 8.3+, so the supported checkout for
			// < 8.3 is the classic one; 8.3+ uses the block checkout.
			if ( scenario.mode === 'block' ) {
				setCheckoutToBlock();
			} else {
				restoreCheckoutToClassic();
			}
			productId = wp( [
				'eval',
				`$p = wc_get_products( array( 'limit' => 1, 'status' => 'publish' ) ); echo $p ? $p[0]->get_id() : '';`,
			] ).trim();
		} );

		test.afterAll( () => {
			restoreCheckoutToClassic();
			restoreOptions( snapshot );
		} );

		// Add the seeded product via the add-to-cart URL — version-agnostic,
		// unlike the storefront "added to cart" notice markup.
		test.beforeEach( async ( { page } ) => {
			await page.goto( `/?add-to-cart=${ productId }` );
		} );

		test( 'places a healthy TR order with il/ilçe/mahalle + company invoice fields', async ( {
			page,
		} ) => {
			await page.goto( '/checkout/' );

			if ( scenario.mode === 'block' ) {
				await waitForBlockCheckoutReady( page );

				await fillTrBlockAddress( page );

				await page
					.locator( '#hezarfen-invoice-type' )
					.selectOption( 'company' );
				await page
					.locator( '#hezarfen-company-title' )
					.fill( 'Hezarfen Test A.Ş.' );
				await page.locator( '#hezarfen-tax-number' ).fill( '1234567890' );
				await page.locator( '#hezarfen-tax-office' ).fill( 'Kadıköy' );

				await placeBlockOrder( page );
			} else {
				await waitForCheckoutIdle( page );

				await fillTrAddressChain( page, {
					type: 'billing',
					cityPlate: TR_SAMPLE_ADDRESS.cityPlate,
					district: TR_SAMPLE_ADDRESS.district,
					neighborhood: TR_SAMPLE_ADDRESS.neighborhood,
				} );

				await page.locator( '#billing_first_name' ).fill( 'Ada' );
				await page.locator( '#billing_last_name' ).fill( 'Lovelace' );
				await page.locator( '#billing_email' ).fill( 'ada@example.test' );
				await page.locator( '#billing_phone' ).fill( '5551112233' );

				const postcode = page.locator( '#billing_postcode' );
				if ( await postcode.isVisible() ) {
					await postcode.fill( TR_SAMPLE_ADDRESS.postcode );
				}
				await page
					.locator( '#billing_address_2' )
					.fill( TR_SAMPLE_ADDRESS.street );
				await page.locator( '#billing_address_2' ).blur();
				await expectCheckoutUpdate( page ).catch( () => {} );

				await pickFromSelect( page, '#hezarfen_invoice_type', 'company' );
				await page
					.locator( '#billing_company' )
					.fill( 'Hezarfen Test A.Ş.' );
				await page.locator( '#hezarfen_tax_number' ).fill( '1234567890' );
				await page
					.locator( '#hezarfen_tax_office' )
					.fill( TR_SAMPLE_ADDRESS.district );

				await waitForCheckoutIdle( page );
				await page
					.locator( '#payment_method_cod' )
					.check( { force: true } );
				await waitForCheckoutIdle( page );
				await page.locator( '#place_order' ).click();
			}

			await page.waitForURL( /order-received/, { timeout: 45_000 } );

			const order = getLatestOrderHezData();
			expect( order.invoice_type ).toBe( 'company' );
			expect( order.tax_number ).toBe( '1234567890' );
			// İlçe → core city, mahalle → core address_1 (same meta keys as classic).
			expect( order.city.length ).toBeGreaterThan( 0 );
			expect( order.address_1.length ).toBeGreaterThan( 0 );
		} );

		test( 'hides the il/ilçe/mahalle and invoice fields when they are disabled', async ( {
			page,
		} ) => {
			const disabledSnapshot = snapshotOptions( [
				'hezarfen_enable_district_neighborhood_fields',
				'hezarfen_show_hezarfen_checkout_tax_fields',
			] );
			applyOptions( {
				hezarfen_enable_district_neighborhood_fields: 'no',
				hezarfen_show_hezarfen_checkout_tax_fields: 'no',
			} );

			try {
				await page.goto( '/checkout/' );

				if ( scenario.mode === 'block' ) {
					await waitForBlockCheckoutReady( page );
					await expect(
						page.locator( '.hezarfen-checkout-fields--address' )
					).toHaveCount( 0 );
					await expect(
						page.locator( '#hezarfen-invoice-type' )
					).toHaveCount( 0 );
				} else {
					await waitForCheckoutIdle( page );
					await expect(
						page.locator( '#hezarfen_invoice_type' )
					).toHaveCount( 0 );
					// District/neighborhood off → the ilçe field is a plain text
					// input again, not a Hezarfen district <select>.
					await expect(
						page.locator( 'select#billing_city' )
					).toHaveCount( 0 );
				}
			} finally {
				restoreOptions( disabledSnapshot );
			}
		} );
	} );
}
