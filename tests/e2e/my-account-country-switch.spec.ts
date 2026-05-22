import { expect, test, type Page } from '@playwright/test';
import { loginAsCustomer } from './helpers/auth';
import { pickFromSelect } from './helpers/checkout';

/**
 * `checkout-country-switch.spec.ts` already pins the select ↔ input
 * swap that `mahalle-helper.js` performs on the checkout form. The
 * same transform also has to fire on the My Account → Edit address
 * screen, where the bind happens through `class-my-account.php` +
 * `assets/js/my-account-addresses.js` rather than the checkout init
 * path. The two code paths share the underlying helper but enter it
 * differently, so a refactor that touches one can quietly break the
 * other — leaving non-TR customers with empty selects and no way to
 * enter their address.
 */
test.describe( 'Hezarfen My Account ülke değişimi davranışı', () => {
	test.beforeEach( async ( { page } ) => {
		await loginAsCustomer( page );
	} );

	test( 'edit-address billing: TR\'den US\'e geçişte city/address_1 input\'a dönüyor; TR\'ye dönüşte tekrar select', async ( {
		page,
	} ) => {
		await page.goto( '/my-account/edit-address/billing/' );
		await ensureCountryIsTR( page );

		// Baseline: TR is in effect, helper has replaced the city +
		// address_1 inputs with mahalle selects.
		await expect( page.locator( 'select#billing_city' ) ).toBeAttached();
		await expect(
			page.locator( 'select#billing_address_1' )
		).toBeAttached();

		// Switch to United States — country_to_state_changing fires,
		// helper turns both selects back into plain text inputs and
		// removes the AJAX bind.
		await pickFromSelect( page, '#billing_country', 'US' );
		await waitForCountryFieldsSettled( page, 'input' );

		await expect( page.locator( 'input#billing_city' ) ).toBeAttached();
		await expect(
			page.locator( 'input#billing_address_1' )
		).toBeAttached();
		await expect( page.locator( 'select#billing_city' ) ).toHaveCount( 0 );
		await expect(
			page.locator( 'select#billing_address_1' )
		).toHaveCount( 0 );

		// Back to TR — selects must reappear so mahalle cascading works.
		await pickFromSelect( page, '#billing_country', 'TR' );
		await waitForCountryFieldsSettled( page, 'select' );

		await expect( page.locator( 'select#billing_city' ) ).toBeAttached();
		await expect(
			page.locator( 'select#billing_address_1' )
		).toBeAttached();
	} );
} );

/**
 * New customers have no saved billing_country, leaving the dropdown on
 * its default placeholder until the helper kicks in. Force TR so the
 * helper transforms city + address_1 into selects we can assert against.
 */
async function ensureCountryIsTR( page: Page ): Promise< void > {
	const country = page.locator( '#billing_country' );
	if ( ( await country.inputValue() ) === 'TR' ) return;
	await pickFromSelect( page, '#billing_country', 'TR' );
	await waitForCountryFieldsSettled( page, 'select' );
}

/**
 * The helper swaps DOM nodes asynchronously after `country_to_state_changing`.
 * Wait until both fields settle on the expected tag name so subsequent
 * assertions don't race the transform.
 */
async function waitForCountryFieldsSettled(
	page: Page,
	expected: 'select' | 'input'
): Promise< void > {
	await page.waitForFunction(
		( tag ) => {
			const city = document.querySelector( '#billing_city' );
			const addr = document.querySelector( '#billing_address_1' );
			return (
				!! city &&
				!! addr &&
				city.tagName.toLowerCase() === tag &&
				addr.tagName.toLowerCase() === tag
			);
		},
		expected,
		{ timeout: 10_000 }
	);
}
