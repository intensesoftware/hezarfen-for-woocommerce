import { expect, test } from '@playwright/test';
import { deleteOrder } from './helpers/orders';
import { NOTICE_ERROR } from './helpers/notices';
import {
	clearReturns,
	enableReturns,
	getOrderToken,
	getReturnsPageUrl,
	refundFirstLine,
	seedDigitalOrder,
	seedReturn,
	seedReturnableOrder,
	setEligibleStatuses,
	setOption,
} from './helpers/returns';
import { restoreOptions, snapshotOptions } from './helpers/wp-options';

/**
 * What may be returned, and how much of it.
 *
 * The rules live in
 * [Return_Eligibility](../../includes/returns/core/class-return-eligibility.php)
 * and the store-wide policy provider; these specs drive them through the
 * guest form so the assertion is on what a customer is actually offered,
 * not on an internal return value.
 */

const GUEST_EMAIL = 'hezarfen-e2e-eligibility@example.test';

const OPTION_KEYS = [
	'hezarfen_returns_enabled',
	'hezarfen_returns_guest_enabled',
	'hezarfen_returns_window_days',
	'hezarfen_returns_window_reference',
	'hezarfen_returns_eligible_order_statuses',
];

let optionSnapshot: Record< string, string >;
let returnsPageUrl: string;
const seededOrders: string[] = [];

function seedOrder(
	opts: Parameters< typeof seedReturnableOrder >[ 0 ] = {}
): string {
	const orderId = seedReturnableOrder( { email: GUEST_EMAIL, ...opts } );
	seededOrders.push( orderId );
	return orderId;
}

/**
 * Open the guest return form for an order, skipping the lookup form.
 */
function formUrl( orderId: string ): string {
	return `${ returnsPageUrl }?hezarfen_order=${ orderId }&hezarfen_key=${ getOrderToken(
		orderId
	) }`;
}

test.describe( 'Hezarfen iade — iade edilebilirlik kuralları', () => {
	test.beforeAll( () => {
		optionSnapshot = snapshotOptions( OPTION_KEYS );
		enableReturns();
		returnsPageUrl = getReturnsPageUrl();
	} );

	test.afterAll( () => {
		for ( const orderId of seededOrders ) {
			deleteOrder( orderId );
		}
		clearReturns();
		restoreOptions( optionSnapshot );
	} );

	test.beforeEach( async ( { context } ) => {
		clearReturns();
		await context.clearCookies();
	} );

	test( 'iade süresi dolmuş sipariş reddediliyor', async ( { page } ) => {
		setOption( 'hezarfen_returns_window_days', '14' );
		const orderId = seedOrder( { completedDaysAgo: 30 } );

		await page.goto( formUrl( orderId ) );

		await expect( page.locator( NOTICE_ERROR ) ).toContainText(
			'iade süresi'
		);
		await expect( page.locator( '.hez-return-form' ) ).toHaveCount( 0 );
	} );

	test( 'süre içindeki sipariş kabul ediliyor ve son tarih gösteriliyor', async ( {
		page,
	} ) => {
		setOption( 'hezarfen_returns_window_days', '14' );
		const orderId = seedOrder( { completedDaysAgo: 3 } );

		await page.goto( formUrl( orderId ) );

		await expect( page.locator( '.hez-return-form' ) ).toBeVisible();
		await expect( page.locator( '.hez-returns__deadline' ) ).toContainText(
			'son iade tarihi'
		);
	} );

	test( 'süre 0 iken zaman sınırı uygulanmıyor', async ( { page } ) => {
		setOption( 'hezarfen_returns_window_days', '0' );
		const orderId = seedOrder( { completedDaysAgo: 400 } );

		try {
			await page.goto( formUrl( orderId ) );

			await expect( page.locator( '.hez-return-form' ) ).toBeVisible();
			// No window means no deadline line to show.
			await expect(
				page.locator( '.hez-returns__deadline' )
			).toHaveCount( 0 );
		} finally {
			setOption( 'hezarfen_returns_window_days', '14' );
		}
	} );

	test( 'uygun olmayan sipariş durumu iade talebini engelliyor', async ( {
		page,
	} ) => {
		const orderId = seedOrder( { status: 'processing' } );

		await page.goto( formUrl( orderId ) );

		await expect( page.locator( NOTICE_ERROR ) ).toContainText(
			'durumu iade talebine uygun değil'
		);
	} );

	test( 'ayarlarda izin verilen durum eklenince talep açılabiliyor', async ( {
		page,
	} ) => {
		const orderId = seedOrder( { status: 'processing' } );

		setEligibleStatuses( [ 'wc-completed', 'wc-processing' ] );

		try {
			await page.goto( formUrl( orderId ) );
			await expect( page.locator( '.hez-return-form' ) ).toBeVisible();
		} finally {
			setEligibleStatuses( [ 'wc-completed' ] );
		}
	} );

	test( 'tamamen iade edilmiş siparişte seçilecek ürün kalmıyor', async ( {
		page,
	} ) => {
		const orderId = seedOrder( { quantity: 2 } );
		seedReturn( { orderId, quantity: 2 } );

		await page.goto( formUrl( orderId ) );

		await expect( page.locator( NOTICE_ERROR ) ).toContainText(
			'iade edilebilecek ürün kalmadı'
		);
	} );

	test( 'reddedilen talep ayırdığı adedi geri bırakıyor', async ( {
		page,
	} ) => {
		const orderId = seedOrder( { quantity: 2 } );
		seedReturn( { orderId, quantity: 2, status: 'rejected' } );

		await page.goto( formUrl( orderId ) );

		// A rejected request must not keep the units locked forever.
		await expect( page.locator( '.hez-return-form' ) ).toBeVisible();
		await expect(
			page.locator( '[data-hez-item] .hez-item__meta' ).first()
		).toContainText( '2 adet' );
	} );

	test( 'dijital ürünler iade listesinde yer almıyor', async ( { page } ) => {
		const orderId = seedDigitalOrder( GUEST_EMAIL );
		seededOrders.push( orderId );

		await page.goto( formUrl( orderId ) );

		await expect( page.locator( NOTICE_ERROR ) ).toContainText(
			'iade edilebilecek ürün kalmadı'
		);
	} );

	test( 'WooCommerce üzerinden iade edilen adet düşülüyor', async ( {
		page,
	} ) => {
		const orderId = seedOrder( { quantity: 3 } );

		// The returns module has to respect a refund it did not create.
		refundFirstLine( orderId, 1 );

		await page.goto( formUrl( orderId ) );

		await expect(
			page.locator( '[data-hez-item] .hez-item__meta' ).first()
		).toContainText( '2 adet' );
	} );
} );
