import { expect, test } from '@playwright/test';
import {
	addE2EProductToCart,
	expectCheckoutUpdate,
	expectMahalleAjax,
	fillTrAddressChain,
	pickFromSelect,
	TR_SAMPLE_ADDRESS,
	waitForCheckoutIdle,
	waitForOptionsPopulated,
} from './helpers/checkout';

/**
 * When the customer ticks "Farklı bir adrese gönderilsin mi?" the
 * shipping_state / shipping_city / shipping_address_1 fields appear
 * and Hezarfen mirrors the same mahalle dropdown chain onto them.
 * If the helper ever fails to bind to the shipping wrapper, customers
 * can pick TR for shipping but get no district/neighborhood options.
 */
const SHIPPING = {
	cityPlate: 'TR34',
	city: 'İstanbul',
	district: 'Kadıköy',
	neighborhood: '19 Mayıs Mah',
	street: 'Bahariye Cad. No:5',
	postcode: '34710',
};

test.describe( 'Hezarfen farklı kargo adresi', () => {
	test.beforeEach( async ( { page } ) => {
		await addE2EProductToCart( page );
	} );

	test( 'ship-to-different ile shipping il/ilçe/mahalle zinciri çalışıyor ve sipariş geçiyor', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );

		// Billing first.
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
		const billingPostcode = page.locator( '#billing_postcode' );
		if ( await billingPostcode.isVisible() ) {
			await billingPostcode.fill( TR_SAMPLE_ADDRESS.postcode );
		}
		await page
			.locator( '#billing_address_2' )
			.fill( TR_SAMPLE_ADDRESS.street );

		// Tick ship-to-different so the shipping fields render.
		const shipDifferent = page.locator(
			'#ship-to-different-address-checkbox'
		);
		await shipDifferent.check( { force: true } );
		await expect(
			page.locator( '#shipping_first_name' )
		).toBeVisible();

		// Pull the first shipping neighborhood for the chosen state →
		// district so we don't have to hard-code one. Allowing the chain
		// to drive itself catches breakage in either AJAX hop.
		await page.locator( '#shipping_first_name' ).fill( 'Charles' );
		await page.locator( '#shipping_last_name' ).fill( 'Babbage' );

		const districtPromise = expectMahalleAjax( page, 'district' );
		await pickFromSelect(
			page,
			'#shipping_state',
			SHIPPING.cityPlate
		);
		await districtPromise;
		await waitForOptionsPopulated( page, '#shipping_city' );
		const districts = await page
			.locator( '#shipping_city option' )
			.allTextContents();
		expect( districts ).toContain( SHIPPING.district );

		const neighborhoodPromise = expectMahalleAjax( page, 'neighborhood' );
		await pickFromSelect( page, '#shipping_city', SHIPPING.district );
		await neighborhoodPromise;
		await waitForOptionsPopulated( page, '#shipping_address_1' );
		const shippingNeighborhoods = await page
			.locator( '#shipping_address_1 option' )
			.allTextContents();
		expect( shippingNeighborhoods ).toContain(
			SHIPPING.neighborhood
		);
		await pickFromSelect(
			page,
			'#shipping_address_1',
			SHIPPING.neighborhood
		);

		await page.locator( '#shipping_address_2' ).fill( SHIPPING.street );
		const shippingPostcode = page.locator( '#shipping_postcode' );
		if ( await shippingPostcode.isVisible() ) {
			await shippingPostcode.fill( SHIPPING.postcode );
		}

		await page.locator( '#billing_address_2' ).blur();
		await expectCheckoutUpdate( page ).catch( () => {} );
		await waitForCheckoutIdle( page );

		const cod = page.locator( '#payment_method_cod' );
		await cod.check( { force: true } );
		await waitForCheckoutIdle( page );

		await page.locator( '#place_order' ).click();
		await page.waitForURL( /order-received/, { timeout: 30_000 } );
		await expect( page.locator( 'body' ) ).toContainText(
			/(siparişiniz alın|order has been received)/i
		);

		// Order received page lists the shipping address — both Kadıköy
		// and the chosen mahalle should appear there if the form sent
		// the right shipping_* fields.
		const shippingAddressBlock = page
			.locator( '.woocommerce-customer-details, address' )
			.first();
		await expect( shippingAddressBlock ).toContainText( SHIPPING.district );
	} );
} );
