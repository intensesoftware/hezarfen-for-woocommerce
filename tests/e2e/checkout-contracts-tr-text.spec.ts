import { expect, test } from '@playwright/test';
import {
	addE2EProductToCart,
	waitForCheckoutIdle,
} from './helpers/checkout';
import { deleteMuPlugin, writeMuPlugin } from './helpers/mu-plugin';
import {
	applyOptions,
	restoreOptions,
	snapshotOptions,
} from './helpers/wp-options';

/**
 * When the site locale starts with `tr`,
 * [Contract_Renderer::render_contract_checkboxes](../../includes/contracts/core/class-contract-renderer.php)
 * routes each contract name through `get_ek` to add a Turkish
 * possessive suffix (`Mesafeli Satış Sözleşmesi'ni`,
 * `Ön Bilgilendirme Formu'nu`) before interpolating into the combined
 * checkbox label. The translated label string is
 *   "%s ve %s okudum ve kabul ediyorum."
 * and is rendered via `printf` with the per-contract anchors as the
 * positional arguments.
 *
 * This guards two interlocked regressions:
 *   - A downstream sanitizer that strips the `<a>` tag from the
 *     translated format string — the customer sees plain text without
 *     the clickable contract link.
 *   - A locale lookup that no longer triggers the `get_ek` branch —
 *     the label reads "Mesafeli Satış Sözleşmesi ve Ön Bilgilendirme
 *     Formu okudum ve kabul ediyorum." (grammatically broken Turkish).
 */
const MU_SLUG = 'hezarfen-e2e-force-tr-contracts-label';
const FLAG_OPTION = 'hezarfen_e2e_force_tr_contracts_label';
let optionSnapshot: Record< string, string >;

test.describe( 'Hezarfen sözleşme — TR locale\'de birleşik label tam render olur', () => {
	test.beforeAll( () => {
		// Two environment knobs must hold for `Contract_Renderer` to
		// render Turkish: `get_locale()` must start with "tr" (to
		// trigger the `get_ek` branch) AND the gettext lookup for the
		// combined label must return the Turkish translation. Setting
		// the `WPLANG` option only covers the first half on older WP
		// versions; on modern WP / wp-env the canonical option is
		// `locale`, and even when locale is right the `.mo` file may
		// not be installed (CI containers ship without site
		// translations). A mu-plugin filtering `locale` + `gettext`
		// pins both sides without depending on what's on disk.
		writeMuPlugin(
			MU_SLUG,
			`<?php
defined( 'ABSPATH' ) || exit;

if ( 'yes' !== get_option( 'hezarfen_e2e_force_tr_contracts_label' ) ) {
	return;
}

add_filter( 'locale', function () { return 'tr_TR'; }, 999 );

add_filter(
	'gettext',
	function ( $translation, $text, $domain ) {
		if (
			'hezarfen-for-woocommerce' === $domain
			&& 'I have read and agree to %s and %s.' === $text
		) {
			return '%s ve %s okudum ve kabul ediyorum.';
		}
		return $translation;
	},
	10,
	3
);`
		);

		optionSnapshot = snapshotOptions( [
			'hezarfen_contracts_enabled',
			FLAG_OPTION,
		] );
		applyOptions( {
			hezarfen_contracts_enabled: 'yes',
			[ FLAG_OPTION ]: 'yes',
		} );
	} );
	test.afterAll( () => {
		restoreOptions( optionSnapshot );
		deleteMuPlugin( MU_SLUG );
	} );

	test.beforeEach( async ( { page } ) => {
		await addE2EProductToCart( page );
	} );

	test( 'birleşik label Türkçe çeviri + getEk ile render ediliyor', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/', { waitUntil: 'domcontentloaded' } );

		// Fail fast (with a useful message) if the cart didn't carry
		// over from `addE2EProductToCart` and WC is showing the
		// empty-cart placeholder instead of the checkout form. Without
		// this check the contracts-label locator times out after 15s
		// and we have to read the trace to find out why.
		await expect(
			page.locator( 'form.checkout, form.woocommerce-checkout' )
		).toBeVisible( { timeout: 20_000 } );

		await waitForCheckoutIdle( page );

		const label = page.locator(
			'.in-sozlesme-onay-checkboxes p.in-sozlesme-onay-checkbox label'
		);
		// The contract is purely about the rendered text and anchor
		// structure that comes from `Contract_Renderer`. Asserting
		// `toBeVisible()` would also fail when WC's checkout JS
		// transiently styles ancestors with `display:none` /
		// `visibility:hidden` during `update_order_review` reflows,
		// which is unrelated to the regression we're catching here.
		await expect( label ).toHaveCount( 1, { timeout: 20_000 } );

		const labelText = ( await label.textContent() ) ?? '';

		// Turkish translation fingerprint from the seeded combined
		// label — neither piece can be missing without breaking the
		// rendered sentence.
		expect( labelText ).toMatch( /okudum/i );
		expect( labelText ).toMatch( /kabul ediyorum/i );

		// `get_ek` is responsible for the possessive suffix. Accept
		// either the ASCII apostrophe (') or the typographic one (ʼ /
		// ’) so the test stays robust against font / curly-quote
		// substitution.
		expect( labelText ).toMatch(
			/Mesafeli Satış Sözleşmesi['ʼ’]ni/u
		);
		expect( labelText ).toMatch(
			/Ön Bilgilendirme Formu['ʼ’]nu/u
		);

		// Both contract names must still be rendered as anchors — if
		// the `<a>` tag is stripped from the format string, `printf`
		// would interpolate the raw HTML as text and the markup would
		// be lost.
		await expect(
			label.locator( 'a.contract-modal-link[data-contract-id="mss"]' )
		).toHaveCount( 1 );
		await expect(
			label.locator( 'a.contract-modal-link[data-contract-id="obf"]' )
		).toHaveCount( 1 );
	} );
} );
