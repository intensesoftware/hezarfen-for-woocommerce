import { expect, test, type Page } from '@playwright/test';
import {
	addE2EProductToCart,
	waitForCheckoutIdle,
} from './helpers/checkout';
import { wp } from './helpers/wp-cli';
import {
	applyOptions,
	restoreOptions,
	snapshotOptions,
} from './helpers/wp-options';

/**
 * `Contract_Renderer::get_contract_fragments` is wired into
 * `woocommerce_update_order_review_fragments` ([class-contract-renderer.php:32]).
 * Every time WC fires `update_order_review` (state change, payment-method
 * switch, programmatic `$('body').trigger('update_checkout')`) the
 * filter rebuilds every active contract by piping the POSTed
 * `post_data` through `Template_Processor` and surfaces the result
 * back to the client as a `.hezarfen-contract-data` fragment whose
 * `data-contracts` attribute is a JSON object keyed by contract id.
 *
 * That fragment is what the modal/inline content the customer
 * actually reads before ticking the agreement checkbox renders from.
 * If the filter stops firing — or the renderer stops respecting the
 * live form values — the customer ends up agreeing to a contract that
 * still references the old name/address. This file pins both halves:
 *
 *   1. After the first update_order_review, the response carries a
 *      fragment whose JSON includes the billing first name the
 *      customer just typed.
 *   2. Editing the name and triggering another update_order_review
 *      swaps the fragment to the new value — proving the renderer
 *      doesn't cache the previous payload.
 *
 * We trigger `update_checkout` via the body event directly rather
 * than relying on WC's "state change fires it implicitly" branch:
 * that branch fights with Hezarfen's own mahalle helper for the
 * state-change handler, and the race is unreliable across LocalWP
 * vs wp-env. The body trigger is what every WC integration uses
 * and is the contract we want to pin.
 */
const FEATURE_OPTIONS = {
	hezarfen_contracts_enabled: 'yes',
};

const TEMPLATE_MARKER = 'HEZARFEN_E2E_FATURA_ADI__';
const TEMPLATE_BODY = `<p>${ TEMPLATE_MARKER }{{fatura_adi}}</p>`;

let snapshot: Record< string, string >;
let mssSettingsSnapshot: string;
let templatePageId: string;

