import { test } from '@playwright/test';
import { addE2EProductToCart, waitForCheckoutIdle } from './helpers/checkout';
import { deleteMuPlugin, writeMuPlugin } from './helpers/mu-plugin';
import { wp } from './helpers/wp-cli';

/**
 * TEMPORARY diagnostic — not a real regression test. Captures the rendered
 * billing field order under the ADP-style early get_checkout_fields() fixture
 * in two conditions (auto_sort off vs on) and throws the data so it shows up
 * in the CI log. Delete after reading.
 */
const FIXTURE_SLUG = 'hezarfen-e2e-early-checkout-fields-diag';
const FIXTURE_OPTION = 'hezarfen_e2e_force_early_checkout_fields_diag';

const FIXTURE_PHP = `<?php
add_action( 'wp_loaded', function () {
	if ( is_admin() ) { return; }
	if ( get_option( '${ FIXTURE_OPTION }' ) !== 'yes' ) { return; }
	if ( function_exists( 'WC' ) && WC()->checkout() ) {
		WC()->checkout()->get_checkout_fields();
	}
}, 5 );
`;

async function captureBillingOrder( page: any ): Promise< string[] > {
	await page.goto( '/checkout/' );
	await waitForCheckoutIdle( page );
	return page.evaluate( () => {
		const rows = Array.from(
			document.querySelectorAll< HTMLElement >(
				'.woocommerce-billing-fields .form-row[id], #customer_details .form-row[id]'
			)
		);
		return rows
			.map( ( el ) => ( {
				id: el.id,
				top: el.getBoundingClientRect().top,
			} ) )
			.sort( ( a, b ) => a.top - b.top )
			.map( ( r ) => r.id );
	} );
}

test( 'DIAG: capture billing order under early-build fixture', async ( {
	page,
} ) => {
	writeMuPlugin( FIXTURE_SLUG, FIXTURE_PHP );
	wp( [ 'option', 'update', FIXTURE_OPTION, 'yes' ] );

	try {
		wp( [ 'option', 'delete', 'hezarfen_checkout_fields_auto_sort' ], {
			allowFailure: true,
		} );
		await addE2EProductToCart( page );
		const offOrder = await captureBillingOrder( page );

		wp( [ 'option', 'update', 'hezarfen_checkout_fields_auto_sort', 'yes' ] );
		const onOrder = await captureBillingOrder( page );

		throw new Error(
			'DIAG_RESULT ' +
				JSON.stringify( { autoSortOff: offOrder, autoSortOn: onOrder } )
		);
	} finally {
		wp( [ 'option', 'delete', 'hezarfen_checkout_fields_auto_sort' ], {
			allowFailure: true,
		} );
		wp( [ 'option', 'delete', FIXTURE_OPTION ], { allowFailure: true } );
		deleteMuPlugin( FIXTURE_SLUG );
	}
} );
