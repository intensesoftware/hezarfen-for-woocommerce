import { expect, test } from '@playwright/test';
import { E2E_CUSTOMER } from './global-setup';
import { loginAsAdmin, loginAsCustomer } from './helpers/auth';
import {
	addE2EProductToCart,
	expectCheckoutUpdate,
	fillTrAddressChain,
	TR_SAMPLE_ADDRESS,
	waitForCheckoutIdle,
} from './helpers/checkout';
import { wp } from './helpers/wp-cli';
import {
	applyOptions,
	restoreOptions,
	snapshotOptions,
} from './helpers/wp-options';

/**
 * `checkout-contracts.spec.ts` covers the **checkbox** layer.
 * What it doesn't cover is what happens after the order is placed:
 *   - [Post_Order_Processor](../../includes/contracts/core/class-post-order-processor.php)
 *     copies a snapshot of each enabled contract into
 *     `{prefix}hezarfen_contracts` on the `processing` status hook
 *   - [Order_Agreements](../../includes/contracts/admin/class-order-agreements.php)
 *     surfaces those rows in a "Customer Agreements" metabox on the
 *     admin order edit screen
 *   - [Customer_Agreements](../../includes/contracts/frontend/class-customer-agreements.php)
 *     surfaces them on the thank-you page and the my-account view-order
 *     endpoint
 *
 * If any one of those three breaks, the customer or merchant loses
 * legal evidence of the agreement they accepted at checkout — that's
 * the regression class this spec exists for.
 */
const FEATURE_OPTIONS = {
	hezarfen_contracts_enabled: 'yes',
};

let snapshot: Record< string, string >;
let orderId: string;

test.describe( 'Hezarfen sözleşme post-order kayıt + görüntüleme', () => {
	test.beforeAll( () => {
		snapshot = snapshotOptions( Object.keys( FEATURE_OPTIONS ) );
		applyOptions( FEATURE_OPTIONS );
	} );
	test.afterAll( () => {
		if ( orderId ) {
			wp( [ 'post', 'delete', orderId, '--force' ], {
				allowFailure: true,
			} );
			// Clean the snapshot rows so a re-run starts with an empty
			// hezarfen_contracts table for this order id.
			wp( [
				'eval',
				`
					global $wpdb;
					$wpdb->delete( $wpdb->prefix . 'hezarfen_contracts', array( 'order_id' => ${ orderId } ) );
				`,
			] );
		}
		restoreOptions( snapshot );
	} );

	test( 'Place order → kontratlar hezarfen_contracts tablosuna yazılıyor', async ( {
		page,
	} ) => {
		// Use the e2e customer so the my-account view-order endpoint
		// recognises the order in a later test.
		await loginAsCustomer( page );
		await addE2EProductToCart( page );
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );
		await fillBillingForm( page );

		// Combined-mode contracts checkbox (global-setup seeds 2 active
		// contracts, so renderer goes through the combined branch).
		const checkbox = page
			.locator( 'input[name="contract_combined_checkbox"]' )
			.first();
		await checkbox.check( { force: true } );

		await waitForCheckoutIdle( page );
		await page.locator( '#place_order' ).click();
		await page.waitForURL( /order-received/, { timeout: 30_000 } );

		// Pluck the order id out of the order-received URL so the
		// follow-up tests in this describe can reuse it.
		const url = new URL( page.url() );
		const match = url.pathname.match( /order-received\/(\d+)/ );
		expect( match, 'order-received URL should contain order id' ).not.toBeNull();
		orderId = match![ 1 ];

		// Two contracts seeded → expect two rows in hezarfen_contracts.
		const count = wp( [
			'eval',
			`
				global $wpdb;
				echo (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}hezarfen_contracts WHERE order_id = %d",
					${ orderId }
				) );
			`,
		] ).trim();
		expect( Number( count ) ).toBe( 2 );

		// And the thank-you page should already render the agreements
		// summary (Customer_Agreements::display_on_thankyou_page).
		await expect(
			page.locator( '.woocommerce-customer-agreements' )
		).toBeVisible();
		await expect(
			page.locator( '.agreements-list .agreement-name' )
		).toHaveCount( 2 );
	} );

	test( 'Admin "Customer Agreements" metabox kaydedilen kontratları gösteriyor', async ( {
		page,
	} ) => {
		test.skip( ! orderId, 'depends on the place-order test above' );
		await loginAsAdmin( page );
		await page.goto(
			`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }`
		);
		await expect(
			page.locator( '#hezarfen-customer-agreements' )
		).toBeVisible();
		// The metabox table renders one <tr> per saved agreement; we
		// asserted 2 in the previous test.
		await expect(
			page.locator(
				'#hezarfen-customer-agreements table.widefat tbody tr'
			)
		).toHaveCount( 2 );
		// And both agreement names should appear (matches the seed in
		// global-setup.ts ensureMssContracts).
		await expect(
			page.locator( '#hezarfen-customer-agreements' )
		).toContainText( 'Mesafeli Satış Sözleşmesi' );
		await expect(
			page.locator( '#hezarfen-customer-agreements' )
		).toContainText( 'Ön Bilgilendirme Formu' );
	} );

	test( 'Müşteri view-order sayfasında "Your Agreements" görünüyor', async ( {
		page,
	} ) => {
		test.skip( ! orderId, 'depends on the place-order test above' );
		await loginAsCustomer( page );
		await page.goto( `/my-account/view-order/${ orderId }/` );

		await expect(
			page.locator( '.woocommerce-customer-agreements' )
		).toBeVisible();
		await expect(
			page.locator( '.agreements-list .agreement-name' )
		).toHaveCount( 2 );
		await expect(
			page.locator( '.hezarfen-view-agreements-btn' )
		).toBeVisible();
	} );
} );

async function fillBillingForm(
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
	await page.locator( '#billing_email' ).fill( E2E_CUSTOMER.email );
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
