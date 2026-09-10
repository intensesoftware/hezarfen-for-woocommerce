import { expect, test } from '@playwright/test';
import { deleteMuPlugin, writeMuPlugin } from './helpers/mu-plugin';
import { loginAsAdmin } from './helpers/auth';
import { enableReturns, proReturnsActive } from './helpers/returns';
import { restoreOptions, snapshotOptions } from './helpers/wp-options';
import { wp } from './helpers/wp-cli';

/**
 * The "İade Yönetimi" section under WooCommerce → Ayarlar → Hezarfen.
 *
 * It is registered through WooCommerce's own `woocommerce_get_sections_*`
 * and `woocommerce_get_settings_*` filters rather than by editing the
 * settings page class, so these specs double as a regression guard on
 * that wiring: if the filters stop matching, the section silently
 * disappears and nothing else in the plugin would notice.
 */

const SETTINGS_URL =
	'/wp-admin/admin.php?page=wc-settings&tab=hezarfen&section=returns';

const OPTION_KEYS = [
	'hezarfen_returns_enabled',
	'hezarfen_returns_window_days',
	'hezarfen_returns_window_reference',
	'hezarfen_returns_shipping_method',
	'hezarfen_returns_instructions',
	'hezarfen_returns_auto_refund',
	'hezarfen_returns_restock',
	'hezarfen_returns_address_contact',
	'hezarfen_returns_address_line',
	'hezarfen_returns_address_city',
];

/** The locked row of a Pro-backed setting, found by the setting's own name. */
const LOCKED_STATUSES_ROW =
	'tr.hez-locked-row:has-text("İade edilebilir sipariş durumları")';

const LOCKED_REASONS_ROW = 'tr.hez-locked-row:has-text("İade sebepleri")';

/**
 * Stands in for a Pro that implements one placement: the `hezarfen_returns_setting_fields`
 * seam lets it drop a locked preview row (here, reasons) without any change to
 * the free plugin.
 */
/**
 * Pro'nun çalıştığını taklit eder. Satış bağlantısının kapısı sürüm
 * sabitine bakıyor; option damgası Pro kaldırıldıktan sonra da geride
 * kaldığı için kapı olamazdı.
 */
const PRO_ACTIVE_SLUG = 'hezarfen-e2e-returns-pro-active';
const PRO_ACTIVE_PHP = `<?php
if ( ! defined( 'HEZARFEN_PRO_VERSION' ) ) {
	define( 'HEZARFEN_PRO_VERSION', '0.0.0-e2e' );
}
`;

const SWAP_SLUG = 'hezarfen-e2e-returns-setting-fields';
const SWAP_PHP = `<?php
add_filter( 'hezarfen_returns_setting_fields', function ( $fields, $placement ) {
	return 'reasons' === $placement ? array() : $fields;
}, 10, 2 );
`;

/** Kilitli satırın temsil ettiği option'ın ham hâli. */
function readEligibleStatuses(): string {
	return wp( [
		'eval',
		`var_export( get_option( 'hezarfen_returns_eligible_order_statuses', 'MISSING' ) );`,
	] ).trim();
}

let optionSnapshot: Record< string, string >;

