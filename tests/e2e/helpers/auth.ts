import { expect, type Page } from '@playwright/test';
import { E2E_ADMIN, E2E_CUSTOMER } from '../global-setup';

export async function loginAsCustomer( page: Page ): Promise< void > {
	await page.goto( '/my-account/' );
	if (
		await page
			.locator( 'nav.woocommerce-MyAccount-navigation' )
			.isVisible()
			.catch( () => false )
	) {
		return;
	}
	await page.locator( '#username' ).fill( E2E_CUSTOMER.username );
	await page.locator( '#password' ).fill( E2E_CUSTOMER.password );
	await page.locator( 'button[name="login"]' ).click();
	await expect(
		page.locator( 'nav.woocommerce-MyAccount-navigation' )
	).toBeVisible();
}

export async function loginAsAdmin( page: Page ): Promise< void > {
	// On wp-env cold-starts (first test in a worker), `wp-login.php`'s
	// show-password JS can mutate `#user_pass` after we've already
	// resolved a locator for it — the fill then lands on whatever input
	// still has focus (typically `#user_login`), and the submit is
	// rejected by the browser's native required-field validator.
	// Waiting for both inputs to be attached + clicking before fill
	// pins focus to the right element, and Promise.all on submit avoids
	// the race where waitForURL is registered after the redirect already
	// fired.
	await page.goto( '/wp-login.php', { waitUntil: 'domcontentloaded' } );
	const userInput = page.locator( '#user_login' );
	const passInput = page.locator( '#user_pass' );
	await userInput.waitFor( { state: 'visible' } );
	await passInput.waitFor( { state: 'visible' } );

	await userInput.click();
	await userInput.fill( E2E_ADMIN.username );
	await passInput.click();
	await passInput.fill( E2E_ADMIN.password );

	await Promise.all( [
		page.waitForURL( /wp-admin/, { timeout: 30_000 } ),
		page.locator( '#wp-submit' ).click(),
	] );
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
}
