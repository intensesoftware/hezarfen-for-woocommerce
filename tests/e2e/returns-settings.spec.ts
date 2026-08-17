import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth';
import { enableReturns } from './helpers/returns';
import { restoreOptions, snapshotOptions } from './helpers/wp-options';
import { wp } from './helpers/wp-cli';

/**
 * The "İade Yönetimi" section under WooCommerce → Ayarlar → Hezarfen.
 *
 * It is registered through WooCommerce's own `woocommerce_get_sections_*`
 * and `woocommerce_get_settings_*` filters rather than by editing the
 * settings page class, so these specs double as a regression guard on
 * that wiring: if the filters stop matching, the section silently
 * disappears and nothing else in the plugin would notice.
 */

const SETTINGS_URL =
	'/wp-admin/admin.php?page=wc-settings&tab=hezarfen&section=returns';

const OPTION_KEYS = [
	'hezarfen_returns_enabled',
	'hezarfen_returns_window_days',
	'hezarfen_returns_window_reference',
	'hezarfen_returns_eligible_order_statuses',
	'hezarfen_returns_shipping_method',
	'hezarfen_returns_instructions',
	'hezarfen_returns_address_contact',
	'hezarfen_returns_address_line',
	'hezarfen_returns_address_city',
];

let optionSnapshot: Record< string, string >;

test.describe( 'Hezarfen iade — ayarlar bölümü', () => {
	test.beforeAll( () => {
		optionSnapshot = snapshotOptions( OPTION_KEYS );
		enableReturns();
	} );

	test.afterAll( () => {
		restoreOptions( optionSnapshot );
	} );

	test.beforeEach( async ( { page } ) => {
		await loginAsAdmin( page );
	} );

	test( 'Hezarfen sekmesinde iade bölümü listeleniyor', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=hezarfen' );

		await expect( page.locator( '.subsubsub' ) ).toContainText(
			'İade Yönetimi'
		);
	} );

	test( 'bölüm tüm alanlarıyla açılıyor', async ( { page } ) => {
		await page.goto( SETTINGS_URL );

		await expect(
			page.locator( '#hezarfen_returns_enabled' )
		).toBeVisible();
		await expect(
			page.locator( '#hezarfen_returns_window_days' )
		).toBeVisible();
		await expect(
			page.locator( '#hezarfen_returns_window_reference' )
		).toBeVisible();
		await expect(
			page.locator( '#hezarfen_returns_eligible_order_statuses' )
		).toBeAttached();
		await expect(
			page.locator( '#hezarfen_returns_shipping_method' )
		).toBeVisible();
		await expect(
			page.locator( '#hezarfen_returns_address_line' )
		).toBeVisible();
	} );

	test( 'ayarlar kaydediliyor ve option değerleri güncelleniyor', async ( {
		page,
	} ) => {
		await page.goto( SETTINGS_URL );

		await page.locator( '#hezarfen_returns_window_days' ).fill( '21' );
		await page
			.locator( '#hezarfen_returns_window_reference' )
			.selectOption( 'paid' );
		await page
			.locator( '#hezarfen_returns_address_contact' )
			.fill( 'E2E Ayar Depo' );
		await page
			.locator( '#hezarfen_returns_instructions' )
			.fill( 'Ürünü orijinal kutusunda gönderin.' );

		await page.locator( 'button[name="save"]' ).click();
		// WooCommerce's own "settings saved" notice, not whatever
		// promotional `.updated` banner the store happens to be showing.
		await expect( page.locator( '#message.updated.inline' ) ).toBeVisible();

		expect(
			wp( [ 'option', 'get', 'hezarfen_returns_window_days' ] ).trim()
		).toBe( '21' );
		expect(
			wp( [ 'option', 'get', 'hezarfen_returns_window_reference' ] ).trim()
		).toBe( 'paid' );
		expect(
			wp( [ 'option', 'get', 'hezarfen_returns_address_contact' ] ).trim()
		).toBe( 'E2E Ayar Depo' );

		// The saved values survive a reload of the section.
		await page.goto( SETTINGS_URL );
		await expect(
			page.locator( '#hezarfen_returns_window_days' )
		).toHaveValue( '21' );
	} );

} );
