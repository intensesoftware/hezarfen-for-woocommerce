import { expect, test } from '@playwright/test';
import { addE2EProductToCart } from './helpers/checkout';
import {
	applyOptions,
	restoreOptions,
	snapshotOptions,
} from './helpers/wp-options';
import {
	clickPlaceOrderOnce,
	fillTrBlockAddress,
	getLatestOrderHezData,
	hezAddressGroup,
	pickCombobox,
	pickComboboxFirstOption,
	placeBlockOrder,
	restoreCheckoutToClassic,
	setCheckoutCountry,
	setCheckoutToBlock,
	waitForBlockCheckoutReady,
} from './helpers/block-checkout';

/**
 * E2E coverage for Hezarfen on the WooCommerce **block (Gutenberg) checkout** —
 * the path added by the checkout-blocks PR. This is intentionally a separate
 * spec from the classic checkout suite: the block checkout swaps the page
 * content, mounts a React integration and persists invoice/tax fields through
 * the Store API rather than the classic `woocommerce_checkout_fields` filter.
 *
 * What we assert:
 *   1. Hezarfen's il/ilçe/mahalle comboboxes render on the block checkout.
 *   2. The cascade works: province → district (inline map) → neighborhood (REST).
 *   3. The locations REST endpoint backing the cascade returns data.
 *   4. The invoice-type selector toggles person (TC) vs company (tax) fields.
 *   5. A full company-invoice TR order persists the same `_billing_hez_*` meta
 *      + core city/address_1 mapping the classic flow uses.
 *   6. A full person-invoice TR order persists the TC number encrypted.
 *   7. An invalid TC number blocks the order (validation, stays on checkout).
 *   8. Switching to a non-TR country hides the Hezarfen address fields.
 */

const FEATURE_OPTIONS = {
	hezarfen_enable_district_neighborhood_fields: 'yes',
	hezarfen_show_hezarfen_checkout_tax_fields: 'yes',
	hezarfen_checkout_show_TC_identity_field: 'yes',
	hezarfen_checkout_is_TC_identity_number_field_required: 'yes',
};

const PROVINCE_CLASS = 'wc-block-components-address-form__hez-province';
const DISTRICT_CLASS = 'wc-block-components-address-form__hez-district';
const NEIGHBORHOOD_CLASS = 'wc-block-components-address-form__hez-neighborhood';

let snapshot: Record< string, string >;

