import { expect, test } from '@playwright/test';
import {
	addE2EProductToCart,
	waitForCheckoutIdle,
} from './helpers/checkout';
import {
	applyOptions,
	restoreOptions,
	snapshotOptions,
} from './helpers/wp-options';

/**
 * When two or more contracts are active,
 * [Contract_Renderer::render_contract_checkboxes](../../includes/contracts/core/class-contract-renderer.php)
 * collapses them into a single combined checkbox whose label embeds
 * inline `<a class="contract-modal-link" data-contract-id="…"
 * data-contract-name="…">` anchors. The modal handler in
 * `add_contract_modal_script` delegates clicks on `.contract-modal-link`
 * and reads the `data-contract-id` attribute to decide which tab to
 * open.
 *
 * Failure modes covered:
 *   - The renderer or a downstream sanitizer strips the `data-*`
 *     attributes — the modal would open on the wrong tab (or silently
 *     skip the click handler).
 *   - The link's tag gets replaced with plain text — the customer
 *     loses the ability to read the contract before agreeing.
 *
 * `contracts-single-checkbox.spec.ts` covers the equivalent assertion
 * for shops with only one active contract; this file pins the combined
 * branch.
 */
let snapshot: Record< string, string >;

test.describe( 'Hezarfen sözleşme — birleşik checkbox link tıklaması modalı açar', () => {
	test.beforeAll( () => {
		snapshot = snapshotOptions( [ 'hezarfen_contracts_enabled' ] );
		applyOptions( { hezarfen_contracts_enabled: 'yes' } );
	} );
	test.afterAll( () => {
		restoreOptions( snapshot );
	} );

	test.beforeEach( async ( { page } ) => {
		await addE2EProductToCart( page );
	} );

	test( 'birleşik label içinde her sözleşme için data-contract-id taşıyan link render ediliyor', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );

		// Combined branch is in effect: exactly one combined checkbox,
		// no per-contract checkboxes alongside it.
		await expect(
			page.locator( 'input[name="contract_combined_checkbox"]' )
		).toHaveCount( 1 );

		// Both seeded contracts (MSS + OBF) must appear as anchors that
		// keep the `data-contract-id` attribute the modal handler reads.
		const mssLink = page.locator(
			'a.contract-modal-link[data-contract-id="mss"]'
		);
		const obfLink = page.locator(
			'a.contract-modal-link[data-contract-id="obf"]'
		);
		await expect( mssLink ).toBeVisible();
		await expect( obfLink ).toBeVisible();

		// The user-visible name lives both as the anchor's text content
		// and as a `data-contract-name` attribute — sanitizers that
		// strip `data-*` would leave the text but break tab switching.
		await expect( mssLink ).toHaveAttribute(
			'data-contract-name',
			/Mesafeli|MSS/i
		);
		await expect( obfLink ).toHaveAttribute(
			'data-contract-name',
			/Bilgilendirme|OBF/i
		);
	} );

	test( 'MSS linkine tıklamak unified modal\'ı açıyor ve MSS sekmesi aktif geliyor', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );

		const mssLink = page.locator(
			'a.contract-modal-link[data-contract-id="mss"]'
		);
		await expect( mssLink ).toBeVisible();
		await mssLink.click();

		// `add_contract_modal_script` builds a unified tabbed modal on
		// first click — the wrapping element carries
		// `.hezarfen-unified-modal`.
		const modal = page.locator( '.hezarfen-unified-modal' );
		await expect( modal ).toBeVisible();

		// Each tab pane carries `data-contract-id="<id>"`; the active
		// one is rendered with inline `display: block`, the rest with
		// `display: none`. Playwright's visibility check honors the
		// computed style, so toBeVisible / toBeHidden gives us the
		// right signal without coupling to the exact inline style
		// string.
		await expect(
			modal.locator( '.tab-pane[data-contract-id="mss"]' )
		).toBeVisible();
		await expect(
			modal.locator( '.tab-pane[data-contract-id="obf"]' )
		).toBeHidden();
	} );
} );
