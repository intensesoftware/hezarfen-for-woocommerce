import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth';
import { deleteMuPlugin, writeMuPlugin } from './helpers/mu-plugin';
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
	'hezarfen_returns_shipping_method',
	'hezarfen_returns_instructions',
	'hezarfen_returns_auto_refund',
	'hezarfen_returns_restock',
	'hezarfen_returns_address_contact',
	'hezarfen_returns_address_line',
	'hezarfen_returns_address_city',
];

/**
 * The promotions flag is a constant, not an option, so a mu-plugin is the
 * only way to flip it from a spec.
 */
const PROMO_SLUG = 'hezarfen-e2e-returns-promotions';
const PROMO_PHP = `<?php
if ( ! defined( 'HEZARFEN_SHOW_PRO_PROMOTIONS' ) ) {
	define( 'HEZARFEN_SHOW_PRO_PROMOTIONS', true );
}
`;

/** The locked row of a Pro-backed setting, found by the setting's own name. */
const LOCKED_STATUSES_ROW =
	'tr.hez-locked-row:has-text("İade edilebilir sipariş durumları")';

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
			page.locator( '#hezarfen_returns_shipping_method' )
		).toBeVisible();
		await expect(
			page.locator( '#hezarfen_returns_auto_refund' )
		).toBeVisible();
		await expect(
			page.locator( '#hezarfen_returns_restock' )
		).toBeVisible();
		await expect(
			page.locator( '#hezarfen_returns_address_line' )
		).toBeVisible();

		// Which order statuses may be returned is a Pro setting, so the free
		// section shows the locked row in its place. Asserting on the field
		// would go green the day someone ships the Pro setting for free.
		await expect(
			page.locator( '#hezarfen_returns_eligible_order_statuses' )
		).toHaveCount( 0 );
		await expect( page.locator( LOCKED_STATUSES_ROW ) ).toBeVisible();
	} );

	test( 'kilitli satırlar kaydetmede boş option yazmıyor', async ( {
		page,
	} ) => {
		await page.goto( SETTINGS_URL );
		await page.locator( 'button[name="save"]' ).click();
		await expect( page.locator( '#message.updated.inline' ) ).toBeVisible();

		// Every locked row is marked `is_option => false` precisely so
		// WooCommerce's default save branch does not write an empty option for
		// it — wc_clean( null ) yields '' there, not null. A written empty value
		// would later read back as "nothing is eligible" and quietly stop
		// orders from being returnable. So none of the four placeholder ids may
		// appear after a save, and the real statuses key the free flow falls
		// back on must stay untouched. Checking all four guards against a
		// regression that makes just one of them savable.
		for ( const id of [
			'hezarfen_returns_locked_statuses',
			'hezarfen_returns_locked_products',
			'hezarfen_returns_locked_reasons',
			'hezarfen_returns_locked_photos',
			'hezarfen_returns_eligible_order_statuses',
		] ) {
			expect(
				wp( [
					'eval',
					`var_export( get_option( '${ id }', 'MISSING' ) );`,
				] ).trim()
			).toBe( "'MISSING'" );
		}
	} );

	test( 'satış bağlantısı yalnızca promosyonlar açıkken çıkıyor', async ( {
		page,
	} ) => {
		// Promotions off, which is the plugin's own default: the merchant
		// still sees that the setting exists, but is not sold anything.
		await page.goto( SETTINGS_URL );
		await expect( page.locator( LOCKED_STATUSES_ROW ) ).toBeVisible();
		await expect( page.locator( '.hez-locked__cta' ) ).toHaveCount( 0 );

		writeMuPlugin( PROMO_SLUG, PROMO_PHP );

		try {
			await page.goto( SETTINGS_URL );
			await expect(
				page.locator( `${ LOCKED_STATUSES_ROW } .hez-locked__cta` )
			).toBeVisible();
		} finally {
			deleteMuPlugin( PROMO_SLUG );
		}
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
		await page.locator( '#hezarfen_returns_auto_refund' ).check();
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
		expect(
			wp( [ 'option', 'get', 'hezarfen_returns_auto_refund' ] ).trim()
		).toBe( 'yes' );

		// The saved values survive a reload of the section.
		await page.goto( SETTINGS_URL );
		await expect(
			page.locator( '#hezarfen_returns_window_days' )
		).toHaveValue( '21' );
	} );

} );