test.describe( 'Hezarfen iade — ayarlar bölümü', () => {
	test.beforeAll( () => {
		optionSnapshot = snapshotOptions( OPTION_KEYS );
		enableReturns();
	} );

	test.afterAll( () => {
		restoreOptions( optionSnapshot );
	} );

	test.beforeEach( async ( { page } ) => {
		await loginAsAdmin( page );
	} );

	test( 'Hezarfen sekmesinde iade bölümü listeleniyor', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=hezarfen' );

		await expect( page.locator( '.subsubsub' ) ).toContainText(
			'İade Yönetimi'
		);
	} );

	test( 'bölüm tüm alanlarıyla açılıyor', async ( { page } ) => {
		await page.goto( SETTINGS_URL );

		await expect(
			page.locator( '#hezarfen_returns_enabled' )
		).toBeVisible();
		await expect(
			page.locator( '#hezarfen_returns_window_days' )
		).toBeVisible();
		await expect(
			page.locator( '#hezarfen_returns_window_reference' )
		).toBeVisible();
		await expect(
			page.locator( '#hezarfen_returns_shipping_method' )
		).toBeVisible();
		await expect(
			page.locator( '#hezarfen_returns_auto_refund' )
		).toBeVisible();
		await expect(
			page.locator( '#hezarfen_returns_restock' )
		).toBeVisible();
		await expect(
			page.locator( '#hezarfen_returns_address_line' )
		).toBeVisible();

		// Which order statuses may be returned is a Pro setting, so the free
		// section shows the locked row in its place. Asserting on the field
		// would go green the day someone ships the Pro setting for free.
		//
		// Pro kuruluysa denklem tersine döner: kilit gider, gerçek alan
		// gelir. Bu yüzden iki hâl de burada, aynı yerde sınanıyor -- birini
		// atlamak, spec'i geliştirme sitesinin eklenti durumuna bağımlı
		// kılardı.
		if ( proReturnsActive() ) {
			await expect(
				page.locator( '#hezarfen_returns_eligible_order_statuses' )
			).toBeVisible();
			await expect( page.locator( LOCKED_STATUSES_ROW ) ).toHaveCount( 0 );
		} else {
			await expect(
				page.locator( '#hezarfen_returns_eligible_order_statuses' )
			).toHaveCount( 0 );
			await expect( page.locator( LOCKED_STATUSES_ROW ) ).toBeVisible();
		}
	} );

	test( 'kilitli satırlar kaydetmede boş option yazmıyor', async ( {
		page,
	} ) => {
		const before = readEligibleStatuses();

		await page.goto( SETTINGS_URL );

		// WooCommerce kaydet butonunu sayfada bir değişiklik olana kadar
		// devre dışı bırakıyor. Kaydetmeyi tetiklemek için formu kirletmek
		// gerekiyor; kirletilen alan kilitli satırın kendisi değil, yanındaki
		// gerçek bir ayar -- iddia zaten kilitli satırın kayda katılmadığı.
		const days = page.locator( '#hezarfen_returns_window_days' );
		await days.fill( String( Number( await days.inputValue() ) || 14 ) );
		await days.dispatchEvent( 'change' );

		await page.locator( 'button[name="save"]' ).click();
		await expect( page.locator( '#message.updated.inline' ) ).toBeVisible();

		// Every locked row is marked `is_option => false` precisely so
		// WooCommerce's default save branch does not write an empty option for
		// it — wc_clean( null ) yields '' there, not null. A written empty value
		// would later read back as "nothing is eligible" and quietly stop
		// orders from being returnable. So none of the four placeholder ids may
		// appear after a save, and the real statuses key the free flow falls
		// back on must stay untouched. Checking all four guards against a
		// regression that makes just one of them savable.
		for ( const id of [
			'hezarfen_returns_locked_statuses',
			'hezarfen_returns_locked_products',
			'hezarfen_returns_locked_reasons',
			'hezarfen_returns_locked_photos',
		] ) {
			expect(
				wp( [
					'eval',
					`var_export( get_option( '${ id }', 'MISSING' ) );`,
				] ).trim()
			).toBe( "'MISSING'" );
		}

		// Ücretsiz akışın geri düştüğü gerçek anahtar için iddia "yok" değil
		// "dokunulmadı": Pro bir kez çalışmış sitede değer zaten yazılıdır ve
		// yokluğunu beklemek eklentiyi değil ortamın geçmişini sınardı.
		expect(
			readEligibleStatuses(),
			'Kilitli satır option değerini değiştirmemeli.'
		).toBe( before );
	} );

	test( 'Pro placement filtresi kilitli satırı değiştirebiliyor', async ( {
		page,
	} ) => {
		test.skip( proReturnsActive(), 'Kilitli satırların yerini gerçek ayarlar aldı.' );

		writeMuPlugin( SWAP_SLUG, SWAP_PHP );

		try {
			await page.goto( SETTINGS_URL );

			// The seam a future Pro relies on: it returns its own fields (or an
			// empty array) for a placement it implements, and the locked
			// preview goes away — here the reasons row is dropped while the
			// others stay. A regression on the splice filter would silently
			// block Pro from ever replacing these rows.
			await expect( page.locator( LOCKED_REASONS_ROW ) ).toHaveCount( 0 );
			await expect( page.locator( LOCKED_STATUSES_ROW ) ).toBeVisible();
		} finally {
			deleteMuPlugin( SWAP_SLUG );
		}
	} );

	test( 'satış bağlantısı yalnızca Pro çalışmazken çıkıyor', async ( {
		page,
	} ) => {
		test.skip( proReturnsActive(), 'Kilitli satırların yerini gerçek ayarlar aldı.' );

		await page.goto( SETTINGS_URL );

		const row = page.locator( LOCKED_STATUSES_ROW );

		await expect( row ).toBeVisible();

		// Rozetin kendisi de bağlantı: kilidi gören mağazacının ilk
		// tıklayacağı yer orası, ayrı bir düğmeyi aramak zorunda kalmasın.
		for ( const link of [
			row.locator( '.hez-locked__cta' ),
			row.locator( 'a.hez-locked-badge' ),
		] ) {
			await expect( link ).toBeVisible();
			await expect( link ).toHaveAttribute(
				'href',
				'https://intense.com.tr/hezarfen-pro'
			);
			await expect( link ).toHaveAttribute( 'target', '_blank' );

			// Dış bağlantı: mağazanın yönetim adresi karşı tarafa sızmasın,
			// ve her kuruluma basılan ticari bağlantı takip edilmesin.
			await expect( link ).toHaveAttribute( 'rel', /nofollow/ );
			await expect( link ).toHaveAttribute( 'rel', /noreferrer/ );
		}

		await expect(
			row.locator( '.hez-locked-badge .dashicons-lock' ),
			'Rozet kilidi göstermeli.'
		).toBeVisible();

		// Pro çalışırken kilitli kalan bir satır, Pro'nun henüz yapmadığı bir
		// yerleşim demek -- satılacak bir şey değil.
		writeMuPlugin( PRO_ACTIVE_SLUG, PRO_ACTIVE_PHP );

		try {
			await page.goto( SETTINGS_URL );

			await expect( page.locator( '.hez-locked__cta' ) ).toHaveCount( 0 );
			await expect( page.locator( 'a.hez-locked-badge' ) ).toHaveCount( 0 );
			await expect(
				page.locator( 'span.hez-locked-badge' ).first()
			).toBeVisible();
		} finally {
			deleteMuPlugin( PRO_ACTIVE_SLUG );
		}
	} );

	test( 'ayarlar kaydediliyor ve option değerleri güncelleniyor', async ( {
		page,
	} ) => {
		await page.goto( SETTINGS_URL );

		await page.locator( '#hezarfen_returns_window_days' ).fill( '21' );
		await page
			.locator( '#hezarfen_returns_window_reference' )
			.selectOption( 'paid' );
		await page
			.locator( '#hezarfen_returns_address_contact' )
			.fill( 'E2E Ayar Depo' );
		await page.locator( '#hezarfen_returns_auto_refund' ).check();
		await page
			.locator( '#hezarfen_returns_instructions' )
			.fill( 'Ürünü orijinal kutusunda gönderin.' );

		await page.locator( 'button[name="save"]' ).click();
		// WooCommerce's own "settings saved" notice, not whatever
		// promotional `.updated` banner the store happens to be showing.
		await expect( page.locator( '#message.updated.inline' ) ).toBeVisible();

		expect(
			wp( [ 'option', 'get', 'hezarfen_returns_window_days' ] ).trim()
		).toBe( '21' );
		expect(
			wp( [ 'option', 'get', 'hezarfen_returns_window_reference' ] ).trim()
		).toBe( 'paid' );
		expect(
			wp( [ 'option', 'get', 'hezarfen_returns_address_contact' ] ).trim()
		).toBe( 'E2E Ayar Depo' );
		expect(
			wp( [ 'option', 'get', 'hezarfen_returns_auto_refund' ] ).trim()
		).toBe( 'yes' );

		// The saved values survive a reload of the section.
		await page.goto( SETTINGS_URL );
		await expect(
			page.locator( '#hezarfen_returns_window_days' )
		).toHaveValue( '21' );
	} );

} );
