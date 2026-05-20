import { expect, test } from '@playwright/test';
import {
	addE2EProductToCart,
	expectCheckoutUpdate,
	fillTrAddressChain,
	pickFromSelect,
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
 * Hezarfen's TC encryption + tax fields code paths run through
 * `Checkout::handle_order_meta_data`, which doesn't care whether the
 * buyer is logged in. But the WC checkout has a meaningful split
 * between guest and logged-in flows (no customer_id, different
 * `wc_create_new_customer` branch), and Hezarfen's filter ordering
 * has bitten guest orders before — TC went out unencrypted, or the
 * billing_company field validation ran twice.
 *
 * This spec exercises the guest path end-to-end with TC encryption
 * enabled and asserts:
 *   1. order-received page renders for the guest
 *   2. the order is created with `customer_id = 0`
 *   3. the encrypted TC ciphertext is what's actually stored
 */
const FEATURE_OPTIONS = {
	hezarfen_show_hezarfen_checkout_tax_fields: 'yes',
	hezarfen_checkout_show_TC_identity_field: 'yes',
	hezarfen_checkout_is_TC_identity_number_field_required: 'yes',
};

const GUEST_TC = '11111111110';
let snapshot: Record< string, string >;
let createdOrderId = '';

test.describe( 'Hezarfen misafir (guest) checkout', () => {
	test.beforeAll( () => {
		snapshot = snapshotOptions( Object.keys( FEATURE_OPTIONS ) );
		applyOptions( FEATURE_OPTIONS );
	} );
	test.afterAll( () => {
		if ( createdOrderId ) {
			wp( [ 'post', 'delete', createdOrderId, '--force' ], {
				allowFailure: true,
			} );
		}
		restoreOptions( snapshot );
	} );

	test( 'misafir TR siparişi geçiyor ve TC şifrelenmiş kaydediliyor', async ( {
		page,
	} ) => {
		// Confirm we are NOT logged in. /my-account/ should show the
		// login form, not the navigation.
		await page.goto( '/my-account/' );
		await expect( page.locator( '#username' ) ).toBeVisible();

		await addE2EProductToCart( page );
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );

		await fillTrAddressChain( page, {
			type: 'billing',
			cityPlate: TR_SAMPLE_ADDRESS.cityPlate,
			district: TR_SAMPLE_ADDRESS.district,
			neighborhood: TR_SAMPLE_ADDRESS.neighborhood,
		} );

		await page.locator( '#billing_first_name' ).fill( 'Guest' );
		await page.locator( '#billing_last_name' ).fill( 'Lovelace' );
		await page.locator( '#billing_email' ).fill( 'guest-buyer@example.test' );
		await page.locator( '#billing_phone' ).fill( '5551112233' );
		const postcode = page.locator( '#billing_postcode' );
		if ( await postcode.isVisible() ) {
			await postcode.fill( TR_SAMPLE_ADDRESS.postcode );
		}
		await page
			.locator( '#billing_address_2' )
			.fill( TR_SAMPLE_ADDRESS.street );
		await page.locator( '#billing_address_2' ).blur();
		await expectCheckoutUpdate( page ).catch( () => {} );
		await waitForCheckoutIdle( page );

		await pickFromSelect( page, '#hezarfen_invoice_type', 'person' );
		await page.locator( '#hezarfen_TC_number' ).fill( GUEST_TC );

		const cod = page.locator( '#payment_method_cod' );
		if ( await cod.isVisible() ) {
			await cod.check( { force: true } );
		}
		await waitForCheckoutIdle( page );
		await page.locator( '#place_order' ).click();
		await page.waitForURL( /order-received/, { timeout: 30_000 } );
		await expect( page.locator( 'body' ) ).toContainText(
			/(siparişiniz alın|order has been received)/i
		);

		const url = new URL( page.url() );
		const match = url.pathname.match( /order-received\/(\d+)/ );
		expect( match, 'order-received URL should contain order id' ).not.toBeNull();
		createdOrderId = match![ 1 ];

		// `customer_id` should be 0 for a true guest order.
		const customerId = wp( [
			'eval',
			`
				$order = wc_get_order( ${ createdOrderId } );
				echo $order ? (int) $order->get_customer_id() : 'NO_ORDER';
			`,
		] ).trim();
		expect( customerId ).toBe( '0' );

		// The TC ciphertext on disk should NOT match the plaintext.
		const rawTC = wp( [
			'eval',
			`
				global $wpdb;
				$row = $wpdb->get_var( $wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->prefix}wc_orders_meta WHERE order_id = %d AND meta_key = %s LIMIT 1",
					${ createdOrderId },
					'_billing_hez_TC_number'
				) );
				echo $row !== null ? $row : 'NULL';
			`,
		] ).trim();
		expect( rawTC ).not.toBe( 'NULL' );
		expect( rawTC ).not.toBe( GUEST_TC );
		expect( rawTC ).not.toContain( GUEST_TC );

		// And decrypt round-trips back to the plaintext we typed.
		const decrypted = wp( [
			'eval',
			`
				global $wpdb;
				$row = $wpdb->get_var( $wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->prefix}wc_orders_meta WHERE order_id = %d AND meta_key = %s LIMIT 1",
					${ createdOrderId },
					'_billing_hez_TC_number'
				) );
				echo ( new \\Hezarfen\\Inc\\Data\\PostMetaEncryption() )->decrypt( $row );
			`,
		] ).trim();
		expect( decrypted ).toBe( GUEST_TC );
	} );
} );
