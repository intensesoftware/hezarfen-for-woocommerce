import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth';
import { deleteOrder, seedTestOrder } from './helpers/orders';
import { wp } from './helpers/wp-cli';

/**
 * The "Hezarfen Cargo Tracking & SMS Notifications" metabox is the
 * primary admin surface for assigning a courier + tracking number
 * (lite mode). The flow is jQuery-driven by
 * [main.js](../../assets/admin/order-edit/src/main.js):
 *   1. picking a courier radio toggles `#standard-tracking-fields`
 *      and `#add-to-tracking-list` from `.hidden`
 *   2. clicking the button POSTs `hezarfen_mst_new_shipment_data`
 *      with the wp-nonce, which routes to
 *      [class-admin-ajax.php](../../packages/manual-shipment-tracking/includes/admin/class-admin-ajax.php)
 *      → `Helper::new_order_shipment_data`
 *
 * `admin-shipment-tracking.spec.ts` covers the customer-facing rendering
 * by calling the Helper directly. This file covers what that file
 * explicitly skips: the metabox UI + AJAX path. If a future change
 * breaks the JS bundle, the radio binding, or the AJAX nonce, the
 * customer never sees a tracking number again — that's the regression
 * we want flagged on every run.
 *
 * When Hezarfen Pro is also active the metabox renders a tabbed
 * interface and `#hezarfen-lite` starts with `.hidden`. We force the
 * lite tab open via `ensureLiteTabActive` so this spec is meaningful
 * with or without Pro installed — the regression risk is the same.
 */
let orderId: string;

test.describe( 'Hezarfen MST kargo metabox UI (admin)', () => {
	test.beforeEach( () => {
		orderId = seedTestOrder( { status: 'processing' } );
	} );
	test.afterEach( () => {
		deleteOrder( orderId );
	} );

	test( 'metabox yükleniyor ve kurye listesi populate oluyor', async ( {
		page,
	} ) => {
		await loginAsAdmin( page );
		await page.goto(
			`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }`
		);
		await expect(
			page.locator( '#hezarfen-mst-order-edit-metabox' )
		).toBeVisible();
		await ensureLiteTabActive( page );
		await expect( page.locator( '#hez-order-shipments' ) ).toBeAttached();

		// `Helper::courier_company_options()` registers around two dozen
		// couriers; we don't pin a specific count (it changes as the
		// plugin adds new ones), but a healthy render has at least the
		// big TR couriers wired up.
		await expect(
			page.locator( '#courier-company-select-aras' )
		).toBeAttached();
		await expect(
			page.locator( '#courier-company-select-yurtici' )
		).toBeAttached();
	} );

	test( 'kurye seçilince standart tracking alanları görünür hale geliyor', async ( {
		page,
	} ) => {
		await loginAsAdmin( page );
		await page.goto(
			`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }`
		);
		await ensureLiteTabActive( page );
		await expect( page.locator( '#hez-order-shipments' ) ).toBeAttached();

		// Initial state: standard fields + save button hidden until a
		// courier is picked.
		await expect(
			page.locator( '#standard-tracking-fields' )
		).toBeHidden();
		await expect(
			page.locator( '#add-to-tracking-list' )
		).toBeHidden();

		// Pick aras kargo. The radio's <label> is visible; the input is
		// `.hidden peer` for styling, so we check via JS to avoid the
		// "hidden input" error path.
		await page.evaluate( () => {
			const el = document.querySelector< HTMLInputElement >(
				'#courier-company-select-aras'
			);
			if ( ! el ) throw new Error( 'aras radio missing' );
			el.checked = true;
			el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			const $ = ( window as any ).jQuery;
			if ( $ ) $( el ).trigger( 'change' );
		} );

		await expect(
			page.locator( '#standard-tracking-fields' )
		).toBeVisible();
		await expect(
			page.locator( '#add-to-tracking-list' )
		).toBeVisible();
		await expect(
			page.locator( '#tracking-num-input' )
		).toBeVisible();
	} );

	test( '"Add to Tracking List" AJAX kaydı sonrası order shipment data\'ya yazılıyor', async ( {
		page,
	} ) => {
		await loginAsAdmin( page );
		await page.goto(
			`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }`
		);
		await ensureLiteTabActive( page );
		await expect( page.locator( '#hez-order-shipments' ) ).toBeAttached();

		await page.evaluate( () => {
			const el = document.querySelector< HTMLInputElement >(
				'#courier-company-select-yurtici'
			);
			if ( ! el ) throw new Error( 'yurtici radio missing' );
			el.checked = true;
			el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			const $ = ( window as any ).jQuery;
			if ( $ ) $( el ).trigger( 'change' );
		} );

		await expect(
			page.locator( '#standard-tracking-fields' )
		).toBeVisible();

		const trackingNumber = `TRK${ Date.now() }`;
		await page.locator( '#tracking-num-input' ).fill( trackingNumber );

		// Wait for the AJAX response; main.js `location.reload()`s on
		// success, so we also wait for the navigation that follows.
		const ajaxResponse = page.waitForResponse(
			( res ) =>
				res.url().includes( 'admin-ajax.php' ) &&
				res
					.request()
					.postData()
					?.includes( 'hezarfen_mst_new_shipment_data' ) === true
		);
		await page.locator( '#add-to-tracking-list' ).click();
		const response = await ajaxResponse;
		expect( response.status() ).toBe( 200 );

		// Helper::new_order_shipment_data persists the encapsulated
		// shipment_data array against the order. Read it back through
		// wp-cli rather than trusting the JS-driven post-reload UI.
		const persisted = wp( [
			'eval',
			`
				$data = \\Hezarfen\\ManualShipmentTracking\\Helper::get_all_shipment_data( ${ orderId } );
				if ( empty( $data ) ) { echo 'NONE'; return; }
				echo $data[0]->courier_id . '|' . $data[0]->tracking_num;
			`,
		] ).trim();
		expect( persisted ).toBe( `yurtici|${ trackingNumber }` );
	} );
} );

/**
 * With Hezarfen Pro active the metabox renders a Pro/Lite tab toggle
 * and `#hezarfen-lite` carries `.hidden` until the customer clicks the
 * Manual Tracking button. Without Pro it's already visible.
 *
 * Drop the `.hidden` class outright instead of clicking the tab so the
 * test stays dependable across Flowbite/jQuery readiness races. The
 * point of these tests is the courier picker + AJAX, not the tabs.
 */
async function ensureLiteTabActive(
	page: import( '@playwright/test' ).Page
): Promise< void > {
	await page.waitForFunction( () => {
		return !! document.getElementById( 'hezarfen-lite' );
	} );
	await page.evaluate( () => {
		const lite = document.getElementById( 'hezarfen-lite' );
		if ( ! lite ) return;
		lite.classList.remove( 'hidden' );
		( lite as HTMLElement ).style.display = 'block';
	} );
}

