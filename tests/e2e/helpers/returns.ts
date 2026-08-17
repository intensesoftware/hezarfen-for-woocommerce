import { expect, type Page } from '@playwright/test';
import { wp } from './wp-cli';

/**
 * Helpers for the Hezarfen returns module.
 *
 * Everything that seeds state goes through `wp eval` and the module's own
 * service layer rather than through raw SQL, so a spec can never assert
 * against a row shape the plugin would never actually write.
 *
 * Returns are account-only: every seeded order belongs to the customer
 * below, because an order with no customer can never reach the return form.
 */

export const RETURNS_CUSTOMER = {
	username: 'hezarfen-e2e-returns-customer',
	email: 'hezarfen-e2e-returns@example.test',
	password: 'hezarfen-e2e-returns-pass-1234',
};

export const RETURNS_OPTIONS = {
	hezarfen_returns_enabled: 'yes',
	hezarfen_returns_window_days: '14',
	hezarfen_returns_window_reference: 'completed',
	hezarfen_returns_shipping_method: 'customer-ships',
	hezarfen_returns_address_contact: 'Hezarfen E2E Depo',
	hezarfen_returns_address_line: 'Test Mah. Test Sk. No:1',
	hezarfen_returns_address_district: 'Çankaya',
	hezarfen_returns_address_city: 'Ankara',
};

/**
 * Write an option, tolerating wp-cli's non-zero exit when the value is
 * already what we are setting. Every helper here is meant to be callable
 * repeatedly, so "unchanged" must not read as "failed".
 */
export function setOption( key: string, value: string ): void {
	wp( [ 'option', 'update', key, value ], { allowFailure: true } );
}

/**
 * Replace the list of order statuses a return may be opened against.
 */
export function setEligibleStatuses( statuses: string[] ): void {
	wp(
		[
			'option',
			'update',
			'hezarfen_returns_eligible_order_statuses',
			JSON.stringify( statuses ),
			'--format=json',
		],
		{ allowFailure: true }
	);
}

/**
 * wp-cli shares stdout with whatever else the site prints during
 * bootstrap (agents, debug shims). Seeders echo their result last, so
 * read the final non-empty line rather than trusting the whole stream.
 */
function lastLine( output: string ): string {
	const lines = output
		.split( '\n' )
		.map( ( line ) => line.trim() )
		.filter( Boolean );

	return lines.length ? lines[ lines.length - 1 ] : '';
}

/**
 * Turn the module on and make sure the tables and the account endpoints
 * exist. Idempotent, so a spec can call it in `beforeAll` without caring
 * about ordering.
 */
export function enableReturns(): void {
	// Before anything logs in: the first call resets the customer's password
	// and would otherwise drop a live session mid-test.
	ensureReturnsCustomer();

	for ( const [ key, value ] of Object.entries( RETURNS_OPTIONS ) ) {
		setOption( key, value );
	}
	setEligibleStatuses( [ 'wc-completed' ] );

	wp( [
		'eval',
		`
			\\Hezarfen\\Inc\\Returns\\Core\\Returns_Schema::install();
			delete_option( 'hezarfen_returns_endpoints_version' );
		`,
	] );

	// Endpoints are added on `init`, which already ran for the eval above,
	// so the rules have to be rebuilt in a fresh request.
	wp( [ 'rewrite', 'flush' ] );
}

export function disableReturns(): void {
	setOption( 'hezarfen_returns_enabled', 'no' );
}

let cachedCustomerId: string | null = null;

/**
 * Create (or reset) the customer the returns specs log in as.
 *
 * Memoized per worker on purpose: resetting a password destroys that user's
 * WordPress sessions, so calling this again mid-test would silently log the
 * browser out and every later assertion would be made against the login
 * form. `enableReturns()` calls it during setup, before anyone logs in.
 */
export function ensureReturnsCustomer(): string {
	if ( cachedCustomerId ) {
		return cachedCustomerId;
	}

	const existing = wp(
		[ 'user', 'get', RETURNS_CUSTOMER.username, '--field=ID' ],
		{ allowFailure: true }
	).trim();

	if ( existing ) {
		wp( [
			'user',
			'update',
			existing,
			`--user_pass=${ RETURNS_CUSTOMER.password }`,
		] );

		cachedCustomerId = existing;

		return cachedCustomerId;
	}

	cachedCustomerId = wp( [
		'user',
		'create',
		RETURNS_CUSTOMER.username,
		RETURNS_CUSTOMER.email,
		'--role=customer',
		`--user_pass=${ RETURNS_CUSTOMER.password }`,
		'--first_name=Ada',
		'--last_name=Lovelace',
		'--porcelain',
	] ).trim();

	return cachedCustomerId;
}

