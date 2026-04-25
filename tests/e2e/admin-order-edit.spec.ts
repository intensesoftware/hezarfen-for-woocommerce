import { expect, test, type ConsoleMessage, type Page } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth';
import { deleteOrder, seedTestOrder } from './helpers/orders';
import {
	applyOptions,
	restoreOptions,
	snapshotOptions,
} from './helpers/wp-options';

/**
 * Hezarfen ships several admin-side mutations to the order edit screen:
 *   - injects Vergi No / Vergi Dairesi / Invoice type into the billing
 *     details block (`woocommerce_admin_billing_fields`)
 *   - prints the decrypted TC ID after the billing address block
 *   - registers a "Sözleşmeler" metabox when contracts are enabled
 *   - encrypts/decrypts billing_hez_TC_number meta on read/write
 *
 * Each of those touchpoints is a chance to break the order edit page
 * — past regressions wedged the screen with PHP fatals, JS exceptions
 * from the order-edit React bundle, or empty save buttons. This file
 * exists to catch that class of breakage on every run.
 */
const FEATURE_OPTIONS = {
	hezarfen_show_hezarfen_checkout_tax_fields: 'yes',
	hezarfen_checkout_show_TC_identity_field: 'yes',
};

let snapshot: Record< string, string >;
let orderId: string;

test.describe( 'Hezarfen admin order edit (HPOS)', () => {
	test.beforeAll( () => {
		snapshot = snapshotOptions( Object.keys( FEATURE_OPTIONS ) );
		applyOptions( FEATURE_OPTIONS );
		orderId = seedTestOrder( { status: 'on-hold' } );
	} );
	test.afterAll( () => {
		deleteOrder( orderId );
		restoreOptions( snapshot );
	} );

	test.beforeEach( async ( { page } ) => {
		await loginAsAdmin( page );
	} );

	test( 'order edit page loads without JS or PHP errors', async ( {
		page,
	} ) => {
		const errors = collectPageErrors( page );

		await page.goto(
			`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }`
		);

		// WordPress core / WooCommerce / Hezarfen all enqueue scripts on
		// this screen — give them a beat to settle, then check.
		await expect( page.locator( '#order_data' ) ).toBeVisible();
		await page.waitForLoadState( 'networkidle' );

		// PHP fatals usually surface as a "There has been a critical
		// error on this website" notice or a missing primary form.
		await expect(
			page.locator( '.wp-die-message, body.error404' )
		).toHaveCount( 0 );
		await expect( page.locator( 'form#order' ) ).toBeVisible();

		expect.soft( errors.pageErrors, 'uncaught JS errors on the page' )
			.toEqual( [] );
		expect.soft( errors.consoleErrors, 'console.error during load' )
			.toEqual( [] );
	} );

	test( 'standard WC + Hezarfen billing fields all render', async ( {
		page,
	} ) => {
		await page.goto(
			`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }`
		);
		await expect( page.locator( '#order_data' ) ).toBeVisible();

		// Standard WC bits — order status + items + actions metaboxes.
		await expect( page.locator( '#order_status' ) ).toBeVisible();
		await expect( page.locator( '#woocommerce-order-items' ) ).toBeVisible();
		await expect(
			page.locator( '#woocommerce-order-actions' )
		).toBeVisible();

		// Hezarfen tax fields are registered in the billing details
		// block. They're hidden by CSS until the editor pencil is clicked,
		// so we assert presence in the DOM (attached) rather than visual
		// visibility — that's enough to detect a regression that would
		// drop Hezarfen's `woocommerce_admin_billing_fields` injection.
		await expect(
			page.locator( '#_billing_hez_tax_number' )
		).toBeAttached();
		await expect(
			page.locator( '#_billing_hez_tax_office' )
		).toBeAttached();
		await expect(
			page.locator( '#_billing_hez_invoice_type' )
		).toBeAttached();
	} );

	test( 'status change saves and persists', async ( { page } ) => {
		await page.goto(
			`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }`
		);
		await expect( page.locator( '#order_data' ) ).toBeVisible();

		// Reset to on-hold first so the test is order-independent.
		await page
			.locator( '#order_status' )
			.evaluate( ( el ) => {
				const sel = el as HTMLSelectElement;
				sel.value = 'wc-on-hold';
				sel.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				const $ = ( window as any ).jQuery;
				if ( $ ) $( sel ).trigger( 'change' );
			} );

		// Save (Update button submits the form to wc-orders endpoint).
		await page.locator( 'button.save_order' ).click();
		await page.waitForURL( /wc-orders/, { timeout: 15_000 } );
		await expect( page.locator( '#order_status' ) ).toHaveValue(
			'wc-on-hold'
		);

		// Now move it to completed.
		await page
			.locator( '#order_status' )
			.evaluate( ( el ) => {
				const sel = el as HTMLSelectElement;
				sel.value = 'wc-completed';
				sel.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				const $ = ( window as any ).jQuery;
				if ( $ ) $( sel ).trigger( 'change' );
			} );
		await page.locator( 'button.save_order' ).click();
		await page.waitForURL( /wc-orders/, { timeout: 15_000 } );
		await expect( page.locator( '#order_status' ) ).toHaveValue(
			'wc-completed'
		);
	} );

	test( 'admin can add an order note', async ( { page } ) => {
		await page.goto(
			`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }`
		);
		await expect(
			page.locator( '#woocommerce-order-notes' )
		).toBeVisible();

		const noteText = `e2e note ${ Date.now() }`;
		await page.locator( '#add_order_note' ).fill( noteText );
		await page.locator( 'button.add_note' ).click();

		// The new note is rendered into the notes list via AJAX.
		await expect(
			page.locator( '.order_notes' )
		).toContainText( noteText );
	} );
} );

/**
 * Capture both `pageerror` (uncaught exceptions) and console.error
 * messages while the test runs. Returned arrays are mutated as new
 * events fire, so callers should grab them after the page settles.
 */
function collectPageErrors( page: Page ): {
	pageErrors: string[];
	consoleErrors: string[];
} {
	const pageErrors: string[] = [];
	const consoleErrors: string[] = [];
	page.on( 'pageerror', ( err ) => {
		pageErrors.push( err.message );
	} );
	page.on( 'console', ( msg: ConsoleMessage ) => {
		if ( msg.type() !== 'error' ) return;
		const text = msg.text();
		// Filter known noisy warnings from third-party admin assets
		// that are unrelated to the order edit screen we're vetting.
		if (
			text.includes( 'favicon' ) ||
			text.includes( 'net::ERR_BLOCKED_BY_CLIENT' )
		) {
			return;
		}
		consoleErrors.push( text );
	} );
	return { pageErrors, consoleErrors };
}
