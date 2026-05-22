import { expect, test } from '@playwright/test';
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
let optionSnapshot: Record< string, string >;
let priorLocale: string;

test.describe( 'Hezarfen sözleşme — TR locale\'de birleşik label tam render olur', () => {
	test.beforeAll( () => {
		priorLocale = wp(
			[ 'option', 'get', 'WPLANG' ],
			{ allowFailure: true }
		).trim();

		optionSnapshot = snapshotOptions( [ 'hezarfen_contracts_enabled' ] );
		applyOptions( {
			hezarfen_contracts_enabled: 'yes',
			// `Contract_Renderer` triggers the Turkish `getEk` path
			// when `get_locale()` starts with "tr"; setting WPLANG is
			// the canonical way to flip site locale via WP options.
			WPLANG: 'tr_TR',
		} );
	} );
	test.afterAll( () => {
		restoreOptions( optionSnapshot );
		if ( priorLocale === '' ) {
			wp(
				[ 'option', 'delete', 'WPLANG' ],
				{ allowFailure: true }
			);
		} else {
			wp( [ 'option', 'update', 'WPLANG', priorLocale ] );
		}
	} );

	test.beforeEach( async ( { page } ) => {
		await addE2EProductToCart( page );
	} );

	test( 'birleşik label Türkçe çeviri + getEk ile render ediliyor', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );

		const label = page.locator(
			'.in-sozlesme-onay-checkboxes p.in-sozlesme-onay-checkbox label'
		);
		await expect( label ).toBeVisible();

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
