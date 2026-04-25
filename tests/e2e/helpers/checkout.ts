import { expect, type Page } from '@playwright/test';

/**
 * Set the value of a native <select> and tell jQuery / selectWoo that it
 * changed. We talk to the DOM directly rather than driving the select2
 * chrome — same result, no theme coupling.
 */
export async function pickFromSelect(
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
			const $ = ( window as any ).jQuery;
			if ( $ ) {
				$( el ).trigger( 'change' );
			}
		},
		{ sel: selector, val: value }
	);
}

/**
 * Hezarfen's mahalle/district AJAX endpoint. We register the listener
 * BEFORE the action that triggers the AJAX so a fast response can't
 * outrun us.
 */
export function expectMahalleAjax(
	page: Page,
	dataType: 'district' | 'neighborhood'
): Promise< unknown > {
	return page.waitForResponse(
		( res ) =>
			res.url().includes( 'get-mahalle-data.php' ) &&
			res.url().includes( `dataType=${ dataType }` ) &&
			res.status() === 200,
		{ timeout: 15_000 }
	);
}

export function expectCheckoutUpdate( page: Page ): Promise< unknown > {
	return page.waitForResponse(
		( res ) =>
			res.url().includes( 'wc-ajax=update_order_review' ) &&
			res.status() === 200,
		{ timeout: 15_000 }
	);
}

/**
 * Wait until WooCommerce stops overlaying the checkout form with its
 * blockUI loading state. Clicks land unreliably otherwise.
 */
export async function waitForCheckoutIdle( page: Page ): Promise< void > {
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

/**
 * Add the seeded e2e product to the cart so /checkout/ has a line item.
 * Used by every test that exercises the checkout form.
 */
export async function addE2EProductToCart(
	page: Page,
	productSlug = 'hezarfen-e2e-product'
): Promise< void > {
	await page.goto( `/product/${ productSlug }/` );
	await page
		.locator( 'button[name="add-to-cart"], .single_add_to_cart_button' )
		.first()
		.click();
	await expect(
		page.locator( '.woocommerce-message' ).first()
	).toContainText( /sepet|cart/i );
}

/**
 * Drive the il → ilçe → mahalle dropdown chain on whichever address
 * form we're given (works for both checkout and my-account, since
 * mahalle-helper.js binds the same way on both).
 */
export async function fillTrAddressChain(
	page: Page,
	{
		type,
		cityPlate,
		district,
		neighborhood,
	}: {
		type: 'billing' | 'shipping';
		cityPlate: string;
		district: string;
		neighborhood: string;
	}
): Promise< void > {
	const districtPromise = expectMahalleAjax( page, 'district' );
	await pickFromSelect( page, `#${ type }_state`, cityPlate );
	await districtPromise;

	const neighborhoodPromise = expectMahalleAjax( page, 'neighborhood' );
	await pickFromSelect( page, `#${ type }_city`, district );
	await neighborhoodPromise;

	await pickFromSelect( page, `#${ type }_address_1`, neighborhood );
}

export const TR_SAMPLE_ADDRESS = {
	cityPlate: 'TR06',
	city: 'Ankara',
	district: 'Çankaya',
	neighborhood: '100.Yıl Mah',
	street: 'Ada Sk. No:1 D:2',
	postcode: '06520',
};
