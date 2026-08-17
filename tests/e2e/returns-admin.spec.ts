import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth';
import { deleteOrder } from './helpers/orders';
import {
	clearReturns,
	enableReturns,
	getReturnStatus,
	seedReturn,
	seedReturnableOrder,
} from './helpers/returns';
import { restoreOptions, snapshotOptions } from './helpers/wp-options';

/**
 * The merchant side: Hezarfen → İadeler. Covers the list chrome (status
 * views, search), the detail screen and every manual transition the free
 * module offers — approve, reject, ask for more information, record a
 * carrier, and note-taking with its internal/visible distinction.
 */

const ADMIN_URL = '/wp-admin/admin.php?page=hezarfen-returns';

// The order edit screen ships hidden tables from other Hezarfen features,
// so target the list table's own tbody rather than any `tbody` on the page.
const ROWS = '#the-list';

const OPTION_KEYS = [
	'hezarfen_returns_enabled',
	'hezarfen_returns_window_days',
	'hezarfen_returns_shipping_method',
];

let optionSnapshot: Record< string, string >;
const seededOrders: string[] = [];

function seedOrder(): string {
	const orderId = seedReturnableOrder( { quantity: 2 } );
	seededOrders.push( orderId );
	return orderId;
}

test.describe( 'Hezarfen iade — yönetim ekranı', () => {
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

	test.beforeEach( async ( { page } ) => {
		clearReturns();
		await loginAsAdmin( page );
	} );

	test( 'liste ekranı talepleri ve durum filtrelerini gösteriyor', async ( {
		page,
	} ) => {
		const orderId = seedOrder();
		const pending = seedReturn( { orderId } );
		const approved = seedReturn( { orderId, status: 'approved' } );

		await page.goto( ADMIN_URL );

		await expect( page.locator( '.wp-heading-inline' ) ).toContainText(
			'İade Talepleri'
		);
		await expect( page.locator( ROWS ) ).toContainText( pending.number );
		await expect( page.locator( ROWS ) ).toContainText( approved.number );

		// Status views count what the repository grouped, not what the page
		// happens to render.
		await expect( page.locator( '.subsubsub' ) ).toContainText( 'Tümü (2)' );
		await expect( page.locator( '.subsubsub' ) ).toContainText(
			'Beklemede (1)'
		);

		await page.locator( '.subsubsub a', { hasText: 'Onaylandı' } ).click();
		await expect( page.locator( ROWS ) ).toContainText( approved.number );
		await expect( page.locator( ROWS ) ).not.toContainText(
			pending.number
		);
	} );

	test( 'talep numarasıyla arama yapılabiliyor', async ( { page } ) => {
		const orderId = seedOrder();
		const first = seedReturn( { orderId } );
		const second = seedReturn( { orderId } );

		await page.goto( ADMIN_URL );
		await page.locator( '#hezarfen-returns-search-input' ).fill( second.number );
		await page.locator( '#search-submit' ).click();

		await expect( page.locator( ROWS ) ).toContainText( second.number );
		await expect( page.locator( ROWS ) ).not.toContainText(
			first.number
		);
	} );

	test( 'detay ekranı ürün, özet ve geçmiş kartlarını gösteriyor', async ( {
		page,
	} ) => {
		const orderId = seedOrder();
		const seeded = seedReturn( { orderId, quantity: 2 } );

		await page.goto( `${ ADMIN_URL }&return_id=${ seeded.id }` );

		await expect( page.locator( '.wp-heading-inline' ) ).toContainText(
			seeded.number
		);
		await expect( page.locator( '.hez-admin-summary' ) ).toContainText(
			`#${ orderId }`
		);
		await expect( page.locator( '.hez-admin-timeline__item' ) ).toHaveCount(
			1
		);
		await expect( page.locator( '.hez-admin-grid table' ) ).toContainText(
			'Hezarfen E2E İade Ürünü'
		);
	} );

	test( 'talep onaylanabiliyor', async ( { page } ) => {
		const orderId = seedOrder();
		const seeded = seedReturn( { orderId } );

		await page.goto( `${ ADMIN_URL }&return_id=${ seeded.id }` );
		await page
			.locator( '.hez-admin-actions button', { hasText: 'Onayla' } )
			.click();

		await expect( page.locator( '.notice-success' ) ).toBeVisible();
		expect( getReturnStatus( seeded.id ) ).toBe( 'approved' );

		// Approved is not a dead end — the merchant can still record arrival.
		await expect(
			page.locator( '.hez-admin-actions button', {
				hasText: 'Ürünler ulaştı',
			} )
		).toBeVisible();
	} );

	test( 'talep reddedilebiliyor ve kapanınca işlem kalmıyor', async ( {
		page,
	} ) => {
		const orderId = seedOrder();
		const seeded = seedReturn( { orderId } );

		await page.goto( `${ ADMIN_URL }&return_id=${ seeded.id }` );
		await page
			.locator( '.hez-admin-actions button', { hasText: 'Reddet' } )
			.click();

		expect( getReturnStatus( seeded.id ) ).toBe( 'rejected' );
		await expect( page.locator( '.hez-admin-actions' ) ).toHaveCount( 0 );
	} );

	test( 'müşteriden ek bilgi istenebiliyor', async ( { page } ) => {
		const orderId = seedOrder();
		const seeded = seedReturn( { orderId } );

		await page.goto( `${ ADMIN_URL }&return_id=${ seeded.id }` );
		await page
			.locator( '#hez-admin-info' )
			.fill( 'Ürünün kutusunun fotoğrafını paylaşır mısınız?' );
		await page
			.locator( 'form:has(#hez-admin-info) button[type="submit"]' )
			.click();

		expect( getReturnStatus( seeded.id ) ).toBe( 'info-required' );
		await expect( page.locator( '.hez-admin-timeline' ) ).toContainText(
			'Ek bilgi istendi'
		);
		await expect( page.locator( '.hez-admin-timeline' ) ).toContainText(
			'fotoğrafını paylaşır mısınız'
		);
	} );

	test( 'dahili not müşteriye kapalı işaretleniyor', async ( { page } ) => {
		const orderId = seedOrder();
		const seeded = seedReturn( { orderId } );

		await page.goto( `${ ADMIN_URL }&return_id=${ seeded.id }` );
		await page.locator( '#hez-admin-note' ).fill( 'Dahili E2E notu' );
		await page
			.locator( 'form:has(#hez-admin-note) button[type="submit"]' )
			.click();

		const internal = page.locator(
			'.hez-admin-timeline__item.is-internal'
		);
		await expect( internal ).toContainText( 'Dahili E2E notu' );
		await expect( internal ).toContainText( 'sadece mağaza görür' );
	} );

	test( 'yönetici kargo takip numarası girebiliyor', async ( { page } ) => {
		const orderId = seedOrder();
		const seeded = seedReturn( { orderId, status: 'approved' } );

		await page.goto( `${ ADMIN_URL }&return_id=${ seeded.id }` );
		await page.locator( '#hez-admin-courier' ).fill( 'Aras Kargo' );
		await page.locator( '#hez-admin-tracking' ).fill( 'ADMIN-TRACK-1' );
		await page
			.locator( 'form:has(#hez-admin-tracking) button[type="submit"]' )
			.click();

		await expect( page.locator( '.hez-admin-tracking' ) ).toContainText(
			'ADMIN-TRACK-1'
		);
		expect( getReturnStatus( seeded.id ) ).toBe( 'shipped' );
	} );

	test( 'sipariş düzenleme ekranında iade kutusu görünüyor', async ( {
		page,
	} ) => {
		const orderId = seedOrder();
		const seeded = seedReturn( { orderId } );

		await page.goto(
			`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }`
		);

		const box = page.locator( '#hezarfen-order-returns' );
		await expect( box ).toBeVisible();
		await expect( box ).toContainText( seeded.number );
	} );
} );
