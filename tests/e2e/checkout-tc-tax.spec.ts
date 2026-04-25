import { expect, test } from '@playwright/test';
import {
	addE2EProductToCart,
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

/**
 * Hezarfen's tax-fields layer adds an "Invoice Type" select (person /
 * company) to the checkout. Personal invoices show a TC Identity field
 * which is validated server-side; company invoices show Tax Number +
 * Tax Office + a required company title.
 *
 * global-setup turns these features off so the base checkout test stays
 * focused. We flip them on for this describe and roll them back after.
 */
const FEATURE_OPTIONS = {
	hezarfen_show_hezarfen_checkout_tax_fields: 'yes',
	hezarfen_checkout_show_TC_identity_field: 'yes',
	hezarfen_checkout_is_TC_identity_number_field_required: 'yes',
};

let snapshot: Record< string, string >;

test.describe( 'Hezarfen TC / Vergi alanları', () => {
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

	test( 'invoice type switch shows TC for person and Vergi for company', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );

		const invoiceType = page.locator( '#hezarfen_invoice_type' );
		await expect( invoiceType ).toBeVisible();

		// Person — TC alanı görünür, vergi/şirket alanları gizli.
		await pickFromSelect( page, '#hezarfen_invoice_type', 'person' );
		await expect( page.locator( '#hezarfen_TC_number_field' ) ).toBeVisible();
		await expect(
			page.locator( '#hezarfen_tax_number_field' )
		).toBeHidden();
		await expect(
			page.locator( '#hezarfen_tax_office_field' )
		).toBeHidden();

		// Company — TC gizli, vergi no + vergi dairesi + şirket başlığı görünür.
		await pickFromSelect( page, '#hezarfen_invoice_type', 'company' );
		await expect(
			page.locator( '#hezarfen_TC_number_field' )
		).toBeHidden();
		await expect(
			page.locator( '#hezarfen_tax_number_field' )
		).toBeVisible();
		await expect(
			page.locator( '#hezarfen_tax_office_field' )
		).toBeVisible();
		await expect( page.locator( '#billing_company_field' ) ).toBeVisible();
	} );

	test( 'invalid TC (10 digits) blocks order with field-level error', async ( {
		page,
	} ) => {
		await fillCheckoutCommon( page );

		await pickFromSelect( page, '#hezarfen_invoice_type', 'person' );
		await page.locator( '#hezarfen_TC_number' ).fill( '1234567890' ); // 10 haneli, geçersiz

		await waitForCheckoutIdle( page );
		await page.locator( '#place_order' ).click();

		// Server-side validation -> form-level error notice. The actual
		// rendered string depends on the active locale; accept either
		// the source English or the Turkish translation.
		await expect(
			page.locator( '.woocommerce-error' ).first()
		).toContainText(
			/TC (Kimlik No|ID number) (hatalı|is not valid|geçerli değil)/i
		);
		// URL stayed on /checkout/ — order was NOT placed.
		expect( page.url() ).toMatch( /checkout/i );
	} );

	test( 'company invoice with Vergi No places order successfully', async ( {
		page,
	} ) => {
		await fillCheckoutCommon( page );

		await pickFromSelect( page, '#hezarfen_invoice_type', 'company' );
		await page
			.locator( '#billing_company' )
			.fill( 'Hezarfen Test A.Ş.' );
		await page.locator( '#hezarfen_tax_number' ).fill( '1234567890' );
		await page.locator( '#hezarfen_tax_office' ).fill( 'Çankaya' );

		await waitForCheckoutIdle( page );
		const cod = page.locator( '#payment_method_cod' );
		await cod.check( { force: true } );
		await waitForCheckoutIdle( page );
		await page.locator( '#place_order' ).click();

		await page.waitForURL( /order-received/, { timeout: 30_000 } );
		await expect( page.locator( 'body' ) ).toContainText(
			/(siparişiniz alın|order has been received)/i
		);
	} );
} );

/**
 * Fills everything *except* the invoice-type-specific fields so each
 * test can vary the TC / Vergi inputs without repeating boilerplate.
 */
async function fillCheckoutCommon( page: import( '@playwright/test' ).Page ) {
	await page.goto( '/checkout/' );
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
	await waitForCheckoutIdle( page );
}