/**
 * Log the returns customer in, reusing an existing session when there is
 * one.
 */
export async function loginAsReturnsCustomer( page: Page ): Promise< void > {
	await page.goto( '/my-account/' );

	const alreadyIn = await page
		.locator( 'nav.woocommerce-MyAccount-navigation' )
		.isVisible()
		.catch( () => false );

	if ( alreadyIn ) return;

	await page.locator( '#username' ).fill( RETURNS_CUSTOMER.username );
	await page.locator( '#password' ).fill( RETURNS_CUSTOMER.password );
	await page.locator( 'button[name="login"]' ).click();
	await expect(
		page.locator( 'nav.woocommerce-MyAccount-navigation' )
	).toBeVisible();
}

/**
 * A product with a non-zero price, so the specs can assert on money.
 */
export function ensurePricedProduct(): string {
	return lastLine(
		wp( [
			'eval',
			`
			$existing = get_page_by_path( 'hezarfen-e2e-returns-product', OBJECT, 'product' );
			if ( $existing ) { echo $existing->ID; return; }
			$product = new WC_Product_Simple();
			$product->set_name( 'Hezarfen E2E İade Ürünü' );
			$product->set_slug( 'hezarfen-e2e-returns-product' );
			$product->set_regular_price( 100 );
			$product->set_status( 'publish' );
			echo $product->save();
		`,
		] )
	);
}

export interface SeedReturnableOrderOptions {
	/** Line quantity, defaults to 2 so partial returns can be exercised. */
	quantity?: number;
	/** Order status, defaults to `completed`. */
	status?: string;
	/** Customer user ID; defaults to the returns customer. */
	customerId?: string;
	/** How many days ago the order was completed. */
	completedDaysAgo?: number;
}

/**
 * Seed an order that the returns module considers returnable.
 */
export function seedReturnableOrder(
	opts: SeedReturnableOrderOptions = {}
): string {
	const productId = ensurePricedProduct();
	const quantity = opts.quantity ?? 2;
	const status = opts.status ?? 'completed';
	const customerId = opts.customerId ?? ensureReturnsCustomer();
	const daysAgo = opts.completedDaysAgo ?? 0;

	const out = lastLine(
		wp( [
			'eval',
			`
			$order = wc_create_order( array( 'status' => '${ status }', 'customer_id' => ${ customerId } ) );
			$order->add_product( wc_get_product( ${ productId } ), ${ quantity } );
			$order->set_billing_first_name( 'Ada' );
			$order->set_billing_last_name( 'Lovelace' );
			$order->set_billing_email( '${ RETURNS_CUSTOMER.email }' );
			$order->set_billing_country( 'TR' );
			$order->set_billing_state( 'TR06' );
			$order->set_billing_city( 'Çankaya' );
			$order->set_billing_address_1( 'Test Mah.' );
			$order->calculate_totals();
			$order->set_date_completed( time() - ( ${ daysAgo } * DAY_IN_SECONDS ) );
			$order->set_date_paid( time() - ( ${ daysAgo } * DAY_IN_SECONDS ) );
			$order->save();
			echo $order->get_id();
		`,
		] )
	);

	if ( ! /^\d+$/.test( out ) ) {
		throw new Error( `seedReturnableOrder failed: ${ out }` );
	}

	return out;
}

/**
 * Seed a completed order whose only line is a virtual + downloadable
 * product. The store-wide policy refuses to offer those for return.
 */
export function seedDigitalOrder(): string {
	const customerId = ensureReturnsCustomer();

	const out = lastLine(
		wp( [
			'eval',
			`
			$existing = get_page_by_path( 'hezarfen-e2e-returns-digital', OBJECT, 'product' );
			if ( $existing ) {
				$product_id = $existing->ID;
			} else {
				$product = new WC_Product_Simple();
				$product->set_name( 'Hezarfen E2E Dijital Ürün' );
				$product->set_slug( 'hezarfen-e2e-returns-digital' );
				$product->set_regular_price( 50 );
				$product->set_virtual( true );
				$product->set_downloadable( true );
				$product->set_status( 'publish' );
				$product_id = $product->save();
			}

			$order = wc_create_order( array( 'status' => 'completed', 'customer_id' => ${ customerId } ) );
			$order->add_product( wc_get_product( $product_id ), 1 );
			$order->set_billing_email( '${ RETURNS_CUSTOMER.email }' );
			$order->calculate_totals();
			$order->set_date_completed( time() );
			$order->save();
			echo $order->get_id();
		`,
		] )
	);

	if ( ! /^\d+$/.test( out ) ) {
		throw new Error( `seedDigitalOrder failed: ${ out }` );
	}

	return out;
}

