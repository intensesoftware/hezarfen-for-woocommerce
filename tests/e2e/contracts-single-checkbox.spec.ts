import { expect, test } from '@playwright/test';
import {
	addE2EProductToCart,
	waitForCheckoutIdle,
} from './helpers/checkout';
import { wp } from './helpers/wp-cli';

/**
 * `Contract_Renderer::render_contract_checkboxes` has two paths:
 *   - 2+ active contracts → one combined checkbox named
 *     `contract_combined_checkbox`
 *   - exactly 1 active contract → individual checkbox named
 *     `contract_<id>_checkbox`
 *
 * `checkout-contracts.spec.ts` covers the combined branch (the seeded
 * default in global-setup). The single-contract branch is what kicks
 * in for shops that only need MSS or only need OBF, and it has
 * different markup + a different POST field name. If a refactor ever
 * loses that branch we'd silently break those installs without
 * noticing.
 */
let snapshot: string;

test.describe( 'Hezarfen sözleşme — tek sözleşme (ayrı checkbox) varyantı', () => {
	test.beforeAll( () => {
		snapshot = wp(
			[ 'option', 'get', 'hezarfen_mss_settings', '--format=json' ],
			{ allowFailure: true }
		).trim();

		// Disable OBF's `show_in_checkbox` so only MSS remains active —
		// that pushes the renderer onto the single-contract branch.
		wp( [
			'eval',
			`
				$opts = get_option( 'hezarfen_mss_settings', array() );
				if ( ! is_array( $opts ) || empty( $opts['contracts'] ) ) {
					echo 'ERR_NO_CONTRACTS';
					return;
				}
				foreach ( $opts['contracts'] as &$c ) {
					if ( isset( $c['id'] ) && 'obf' === $c['id'] ) {
						$c['show_in_checkbox'] = '0';
					}
				}
				update_option( 'hezarfen_mss_settings', $opts );
				update_option( 'hezarfen_contracts_enabled', 'yes' );
				echo 'OK';
			`,
		] );
	} );
	test.afterAll( () => {
		if ( snapshot ) {
			wp(
				[
					'option',
					'update',
					'hezarfen_mss_settings',
					snapshot,
					'--format=json',
				],
				{ allowFailure: true }
			);
		}
	} );

	test( 'Combined checkbox yerine `contract_mss_checkbox` render ediliyor', async ( {
		page,
	} ) => {
		await addE2EProductToCart( page );
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );

		// The single-contract branch:
		//   <input type="checkbox" name="contract_<id>_checkbox" required>
		// Combined branch is gone in this configuration.
		await expect(
			page.locator( 'input[name="contract_combined_checkbox"]' )
		).toHaveCount( 0 );
		const single = page.locator( 'input[name="contract_mss_checkbox"]' );
		await expect( single ).toBeAttached();
		await expect( single ).not.toBeChecked();
		const isRequired = await single.evaluate(
			( el ) => ( el as HTMLInputElement ).required
		);
		expect( isRequired ).toBe( true );

		// Sanity-check: the contract link still appears in the label so
		// customers can pop the modal.
		await expect(
			page.locator( 'a.contract-modal-link[data-contract-id="mss"]' )
		).toBeAttached();
	} );
} );
