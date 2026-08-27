import { expect, test, type Page } from '@playwright/test';
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

/**
 * Bir ürün seçip iade sebebini doldurur.
 *
 * Sebep dizinle seçilmiyor: hazır listenin ilk sırası açıklama zorunlu bir
 * sebep olabiliyor ve o zaman ücretsiz tarafın doğrulaması gönderimi haklı
 * olarak engelliyor. Sebep seçildikten sonra açıklama alanı görünürse
 * dolduruluyor, böylece test sebep sırasına bağımlı kalmıyor.
 */
async function fillFirstLine( page: Page ): Promise< void > {
	await page.locator( '[data-hez-item-toggle]' ).first().check();

	const item = page.locator( '[data-hez-item]' ).first();
	await item.locator( '[data-hez-reason]' ).selectOption( { index: 1 } );

	const note = item.locator( 'textarea[name*="[note]"]' );

	if ( await note.isVisible() ) {
		await note.fill( 'Test açıklaması' );
	}
}

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
			await expect( upload, 'Dosya alanı formda bulunmalı.' ).toBeAttached();
			await expect(
				page.locator( '[data-hez-photos-drop]' ),
				'Sürükleme alanı görünmeli.'
			).toBeVisible();

			// Alan gerçekten bir dosya taşıyabilmeli: form etiketi multipart
			// kodlamasını ücretsiz taraftan almalı.
			await expect( page.locator( 'form.hez-return-form' ) ).toHaveAttribute(
				'enctype',
				'multipart/form-data'
			);

			await fillFirstLine( page );
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

	test( 'sürükle-bırak önizleme üretir, kaldırma listeden düşürür', async ( { page } ) => {
		test.skip( ! proReturnsActive(), 'Fotoğraf özelliği Hezarfen Pro gerektiriyor.' );

		const orderId = seedReturnableOrder();

		try {
			await loginAsReturnsCustomer( page );
			await page.goto( requestFormUrl( orderId ) );

			const zone = page.locator( '[data-hez-photos-drop]' );
			await expect( zone, 'Sürükleme alanı görünmeli.' ).toBeVisible();

			// Gerçek bir bırakma olayı: dosya DataTransfer ile taşınıyor.
			await page.evaluate( ( pixel ) => {
				const bytes = Uint8Array.from( atob( pixel ), ( c ) => c.charCodeAt( 0 ) );
				const file = new File( [ bytes ], 'kusur.png', { type: 'image/png' } );
				const dt = new DataTransfer();
				dt.items.add( file );

				const drop = document.querySelector( '[data-hez-photos-drop]' );
				drop.dispatchEvent( new DragEvent( 'dragenter', { bubbles: true, dataTransfer: dt } ) );
				drop.dispatchEvent( new DragEvent( 'drop', { bubbles: true, dataTransfer: dt } ) );
			}, PIXEL.toString( 'base64' ) );

			// Bırakılan dosya hem önizlemede hem de gerçekten girdide olmalı;
			// önizleme doğru ama girdi boşsa form boş gönderilirdi.
			await expect( page.locator( '.hez-photos__item' ) ).toHaveCount( 1 );
			await expect( page.locator( '.hez-photos__thumb' ) ).toBeVisible();

			const inputCount = await page.evaluate(
				() => document.querySelector( '.hez-photos__input' ).files.length
			);
			expect( inputCount, 'Bırakılan dosya girdiye yazılmalı.' ).toBe( 1 );

			await page.locator( '.hez-photos__remove' ).click();

			await expect( page.locator( '.hez-photos__item' ) ).toHaveCount( 0 );
			expect(
				await page.evaluate( () => document.querySelector( '.hez-photos__input' ).files.length ),
				'Kaldırılan dosya girdiden de düşmeli.'
			).toBe( 0 );
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

			await fillFirstLine( page );
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

			await fillFirstLine( page );
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
