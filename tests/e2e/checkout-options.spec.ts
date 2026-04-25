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
 * Two checkout-shaping options exposed in Hezarfen settings:
 *   - hezarfen_hide_checkout_postcode_fields → drops the postcode field
 *     from billing + shipping forms.
 *   - hezarfen_checkout_fields_auto_sort → reorders billing fields into
 *     the TR-conventional sequence (state → city → address_1 → ...).
 *
 * Each test snapshots/restores the option so the rest of the suite
 * keeps the defaults set by global-setup.
 */
test.describe( 'Hezarfen checkout opsiyonları', () => {
	test.beforeEach( async ( { page } ) => {
		await addE2EProductToCart( page );
	} );

	test( 'hezarfen_hide_checkout_postcode_fields=yes ile posta kodu alanı gizleniyor', async ( {
		page,
	} ) => {
		const snap = snapshotOptions( [ 'hezarfen_hide_checkout_postcode_fields' ] );
		applyOptions( { hezarfen_hide_checkout_postcode_fields: 'yes' } );

		try {
			await page.goto( '/checkout/' );
			await waitForCheckoutIdle( page );

			// Hezarfen hides the postcode by setting `hidden=true` on
			// the TR locale, which makes WC tag the wrapper with a
			// hidden class instead of removing it from the DOM. We
			// assert it isn't visible to the user.
			await expect(
				page.locator( '#billing_postcode_field' )
			).toBeHidden();
		} finally {
			restoreOptions( snap );
		}
	} );

	test( 'hezarfen_checkout_fields_auto_sort=yes ile billing alanları TR sırasında', async ( {
		page,
	} ) => {
		const snap = snapshotOptions( [ 'hezarfen_checkout_fields_auto_sort' ] );
		applyOptions( { hezarfen_checkout_fields_auto_sort: 'yes' } );

		try {
			await page.goto( '/checkout/' );
			await waitForCheckoutIdle( page );

			// Read the priority/order from the rendered form. The TR
			// canonical order is state (il) → city (ilçe) → address_1
			// (mahalle) → address_2 (sokak/no) — that's what auto_sort
			// has to enforce.
			const order = await page.evaluate( () => {
				const ids = [
					'billing_state_field',
					'billing_city_field',
					'billing_address_1_field',
					'billing_address_2_field',
				];
				return ids
					.map( ( id ) => {
						const el = document.getElementById( id );
						return el
							? {
									id,
									top: el.getBoundingClientRect().top,
							  }
							: null;
					} )
					.filter( Boolean ) as { id: string; top: number }[];
			} );

			expect( order.length ).toBe( 4 );
			// Each subsequent field should sit lower on the page than
			// the previous one — i.e. document order matches TR order.
			for ( let i = 1; i < order.length; i++ ) {
				expect.soft( order[ i ].top, `${ order[ i ].id } follows ${ order[ i - 1 ].id }` )
					.toBeGreaterThan( order[ i - 1 ].top );
			}
		} finally {
			restoreOptions( snap );
		}
	} );
} );
