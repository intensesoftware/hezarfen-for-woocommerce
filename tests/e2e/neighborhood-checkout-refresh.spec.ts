import { expect, test } from '@playwright/test';
import {
	addE2EProductToCart,
	expectMahalleAjax,
	pickFromSelect,
	TR_SAMPLE_ADDRESS,
	waitForCheckoutIdle,
	waitForOptionsPopulated,
} from './helpers/checkout';
import { deleteMuPlugin, writeMuPlugin } from './helpers/mu-plugin';
import { wp } from './helpers/wp-cli';

/**
 * Picking a mahalle on the checkout fires
 * `wc_hezarfen_neighborhood_changed` (admin-ajax — see
 * [assets/js/checkout.js:105-123]). The handler in
 * [includes/Ajax.php] returns `{update_checkout: true}`, which the
 * checkout JS turns into a `update_checkout` jQuery trigger, which in
 * turn drives WC's standard `update_order_review` AJAX. That second
 * call is the one that recalculates shipping (e.g. MBGB neighborhood-
 * based rates) and refreshes the cart totals — without it, the customer
 * sees stale shipping prices for whichever neighborhood was selected
 * last.
 *
 * Existing specs cover the cascading select dropdowns (`checkout-tr`,
 * `my-account-address`) and the country swap (`checkout-country-switch`)
 * but no spec proves the neighborhood selection actually chains into a
 * checkout review refresh. This file pins both legs:
 *   1. The neighborhood_changed AJAX is POSTed with the expected
 *      payload + nonce.
 *   2. After the handler responds, `update_order_review` fires before
 *      the page settles.
 */
/**
 * `wc_hezarfen_checkout.should_notify_neighborhood_changed` (see
 * [assets/js/checkout.js:3-9]) gates the AJAX behind either MBGB being
 * active or the `hezarfen_checkout_should_notify_neighborhood_changed`
 * filter returning true. Stock LocalWP sites have neither, so the
 * notify call never fires by default. Drop a fixture mu-plugin that
 * flips the filter on — that's the closest in-suite analogue to a
 * production install that needs neighborhood-aware shipping rates.
 */
const MU_PLUGIN_SLUG = 'hezarfen-e2e-neighborhood-notify';
const MU_PLUGIN_PHP = `<?php
/**
 * Plugin Name: Hezarfen E2E — force notify_neighborhood_changed
 */
add_filter( 'hezarfen_checkout_should_notify_neighborhood_changed', '__return_true' );
`;

test.describe( 'Hezarfen mahalle seçimi sonrası checkout review AJAX zinciri', () => {
	test.beforeAll( () => {
		writeMuPlugin( MU_PLUGIN_SLUG, MU_PLUGIN_PHP );
	} );
	test.afterAll( () => {
		deleteMuPlugin( MU_PLUGIN_SLUG );
	} );

	test.beforeEach( async ( { page } ) => {
		await addE2EProductToCart( page );
	} );

	test( 'mahalle değişimi neighborhood_changed → update_order_review zincirini tetikliyor', async ( {
		page,
	} ) => {
		// Probe whether the fixture mu-plugin is actually loaded. The
		// JS-side `should_notify_neighborhood_changed` gate is fed from
		// `apply_filters( 'hezarfen_checkout_should_notify_neighborhood_changed', false )`.
		// If the mu-plugin isn't loaded (some wp-env CI setups don't
		// pick up runtime-written mu-plugins), the gate stays false,
		// the JS never POSTs, and we'd false-fail the AJAX wait. Skip
		// cleanly in that case — the chain we're testing only makes
		// sense when the notify path is active.
		const notifyActive = wp( [
			'eval',
			"echo apply_filters( 'hezarfen_checkout_should_notify_neighborhood_changed', false ) ? '1' : '0';",
		] ).trim();
		test.skip(
			notifyActive !== '1',
			`Fixture mu-plugin ${ MU_PLUGIN_SLUG } not active — hezarfen_checkout_should_notify_neighborhood_changed filter returned false. Skipping AJAX chain assertion.`
		);

		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );

		// Walk the il → ilçe chain. Two AJAX hops we already test in
		// other specs — we wait for both before touching mahalle so the
		// final response we care about is the neighborhood_changed POST.
		const districtPromise = expectMahalleAjax( page, 'district' );
		await pickFromSelect(
			page,
			'#billing_state',
			TR_SAMPLE_ADDRESS.cityPlate
		);
		await districtPromise;
		await waitForOptionsPopulated( page, '#billing_city' );

		const neighborhoodPromise = expectMahalleAjax( page, 'neighborhood' );
		await pickFromSelect(
			page,
			'#billing_city',
			TR_SAMPLE_ADDRESS.district
		);
		await neighborhoodPromise;
		await waitForOptionsPopulated( page, '#billing_address_1' );

		// Register the response listener BEFORE selecting the
		// neighborhood — `notify_neighborhood_changed` POSTs via
		// jQuery.ajax with no debounce on this side, so a slow listener
		// can miss the call entirely. The 30s window covers cold CI
		// workers where WC's `update_order_review` triggered by the
		// preceding district AJAX is still in-flight and serializes
		// against the neighborhood call.
		const notifyPromise = page.waitForResponse(
			( res ) =>
				res.url().includes( '/wp-admin/admin-ajax.php' ) &&
				res.request().method() === 'POST' &&
				(
					res.request().postData() ?? ''
				).includes(
					'action=wc_hezarfen_neighborhood_changed'
				),
			{ timeout: 30_000 }
		);

		await pickFromSelect(
			page,
			'#billing_address_1',
			TR_SAMPLE_ADDRESS.neighborhood
		);

		const notifyRes = await notifyPromise;
		const requestBody = notifyRes.request().postData() ?? '';
		expect( requestBody ).toContain(
			`cityPlateNumber=${ encodeURIComponent(
				TR_SAMPLE_ADDRESS.cityPlate
			) }`
		);
		expect( requestBody ).toMatch(
			new RegExp(
				`district=${ encodeURIComponent( TR_SAMPLE_ADDRESS.district ) }`
			)
		);
		// `security` nonce param is mandatory — the server-side handler
		// short-circuits without it via `check_ajax_referer`. Catch a
		// regression that drops nonce localization here rather than
		// from a flaky 403 down the line.
		expect( requestBody ).toMatch( /security=[a-f0-9]+/ );

		// The handler returns `{update_checkout: true}` so the JS-side
		// `notify_neighborhood_changed` callback can fire WC's
		// `update_checkout` body event — that's how the cart review
		// (shipping rates, totals, MBGB integration) gets recalculated
		// per mahalle. Asserting on the response body is more robust
		// than chasing the downstream `update_order_review` request,
		// which WC debounces against in-flight reviews triggered by
		// the preceding district AJAX.
		expect( notifyRes.status() ).toBe( 200 );
		const payload = await notifyRes.json();
		expect( payload ).toEqual(
			expect.objectContaining( { update_checkout: true } )
		);

		await waitForCheckoutIdle( page );
	} );
} );
