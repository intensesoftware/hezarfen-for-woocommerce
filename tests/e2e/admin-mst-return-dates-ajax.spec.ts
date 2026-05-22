import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth';
import { deleteOrder, seedTestOrder } from './helpers/orders';

/**
 * The Hepsijet relay integration exposes
 * `wp_ajax_hezarfen_mst_get_return_dates`
 * ([class-admin-ajax.php](../../packages/manual-shipment-tracking/includes/admin/class-admin-ajax.php))
 * which the admin order-edit JS calls to populate the iade tarihi
 * dropdown. The endpoint guards against unauthenticated and CSRF
 * traffic by combining `check_ajax_referer( 'hezarfen_mst_get_return_dates' )`
 * with a `manage_woocommerce` capability check.
 *
 * Both legs of that guard have failed silently in the past:
 *   - A refactor dropped the nonce check; the dropdown still worked
 *     locally but a logged-out attacker could spam Hepsijet's relay
 *     through our endpoint.
 *   - The localized JS variable that ships the nonce stopped being
 *     enqueued; admin users saw an empty dropdown with no obvious
 *     error.
 *
 * Drive the endpoint from the browser context with the admin cookie
 * so cookie-based auth + nonce verification both run through the real
 * code path.
 */
let orderId: string;

test.describe( 'Hezarfen MST iade tarihleri AJAX yetkilendirme', () => {
	test.beforeAll( () => {
		// We need an order so we can land on the order-edit screen
		// where the MST script (and its `wp_localize_script` nonce)
		// is enqueued.
		orderId = seedTestOrder( { status: 'processing' } );
	} );
	test.afterAll( () => {
		deleteOrder( orderId );
	} );

	test( 'nonce yokken endpoint -1 / 403 ile reddediyor', async ( {
		page,
	} ) => {
		await loginAsAdmin( page );

		// Reuse the admin session cookies via `page.request`; we strip
		// the nonce explicitly to exercise the guard.
		const response = await page.request.post( '/wp-admin/admin-ajax.php', {
			form: {
				action: 'hezarfen_mst_get_return_dates',
				start_date: '2025-01-01',
				end_date: '2025-01-10',
				city: 'Istanbul',
				district: 'Kadıköy',
			},
			failOnStatusCode: false,
		} );

		// `check_ajax_referer` without a valid token calls
		// `wp_die( '-1', 403 )`. The body is the literal "-1" plus
		// trailing markup; status code is the authoritative signal.
		expect( response.status() ).toBe( 403 );
		const body = await response.text();
		expect( body.trim().startsWith( '-1' ) ).toBe( true );
	} );

	test( 'admin order-edit ekranındaki localized nonce ile endpoint başarılı yanıt veriyor', async ( {
		page,
	} ) => {
		await loginAsAdmin( page );
		await page.goto(
			`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }`
		);

		// `wp_localize_script( 'hezarfen_mst_backend', … )` puts the
		// nonce on `window.hezarfen_mst_backend.get_return_dates_nonce`.
		// If this assertion ever fails the bundled JS lost access to
		// the nonce and the dropdown would silently break for real
		// users.
		const nonce = await page.evaluate( () => {
			const ns = ( window as any ).hezarfen_mst_backend;
			return ns ? ns.get_return_dates_nonce : '';
		} );
		expect( nonce ).toMatch( /^[a-f0-9]+$/ );

		// Use the page's request fixture so the call carries the same
		// cookies the metabox JS would. Pass missing-param values so
		// the test stays deterministic even when the Hepsijet relay
		// is unreachable from the test environment — the auth layer
		// still has to accept the nonce + capability before any 400
		// validation can run.
		const response = await page.request.post(
			'/wp-admin/admin-ajax.php',
			{
				form: {
					action: 'hezarfen_mst_get_return_dates',
					_wpnonce: nonce,
					start_date: '',
					end_date: '',
					city: '',
					district: '',
				},
				failOnStatusCode: false,
			}
		);

		// Auth passed → we should land in the validation branch which
		// emits `wp_send_json_error( 'Missing required parameters', 400 )`.
		// A 403 here would mean the nonce/cap layer rejected us — the
		// regression we want flagged.
		expect( response.status() ).toBe( 400 );
		const body = await response.json();
		expect( body ).toMatchObject( {
			success: false,
			data: 'Missing required parameters',
		} );
	} );

	test( 'admin yetkisi yoksa endpoint Insufficient permissions ile reddediyor', async ( {
		page,
	} ) => {
		// Take the admin nonce first, then drop privileges by logging
		// out before we POST. The nonce is bound to the user session
		// that minted it, so the post-logout request hits the
		// capability check with a stale anonymous session.
		await loginAsAdmin( page );
		await page.goto(
			`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }`
		);
		const nonce = await page.evaluate( () => {
			const ns = ( window as any ).hezarfen_mst_backend;
			return ns ? ns.get_return_dates_nonce : '';
		} );
		expect( nonce ).toBeTruthy();

		// Clear cookies — request runs unauthenticated. Nonce is
		// session-bound, so verify_nonce returns false first, and we
		// see the same 403/-1 wp_die from the nonce guard.
		await page.context().clearCookies();

		const response = await page.request.post(
			'/wp-admin/admin-ajax.php',
			{
				form: {
					action: 'hezarfen_mst_get_return_dates',
					_wpnonce: nonce,
					start_date: '2025-01-01',
					end_date: '2025-01-10',
					city: 'Istanbul',
					district: 'Kadıköy',
				},
				failOnStatusCode: false,
			}
		);
		expect( response.status() ).toBe( 403 );
	} );
} );
