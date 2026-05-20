import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth';
import { wp } from './helpers/wp-cli';
import {
	applyOptions,
	restoreOptions,
	snapshotOptions,
} from './helpers/wp-options';

/**
 * Hezarfen registers its own WooCommerce settings tab via
 * [class-hezarfen-settings-hezarfen.php](../../includes/admin/settings/class-hezarfen-settings-hezarfen.php).
 * The tab and each section are rendered by WC_Settings_Page, but the
 * "save" submit goes through Hezarfen-defined fields (custom types like
 * sms_rules_button + roadmap_voting hooks). We've had silent regressions
 * here when:
 *   - a custom field's `woocommerce_admin_field_*` action threw and
 *     wedged the form
 *   - the settings tab id changed and `wc-settings&tab=hezarfen` 404'd
 *
 * This spec drives the General + Checkout Page sections through a real
 * form submit and checks that the option values landed in the DB.
 */
const KEYS = [
	'hezarfen_show_hezarfen_checkout_tax_fields',
	'hezarfen_hide_checkout_postcode_fields',
	'hezarfen_checkout_fields_auto_sort',
];

let snapshot: Record< string, string >;

test.describe( 'Hezarfen ayar sekmesi kayıt akışı', () => {
	test.beforeAll( () => {
		snapshot = snapshotOptions( KEYS );
	} );
	test.afterAll( () => {
		restoreOptions( snapshot );
	} );

	test.beforeEach( async ( { page } ) => {
		await loginAsAdmin( page );
	} );

	test( 'General sekmesi açılıyor ve checkbox kaydedilebiliyor', async ( {
		page,
	} ) => {
		// Reset to a known starting value.
		applyOptions( {
			hezarfen_show_hezarfen_checkout_tax_fields: 'no',
		} );

		await page.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=hezarfen&section=general'
		);
		// The page renders fine if the tab id resolves. A regression here
		// would 302 to the WC General tab and the field would be missing.
		await expect(
			page.locator( '#hezarfen_show_hezarfen_checkout_tax_fields' )
		).toBeAttached();

		await page
			.locator( '#hezarfen_show_hezarfen_checkout_tax_fields' )
			.check();
		await Promise.all( [
			page.waitForURL( /tab=hezarfen/, { timeout: 15_000 } ),
			page.locator( 'button[name="save"]' ).click(),
		] );
		await page.waitForLoadState( 'networkidle' );

		// Authoritative check: option in DB now matches what we ticked
		// (the WC notice's CSS class has churned across versions; the
		// DB value is what we actually care about).
		expect(
			wp( [
				'option',
				'get',
				'hezarfen_show_hezarfen_checkout_tax_fields',
			] ).trim()
		).toBe( 'yes' );
	} );

	test( 'Checkout Page sekmesi: postcode hide + auto-sort flipleri kaydediliyor', async ( {
		page,
	} ) => {
		applyOptions( {
			hezarfen_hide_checkout_postcode_fields: 'no',
			hezarfen_checkout_fields_auto_sort: 'no',
		} );

		await page.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=hezarfen&section=checkout_page'
		);
		await expect(
			page.locator( '#hezarfen_hide_checkout_postcode_fields' )
		).toBeAttached();

		await page.locator( '#hezarfen_hide_checkout_postcode_fields' ).check();
		await page.locator( '#hezarfen_checkout_fields_auto_sort' ).check();
		await Promise.all( [
			page.waitForURL( /tab=hezarfen/, { timeout: 15_000 } ),
			page.locator( 'button[name="save"]' ).click(),
		] );
		await page.waitForLoadState( 'networkidle' );

		expect(
			wp( [
				'option',
				'get',
				'hezarfen_hide_checkout_postcode_fields',
			] ).trim()
		).toBe( 'yes' );
		expect(
			wp( [
				'option',
				'get',
				'hezarfen_checkout_fields_auto_sort',
			] ).trim()
		).toBe( 'yes' );

		// Uncheck both and save again — make sure WC's "missing checkbox
		// in POST = unchecked" branch also persists, not just truthy
		// values. (A bug here would silently leave the option `yes`.)
		await page.locator( '#hezarfen_hide_checkout_postcode_fields' ).uncheck();
		await page.locator( '#hezarfen_checkout_fields_auto_sort' ).uncheck();
		await Promise.all( [
			page.waitForURL( /tab=hezarfen/, { timeout: 15_000 } ),
			page.locator( 'button[name="save"]' ).click(),
		] );
		await page.waitForLoadState( 'networkidle' );

		expect(
			wp( [
				'option',
				'get',
				'hezarfen_hide_checkout_postcode_fields',
			] ).trim()
		).toBe( 'no' );
		expect(
			wp( [
				'option',
				'get',
				'hezarfen_checkout_fields_auto_sort',
			] ).trim()
		).toBe( 'no' );
	} );
} );
