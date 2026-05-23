import { expect, test, type Page } from '@playwright/test';
import { loginAsCustomer } from './helpers/auth';
import {
	addE2EProductToCart,
	pickFromSelect,
	waitForCheckoutIdle,
} from './helpers/checkout';

/**
 * `mahalle-helper.js` swaps `billing_city` + `billing_address_1`
 * between `<select>` (TR) and `<input>` (anywhere else) whenever the
 * country changes. The swap operates on the DOM nodes themselves —
 * the helper replaces the element rather than just toggling type —
 * which historically lost typed values on adjacent fields when a
 * subsequent JS handler took an incomplete snapshot.
 *
 * `checkout-country-switch.spec.ts` and `my-account-country-switch.spec.ts`
 * cover the *shape* of the swap (select ↔ input). This file pins the
 * *retention* contract: typed first/last name + phone + email values
 * the customer already entered must survive a TR → other-country → TR
 * round-trip. If they don't, a customer who picks the wrong country by
 * accident has to retype their entire address.
 */
test.describe( 'Hezarfen form veri korunması — ülke değişim turu', () => {
	test( 'checkout: TR → US → TR turunda name/phone/email değerleri korunuyor', async ( {
		page,
	} ) => {
		await addE2EProductToCart( page );
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );

		const fields = {
			'#billing_first_name': 'Ada',
			'#billing_last_name': 'Lovelace',
			'#billing_phone': '5551112233',
			'#billing_email': 'ada-retention@example.test',
		};
		for ( const [ selector, value ] of Object.entries( fields ) ) {
			await page.locator( selector ).fill( value );
		}

		// TR → US — billing_city + billing_address_1 swap to text inputs;
		// the surrounding name/email/phone inputs should NOT be touched.
		await pickFromSelect( page, '#billing_country', 'US' );
		await waitForCountryFieldsSettled( page, 'input' );
		await assertFieldsKept( page, fields );

		// US → TR — selects come back. Same fields must still carry the
		// values we typed before the round trip.
		await pickFromSelect( page, '#billing_country', 'TR' );
		await waitForCountryFieldsSettled( page, 'select' );
		await assertFieldsKept( page, fields );
	} );

	test( 'my-account edit-address: TR → US → TR turunda first/last name korunuyor', async ( {
		page,
	} ) => {
		await loginAsCustomer( page );
		await page.goto( '/my-account/edit-address/billing/' );

		// The form may have stored values from previous specs. Force
		// TR + known field values so the assertion has a fixed baseline.
		await ensureCountryIsTR( page );

		const fields = {
			'#billing_first_name': 'Ada',
			'#billing_last_name': 'Lovelace',
			'#billing_phone': '5551112233',
		};
		for ( const [ selector, value ] of Object.entries( fields ) ) {
			await page.locator( selector ).fill( value );
		}

		await pickFromSelect( page, '#billing_country', 'US' );
		await waitForCountryFieldsSettled( page, 'input' );
		await assertFieldsKept( page, fields );

		await pickFromSelect( page, '#billing_country', 'TR' );
		await waitForCountryFieldsSettled( page, 'select' );
		await assertFieldsKept( page, fields );
	} );
} );

async function assertFieldsKept(
	page: Page,
	fields: Record< string, string >
): Promise< void > {
	for ( const [ selector, value ] of Object.entries( fields ) ) {
		await expect( page.locator( selector ) ).toHaveValue( value );
	}
}

async function ensureCountryIsTR( page: Page ): Promise< void > {
	const country = page.locator( '#billing_country' );
	if ( ( await country.inputValue() ) === 'TR' ) return;
	await pickFromSelect( page, '#billing_country', 'TR' );
	await waitForCountryFieldsSettled( page, 'select' );
}

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
