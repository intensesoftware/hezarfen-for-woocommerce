import { expect, test } from '@playwright/test';
import {
	addE2EProductToCart,
	expectCheckoutUpdate,
	expectMahalleAjax,
	fillTrAddressChain,
	pickFromSelect,
	TR_SAMPLE_ADDRESS,
	waitForCheckoutIdle,
} from './helpers/checkout';

/**
 * The Hezarfen plugin replaces the default WooCommerce city / address_1
 * inputs with select2 dropdowns:
 *   billing_state         -> il
 *   billing_city          -> ilçe (loaded via AJAX after il changes)
 *   billing_address_1     -> mahalle (loaded via AJAX after ilçe changes)
 */
test.describe( 'Hezarfen TR checkout', () => {
	test.beforeEach( async ( { page } ) => {
		await addE2EProductToCart( page );
	} );

	test( 'il / ilçe / mahalle dropdowns populate Türkiye data', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );

		await expect( page.locator( '#billing_country' ) ).toHaveValue( 'TR' );

		const cities = await page
			.locator( '#billing_state option' )
			.allTextContents();
		expect( cities ).toContain( TR_SAMPLE_ADDRESS.city );

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

		const neighborhoodPromise = expectMahalleAjax( page, 'neighborhood' );
		await pickFromSelect(
			page,
			'#billing_city',
			TR_SAMPLE_ADDRESS.district
		);
		await neighborhoodPromise;

		const neighborhoods = await page
			.locator( '#billing_address_1 option' )
			.allTextContents();
		expect( neighborhoods ).toContain( TR_SAMPLE_ADDRESS.neighborhood );
	} );

	test( 'places COD order with Ankara / Çankaya / 100.Yıl Mah', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );
		await expect( page.locator( '#billing_country' ) ).toHaveValue( 'TR' );

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

		const cod = page.locator( '#payment_method_cod' );
		await expect( cod ).toBeAttached();
		await cod.check( { force: true } );
		await expect( cod ).toBeChecked();

		await waitForCheckoutIdle( page );
		await page.locator( '#place_order' ).click();

		await page.waitForURL( /order-received/, { timeout: 30_000 } );
		await expect( page.locator( 'body' ) ).toContainText(
			/(siparişiniz alın|order has been received)/i
		);
		await expect(
			page.locator( 'ul.order_details, .woocommerce-order-overview' )
		).toBeVisible();
	} );
} );
