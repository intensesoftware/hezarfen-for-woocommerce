import { expect, test } from '@playwright/test';
import { deleteOrder } from './helpers/orders';
import { NOTICE_ERROR, NOTICE_SUCCESS } from './helpers/notices';
import {
	clearReturns,
	countReturnEvents,
	customerTrackingError,
	disableReturns,
	enableReturns,
	getReturnStatus,
	loginAsReturnsCustomer,
	requestFormUrl,
	seedReturn,
	seedReturnableOrder,
	viewOrderUrl,
} from './helpers/returns';
import { snapshotOptions, restoreOptions } from './helpers/wp-options';

/**
 * The account-side return journey. Returns are account-only and the single
 * entry point is the order's own detail page, so these specs walk in the
 * way a customer actually does: Hesabım → Siparişler → sipariş detayı →
 * "İade talebi oluştur".
 *
 * They drive the real form rather than the service layer, which is what
 * keeps the template and
 * [Return_Form_Handler](../../includes/returns/frontend/class-return-form-handler.php)
 * in agreement.
 */

const OPTION_KEYS = [
	'hezarfen_returns_enabled',
	'hezarfen_returns_window_days',
	'hezarfen_returns_window_reference',
	'hezarfen_returns_shipping_method',
	'hezarfen_returns_address_contact',
	'hezarfen_returns_address_line',
	'hezarfen_returns_address_district',
	'hezarfen_returns_address_city',
];

let optionSnapshot: Record< string, string >;
const seededOrders: string[] = [];

function seedOrder( quantity = 2 ): string {
	const orderId = seedReturnableOrder( { quantity } );
	seededOrders.push( orderId );
	return orderId;
}

