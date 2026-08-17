import { expect, test, type Page } from '@playwright/test';
import { deleteOrder } from './helpers/orders';
import { NOTICE_ERROR, NOTICE_SUCCESS } from './helpers/notices';
import {
	clearReturns,
	countReturnEvents,
	disableReturns,
	enableReturns,
	ensureReturnsCustomer,
	getReturnStatus,
	RETURNS_CUSTOMER,
	seedReturn,
	seedReturnableOrder,
} from './helpers/returns';
import { snapshotOptions, restoreOptions } from './helpers/wp-options';

/**
 * The account-side return journey: a customer opens "İadelerim", starts a
 * request from an eligible order, picks part of a line, and follows the
 * result. These specs deliberately drive the real form rather than the
 * service layer — the point is that what the template renders and what
 * [Return_Form_Handler](../../includes/returns/frontend/class-return-form-handler.php)
 * accepts stay in agreement.
 */

const OPTION_KEYS = [
	'hezarfen_returns_enabled',
	'hezarfen_returns_guest_enabled',
	'hezarfen_returns_window_days',
	'hezarfen_returns_window_reference',
	'hezarfen_returns_eligible_order_statuses',
	'hezarfen_returns_shipping_method',
	'hezarfen_returns_address_contact',
	'hezarfen_returns_address_line',
	'hezarfen_returns_address_district',
	'hezarfen_returns_address_city',
];

let optionSnapshot: Record< string, string >;
let customerId: string;
const seededOrders: string[] = [];

async function loginAsReturnsCustomer( page: Page ): Promise< void > {
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

function seedOrderForCustomer( quantity = 2 ): string {
	const orderId = seedReturnableOrder( {
		quantity,
		customerId,
		email: RETURNS_CUSTOMER.email,
	} );
	seededOrders.push( orderId );
	return orderId;
}

test.describe( 'Hezarfen iade — Hesabım akışı', () => {
	test.beforeAll( () => {
		optionSnapshot = snapshotOptions( OPTION_KEYS );
		enableReturns();
		customerId = ensureReturnsCustomer();
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

	test( '"İadelerim" menü öğesi ve boş durum görünüyor', async ( {
		page,
	} ) => {
		seedOrderForCustomer();
		await loginAsReturnsCustomer( page );

		await expect(
			page.locator( 'nav.woocommerce-MyAccount-navigation' )
		).toContainText( 'İadelerim' );

		await page.goto( '/my-account/iadelerim/' );

		await expect( page.locator( '.hez-empty__title' ) ).toContainText(
			'Henüz bir iade talebiniz yok'
		);
		// The empty state is only useful if it also offers a way out.
		await expect(
			page.locator( '.hez-order-list__item .hez-btn' ).first()
		).toBeVisible();
	} );

	test( 'kısmi iade talebi oluşturulabiliyor ve kalan adet düşüyor', async ( {
		page,
	} ) => {
		const orderId = seedOrderForCustomer( 2 );
		await loginAsReturnsCustomer( page );

		await page.goto( `/my-account/iade-talebi/${ orderId }/` );

		const item = page.locator( '[data-hez-item]' ).first();
		await expect( item ).toBeVisible();

		// Details stay collapsed until the line is picked.
		await expect(
			item.locator( '[data-hez-item-details]' )
		).toBeHidden();

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
		await expect(
			page.locator( '.hez-returns__title' )
		).toContainText( 'IADE-' );
		await expect( page.locator( '.hez-table tbody' ) ).toContainText(
			'Beden / ölçü uymadı'
		);

		// One of the two units is still returnable, so the form offers it.
		await page.goto( `/my-account/iade-talebi/${ orderId }/` );
		await expect(
			page.locator( '[data-hez-item] .hez-item__meta' ).first()
		).toContainText( '1 adet' );
	} );

	test( '"Diğer" sebebi açıklama olmadan sunucu tarafında reddediliyor', async ( {
		page,
	} ) => {
		const orderId = seedOrderForCustomer();
		await loginAsReturnsCustomer( page );

		await page.goto( `/my-account/iade-talebi/${ orderId }/` );

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
		const orderId = seedOrderForCustomer();
		await loginAsReturnsCustomer( page );

		await page.goto( `/my-account/iade-talebi/${ orderId }/` );
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

	test( 'talep listede görünüyor ve detayında durum takibi var', async ( {
		page,
	} ) => {
		const orderId = seedOrderForCustomer();
		const seeded = seedReturn( { orderId } );

		await loginAsReturnsCustomer( page );
		await page.goto( '/my-account/iadelerim/' );

		await expect( page.locator( '.hez-card__number' ) ).toContainText(
			seeded.number
		);

		await page.locator( '.hez-card__link' ).first().click();

		await expect( page.locator( '.hez-progress' ) ).toBeVisible();
		await expect(
			page.locator( '.hez-progress__step--current' )
		).toContainText( 'Talebiniz alındı' );
		await expect( page.locator( '.hez-timeline__item' ) ).toHaveCount( 1 );
	} );

	test( 'müşteri bekleyen talebini iptal edebiliyor', async ( { page } ) => {
		const orderId = seedOrderForCustomer();
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
		const orderId = seedOrderForCustomer();
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

	test( 'özellik kapalıyken hesabım menüsünde iade sekmesi yok', async ( {
		page,
	} ) => {
		disableReturns();

		try {
			await loginAsReturnsCustomer( page );
			await page.goto( '/my-account/' );

			await expect(
				page.locator( 'nav.woocommerce-MyAccount-navigation' )
			).not.toContainText( 'İadelerim' );
		} finally {
			enableReturns();
		}
	} );
} );