test.describe( 'Hezarfen block (Gutenberg) checkout', () => {
	test.beforeAll( () => {
		snapshot = snapshotOptions( Object.keys( FEATURE_OPTIONS ) );
		applyOptions( FEATURE_OPTIONS );
		setCheckoutToBlock();
	} );

	test.afterAll( () => {
		restoreCheckoutToClassic();
		restoreOptions( snapshot );
	} );

	test.beforeEach( async ( { page } ) => {
		await addE2EProductToCart( page );
	} );

	test( 'renders Hezarfen comboboxes and hides the redundant core fields under TR', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForBlockCheckoutReady( page );

		const group = hezAddressGroup( page );
		await expect( group ).toBeVisible();
		await expect(
			group.locator( `.${ PROVINCE_CLASS } .hezarfen-combobox__input` )
		).toBeVisible();
		await expect(
			group.locator( `.${ DISTRICT_CLASS } .hezarfen-combobox__input` )
		).toBeVisible();
		await expect(
			group.locator( `.${ NEIGHBORHOOD_CLASS } .hezarfen-combobox__input` )
		).toBeVisible();

		// TR is the default country, so the body flag that hides the core
		// State / City / Address line 1 inputs should be set.
		await expect( page.locator( 'body' ) ).toHaveClass(
			/hezarfen-tr-checkout/
		);
	} );

	test( 'il → ilçe → mahalle cascade populates each level', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForBlockCheckoutReady( page );

		// İl (province) comes from the inline data map.
		await pickCombobox( page, PROVINCE_CLASS, {
			query: 'İstanbul',
			optionText: /İstanbul/,
		} );

		// Selecting the district triggers the mahalle REST fetch, so register the
		// listener *before* the click (a fast response could otherwise outrun us).
		const neighborhoodResponse = page.waitForResponse(
			( res ) =>
				res.url().includes( '/hezarfen/v1/neighborhoods' ) &&
				res.status() === 200,
			{ timeout: 15_000 }
		);
		// İlçe (district) options are populated client-side from the inline map.
		await pickCombobox( page, DISTRICT_CLASS, {
			query: 'Kadıköy',
			optionText: /Kadıköy/,
		} );

		// Mahalle (neighborhood) options arrive over REST — wait for the call,
		// then pick whatever the endpoint returned.
		await neighborhoodResponse;
		const picked = await pickComboboxFirstOption( page, NEIGHBORHOOD_CLASS );
		expect( picked.length ).toBeGreaterThan( 0 );
	} );

	test( 'locations REST endpoint returns districts for a province', async ( {
		page,
	} ) => {
		const response = await page.request.get(
			'/wp-json/hezarfen/v1/districts?city=TR34'
		);
		expect( response.ok() ).toBeTruthy();
		const districts = await response.json();
		expect( Array.isArray( districts ) ).toBeTruthy();
		expect( districts.length ).toBeGreaterThan( 0 );
		// Each entry is a { value, label } option.
		expect( districts[ 0 ] ).toHaveProperty( 'value' );
		expect( districts[ 0 ] ).toHaveProperty( 'label' );
	} );

	test( 'invoice type toggles TC (person) vs Vergi (company) fields', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForBlockCheckoutReady( page );

		const invoiceType = page.locator( '#hezarfen-invoice-type' );
		await expect( invoiceType ).toBeVisible();

		// Person → TC field visible, company fields absent.
		await invoiceType.selectOption( 'person' );
		await expect( page.locator( '#hezarfen-tc-number' ) ).toBeVisible();
		await expect( page.locator( '#hezarfen-tax-number' ) ).toHaveCount( 0 );
		await expect( page.locator( '#hezarfen-tax-office' ) ).toHaveCount( 0 );

		// Company → TC absent, tax number + tax office visible. (The company name
		// uses WooCommerce's own billing company field, not a Hezarfen field.)
		await invoiceType.selectOption( 'company' );
		await expect( page.locator( '#hezarfen-tc-number' ) ).toHaveCount( 0 );
		await expect( page.locator( '#hezarfen-tax-number' ) ).toBeVisible();
		await expect( page.locator( '#hezarfen-tax-office' ) ).toBeVisible();
	} );

	test( 'placing a company-invoice TR order persists Hezarfen meta', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForBlockCheckoutReady( page );

		await fillTrBlockAddress( page );

		// Company name goes in WooCommerce's own company field (Hezarfen makes it
		// available on the block checkout when tax fields are on).
		await page.locator( '#shipping-company' ).fill( 'Hezarfen Test A.Ş.' );

		// Company invoice.
		await page.locator( '#hezarfen-invoice-type' ).selectOption( 'company' );
		await page.locator( '#hezarfen-tax-number' ).fill( '1234567890' );
		await page.locator( '#hezarfen-tax-office' ).fill( 'Kadıköy' );

		await placeBlockOrder( page );

		await page.waitForURL( /order-received/, { timeout: 45_000 } );

		const order = getLatestOrderHezData();
		expect( order.invoice_type ).toBe( 'company' );
		expect( order.tax_number ).toBe( '1234567890' );
		expect( order.tax_office ).toBe( 'Kadıköy' );
		// Company name persisted natively (survives the ship→bill sync).
		expect( order.company ).toBe( 'Hezarfen Test A.Ş.' );
		// District → core city, neighborhood → core address_1.
		expect( order.city ).toBe( 'Kadıköy' );
		expect( order.address_1.length ).toBeGreaterThan( 0 );
	} );

	test( 'placing a person-invoice TR order persists the encrypted TC number', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForBlockCheckoutReady( page );

		await fillTrBlockAddress( page );

		// Personal invoice with a valid (11-digit) TC number.
		await page.locator( '#hezarfen-invoice-type' ).selectOption( 'person' );
		await page.locator( '#hezarfen-tc-number' ).fill( '12345678901' );

		await placeBlockOrder( page );

		await page.waitForURL( /order-received/, { timeout: 45_000 } );

		const order = getLatestOrderHezData();
		expect( order.invoice_type ).toBe( 'person' );
		// Stored ciphertext must not be the plain value…
		expect( order.tc_number.length ).toBeGreaterThan( 0 );
		expect( order.tc_number ).not.toBe( '12345678901' );
		// …but it must decrypt back to what we entered.
		expect( order.tc_decrypted ).toBe( '12345678901' );
		// Company meta must not linger on a personal order.
		expect( order.tax_number ).toBe( '' );
		expect( order.tax_office ).toBe( '' );
	} );

	test( 'an invalid TC number blocks the order on the block checkout', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForBlockCheckoutReady( page );

		await fillTrBlockAddress( page );

		await page.locator( '#hezarfen-invoice-type' ).selectOption( 'person' );
		// 10 digits — invalid (TC must be 11).
		await page.locator( '#hezarfen-tc-number' ).fill( '1234567890' );

		await clickPlaceOrderOnce( page );

		// The field-level validation error is surfaced and the order is not
		// placed — we stay on the checkout page.
		await expect(
			page.locator( '.wc-block-components-validation-error' ).first()
		).toBeVisible( { timeout: 10_000 } );
		await expect( page ).not.toHaveURL( /order-received/ );
	} );

	test( 'a non-TR store country renders no Hezarfen address fields', async ( {
		page,
	} ) => {
		// AddressFields only mounts when the address country is TR. Assert the
		// gating at initial render with a non-TR default country (rather than
		// switching at runtime, which exercises a different code path).
		const countrySnapshot = snapshotOptions( [
			'woocommerce_default_country',
		] );
		applyOptions( { woocommerce_default_country: 'DE' } );

		try {
			await page.goto( '/checkout/' );
			await waitForBlockCheckoutReady( page );

			await expect(
				page.locator(
					'.wc-block-components-address-form__hez-province .hezarfen-combobox__input:visible'
				)
			).toHaveCount( 0 );
			await expect( page.locator( 'body' ) ).not.toHaveClass(
				/hezarfen-tr-checkout/
			);
		} finally {
			restoreOptions( countrySnapshot );
		}
	} );

	test( 'switching away from TR at runtime removes the Hezarfen address fields', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForBlockCheckoutReady( page );

		// TR is the default → the İl combobox is visible.
		await expect(
			page.locator(
				'.wc-block-components-address-form__hez-province .hezarfen-combobox__input:visible'
			)
		).toHaveCount( 1 );

		// Switch the country to a non-TR one. AddressFields must unmount — the
		// İl combobox must not linger orphaned in the form.
		await setCheckoutCountry( page, 'Germany' );

		await expect(
			page.locator(
				'.wc-block-components-address-form__hez-province .hezarfen-combobox__input:visible'
			)
		).toHaveCount( 0 );
		await expect( page.locator( 'body' ) ).not.toHaveClass(
			/hezarfen-tr-checkout/
		);
	} );

	test( 'the Store API rejects an invalid tax number server-side', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForBlockCheckoutReady( page );

		await fillTrBlockAddress( page );

		// Fill a *valid* company invoice so the client-side validation passes and
		// the request is actually sent.
		await page.locator( '#hezarfen-invoice-type' ).selectOption( 'company' );
		await page.locator( '#hezarfen-tax-number' ).fill( '1234567890' );
		await page.locator( '#hezarfen-tax-office' ).fill( 'Kadıköy' );

		// Tamper the checkout request in flight so the value that reaches the
		// server is invalid — this exercises Hezarfen_Store_API's own validation,
		// which the client would otherwise never let us reach.
		await page.route( /\/wc\/store\/v1\/checkout/, async ( route ) => {
			if ( route.request().method() !== 'POST' ) {
				await route.continue();
				return;
			}
			const body = route.request().postDataJSON();
			if ( body?.extensions?.hezarfen ) {
				body.extensions.hezarfen.tax_number = 'not-a-number';
			}
			await route.continue( { postData: JSON.stringify( body ) } );
		} );

		const responsePromise = page.waitForResponse(
			( r ) =>
				/\/wc\/store\/v1\/checkout/.test( r.url() ) &&
				r.request().method() === 'POST'
		);
		await placeBlockOrder( page );
		const response = await responsePromise;

		expect( response.status() ).toBe( 400 );
		const errorBody = await response.json();
		expect( errorBody.code ).toBe( 'hezarfen_tax_number_invalid' );
		await expect( page ).not.toHaveURL( /order-received/ );
	} );

	test( 'renders the Hezarfen fields on the billing form when shipping to a different address', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForBlockCheckoutReady( page );

		// With "use same address" on, only the shipping form carries the fields.
		await expect(
			page.locator( '#hezarfen-shipping-province' )
		).toBeVisible();
		await expect(
			page.locator( '#hezarfen-billing-province' )
		).toHaveCount( 0 );

		// Unchecking it reveals a separate billing address form, which must get
		// its own Hezarfen fields (the block injects into the billing block too).
		await page.getByLabel( /use same address for billing/i ).uncheck();

		await expect(
			page.locator( '#hezarfen-billing-province' )
		).toBeVisible();
	} );
} );
