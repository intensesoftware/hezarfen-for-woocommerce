import { expect, test, type Page } from '@playwright/test';

const CITY = 'Ankara';
const CITY_PLATE = 'TR06';
const DISTRICT = 'Çankaya';
const NEIGHBORHOOD = '100.Yıl Mah';
const PRODUCT_SLUG = 'hezarfen-e2e-product';

/**
 * The Hezarfen plugin replaces the default WooCommerce city / address_1
 * inputs with select2 dropdowns:
 *   billing_state         -> il
 *   billing_city          -> ilçe (loaded via AJAX after il changes)
 *   billing_address_1     -> mahalle (loaded via AJAX after ilçe changes)
 * Each value is fed into a hidden <select> by mahalle-helper.js.
 *
 * We talk to the underlying <select> directly instead of clicking the
 * select2 chrome — that's both faster and more resilient across themes.
 * After each change we wait for the AJAX response so the next dropdown
 * is fully populated before we read it.
 */
async function pickFromSelect(
	page: Page,
	selector: string,
	value: string
): Promise< void > {
	await expect( page.locator( selector ) ).toBeAttached();
	await page.evaluate(
		( { sel, val } ) => {
			const el = document.querySelector< HTMLSelectElement >( sel );
			if ( ! el ) {
				throw new Error( `selector not found: ${ sel }` );
			}
			el.value = val;
			el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			// jQuery select2 listens on the jQuery event system.
			const $ = ( window as any ).jQuery;
			if ( $ ) {
				$( el ).trigger( 'change' );
			}
		},
		{ sel: selector, val: value }
	);
}

function expectMahalleAjax(
	page: Page,
	dataType: 'district' | 'neighborhood'
): Promise< unknown > {
	// Register the listener BEFORE the action that triggers the AJAX,
	// otherwise Playwright can race past a fast response.
	return page.waitForResponse(
		( res ) =>
			res.url().includes( 'get-mahalle-data.php' ) &&
			res.url().includes( `dataType=${ dataType }` ) &&
			res.status() === 200,
		{ timeout: 15_000 }
	);
}

async function waitForCheckoutUpdate( page: Page ): Promise< void > {
	await page.waitForResponse(
		( res ) =>
			res.url().includes( 'wc-ajax=update_order_review' ) &&
			res.status() === 200,
		{ timeout: 15_000 }
	);
}

/**
 * Wait until WooCommerce has stopped overlaying the checkout form with its
 * blockUI loading state. update_checkout AJAX rounds throw a `.blockUI`
 * div over the form; clicking through it is unreliable because Playwright
 * sees the overlay intercept pointer events.
 */
async function waitForCheckoutIdle( page: Page ): Promise< void > {
	await page.waitForFunction(
		() => {
			const form = document.querySelector< HTMLElement >(
				'form.checkout, form.woocommerce-checkout'
			);
			if ( ! form ) return true;
			if ( form.classList.contains( 'processing' ) ) return false;
			const blocks = form.querySelectorAll(
				'.blockUI, .blockOverlay'
			);
			return blocks.length === 0;
		},
		undefined,
		{ timeout: 20_000 }
	);
}

test.describe( 'Hezarfen TR checkout', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( `/product/${ PRODUCT_SLUG }/` );
		await page
			.locator(
				'button[name="add-to-cart"], .single_add_to_cart_button'
			)
			.first()
			.click();
		await expect(
			page.locator( '.woocommerce-message' ).first()
		).toContainText( /sepet|cart/i );
	} );

	test( 'il / ilçe / mahalle dropdowns populate Türkiye data', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );

		// Country may already be Türkiye thanks to woocommerce_default_country.
		await expect( page.locator( '#billing_country' ) ).toHaveValue( 'TR' );

		// il dropdown: must list Ankara as an option.
		const cities = await page.locator( '#billing_state option' ).allTextContents();
		expect( cities ).toContain( CITY );

		// Select Ankara, wait for ilçe AJAX to populate.
		const districtPromise = expectMahalleAjax( page, 'district' );
		await pickFromSelect( page, '#billing_state', CITY_PLATE );
		await districtPromise;

		const districts = await page
			.locator( '#billing_city option' )
			.allTextContents();
		expect( districts ).toContain( DISTRICT );

		// Select Çankaya, wait for mahalle AJAX to populate.
		const neighborhoodPromise = expectMahalleAjax( page, 'neighborhood' );
		await pickFromSelect( page, '#billing_city', DISTRICT );
		await neighborhoodPromise;

		const neighborhoods = await page
			.locator( '#billing_address_1 option' )
			.allTextContents();
		expect( neighborhoods ).toContain( NEIGHBORHOOD );
	} );

	test( 'places COD order with Ankara / Çankaya / 100.Yıl Mah', async ( {
		page,
	} ) => {
		await page.goto( '/checkout/' );
		await waitForCheckoutIdle( page );
		await expect( page.locator( '#billing_country' ) ).toHaveValue( 'TR' );

		// Drive the dropdowns first — they kick off AJAX + update_checkout
		// rounds, and we want to settle that before filling free-text fields.
		let pending = expectMahalleAjax( page, 'district' );
		await pickFromSelect( page, '#billing_state', CITY_PLATE );
		await pending;

		pending = expectMahalleAjax( page, 'neighborhood' );
		await pickFromSelect( page, '#billing_city', DISTRICT );
		await pending;

		await pickFromSelect( page, '#billing_address_1', NEIGHBORHOOD );

		await page.locator( '#billing_first_name' ).fill( 'Ada' );
		await page.locator( '#billing_last_name' ).fill( 'Lovelace' );
		await page.locator( '#billing_email' ).fill( 'ada@example.test' );
		await page.locator( '#billing_phone' ).fill( '5551112233' );

		const postcode = page.locator( '#billing_postcode' );
		if ( await postcode.isVisible() ) {
			await postcode.fill( '06520' );
		}

		// Hezarfen makes address_2 the actual street address field.
		await page.locator( '#billing_address_2' ).fill( 'Ada Sk. No:1 D:2' );

		// Let WooCommerce settle the order review (shipping + payment blocks)
		// before we click Place Order. blur out of address_2 to nudge it.
		await page.locator( '#billing_address_2' ).blur();
		await waitForCheckoutUpdate( page ).catch( () => {} );
		await waitForCheckoutIdle( page );

		// COD is the only enabled gateway, but we still explicitly select it
		// so the test fails loudly if the radio ever stops rendering.
		const cod = page.locator( '#payment_method_cod' );
		await expect( cod ).toBeAttached();
		await cod.check( { force: true } );
		await expect( cod ).toBeChecked();

		await waitForCheckoutIdle( page );
		await page.locator( '#place_order' ).click();

		await page.waitForURL( /order-received/, { timeout: 30_000 } );
		// "Thank you. Your order has been received." → tr: "Teşekkür ederiz. Siparişiniz alınmıştır."
		await expect( page.locator( 'body' ) ).toContainText(
			/(siparişiniz alın|order has been received)/i
		);
		// Order summary card lists order number / total / payment method.
		await expect( page.locator( 'ul.order_details, .woocommerce-order-overview' ) ).toBeVisible();
	} );
} );
