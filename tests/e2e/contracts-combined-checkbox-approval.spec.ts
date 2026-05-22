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
 * When 2+ contracts have `show_in_checkbox = 1`,
 * `Contract_Renderer::render_contract_checkboxes` collapses them into
 * **one** combined checkbox + a tabbed unified modal. Adjacent specs
 * cover slices of this branch:
 *   - `contracts-post-order.spec.ts` proves a checked combined box
 *     persists every contract in `wp_hezarfen_contracts`.
 *   - `checkout-contracts-combined-modal.spec.ts` proves the MSS tab
 *     opens when its link is clicked.
 *
 * Gaps this file fills:
 *   1. Combined-mode renders exactly one combined checkbox AND zero
 *      per-contract `contract_<id>_checkbox` inputs. Without that
 *      exclusivity, a regression that re-emits the per-contract row
 *      alongside the combined row would leave the customer staring at
 *      three checkboxes for two contracts.
 *   2. Tab switching inside the unified modal is bidirectional — the
 *      existing modal spec only proves the MSS tab is the default
 *      active one. Customers click OBF first just as often.
 *   3. Toggling the combined checkbox off after checking it must put
 *      every contract back into the rejected state (HTML5 validity
 *      check, since the combined box is the only required input).
 */
let snapshot: Record< string, string >;

test.describe( 'Hezarfen birleşik sözleşme checkbox\'ı (çoklu kontrat)', () => {
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

	test( 'iki sözleşme aktifken sadece tek birleşik checkbox render ediliyor, per-contract checkbox yok', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );

		await expect(
			page.locator( 'input[name="contract_combined_checkbox"]' )
		).toHaveCount( 1 );

		// Per-contract inputs (`contract_<id>_checkbox`) must NOT also
		// render — that would expose the customer to a confusing "3
		// checkboxes for 2 contracts" UI and make form validation
		// undefined (which one is required?).
		await expect(
			page.locator(
				'input[name="contract_mss_checkbox"], input[name="contract_obf_checkbox"]'
			)
		).toHaveCount( 0 );
	} );

	test( 'birleşik modal sekmesi MSS ↔ OBF arasında çift yönlü değişiyor', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );

		// OBF link first — the existing combined-modal spec opens MSS
		// only. We want to prove the OBF link doesn't silently re-open
		// the MSS tab, which would happen if `data-contract-id` was
		// dropped on the OBF anchor by a future sanitizer change.
		await page
			.locator( 'a.contract-modal-link[data-contract-id="obf"]' )
			.click();

		const modal = page.locator( '.hezarfen-unified-modal' );
		await expect( modal ).toBeVisible();
		await expect(
			modal.locator( '.tab-pane[data-contract-id="obf"]' )
		).toBeVisible();
		await expect(
			modal.locator( '.tab-pane[data-contract-id="mss"]' )
		).toBeHidden();

		// Tab nav inside the modal must let the customer flip to MSS
		// without closing/reopening. The modal renders a tab control
		// per contract — buttons carry the same `data-contract-id`
		// the panes do.
		await modal
			.locator( '[data-contract-id="mss"]' )
			.filter( { hasNot: page.locator( '.tab-pane' ) } )
			.first()
			.click();
		await expect(
			modal.locator( '.tab-pane[data-contract-id="mss"]' )
		).toBeVisible();
		await expect(
			modal.locator( '.tab-pane[data-contract-id="obf"]' )
		).toBeHidden();
	} );

	test( 'birleşik checkbox işaretlenip sonra kaldırıldığında validity yeniden invalid oluyor', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );

		const checkbox = page.locator(
			'input[name="contract_combined_checkbox"]'
		);
		await expect( checkbox ).toBeAttached();

		// Required + unchecked → invalid (baseline guard).
		const initialValidity = await checkbox.evaluate(
			( el ) => ( el as HTMLInputElement ).validity.valid
		);
		expect( initialValidity ).toBe( false );

		await checkbox.check( { force: true } );
		const checkedValidity = await checkbox.evaluate(
			( el ) => ( el as HTMLInputElement ).validity.valid
		);
		expect( checkedValidity ).toBe( true );

		// Uncheck — single source of truth means BOTH contracts roll
		// back to unagreed. Without a `contract_<id>_checkbox` fallback
		// the only `required` input is the combined one, so its
		// validity flip is what gates submit.
		await checkbox.uncheck( { force: true } );
		const finalValidity = await checkbox.evaluate(
			( el ) => ( el as HTMLInputElement ).validity.valid
		);
		expect( finalValidity ).toBe( false );
	} );
} );
