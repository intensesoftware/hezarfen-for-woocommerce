import { expect, test } from '@playwright/test';
import {
	addE2EProductToCart,
	expectMahalleAjax,
	pickFromSelect,
	waitForCheckoutIdle,
	TR_SAMPLE_ADDRESS,
} from './helpers/checkout';
import { deleteMuPlugin, writeMuPlugin } from './helpers/mu-plugin';
import { wp } from './helpers/wp-cli';
import {
	applyOptions,
	restoreOptions,
	snapshotOptions,
} from './helpers/wp-options';

/**
 * Regression: Hezarfen ↔ early checkout-fields build race.
 *
 * Reproduces the Advanced Dynamic Pricing (ADP) interaction reported in
 * production. ADP processes the cart on the `wp_loaded` hook:
 *
 *   Engine::firstTimeProcessCart → CartProcessor::process →
 *   WC_Cart_Totals → WC_Cart::calculate_shipping →
 *   WC_Cart::show_shipping → WC_Checkout::get_checkout_fields()
 *
 * That call makes WooCommerce build AND memoize the checkout field
 * definitions. Hezarfen registers its TR district/neighborhood field
 * transformation on the *later* `wp` hook, so by the time it runs the field
 * set is already cached and the İlçe (#billing_city) / Mahalle
 * (#billing_address_1) selects are never applied — they stay plain inputs.
 *
 * Only guests are affected (logged-in carts are not processed the same way
 * on `wp_loaded`), which matches the "broken in incognito, fine when logged
 * in as admin" report.
 *
 * The early `get_checkout_fields()` side effect is triggered deterministically
 * via a tiny fixture mu-plugin rather than by configuring an ADP pricing rule:
 * the mechanism — and therefore the regression guard — is identical and
 * version-independent. (Activating ADP alone does not reproduce it; ADP only
 * processes the cart when at least one pricing rule is enabled.)
 *
 * Expected: FAILS against current code (İlçe renders as <input>), PASSES once
 * Hezarfen registers its checkout-field filters before `wp_loaded`
 * (e.g. in the constructor or on `init` instead of `wp`).
 */
const FIXTURE_SLUG = 'hezarfen-e2e-early-checkout-fields';
const FIXTURE_OPTION = 'hezarfen_e2e_force_early_checkout_fields';

const FIXTURE_PHP = `<?php
/**
 * E2E fixture: emulate a plugin (e.g. Advanced Dynamic Pricing) that processes
 * the cart on wp_loaded and forces WC_Checkout::get_checkout_fields() to build
 * + memoize before Hezarfen's 'wp' hook runs. Inert unless the gating option is
 * set to 'yes'.
 */
add_action( 'wp_loaded', function () {
	if ( is_admin() ) {
		return;
	}
	if ( get_option( '${ FIXTURE_OPTION }' ) !== 'yes' ) {
		return;
	}
	if ( function_exists( 'WC' ) && WC()->checkout() ) {
		WC()->checkout()->get_checkout_fields();
	}
}, 5 );
`;

test.describe( 'Hezarfen checkout field race (ADP-style early build)', () => {
	test.beforeAll( () => {
		writeMuPlugin( FIXTURE_SLUG, FIXTURE_PHP );
		wp( [ 'option', 'update', FIXTURE_OPTION, 'yes' ] );
	} );

	test.afterAll( () => {
		wp( [ 'option', 'delete', FIXTURE_OPTION ], { allowFailure: true } );
		deleteMuPlugin( FIXTURE_SLUG );
	} );

	test.beforeEach( async ( { page } ) => {
		await addE2EProductToCart( page );
	} );

	test( 'İlçe stays a Hezarfen select dropdown despite an early get_checkout_fields() (guest)', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );

		await expect( page.locator( '#billing_country' ) ).toHaveValue( 'TR' );

		// The bug: the early get_checkout_fields() locks in WooCommerce's
		// default text input for billing_city, so Hezarfen never turns it into
		// the il-driven district <select>.
		await expect( page.locator( 'select#billing_city' ) ).toBeAttached();
		await expect( page.locator( 'input#billing_city' ) ).toHaveCount( 0 );

		// And it must still work end-to-end: picking the il triggers the
		// district AJAX and populates İlçe options.
		const districtPromise = expectMahalleAjax( page, 'district' );
		await pickFromSelect(
			page,
			'#billing_state',
			TR_SAMPLE_ADDRESS.cityPlate
		);
		await districtPromise;

		const districts = await page
			.locator( '#billing_city option' )
			.allTextContents();
		expect( districts ).toContain( TR_SAMPLE_ADDRESS.district );
	} );

	/**
	 * Same race, second potential symptom: field ORDER.
	 *
	 * `hezarfen_checkout_fields_auto_sort` reorders the TR billing fields
	 * into state (il) → city (ilçe) → address_1 (mahalle) → address_2 →
	 * postcode by attaching priority filters (woocommerce_get_country_locale
	 * / woocommerce_billing_fields) from Checkout::sort_checkout_fields →
	 * Helper::sort_address_fields, on the `wp` hook.
	 *
	 * Unlike the İlçe/Mahalle input→select transformation (whose memoized
	 * result PR #174 had to register early), the sort PRIORITIES are NOT
	 * locked in by an early WC_Checkout::get_checkout_fields() build:
	 * WooCommerce still applies the country-locale / billing-field priority
	 * filters when the classic checkout renders, so registering them on the
	 * later `wp` hook is fine. Verified directly under this fixture — with
	 * auto_sort off the billing order is WC default
	 * (address_1, address_2, postcode, city, state); with auto_sort on it is
	 * the canonical TR order below, even though ADP forced the early build.
	 *
	 * This test is a regression guard for that: if a future change ever makes
	 * the sort priorities susceptible to the same early-build race, the
	 * billing fields would fall back to the WC default order and this fails.
	 */
	test( 'billing alanları ADP erken build ile bile TR sırasında kalıyor (guest)', async ( {
		page,
	} ) => {
		const snap = snapshotOptions( [ 'hezarfen_checkout_fields_auto_sort' ] );
		applyOptions( { hezarfen_checkout_fields_auto_sort: 'yes' } );

		try {
			await page.goto( '/checkout/' );
			await waitForCheckoutIdle( page );

			await expect( page.locator( '#billing_country' ) ).toHaveValue(
				'TR'
			);

			// Read the rendered top-to-bottom order of the TR billing
			// fields. Canonical TR order is state → city → address_1 →
			// address_2; under the race they fall back to WC defaults
			// (address_1 before city/state).
			const order = await page.evaluate( () => {
				const ids = [
					'billing_state_field',
					'billing_city_field',
					'billing_address_1_field',
					'billing_address_2_field',
				];
				return ids
					.map( ( id ) => {
						const el = document.getElementById( id );
						return el
							? { id, top: el.getBoundingClientRect().top }
							: null;
					} )
					.filter( Boolean ) as { id: string; top: number }[];
			} );

			expect( order.length ).toBe( 4 );
			for ( let i = 1; i < order.length; i++ ) {
				expect
					.soft(
						order[ i ].top,
						`${ order[ i ].id } follows ${ order[ i - 1 ].id }`
					)
					.toBeGreaterThan( order[ i - 1 ].top );
			}
		} finally {
			restoreOptions( snap );
		}
	} );
} );
