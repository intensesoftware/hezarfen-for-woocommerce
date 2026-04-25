import { expect, test } from '@playwright/test';
import {
	addE2EProductToCart,
	expectCheckoutUpdate,
	fillTrAddressChain,
	TR_SAMPLE_ADDRESS,
	waitForCheckoutIdle,
} from './helpers/checkout';
import {
	applyOptions,
	restoreOptions,
	snapshotOptions,
} from './helpers/wp-options';

/**
 * Hezarfen's contracts module renders one or more required checkboxes
 * (Mesafeli Satış Sözleşmesi + Ön Bilgilendirme Formu) on the checkout
 * via `woocommerce_checkout_after_terms_and_conditions`. The combined
 * variant lives at `input[name="contract_combined_checkbox"]`. Both
 * variants carry the `required` attribute, so submitting unchecked
 * trips the browser's HTML5 validity check before WC ever sees the
 * POST. We assert both: presence + that the order does NOT reach the
 * thank-you page if the checkbox is left unchecked.
 */
const FEATURE_OPTIONS = {
	hezarfen_contracts_enabled: 'yes',
};

let snapshot: Record< string, string >;

test.describe( 'Hezarfen MSS / Ön Bilgilendirme sözleşmeleri', () => {
	test.beforeAll( () => {
		snapshot = snapshotOptions( Object.keys( FEATURE_OPTIONS ) );
		applyOptions( FEATURE_OPTIONS );
	} );
	test.afterAll( () => {
		restoreOptions( snapshot );
	} );

	test.beforeEach( async ( { page } ) => {
		await addE2EProductToCart( page );
	} );

	test( 'sözleşme checkbox\'ı zorunlu, işaretlemeden sipariş geçmiyor', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );
		await fillCheckoutWithoutContracts( page );

		// Combined or per-contract checkbox must exist.
		const checkbox = page
			.locator(
				'input[name="contract_combined_checkbox"], input[name^="contract_"][type="checkbox"]'
			)
			.first();
		await expect( checkbox ).toBeAttached();
		await expect( checkbox ).not.toBeChecked();
		const isRequired = await checkbox.evaluate(
			( el ) => ( el as HTMLInputElement ).required
		);
		expect( isRequired ).toBe( true );

		// Click place order without ticking — browser HTML5 validation
		// should keep us on /checkout/ and the checkbox should report
		// as invalid.
		await page.locator( '#place_order' ).click();
		await page.waitForLoadState( 'networkidle' );
		expect( page.url() ).toMatch( /checkout/i );
		const isValid = await checkbox.evaluate(
			( el ) => ( el as HTMLInputElement ).validity.valid
		);
		expect( isValid ).toBe( false );
	} );

	test( 'sözleşme checkbox\'ı işaretlenince sipariş başarıyla geçiyor', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );
		await fillCheckoutWithoutContracts( page );

		const checkbox = page
			.locator(
				'input[name="contract_combined_checkbox"], input[name^="contract_"][type="checkbox"]'
			)
			.first();
		await checkbox.check( { force: true } );
		await expect( checkbox ).toBeChecked();

		await waitForCheckoutIdle( page );
		await page.locator( '#place_order' ).click();
		await page.waitForURL( /order-received/, { timeout: 30_000 } );
		await expect( page.locator( 'body' ) ).toContainText(
			/(siparişiniz alın|order has been received)/i
		);
	} );
} );

async function fillCheckoutWithoutContracts(
	page: import( '@playwright/test' ).Page
): Promise< void > {
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
	await page.locator( '#billing_address_2' ).fill( TR_SAMPLE_ADDRESS.street );
	await page.locator( '#billing_address_2' ).blur();
	await expectCheckoutUpdate( page ).catch( () => {} );
	await waitForCheckoutIdle( page );
	const cod = page.locator( '#payment_method_cod' );
	if ( await cod.isVisible() ) {
		await cod.check( { force: true } );
	}
}
