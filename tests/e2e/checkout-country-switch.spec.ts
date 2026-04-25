import { expect, test } from '@playwright/test';
import {
	addE2EProductToCart,
	expectMahalleAjax,
	pickFromSelect,
	TR_SAMPLE_ADDRESS,
	waitForCheckoutIdle,
} from './helpers/checkout';

/**
 * `mahalle-helper.js` swaps billing_city / billing_address_1 between
 * <select> (TR — Hezarfen-driven mahalle dropdowns) and <input> (any
 * other country — fall back to plain text). The `country_to_state_changing`
 * event is what triggers the swap, see assets/js/mahalle-helper.js.
 *
 * If we ever break that branch, customers from non-TR countries get
 * empty selects with no text input — they can't enter their address.
 * This spec catches that.
 */
test.describe( 'Hezarfen ülke değişimi davranışı', () => {
	test.beforeEach( async ( { page } ) => {
		await addE2EProductToCart( page );
	} );

	test( 'TR seçiliyken city/address_1 select; başka ülkeye geçince input', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );

		// Default country = TR (set by global-setup). City/address_1 are
		// rendered as selects by Hezarfen's checkout-fields filter.
		await expect( page.locator( '#billing_country' ) ).toHaveValue( 'TR' );
		await expect(
			page.locator( 'select#billing_city' )
		).toBeAttached();
		await expect(
			page.locator( 'select#billing_address_1' )
		).toBeAttached();

		// Pick Ankara so the chain is exercised on the TR branch.
		const districtPromise = expectMahalleAjax( page, 'district' );
		await pickFromSelect(
			page,
			'#billing_state',
			TR_SAMPLE_ADDRESS.cityPlate
		);
		await districtPromise;

		// Switch country to United States — country_to_state_changing
		// fires, mahalle-helper replaces both selects with text inputs.
		await pickFromSelect( page, '#billing_country', 'US' );
		await waitForCheckoutIdle( page );

		// city / address_1 must now be plain inputs, not selects.
		await expect( page.locator( 'input#billing_city' ) ).toBeAttached();
		await expect(
			page.locator( 'input#billing_address_1' )
		).toBeAttached();
		await expect( page.locator( 'select#billing_city' ) ).toHaveCount( 0 );
		await expect(
			page.locator( 'select#billing_address_1' )
		).toHaveCount( 0 );

		// Switch back to TR — selects should reappear.
		await pickFromSelect( page, '#billing_country', 'TR' );
		await waitForCheckoutIdle( page );
		await expect(
			page.locator( 'select#billing_city' )
		).toBeAttached();
		await expect(
			page.locator( 'select#billing_address_1' )
		).toBeAttached();
	} );
} );