/**
 * Refund part of an order's first line through WooCommerce itself, so a
 * spec can prove the returns module respects refunds it did not create.
 */
export function refundFirstLine( orderId: string, quantity: number ): void {
	wp( [
		'eval',
		`
			$order = wc_get_order( ${ orderId } );
			$items = $order->get_items();
			$item  = reset( $items );
			$unit  = (float) $order->get_line_total( $item, true, false ) / max( 1, $item->get_quantity() );
			wc_create_refund( array(
				'order_id'   => ${ orderId },
				'amount'     => $unit * ${ quantity },
				'line_items' => array(
					$item->get_id() => array(
						'qty'          => ${ quantity },
						'refund_total' => $unit * ${ quantity },
					),
				),
			) );
		`,
	] );
}

export interface SeedReturnOptions {
	orderId: string;
	quantity?: number;
	reason?: string;
	note?: string;
	/** Status to move the fresh request to, if any. */
	status?: string;
}

export interface SeededReturn {
	id: string;
	number: string;
}

/**
 * Create a request through Return_Service, optionally advancing it to a
 * given status. Returns the identifiers a spec needs to build URLs.
 */
export function seedReturn( opts: SeedReturnOptions ): SeededReturn {
	const quantity = opts.quantity ?? 1;
	const reason = opts.reason ?? 'defective';
	const note = opts.note ?? '';

	const out = lastLine(
		wp( [
			'eval',
			`
			$module = \\Hezarfen\\Inc\\Returns\\Returns_Module::instance();
			$order  = wc_get_order( ${ opts.orderId } );
			$lines  = $module->eligibility()->get_returnable_lines( $order );
			$item   = key( $lines );
			$request = $module->service()->create( $order, array(
				'lines' => array( $item => array(
					'quantity' => ${ quantity },
					'reason'   => '${ reason }',
					'note'     => '${ note }',
				) ),
			) );
			if ( is_wp_error( $request ) ) { echo 'ERR:' . $request->get_error_message(); return; }
			${
				opts.status
					? `$module->service()->change_status( $request, '${ opts.status }' );`
					: ''
			}
			echo wp_json_encode( array(
				'id'     => (string) $request->get_id(),
				'number' => $request->get_return_number(),
			) );
		`,
		] )
	);

	if ( out.startsWith( 'ERR:' ) ) {
		throw new Error( `seedReturn failed: ${ out }` );
	}

	return JSON.parse( out ) as SeededReturn;
}

/**
 * Read a request's current status straight from the repository.
 */
export function getReturnStatus( returnId: string ): string {
	return lastLine(
		wp( [
			'eval',
			`
			$request = \\Hezarfen\\Inc\\Returns\\Returns_Module::instance()->repository()->get( ${ returnId } );
			echo $request ? $request->get_status() : '';
		`,
		] )
	);
}

/**
 * Number of timeline entries stored for a request.
 */
export function countReturnEvents( returnId: string ): number {
	return parseInt(
		lastLine(
			wp( [
				'eval',
				`echo count( \\Hezarfen\\Inc\\Returns\\Returns_Module::instance()->events()->get_for_return( ${ returnId } ) );`,
			] )
		),
		10
	);
}

/**
 * Account URL of the return form for an order.
 */
export function requestFormUrl( orderId: string ): string {
	return `/my-account/iade-talebi/${ orderId }/`;
}

/**
 * Account URL of the order's own detail page — the only entry point into
 * the return flow.
 */
export function viewOrderUrl( orderId: string ): string {
	return `/my-account/view-order/${ orderId }/`;
}

/**
 * Wipe every stored request. Keeps the admin list assertions
 * deterministic without dropping the tables.
 */
export function clearReturns(): void {
	wp( [
		'eval',
		`
			global $wpdb;
			foreach ( array( 'hezarfen_return_events', 'hezarfen_return_items', 'hezarfen_returns' ) as $table ) {
				$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . $table );
			}
		`,
	] );
}
