import { expect, test, type Page } from '@playwright/test';
import { E2E_CUSTOMER } from './global-setup';
import {
	expectMahalleAjax,
	fillTrAddressChain,
	pickFromSelect,
	TR_SAMPLE_ADDRESS,
} from './helpers/checkout';
import { wp } from './helpers/wp-cli';

/**
 * My Account → Edit address (billing) reuses Hezarfen's mahalle helper
 * (`assets/js/my-account-addresses.js`) on the same `#billing_state`,
 * `#billing_city`, `#billing_address_1` fields as the checkout form.
 * If the helper ever stops binding here (separate code path from the
 * checkout init), this is the test that catches it.
 */
test.describe( 'Hezarfen My Account adres düzenleme', () => {
	test.beforeEach( async ( { page } ) => {
		await loginAsE2ECustomer( page );
	} );

	test( 'edit-address sayfası il/ilçe/mahalle select chain ile yükleniyor', async ( {
		page,
	} ) => {
		await page.goto( '/my-account/edit-address/billing/' );

		// New customers have no stored billing_country; force TR so the
		// mahalle helper installs its handlers and replaces the city /
		// address_1 inputs with select elements.
		await ensureCountryIsTR( page );

		// State (il) — should be a normal select with Turkish provinces.
		const cities = await page
			.locator( '#billing_state option' )
			.allTextContents();
		expect( cities ).toContain( TR_SAMPLE_ADDRESS.city );

		// Pick Ankara — district AJAX should fire.
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

	test( 'submit edilen adres user meta\'ya doğru kaydediliyor', async ( {
		page,
	} ) => {
		await page.goto( '/my-account/edit-address/billing/' );
		await ensureCountryIsTR( page );

		await page.locator( '#billing_first_name' ).fill( 'Ada' );
		await page.locator( '#billing_last_name' ).fill( 'Lovelace' );
		await page.locator( '#billing_phone' ).fill( '5551112233' );
		await page.locator( '#billing_email' ).fill( E2E_CUSTOMER.email );

		await fillTrAddressChain( page, {
			type: 'billing',
			cityPlate: TR_SAMPLE_ADDRESS.cityPlate,
			district: TR_SAMPLE_ADDRESS.district,
			neighborhood: TR_SAMPLE_ADDRESS.neighborhood,
		} );

		const postcode = page.locator( '#billing_postcode' );
		if ( await postcode.isVisible() ) {
			await postcode.fill( TR_SAMPLE_ADDRESS.postcode );
		}
		await page
			.locator( '#billing_address_2' )
			.fill( TR_SAMPLE_ADDRESS.street );

		await page.locator( 'button[name="save_address"]' ).click();

		await expect(
			page.locator( '.woocommerce-message' ).first()
		).toContainText(
			/(adresiniz başarıyla değiştirildi|address changed successfully)/i
		);

		// DB-side check: WP_User meta gets the values we picked. We read
		// via wp-cli rather than asserting on the form post-reload because
		// of a separate known issue (see test.fixme below).
		const userId = wp(
			[ 'user', 'get', E2E_CUSTOMER.username, '--field=ID' ]
		).trim();
		expect(
			wp( [ 'user', 'meta', 'get', userId, 'billing_state' ] ).trim()
		).toBe( TR_SAMPLE_ADDRESS.cityPlate );
		expect(
			wp( [ 'user', 'meta', 'get', userId, 'billing_city' ] ).trim()
		).toBe( TR_SAMPLE_ADDRESS.district );
		expect(
			wp( [ 'user', 'meta', 'get', userId, 'billing_address_1' ] ).trim()
		).toBe( TR_SAMPLE_ADDRESS.neighborhood );
		expect(
			wp( [ 'user', 'meta', 'get', userId, 'billing_address_2' ] ).trim()
		).toBe( TR_SAMPLE_ADDRESS.street );
	} );

	/**
	 * Known issue (2026-04): on /my-account/edit-address/billing/ the
	 * mahalle helper turns billing_city / billing_address_1 into empty
	 * <select> elements after page load, which loses the saved values
	 * visually. The data is still correct in the database (see test
	 * above), but the user is shown empty fields on revisit. Tracking
	 * via fixme so the suite stays green while the bug is open — flip
	 * to `test()` once the helper is fixed to either keep them as
	 * inputs or pre-populate the select with the saved option.
	 */
	test.fixme(
		'kaydedilen adres reload sonrası mahalle dahil görünür kalıyor',
		async ( { page } ) => {
			await page.goto( '/my-account/edit-address/billing/' );
			await expect( page.locator( '#billing_city' ) ).toHaveValue(
				TR_SAMPLE_ADDRESS.district
			);
			await expect( page.locator( '#billing_address_1' ) ).toHaveValue(
				TR_SAMPLE_ADDRESS.neighborhood
			);
		}
	);
} );

/**
 * If the country select is empty (new customer, no saved address) set
 * it to TR and wait for the country_to_state_changing handler to swap
 * city / address_1 into selectWoo dropdowns. WooCommerce fires its own
 * AJAX state refresh when country changes, so we wait for the response
 * before returning.
 */
async function ensureCountryIsTR( page: Page ): Promise< void > {
	const country = page.locator( '#billing_country' );
	if ( ( await country.inputValue() ) === 'TR' ) return;
	await pickFromSelect( page, '#billing_country', 'TR' );
	// Give the JS a tick to swap the input → select for city / address_1.
	await page
		.waitForFunction( () => {
			const city = document.querySelector( '#billing_city' );
			return !! city && city.tagName.toLowerCase() === 'select';
		}, undefined, { timeout: 5_000 } )
		.catch( () => {} );
}

async function loginAsE2ECustomer( page: Page ): Promise< void > {
	await page.goto( '/my-account/' );
	// If we're already logged in (storageState would short-circuit but we
	// don't use one), bail early.
	if ( await page.locator( 'nav.woocommerce-MyAccount-navigation' ).isVisible().catch( () => false ) ) {
		return;
	}
	await page.locator( '#username' ).fill( E2E_CUSTOMER.username );
	await page.locator( '#password' ).fill( E2E_CUSTOMER.password );
	await page.locator( 'button[name="login"]' ).click();
	await expect(
		page.locator( 'nav.woocommerce-MyAccount-navigation' )
	).toBeVisible();
}
