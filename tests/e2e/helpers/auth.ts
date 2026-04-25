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
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( E2E_ADMIN.username );
	await page.locator( '#user_pass' ).fill( E2E_ADMIN.password );
	await page.locator( '#wp-submit' ).click();
	await page.waitForURL( /wp-admin/, { timeout: 15_000 } );
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
}
