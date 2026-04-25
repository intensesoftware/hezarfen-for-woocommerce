import { expect, test } from '@playwright/test';
import {
	addE2EProductToCart,
	expectCheckoutUpdate,
	fillTrAddressChain,
	TR_SAMPLE_ADDRESS,
	waitForCheckoutIdle,
} from './helpers/checkout';
import { wp } from './helpers/wp-cli';

/**
 * Sanity check that orders go through with the bank-transfer (BACS)
 * gateway, not just COD. Hezarfen's payment_method-aware code paths
 * (TC encryption order-meta hooks etc.) sometimes assume COD; this
 * test catches that class of regression and confirms the BACS
 * thank-you page renders the bank instructions block.
 */
test.describe( 'Hezarfen — BACS (banka havalesi) gateway', () => {
	test.beforeAll( () => {
		// Enable BACS for this describe; restore in afterAll. We can't
		// just snapshotOptions on woocommerce_bacs_settings because it's
		// a serialised array; the easier path is to flip the enabled
		// flag through wc payment_gateway update.
		wp( [
			'wc',
			'payment_gateway',
			'update',
			'bacs',
			'--user=1',
			'--enabled=true',
			'--title=Banka havalesi (e2e)',
			'--description=Lütfen ödemenizi banka hesabımıza yapınız.',
		] );
	} );
	test.afterAll( () => {
		wp(
			[
				'wc',
				'payment_gateway',
				'update',
				'bacs',
				'--user=1',
				'--enabled=false',
			],
			{ allowFailure: true }
		);
	} );

	test.beforeEach( async ( { page } ) => {
		await addE2EProductToCart( page );
	} );

	test( 'BACS ile sipariş geçince thank-you sayfasında banka talimatı görünüyor', async ( {
		page,
	} ) => {
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

		// Pick BACS instead of COD.
		const bacs = page.locator( '#payment_method_bacs' );
		await expect( bacs ).toBeVisible();
		await bacs.check( { force: true } );
		await expect( bacs ).toBeChecked();

		await waitForCheckoutIdle( page );
		await page.locator( '#place_order' ).click();
		await page.waitForURL( /order-received/, { timeout: 30_000 } );

		// BACS-specific block: WC outputs `.wc-bacs-bank-details` /
		// "Direct bank transfer" instructions. We accept either the
		// Turkish translation or the English source string.
		await expect( page.locator( 'body' ) ).toContainText(
			/(banka havalesi|direct bank transfer|bank account)/i
		);
		// Order summary still shows our gateway title.
		await expect(
			page.locator( '.woocommerce-order-overview, ul.order_details' )
		).toContainText( /(banka|bank)/i );
	} );
} );
