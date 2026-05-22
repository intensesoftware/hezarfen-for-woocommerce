import { expect, test } from '@playwright/test';
import {
	addE2EProductToCart,
	expectCheckoutUpdate,
	fillTrAddressChain,
	TR_SAMPLE_ADDRESS,
	waitForCheckoutIdle,
} from './helpers/checkout';
import { wp } from './helpers/wp-cli';
import {
	applyOptions,
	restoreOptions,
	snapshotOptions,
} from './helpers/wp-options';

/**
 * `Contract_Validator::validate_checkout_contracts`
 * ([class-contract-validator.php](../../includes/contracts/core/class-contract-validator.php))
 * runs on the server-side `woocommerce_checkout_process` hook. The
 * markup-side `required` attribute that ships on the checkbox is a
 * first-line UX gate — anyone with devtools (or a non-conforming
 * browser) can strip the attribute and the server-side validator is
 * the only thing standing between an unchecked agreement and a
 * placed order.
 *
 * `checkout-contracts.spec.ts` already covers the HTML5 `required`
 * happy path. This file pins the server-side leg by deliberately
 * removing `required` from the checkbox before submit, so the request
 * actually reaches `WC_Checkout::process_checkout` with the unchecked
 * value. Both renderer branches — combined (2+ contracts) and single
 * (1 contract) — must reject.
 */
let snapshot: Record< string, string >;
let mssSettingsSnapshot: string;

test.describe( 'Hezarfen sözleşme onayı — server-side validation', () => {
	test.beforeAll( () => {
		snapshot = snapshotOptions( [ 'hezarfen_contracts_enabled' ] );
		mssSettingsSnapshot = wp(
			[ 'option', 'get', 'hezarfen_mss_settings', '--format=json' ],
			{ allowFailure: true }
		).trim();
		applyOptions( { hezarfen_contracts_enabled: 'yes' } );
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
	} );

	test( 'birleşik checkbox — HTML5 required bypass edilse de sunucu siparişi reddediyor', async ( {
		page,
	} ) => {
		await addE2EProductToCart( page );
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );

		await fillCheckoutForm( page );

		const checkbox = page.locator(
			'input[name="contract_combined_checkbox"]'
		);
		await expect( checkbox ).toBeAttached();
		await expect( checkbox ).not.toBeChecked();

		// Strip `required` so the browser submits the form even with
		// the checkbox unchecked — the regression we're guarding is on
		// the server, not the browser.
		await checkbox.evaluate(
			( el ) => ( ( el as HTMLInputElement ).required = false )
		);

		await page.locator( '#place_order' ).click();
		await page.waitForLoadState( 'networkidle' );

		// Order placement must have been blocked.
		expect( page.url() ).not.toMatch( /order-received/ );
		expect( page.url() ).toMatch( /checkout/i );

		// WC renders Hezarfen's `wc_add_notice( …, 'error' )` inside
		// the standard `.woocommerce-error` / `.woocommerce-notices-wrapper`
		// container. Accept either the English source string or the
		// Turkish translation so the assertion holds regardless of
		// active site locale.
		const errors = page.locator(
			'.woocommerce-error, .woocommerce-NoticeGroup-checkout, .wc-block-components-notice-banner.is-error'
		);
		await expect( errors.first() ).toBeVisible();
		await expect( errors.first() ).toContainText(
			/You must accept the agreements|Sözleşmeleri kabul etmelisiniz/i
		);
	} );

	test( 'tek sözleşme — checkbox işaretlenmediğinde sunucu siparişi reddediyor', async ( {
		page,
	} ) => {
		// Flip to the single-contract branch by disabling OBF's
		// `show_in_checkbox` flag. Restored in afterAll via the
		// snapshotted hezarfen_mss_settings value.
		wp( [
			'eval',
			`
				$opts = get_option( 'hezarfen_mss_settings', array() );
				foreach ( $opts['contracts'] as &$c ) {
					if ( 'obf' === ( $c['id'] ?? '' ) ) {
						$c['show_in_checkbox'] = '0';
					}
				}
				update_option( 'hezarfen_mss_settings', $opts );
				echo 'OK';
			`,
		] );

		await addE2EProductToCart( page );
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );

		await fillCheckoutForm( page );

		const checkbox = page.locator( 'input[name="contract_mss_checkbox"]' );
		await expect( checkbox ).toBeAttached();
		await expect( checkbox ).not.toBeChecked();

		await checkbox.evaluate(
			( el ) => ( ( el as HTMLInputElement ).required = false )
		);

		await page.locator( '#place_order' ).click();
		await page.waitForLoadState( 'networkidle' );

		expect( page.url() ).not.toMatch( /order-received/ );
		expect( page.url() ).toMatch( /checkout/i );

		const errors = page.locator(
			'.woocommerce-error, .woocommerce-NoticeGroup-checkout, .wc-block-components-notice-banner.is-error'
		);
		await expect( errors.first() ).toBeVisible();
		// Single-branch builds a per-contract message:
		//   "I must agree to the <contract name>."
		// (Turkish translation interpolates the name with no possessive
		//  suffix — we match either by the trailing word "agree" or its
		//  Turkish counterpart "kabul").
		await expect( errors.first() ).toContainText(
			/I must agree to|kabul etmelisiniz|kabul etmeliyim/i
		);
	} );
} );

async function fillCheckoutForm(
	page: import( '@playwright/test' ).Page
): Promise< void > {
	await fillTrAddressChain( page, {
		type: 'billing',
		cityPlate: TR_SAMPLE_ADDRESS.cityPlate,
		district: TR_SAMPLE_ADDRESS.district,
		neighborhood: TR_SAMPLE_ADDRESS.neighborhood,
	} );
	await page.locator( '#billing_first_name' ).fill( 'Ada' );
	await page.locator( '#billing_last_name' ).fill( 'Lovelace' );
	await page.locator( '#billing_email' ).fill( 'ada@example.test' );
	await page.locator( '#billing_phone' ).fill( '5551112233' );
	const postcode = page.locator( '#billing_postcode' );
	if ( await postcode.isVisible() ) {
		await postcode.fill( TR_SAMPLE_ADDRESS.postcode );
	}
	await page.locator( '#billing_address_2' ).fill( TR_SAMPLE_ADDRESS.street );
	await page.locator( '#billing_address_2' ).blur();
	await expectCheckoutUpdate( page ).catch( () => {} );
	await waitForCheckoutIdle( page );
	const cod = page.locator( '#payment_method_cod' );
	if ( await cod.isVisible() ) {
		await cod.check( { force: true } );
	}
}
