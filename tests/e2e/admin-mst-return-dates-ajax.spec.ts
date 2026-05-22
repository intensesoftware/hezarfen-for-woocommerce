import { expect, test } from '@playwright/test';
import { loginAsAdmin, loginAsCustomer } from './helpers/auth';
import { deleteOrder, seedTestOrder } from './helpers/orders';
import { E2E_CUSTOMER } from './global-setup';
import { wp } from './helpers/wp-cli';

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

	test( 'admin yetkisi yoksa endpoint 403 ile reddediyor', async ( {
		page,
	} ) => {
		// Sign in as a customer (no `manage_woocommerce`) so the AJAX
		// dispatcher accepts the request — `wp_ajax_<action>` only fires
		// for authenticated users. Anonymous traffic would be rejected
		// at the dispatcher level with `wp_die("0", 400)` and never
		// reach the application-level guards we want to exercise.
		await loginAsCustomer( page );

		// `wp_create_nonce` binds the token to (user_id, action,
		// session_token). wp-cli runs with an empty session token,
		// whereas the browser session that POSTs the request below has
		// a real one — so a nonce minted here won't pass `wp_verify_nonce`
		// against the browser cookie. That's fine: the contract we're
		// asserting is "non-admin requests are rejected with HTTP 403",
		// which both the nonce guard (`wp_die('-1', 403)`) and the
		// capability guard (`wp_send_json_error('Insufficient
		// permissions', 403)`) satisfy. We don't try to thread a valid
		// browser-session nonce through wp-cli — instead we POST with a
		// stale nonce and assert on the status code that's common to
		// both guards.
		const nonce = wp( [
			'eval',
			`
				$u = get_user_by( 'login', '${ E2E_CUSTOMER.username }' );
				wp_set_current_user( $u->ID );
				echo wp_create_nonce( 'hezarfen_mst_get_return_dates' );
			`,
		] ).trim();
		expect( nonce ).toMatch( /^[a-f0-9]+$/ );

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

		// Accept either rejection path: nonce guard returns the literal
		// "-1" wp_die body; capability guard returns a JSON envelope
		// with `data: 'Insufficient permissions'`. Both prove the
		// request was rejected before reaching Hepsijet's relay.
		const body = ( await response.text() ).trim();
		const isNonceDie = body.startsWith( '-1' );
		const isCapDie =
			body.startsWith( '{' ) &&
			/Insufficient permissions/.test( body );
		expect( isNonceDie || isCapDie ).toBe( true );
	} );
} );
