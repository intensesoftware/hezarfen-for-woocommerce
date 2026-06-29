import { expect, test } from '@playwright/test';
import { addE2EProductToCart } from './helpers/checkout';
import {
	applyOptions,
	restoreOptions,
	snapshotOptions,
} from './helpers/wp-options';
import {
	fillBlockField,
	getLatestOrderHezData,
	hezAddressGroup,
	pickCombobox,
	pickComboboxFirstOption,
	placeBlockOrder,
	restoreCheckoutToClassic,
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
 *   5. A full TR order placed through the block checkout persists the same
 *      `_billing_hez_*` meta + core city/address_1 mapping the classic flow uses.
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

		// Company → TC absent, company title + tax number + tax office visible.
		await invoiceType.selectOption( 'company' );
		await expect( page.locator( '#hezarfen-tc-number' ) ).toHaveCount( 0 );
		await expect( page.locator( '#hezarfen-company-title' ) ).toBeVisible();
		await expect( page.locator( '#hezarfen-tax-number' ) ).toBeVisible();
		await expect( page.locator( '#hezarfen-tax-office' ) ).toBeVisible();
	} );

	test( 'placing a company-invoice TR order persists Hezarfen meta', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForBlockCheckoutReady( page );

		await fillBlockField( page, 'email', 'block-buyer@example.test' );
		await fillBlockField( page, 'first_name', 'Ada' );
		await fillBlockField( page, 'last_name', 'Lovelace' );
		await fillBlockField( page, 'phone', '5551112233' );

		// İl → İlçe → Mahalle. These map onto core state / city / address_1.
		await pickCombobox( page, PROVINCE_CLASS, {
			query: 'İstanbul',
			optionText: /İstanbul/,
		} );
		const neighborhoodResponse = page.waitForResponse(
			( res ) =>
				res.url().includes( '/hezarfen/v1/neighborhoods' ) &&
				res.status() === 200,
			{ timeout: 15_000 }
		);
		await pickCombobox( page, DISTRICT_CLASS, {
			query: 'Kadıköy',
			optionText: /Kadıköy/,
		} );
		await neighborhoodResponse;
		await pickComboboxFirstOption( page, NEIGHBORHOOD_CLASS );

		await fillBlockField( page, 'address_2', 'Ada Sk. No:1 D:2' );
		await fillBlockField( page, 'postcode', '34000' );

		// Company invoice.
		await page.locator( '#hezarfen-invoice-type' ).selectOption( 'company' );
		await page
			.locator( '#hezarfen-company-title' )
			.fill( 'Hezarfen Test A.Ş.' );
		await page.locator( '#hezarfen-tax-number' ).fill( '1234567890' );
		await page.locator( '#hezarfen-tax-office' ).fill( 'Kadıköy' );

		await placeBlockOrder( page );

		await page.waitForURL( /order-received/, { timeout: 45_000 } );

		const order = getLatestOrderHezData();
		expect( order.invoice_type ).toBe( 'company' );
		expect( order.tax_number ).toBe( '1234567890' );
		expect( order.tax_office ).toBe( 'Kadıköy' );
		// District → core city, neighborhood → core address_1.
		expect( order.city ).toBe( 'Kadıköy' );
		expect( order.address_1.length ).toBeGreaterThan( 0 );
	} );
} );