test.describe( 'Hezarfen sözleşme AJAX refresh — checkout update_order_review', () => {
	test.beforeAll( () => {
		snapshot = snapshotOptions( Object.keys( FEATURE_OPTIONS ) );
		mssSettingsSnapshot = wp(
			[ 'option', 'get', 'hezarfen_mss_settings', '--format=json' ],
			{ allowFailure: true }
		).trim();
		applyOptions( FEATURE_OPTIONS );

		// Seed a template page that carries the `{{fatura_adi}}`
		// placeholder. global-setup wires the contracts to whichever
		// published page it finds first (Sample Page on a clean
		// install), which has no Hezarfen placeholders — so a live
		// fragment refresh would have nothing observable to verify
		// against.
		templatePageId = wp( [
			'post',
			'create',
			'--post_type=page',
			'--post_status=publish',
			'--post_title=Hezarfen E2E Contract Template',
			`--post_content=${ TEMPLATE_BODY }`,
			'--porcelain',
		] ).trim();

		wp( [
			'eval',
			`
				$opts = get_option( 'hezarfen_mss_settings', array() );
				foreach ( $opts['contracts'] as &$c ) {
					$c['template_id'] = '${ templatePageId }';
				}
				update_option( 'hezarfen_mss_settings', $opts );
				echo 'OK';
			`,
		] );
	} );
	test.afterAll( () => {
		restoreOptions( snapshot );
		if ( mssSettingsSnapshot ) {
			wp(
				[
					'option',
					'update',
					'hezarfen_mss_settings',
					mssSettingsSnapshot,
					'--format=json',
				],
				{ allowFailure: true }
			);
		}
		if ( templatePageId ) {
			wp( [ 'post', 'delete', templatePageId, '--force' ], {
				allowFailure: true,
			} );
		}
	} );

	test.beforeEach( async ( { page } ) => {
		// Re-apply the contract-enable flag + template wiring on every
		// test. Sibling files that run earlier in the batch and toggle
		// `hezarfen_contracts_enabled` (e.g. contracts-combined-checkbox)
		// restore their own snapshot in afterAll, but the order of
		// describe-level hooks across files isn't ours to control — a
		// belt-and-suspenders re-apply here pins state per test.
		applyOptions( FEATURE_OPTIONS );
		wp( [
			'eval',
			`
				$opts = get_option( 'hezarfen_mss_settings', array() );
				foreach ( $opts['contracts'] as &$c ) {
					$c['template_id'] = '${ templatePageId }';
					$c['enabled'] = '1';
					$c['show_in_checkbox'] = '1';
				}
				update_option( 'hezarfen_mss_settings', $opts );
			`,
		] );
		await addE2EProductToCart( page );
	} );

	test( 'update_order_review fragment\'ı sözleşme JSON\'ını döndürüyor ve form verisi içerikte görünüyor', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );

		await page.locator( '#billing_first_name' ).fill( 'Ada' );
		await page.locator( '#billing_last_name' ).fill( 'Lovelace' );
		await page.locator( '#billing_phone' ).fill( '5551112233' );
		await page.locator( '#billing_email' ).fill( 'ada@example.test' );

		const fragments = await triggerUpdateCheckout( page );
		expect( fragments[ '.hezarfen-contract-data' ] ).toBeDefined();

		const data = parseContractData( fragments[ '.hezarfen-contract-data' ] );
		expect( Object.keys( data ) ).toEqual(
			expect.arrayContaining( [ 'mss' ] )
		);
		expect( joinValues( data ) ).toContain( `${ TEMPLATE_MARKER }Ada` );
	} );

	test( 'fatura adı değişip yeni AJAX refresh tetiklendiğinde fragment yeni değeri yansıtıyor', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );

		await page.locator( '#billing_first_name' ).fill( 'Ada' );
		await page.locator( '#billing_last_name' ).fill( 'Lovelace' );
		await page.locator( '#billing_phone' ).fill( '5551112233' );
		await page.locator( '#billing_email' ).fill( 'ada@example.test' );

		const firstFragments = await triggerUpdateCheckout( page );
		const firstData = parseContractData(
			firstFragments[ '.hezarfen-contract-data' ]
		);
		expect( joinValues( firstData ) ).toContain(
			`${ TEMPLATE_MARKER }Ada`
		);
		expect( joinValues( firstData ) ).not.toContain(
			`${ TEMPLATE_MARKER }Marie`
		);

		// Re-type the name — the renderer must read the live POST
		// snapshot rather than cache the previous payload.
		await page.locator( '#billing_first_name' ).fill( 'Marie' );
		await page.locator( '#billing_first_name' ).blur();

		const secondFragments = await triggerUpdateCheckout( page );
		const secondData = parseContractData(
			secondFragments[ '.hezarfen-contract-data' ]
		);
		expect( joinValues( secondData ) ).toContain(
			`${ TEMPLATE_MARKER }Marie`
		);
	} );
} );

/**
 * Fire `update_checkout` and return the parsed response fragments.
 * Triggering via jQuery on the body element is the canonical WC entry
 * point — every WC integration that wants a fresh review uses it.
 * Registering the response listener BEFORE the trigger avoids a race
 * where a debounced WC update beats our listener registration.
 */
async function triggerUpdateCheckout(
	page: Page
): Promise< Record< string, string > > {
	const updatePromise = page.waitForResponse(
		( res ) =>
			res.url().includes( 'wc-ajax=update_order_review' ) &&
			res.status() === 200,
		{ timeout: 20_000 }
	);
	await page.evaluate( () => {
		const $ = ( window as any ).jQuery;
		if ( ! $ ) throw new Error( 'jQuery is missing from the checkout page' );
		$( document.body ).trigger( 'update_checkout' );
	} );
	const res = await updatePromise;
	const body = ( await res.json() ) as { fragments?: Record< string, string > };
	if ( ! body.fragments ) {
		throw new Error(
			`update_order_review response had no fragments: ${ JSON.stringify(
				body
			) }`
		);
	}
	return body.fragments;
}

/**
 * Pull the JSON contract payload out of the
 * `.hezarfen-contract-data[data-contracts]` fragment. The renderer
 * serialises the contract id → rendered HTML map onto the element's
 * attribute via `wp_json_encode + esc_attr`, so the value is
 * HTML-attribute-encoded JSON.
 */
function parseContractData( fragmentHtml: string ): Record< string, string > {
	const match = fragmentHtml.match( /data-contracts="([^"]*)"/ );
	if ( ! match ) {
		throw new Error(
			`hezarfen-contract-data fragment missing data-contracts attr: ${ fragmentHtml }`
		);
	}
	const decoded = match[ 1 ]
		.replace( /&quot;/g, '"' )
		.replace( /&amp;/g, '&' )
		.replace( /&lt;/g, '<' )
		.replace( /&gt;/g, '>' )
		.replace( /&#039;/g, "'" );
	return JSON.parse( decoded ) as Record< string, string >;
}

function joinValues( record: Record< string, string > ): string {
	return Object.values( record ).join( '\n' );
}
