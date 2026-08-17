import { expect, test } from '@playwright/test';
import { deleteOrder } from './helpers/orders';
import { NOTICE_ERROR } from './helpers/notices';
import {
	clearReturns,
	enableReturns,
	getOrderToken,
	getReturnsPageUrl,
	getReturnStatus,
	seedReturn,
	seedReturnableOrder,
	setOption,
} from './helpers/returns';
import { restoreOptions, snapshotOptions } from './helpers/wp-options';

/**
 * Guest returns. A visitor with no account proves ownership of an order
 * with the order number plus the billing e-mail, and from then on carries
 * a token derived from the order key — the same secret WooCommerce already
 * trusts for guest order views.
 *
 * The security-relevant assertions here are the negative ones: a wrong
 * e-mail must not reveal that the order exists, and a missing or forged
 * token must not open somebody else's request.
 */

const GUEST_EMAIL = 'hezarfen-e2e-guest@example.test';

const OPTION_KEYS = [
	'hezarfen_returns_enabled',
	'hezarfen_returns_guest_enabled',
	'hezarfen_returns_window_days',
	'hezarfen_returns_eligible_order_statuses',
	'hezarfen_returns_address_contact',
	'hezarfen_returns_address_line',
	'hezarfen_returns_address_city',
];

let optionSnapshot: Record< string, string >;
let returnsPageUrl: string;
const seededOrders: string[] = [];

function seedGuestOrder( quantity = 2 ): string {
	const orderId = seedReturnableOrder( { quantity, email: GUEST_EMAIL } );
	seededOrders.push( orderId );
	return orderId;
}

test.describe( 'Hezarfen iade — üyeliksiz (guest) akış', () => {
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
		// Every guest spec must start without a logged-in session.
		await context.clearCookies();
	} );

	test( 'iade sayfası sipariş sorgulama formunu gösteriyor', async ( {
		page,
	} ) => {
		await page.goto( returnsPageUrl );

		await expect( page.locator( '.hez-lookup-form' ) ).toBeVisible();
		await expect( page.locator( '#hez-order-number' ) ).toBeVisible();
		await expect( page.locator( '#hez-billing-email' ) ).toBeVisible();
	} );

	test( 'yanlış e-posta siparişin varlığını sızdırmıyor', async ( {
		page,
	} ) => {
		const orderId = seedGuestOrder();

		await page.goto( returnsPageUrl );
		await page.locator( '#hez-order-number' ).fill( orderId );
		await page
			.locator( '#hez-billing-email' )
			.fill( 'baska-biri@example.test' );
		await page.locator( '.hez-lookup-form button[type="submit"]' ).click();

		const error = page.locator( NOTICE_ERROR );
		await expect( error ).toContainText( 'eşleşmiyor' );

		// Same wording as a non-existent order: nothing distinguishes the
		// two cases for someone probing the form.
		await page.goto( returnsPageUrl );
		await page.locator( '#hez-order-number' ).fill( '99999999' );
		await page.locator( '#hez-billing-email' ).fill( GUEST_EMAIL );
		await page.locator( '.hez-lookup-form button[type="submit"]' ).click();
		await expect( page.locator( NOTICE_ERROR ) ).toContainText(
			'eşleşmiyor'
		);
	} );

	test( 'doğru bilgilerle iade formu açılıyor ve talep oluşturulabiliyor', async ( {
		page,
	} ) => {
		const orderId = seedGuestOrder( 2 );

		await page.goto( returnsPageUrl );
		await page.locator( '#hez-order-number' ).fill( orderId );
		await page.locator( '#hez-billing-email' ).fill( GUEST_EMAIL );
		await page.locator( '.hez-lookup-form button[type="submit"]' ).click();

		await expect( page.locator( '.hez-return-form' ) ).toBeVisible();
		await expect( page ).toHaveURL( /hezarfen_order=/ );

		const item = page.locator( '[data-hez-item]' ).first();
		await item.locator( '[data-hez-item-toggle]' ).check();
		await item.locator( '[data-hez-reason]' ).selectOption( 'wrong-item' );
		await page
			.locator( '[data-hez-return-form] button[type="submit"]' )
			.click();

		// A guest lands on the token URL of their new request.
		await expect( page ).toHaveURL( /hezarfen_return=\d+/ );
		await expect( page ).toHaveURL( /hezarfen_key=/ );
		await expect( page.locator( '.hez-returns--detail' ) ).toBeVisible();
		await expect( page.locator( '.hez-progress' ) ).toBeVisible();
	} );

	test( 'token olmadan talep detayına erişilemiyor', async ( { page } ) => {
		const orderId = seedGuestOrder();
		const seeded = seedReturn( { orderId } );

		await page.goto( `${ returnsPageUrl }?hezarfen_return=${ seeded.id }` );

		// Falls back to the lookup form instead of rendering the request.
		await expect( page.locator( '.hez-lookup-form' ) ).toBeVisible();
		await expect( page.locator( '.hez-returns--detail' ) ).toHaveCount( 0 );
	} );

	test( 'yanlış token talep detayını açmıyor', async ( { page } ) => {
		const orderId = seedGuestOrder();
		const seeded = seedReturn( { orderId } );

		await page.goto(
			`${ returnsPageUrl }?hezarfen_return=${ seeded.id }&hezarfen_key=gecersiz-token`
		);

		await expect( page.locator( '.hez-lookup-form' ) ).toBeVisible();
		await expect( page.locator( '.hez-returns--detail' ) ).toHaveCount( 0 );
	} );

	test( 'başka bir siparişin tokenı formu açmıyor', async ( { page } ) => {
		const mine = seedGuestOrder();
		const other = seedGuestOrder();
		const otherToken = getOrderToken( other );

		await page.goto(
			`${ returnsPageUrl }?hezarfen_order=${ mine }&hezarfen_key=${ otherToken }`
		);

		await expect( page.locator( '.hez-lookup-form' ) ).toBeVisible();
		await expect( page.locator( '.hez-return-form' ) ).toHaveCount( 0 );
	} );

	test( 'geçerli token ile talep iptal edilebiliyor', async ( { page } ) => {
		const orderId = seedGuestOrder();
		const seeded = seedReturn( { orderId } );

		await page.goto(
			`${ returnsPageUrl }?hezarfen_return=${ seeded.id }&hezarfen_key=${ seeded.token }`
		);
		await expect( page.locator( '.hez-returns--detail' ) ).toBeVisible();

		page.on( 'dialog', ( dialog ) => dialog.accept() );
		await page
			.locator( '.hez-returns__cancel button[type="submit"]' )
			.click();

		expect( getReturnStatus( seeded.id ) ).toBe( 'cancelled' );
	} );

	test( 'üyeliksiz iade kapalıyken sorgulama formu gösterilmiyor', async ( {
		page,
	} ) => {
		setOption( 'hezarfen_returns_guest_enabled', 'no' );

		try {
			await page.goto( returnsPageUrl );

			await expect( page.locator( '.hez-lookup-form' ) ).toHaveCount( 0 );
			await expect( page.locator( '.hez-callout--info' ) ).toContainText(
				'giriş yapmanız gerekiyor'
			);
		} finally {
			setOption( 'hezarfen_returns_guest_enabled', 'yes' );
		}
	} );
} );
