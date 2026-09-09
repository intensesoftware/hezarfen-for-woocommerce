import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth';
import { deleteOrder } from './helpers/orders';
import {
	advanceReturn,
	clearReturns,
	completeReturn,
	enableReturns,
	getRefundState,
	getReturnStatus,
	raceServiceCall,
	reduceStockLevels,
	refundFirstLine,
	seedReturn,
	seedReturnableOrder,
	setOption,
} from './helpers/returns';
import { restoreOptions, snapshotOptions } from './helpers/wp-options';
import { wp } from './helpers/wp-cli';

/**
 * Settling a completed return as a WooCommerce refund.
 *
 * No money moves here. `refund_payment => false` is WooCommerce's own
 * manual refund: it records what left the order without contacting a
 * gateway, which is exactly what a store that transfers refunds by hand
 * needs — WooCommerce simply stops disagreeing with the bank.
 *
 * What these specs pin down is the arithmetic around that record, because
 * getting it wrong costs the merchant real money: a partial return must not
 * refund a whole line, a line already refunded by hand must not be refunded
 * again, and a partially refunded order must not be reported as a fully
 * refunded one.
 */

const OPTION_KEYS = [
	'hezarfen_returns_enabled',
	'hezarfen_returns_window_days',
	'hezarfen_returns_shipping_method',
	'hezarfen_returns_auto_refund',
	'hezarfen_returns_restock',
];

let optionSnapshot: Record< string, string >;
const seededOrders: string[] = [];

/**
 * A request that has reached "received", on an order with two units of the
 * same line — so returning one of them is genuinely partial.
 */
function seedReceivedReturn(
	quantity = 1,
	reduceStock = false
): {
	id: string;
	number: string;
	orderId: string;
} {
	const orderId = seedReturnableOrder( { quantity: 2 } );
	seededOrders.push( orderId );

	if ( reduceStock ) {
		reduceStockLevels( orderId );
	}

	const seeded = seedReturn( { orderId, quantity } );
	advanceReturn( seeded.id, [ 'approved', 'shipped', 'received' ] );

	return { ...seeded, orderId };
}

/** The order total, as WooCommerce itself reports it. */
function orderTotal( orderId: string ): number {
	return parseFloat(
		wp( [
			'eval',
			`echo wc_get_order( ${ orderId } )->get_total();`,
		] ).trim()
	);
}

/**
 * Turn stock management on (or off) for the shared fixture product, so the
 * restock specs have something to measure. Each of those specs sets the
 * quantity it expects and switches management back off afterwards, so no
 * other spec ever sees a managed product.
 */
function setProductStock( managed: boolean, quantity = 10 ): void {
	wp( [
		'eval',
		`
			$post    = get_page_by_path( 'hezarfen-e2e-returns-product', OBJECT, 'product' );
			$product = wc_get_product( $post->ID );
			$product->set_manage_stock( ${ managed ? 'true' : 'false' } );
			$product->set_stock_quantity( ${ managed ? quantity : 'null' } );
			$product->save();
		`,
	] );
}