test.describe( 'Hezarfen iade — Hesabım akışı', () => {
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
	} );

	test( 'sipariş detayında iade talebi başlatma bağlantısı var', async ( {
		page,
	} ) => {
		const orderId = seedOrder();
		await loginAsReturnsCustomer( page );

		await page.goto( viewOrderUrl( orderId ) );

		const panel = page.locator( '.hez-returns--order-panel' );
		await expect( panel ).toBeVisible();

		const start = panel.locator( '.hez-btn--primary' );
		await expect( start ).toContainText( 'İade talebi oluştur' );

		await start.click();
		await expect( page.locator( '.hez-return-form' ) ).toBeVisible();
	} );

	test( 'iade edilemeyen siparişte panel çıkmıyor', async ( { page } ) => {
		// Window closed: the order detail page must not offer a dead end.
		const orderId = seedReturnableOrder( { completedDaysAgo: 60 } );
		seededOrders.push( orderId );

		await loginAsReturnsCustomer( page );
		await page.goto( viewOrderUrl( orderId ) );

		await expect( page.locator( '.hez-returns--order-panel' ) ).toHaveCount(
			0
		);
	} );

	test( 'hesabım menüsüne iade sekmesi eklenmiyor', async ( { page } ) => {
		await loginAsReturnsCustomer( page );

		// Returns live on the order, not in their own account tab.
		await expect(
			page.locator( 'nav.woocommerce-MyAccount-navigation' )
		).not.toContainText( 'İade' );
	} );

	test( 'ID taşımayan talep adresi siparişlere yönleniyor', async ( {
		page,
	} ) => {
		await loginAsReturnsCustomer( page );

		await page.goto( '/my-account/iadelerim/' );

		await expect( page ).toHaveURL( /\/my-account\/orders\/?$/ );
	} );

	test( 'kısmi iade talebi oluşturulabiliyor ve kalan adet düşüyor', async ( {
		page,
	} ) => {
		const orderId = seedOrder( 2 );
		await loginAsReturnsCustomer( page );

		await page.goto( requestFormUrl( orderId ) );

		const item = page.locator( '[data-hez-item]' ).first();
		await expect( item ).toBeVisible();

		// Details stay collapsed until the line is picked.
		await expect( item.locator( '[data-hez-item-details]' ) ).toBeHidden();

		await item.locator( '[data-hez-item-toggle]' ).check();
		await expect( item.locator( '[data-hez-item-details]' ) ).toBeVisible();

		await item.locator( '.hez-input--qty' ).fill( '1' );
		await item.locator( '[data-hez-reason]' ).selectOption( 'size-fit' );
		await page
			.locator( 'textarea[name="customer_note"]' )
			.fill( 'E2E kısmi iade' );

		await page
			.locator( '[data-hez-return-form] button[type="submit"]' )
			.click();

		await expect( page.locator( '.hez-returns--detail' ) ).toBeVisible();
		await expect( page.locator( '.hez-returns__title' ) ).toContainText(
			'IADE-'
		);
		await expect( page.locator( '.hez-table tbody' ) ).toContainText(
			'Beden / ölçü uymadı'
		);

		// One of the two units is still returnable, so the form offers it.
		await page.goto( requestFormUrl( orderId ) );
		await expect(
			page.locator( '[data-hez-item] .hez-item__meta' ).first()
		).toContainText( '1 adet' );
	} );

	test( '"Diğer" sebebi açıklama olmadan sunucu tarafında reddediliyor', async ( {
		page,
	} ) => {
		const orderId = seedOrder();
		await loginAsReturnsCustomer( page );

		await page.goto( requestFormUrl( orderId ) );

		const item = page.locator( '[data-hez-item]' ).first();
		await item.locator( '[data-hez-item-toggle]' ).check();
		await item.locator( '[data-hez-reason]' ).selectOption( 'other' );

		// Bypass the client-side guard the way a scripted POST would, so
		// the assertion is about the server rule and not about the JS.
		await page.evaluate( () => {
			const form = document.querySelector(
				'[data-hez-return-form]'
			) as HTMLFormElement;
			form.submit();
		} );

		await expect( page.locator( NOTICE_ERROR ) ).toContainText(
			'açıklama yazmanız gerekiyor'
		);
	} );

	test( 'seçim yapılmadan gönderilen form hata veriyor', async ( {
		page,
	} ) => {
		const orderId = seedOrder();
		await loginAsReturnsCustomer( page );

		await page.goto( requestFormUrl( orderId ) );
		await page.evaluate( () => {
			const form = document.querySelector(
				'[data-hez-return-form]'
			) as HTMLFormElement;
			form.submit();
		} );

		await expect( page.locator( NOTICE_ERROR ) ).toContainText(
			'en az bir ürün seçin'
		);
	} );

	test( 'başka müşterinin siparişi için form açılamıyor', async ( {
		page,
	} ) => {
		// customer_id 0 → the order belongs to nobody the session can claim.
		const foreignOrder = seedReturnableOrder( { customerId: '0' } );
		seededOrders.push( foreignOrder );

		await loginAsReturnsCustomer( page );
		await page.goto( requestFormUrl( foreignOrder ) );

		await expect( page.locator( '.hez-return-form' ) ).toHaveCount( 0 );
		await expect( page.locator( NOTICE_ERROR ) ).toContainText(
			'iade talebi oluşturamazsınız'
		);
	} );

	test( 'talep detayında durum takibi ve geçmiş var', async ( { page } ) => {
		const orderId = seedOrder();
		const seeded = seedReturn( { orderId } );

		await loginAsReturnsCustomer( page );
		await page.goto( viewOrderUrl( orderId ) );

		// The order panel is how a customer reaches their request.
		await page.locator( '.hez-card__link' ).first().click();

		await expect( page.locator( '.hez-returns__title' ) ).toContainText(
			seeded.number
		);
		await expect( page.locator( '.hez-progress' ) ).toBeVisible();
		await expect(
			page.locator( '.hez-progress__step--current' )
		).toContainText( 'Talebiniz alındı' );
		await expect( page.locator( '.hez-timeline__item' ) ).toHaveCount( 1 );

		// And back to the order it belongs to.
		await page.locator( '.hez-returns__back' ).click();
		await expect( page ).toHaveURL( new RegExp( `view-order/${ orderId }` ) );
	} );

	test( 'açık talep sipariş detayında da listeleniyor', async ( { page } ) => {
		const orderId = seedOrder();
		const seeded = seedReturn( { orderId } );

		await loginAsReturnsCustomer( page );
		await page.goto( viewOrderUrl( orderId ) );

		const panel = page.locator( '.hez-returns--order-panel' );
		await expect( panel ).toContainText( seeded.number );
		await expect( panel ).toContainText( 'Talebiniz alındı' );
	} );

	test( 'müşteri bekleyen talebini iptal edebiliyor', async ( { page } ) => {
		const orderId = seedOrder();
		const seeded = seedReturn( { orderId } );

		await loginAsReturnsCustomer( page );
		await page.goto( `/my-account/iadelerim/${ seeded.id }/` );

		page.on( 'dialog', ( dialog ) => dialog.accept() );
		await page
			.locator( '.hez-returns__cancel button[type="submit"]' )
			.click();

		await expect( page.locator( NOTICE_SUCCESS ) ).toContainText(
			'iptal edildi'
		);
		expect( getReturnStatus( seeded.id ) ).toBe( 'cancelled' );
	} );

	test( 'onaylanan talepte kargo takip numarası girilebiliyor', async ( {
		page,
	} ) => {
		const orderId = seedOrder();
		const seeded = seedReturn( { orderId, status: 'approved' } );

		await loginAsReturnsCustomer( page );
		await page.goto( `/my-account/iadelerim/${ seeded.id }/` );

		// The configured method is "customer ships it", so the store's
		// return address has to be on screen for the parcel to go anywhere.
		await expect( page.locator( '.hez-address__body' ) ).toContainText(
			'Hezarfen E2E Depo'
		);

		await page.locator( '#hez-courier' ).fill( 'Yurtiçi Kargo' );
		await page.locator( '#hez-tracking-number' ).fill( 'E2E-TRACK-1' );
		await page
			.locator( '.hez-inline-form--tracking button[type="submit"]' )
			.click();

		await expect( page.locator( NOTICE_SUCCESS ) ).toContainText(
			'Kargo bilginiz kaydedildi'
		);
		await expect( page.locator( '.hez-tracking__value' ) ).toContainText(
			'E2E-TRACK-1'
		);

		// Handing the parcel over moves the request on by itself.
		expect( getReturnStatus( seeded.id ) ).toBe( 'shipped' );
		expect( countReturnEvents( seeded.id ) ).toBeGreaterThan( 2 );
	} );

	test( 'ek bilgi beklenen talepte ilerleme çubuğu sıfırlanmıyor', async ( {
		page,
	} ) => {
		const orderId = seedOrder();
		const seeded = seedReturn( { orderId, status: 'info-required' } );

		await loginAsReturnsCustomer( page );
		await page.goto( `/my-account/iadelerim/${ seeded.id }/` );

		// Waiting for an answer is a pause, not a reset: the step the
		// request was parked at stays lit.
		await expect(
			page.locator( '.hez-progress__step--current' )
		).toHaveCount( 1 );
		await expect(
			page.locator( '.hez-progress__step' ).first()
		).toHaveClass( /hez-progress__step--current/ );
		await expect(
			page.locator( '.hez-panel--action' )
		).toContainText( 'Sizden ek bilgi bekleniyor' );
	} );

	test( 'onaylanmamış talebe kargo bilgisi yazılamıyor', async () => {
		const orderId = seedOrder();
		const seeded = seedReturn( { orderId } );

		// The form is not on screen for a pending request; a crafted POST
		// must not get any further than the missing form does.
		expect(
			customerTrackingError( seeded.id, 'Yurtiçi Kargo', 'E2E-TRACK-9' )
		).toBe( 'hezarfen_returns_tracking_not_editable' );
	} );

	test( 'özellik kapalıyken sipariş detayında iade paneli yok', async ( {
		page,
	} ) => {
		const orderId = seedOrder();
		disableReturns();

		try {
			await loginAsReturnsCustomer( page );
			await page.goto( viewOrderUrl( orderId ) );

			await expect(
				page.locator( '.hez-returns--order-panel' )
			).toHaveCount( 0 );
		} finally {
			enableReturns();
		}
	} );
} );
