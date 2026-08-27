import { expect, test } from '@playwright/test';
import { deleteOrder } from './helpers/orders';
import {
	clearReturns,
	disableReturns,
	enableReturns,
	loginAsReturnsCustomer,
	requestFormUrl,
	seedReturnableOrder,
} from './helpers/returns';
import { snapshotOptions, restoreOptions } from './helpers/wp-options';
import { wp } from './helpers/wp-cli';

/**
 * Fotoğraflı iade talebi.
 *
 * Fotoğraf özelliği Pro'da, akışın kendisi ücretsiz tarafta: dosya alanı
 * ücretsiz şablonun kancasına basılıyor, dosya Pro'nun deposuna iniyor ve
 * yalnızca yetkisi olana sunuluyor. Bu spec o SÖZLEŞMEYİ sınıyor -- yani tam
 * olarak birim testlerinin göremediği yeri.
 *
 * Neden gerekli: fotoğraflar talebe özel klasörlere taşındığında sunum ucu
 * dosya adını `sanitize_file_name()` ile temizlemeye devam ediyordu; o da eğik
 * çizgiyi sildiği için HİÇBİR fotoğraf eşleşmiyor, hepsi 404 dönüyordu. İki
 * taraf da kendi içinde doğruydu, kırılan şey aralarındaki sözleşmeydi.
 */

const OPTION_KEYS = [
	'hezarfen_returns_enabled',
	'hezarfen_returns_window_days',
	'hezarfen_returns_shipping_method',
];

/** 1x1 piksellik gerçek bir PNG. */
const PIXEL = Buffer.from(
	'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
	'base64'
);

function proReturnsActive(): boolean {
	const out = wp( [ 'plugin', 'list', '--field=name', '--status=active' ] );

	return out.includes( 'hezarfen-pro-for-woocommerce' );
}

test.describe( 'iade fotoğrafları', () => {
	let restore: Record< string, string >;

	test.beforeAll( () => {
		restore = snapshotOptions( OPTION_KEYS );
		enableReturns();
	} );

	test.afterAll( () => {
		clearReturns();
		disableReturns();
		restoreOptions( restore );
	} );

	test( 'müşteri fotoğraf ekler, küçük görsel gerçekten yüklenir', async ( { page } ) => {
		test.skip( ! proReturnsActive(), 'Fotoğraf özelliği Hezarfen Pro gerektiriyor.' );

		const orderId = seedReturnableOrder();

		try {
			await loginAsReturnsCustomer( page );
			await page.goto( requestFormUrl( orderId ) );

			const upload = page.locator( 'input[name="hezarfen_pro_return_photos[]"]' );
			await expect( upload, 'Talep formunda fotoğraf alanı bulunmalı.' ).toBeVisible();

			// Alan gerçekten bir dosya taşıyabilmeli: form etiketi multipart
			// kodlamasını ücretsiz taraftan almalı.
			await expect( page.locator( 'form.hez-return-form' ) ).toHaveAttribute(
				'enctype',
				'multipart/form-data'
			);

			await page.locator( '[data-hez-item-toggle]' ).first().check();
			await page.locator( '[data-hez-reason]' ).first().selectOption( { index: 1 } );
			await upload.setInputFiles( { name: 'kusur.png', mimeType: 'image/png', buffer: PIXEL } );
			await page.getByRole( 'button', { name: /İade talebini gönder/i } ).click();

			const thumb = page.locator( '.hez-pro-photos img' ).first();
			await expect( thumb, 'Eklenen fotoğraf talep sayfasında görünmeli.' ).toBeVisible();

			// Asıl iddia: adres sadece görünmüyor, GERÇEKTEN görsel dönüyor.
			const src = await thumb.getAttribute( 'src' );
			const response = await page.request.get( src as string );

			expect( response.status(), 'Fotoğraf ucu 200 dönmeli.' ).toBe( 200 );
			expect( response.headers()[ 'content-type' ] ).toContain( 'image/' );
			expect( response.headers()[ 'x-content-type-options' ] ).toBe( 'nosniff' );

			// Tarayıcının gerçekten çözebildiği bir görsel mi.
			const decoded = await thumb.evaluate(
				( img: HTMLImageElement ) => img.complete && img.naturalWidth > 0
			);
			expect( decoded, 'Küçük görsel çözülebilmeli.' ).toBe( true );
		} finally {
			deleteOrder( orderId );
		}
	} );

	test( 'oturumu olmayan ziyaretçi fotoğrafa erişemez', async ( { page, browser } ) => {
		test.skip( ! proReturnsActive(), 'Fotoğraf özelliği Hezarfen Pro gerektiriyor.' );

		const orderId = seedReturnableOrder();

		try {
			await loginAsReturnsCustomer( page );
			await page.goto( requestFormUrl( orderId ) );

			await page.locator( '[data-hez-item-toggle]' ).first().check();
			await page.locator( '[data-hez-reason]' ).first().selectOption( { index: 1 } );
			await page
				.locator( 'input[name="hezarfen_pro_return_photos[]"]' )
				.setInputFiles( { name: 'kusur.png', mimeType: 'image/png', buffer: PIXEL } );
			await page.getByRole( 'button', { name: /İade talebini gönder/i } ).click();

			const src = await page.locator( '.hez-pro-photos img' ).first().getAttribute( 'src' );

			// Aynı adres, oturumsuz bir bağlamdan: dosya tahmin edilemez bir adla
			// saklansa da asıl kapı yetki denetimi olmalı.
			const guest = await browser.newContext();
			const response = await guest.request.get( src as string );
			await guest.close();

			expect(
				response.status(),
				'Oturumu olmayan ziyaretçi fotoğrafı görememeli.'
			).not.toBe( 200 );
		} finally {
			deleteOrder( orderId );
		}
	} );

	test( 'müşteri eklediği fotoğrafı silebilir', async ( { page } ) => {
		test.skip( ! proReturnsActive(), 'Fotoğraf özelliği Hezarfen Pro gerektiriyor.' );

		const orderId = seedReturnableOrder();

		try {
			await loginAsReturnsCustomer( page );
			await page.goto( requestFormUrl( orderId ) );

			await page.locator( '[data-hez-item-toggle]' ).first().check();
			await page.locator( '[data-hez-reason]' ).first().selectOption( { index: 1 } );
			await page
				.locator( 'input[name="hezarfen_pro_return_photos[]"]' )
				.setInputFiles( { name: 'kusur.png', mimeType: 'image/png', buffer: PIXEL } );
			await page.getByRole( 'button', { name: /İade talebini gönder/i } ).click();

			await expect( page.locator( '.hez-pro-photos img' ) ).toHaveCount( 1 );

			page.once( 'dialog', ( dialog ) => dialog.accept() );
			await page.locator( '[data-hez-remove-photo], .hez-pro-photos button[type="submit"]' ).first().click();

			await expect(
				page.locator( '.hez-pro-photos img' ),
				'Silinen fotoğraf listeden kalkmalı.'
			).toHaveCount( 0 );
		} finally {
			deleteOrder( orderId );
		}
	} );
} );