test.describe( 'Hezarfen iade — WooCommerce iade kaydı', () => {
	test.beforeAll( () => {
		optionSnapshot = snapshotOptions( OPTION_KEYS );
		enableReturns();
	} );

	test.afterAll( () => {
		for ( const orderId of seededOrders ) {
			deleteOrder( orderId );
		}
		clearReturns();
		restoreOptions( optionSnapshot );
	} );

	test.beforeEach( () => {
		clearReturns();
		setOption( 'hezarfen_returns_auto_refund', 'no' );
		setOption( 'hezarfen_returns_restock', 'no' );
	} );

	test( 'kısmi iade yalnızca dönen adedi iade eder', () => {
		const request = seedReceivedReturn( 1 );
		const total = orderTotal( request.orderId );

		expect( completeReturn( request.id, true ) ).toBe( '' );

		const state = getRefundState( request.orderId, request.id );

		expect( state.refundCount ).toBe( 1 );
		expect( state.firstLineQtyRefunded ).toBe( 1 );
		expect( state.refundIdOnRequest ).toBeGreaterThan( 0 );

		// One of two units came back, so half the order did — not all of it.
		expect( state.totalRefunded ).toBeCloseTo( total / 2, 2 );

		// And a half refunded order is not a refunded order: saying it was
		// would misreport every revenue figure that reads the status.
		expect( state.orderStatus ).not.toBe( 'refunded' );
	} );

	test( 'tamamı dönen siparişi WooCommerce kendisi iade edildi yapar', () => {
		const request = seedReceivedReturn( 2 );
		const total = orderTotal( request.orderId );

		expect( completeReturn( request.id, true ) ).toBe( '' );

		const state = getRefundState( request.orderId, request.id );

		expect( state.totalRefunded ).toBeCloseTo( total, 2 );

		// The module never sets this status. WooCommerce flips it once the
		// refunded total reaches the order total, which is what a store
		// expects from a full return — and only from a full one.
		expect( state.orderStatus ).toBe( 'refunded' );
	} );

	test( 'kayıt istenmediğinde sipariş hiç değişmiyor', () => {
		const request = seedReceivedReturn( 1 );

		expect( completeReturn( request.id, false ) ).toBe( '' );

		const state = getRefundState( request.orderId, request.id );

		expect( getReturnStatus( request.id ) ).toBe( 'completed' );
		expect( state.refundCount ).toBe( 0 );
		expect( state.refundIdOnRequest ).toBe( 0 );
	} );

	test( 'elle iade edilmiş satır ikinci kez iade edilmiyor', () => {
		const request = seedReceivedReturn( 1 );

		// The merchant settled the whole line from the order screen while
		// the request was still open — the usual way a store that has always
		// refunded by hand ends up holding both records.
		refundFirstLine( request.orderId, 2 );

		const before = getRefundState( request.orderId, request.id );

		expect( completeReturn( request.id, true ) ).toBe(
			'hezarfen_returns_refund_nothing_left'
		);

		const after = getRefundState( request.orderId, request.id );

		expect( after.refundCount ).toBe( before.refundCount );
		expect( after.totalRefunded ).toBeCloseTo( before.totalRefunded, 2 );
		expect( after.refundIdOnRequest ).toBe( 0 );

		// The refund failing is not the completion failing: the goods did
		// come back, and the money had already been sent.
		expect( getReturnStatus( request.id ) ).toBe( 'completed' );
	} );

	test( 'tamamlanmış talebin iadesi sonradan kaydedilebilir', () => {
		const request = seedReceivedReturn( 1 );

		// Completed first without a WooCommerce refund — the store refunds by
		// hand and only later decides to log the record in WooCommerce.
		expect( completeReturn( request.id, false ) ).toBe( '' );
		expect( getReturnStatus( request.id ) ).toBe( 'completed' );
		expect(
			getRefundState( request.orderId, request.id ).refundIdOnRequest
		).toBe( 0 );

		// Re-running completion records the refund on the already-closed
		// request. COMPLETED is terminal, so this used to hit the transition
		// guard and lock the refund out for good; now the closed request is
		// left as is and only the refund is written.
		expect( completeReturn( request.id, true ) ).toBe( '' );

		const state = getRefundState( request.orderId, request.id );
		expect( state.refundIdOnRequest ).toBeGreaterThan( 0 );
		expect( state.refundCount ).toBe( 1 );
	} );

	test( 'kısmen elle iade edilmiş satırda yalnızca kalan iade edilir', () => {
		// Two units returned, one of them already refunded by hand: the new
		// record has to cover the gap, not the whole request.
		const request = seedReceivedReturn( 2 );
		const total = orderTotal( request.orderId );

		refundFirstLine( request.orderId, 1 );

		expect( completeReturn( request.id, true ) ).toBe( '' );

		const state = getRefundState( request.orderId, request.id );

		expect( state.firstLineQtyRefunded ).toBe( 2 );
		expect( state.totalRefunded ).toBeCloseTo( total, 2 );
		expect( state.refundCount ).toBe( 2 );
	} );

	test( 'kalan tutarı aşan iade kırpılmadan reddediliyor', () => {
		const request = seedReceivedReturn( 2 );

		// A refund with no line items — a shipping-only one, say — lowers
		// what is left on the order without touching any line's headroom.
		wp( [
			'eval',
			`wc_create_refund( array( 'order_id' => ${ request.orderId }, 'amount' => 150, 'line_items' => array() ) );`,
		] );

		const before = getRefundState( request.orderId, request.id );

		expect( completeReturn( request.id, true ) ).toBe(
			'hezarfen_returns_refund_exceeds_remaining'
		);

		const after = getRefundState( request.orderId, request.id );

		// Clipping the amount while the line items still claim every unit
		// would record units WooCommerce was never paid back for, and the
		// returnable quantity would shrink with them.
		expect( after.refundCount ).toBe( before.refundCount );
		expect( after.firstLineQtyRefunded ).toBe( 0 );
		expect( after.refundIdOnRequest ).toBe( 0 );
		expect( getReturnStatus( request.id ) ).toBe( 'completed' );
	} );

	test( 'iki kere tıklanan "Tamamlandı" tek iade yazıyor', () => {
		setOption( 'hezarfen_returns_auto_refund', 'yes' );

		const request = seedReceivedReturn( 1 );
		const total = orderTotal( request.orderId );

		// Two submits of the same action, each with its own loaded copy of
		// the row — a double click on a slow admin screen. Guarded in PHP
		// alone, both would pass and the customer would be paid twice.
		const [ first, second ] = raceServiceCall(
			request.id,
			`$module->service()->complete( $request, array( 'refund' => true ) )`
		);

		expect( first ).toBe( '' );
		expect( second ).toBe( 'hezarfen_returns_status_conflict' );

		const state = getRefundState( request.orderId, request.id );

		expect( state.refundCount ).toBe( 1 );
		expect( state.totalRefunded ).toBeCloseTo( total / 2, 2 );
		expect( state.firstLineQtyRefunded ).toBe( 1 );
	} );

	test( 'stok geri ekleme ayara bağlı', () => {
		setOption( 'hezarfen_returns_restock', 'yes' );
		setProductStock( true, 10 );

		try {
			const request = seedReceivedReturn( 1, true );

			// Two units sold off a stock of ten.
			expect(
				getRefundState( request.orderId, request.id ).firstLineStock
			).toBe( 8 );
			expect( completeReturn( request.id, true ) ).toBe( '' );

			// One unit came back, so one unit is sellable again — one, not
			// however many were on the original line.
			expect(
				getRefundState( request.orderId, request.id ).firstLineStock
			).toBe( 9 );
		} finally {
			setProductStock( false );
		}
	} );

	test( 'stok ayarı kapalıyken stoğa dokunulmuyor', () => {
		setOption( 'hezarfen_returns_restock', 'no' );
		setProductStock( true, 10 );

		try {
			const request = seedReceivedReturn( 1, true );

			expect( completeReturn( request.id, true ) ).toBe( '' );

			// Damaged goods are the common case for a return, so putting
			// them back on the shelf has to be something the merchant asked
			// for rather than something that happens to them.
			expect(
				getRefundState( request.orderId, request.id ).firstLineStock
			).toBe( 8 );
		} finally {
			setProductStock( false );
		}
	} );

	test( 'yönetim ekranı iade kaydını gösteriyor', async ( { page } ) => {
		const request = seedReceivedReturn( 1 );

		expect( completeReturn( request.id, true ) ).toBe( '' );

		await loginAsAdmin( page );
		await page.goto(
			`/wp-admin/admin.php?page=hezarfen-returns&return_id=${ request.id }`
		);

		await expect( page.locator( '.hez-admin-refund' ) ).toContainText(
			'WooCommerce iade kaydı'
		);
	} );
} );
