import { expect, type Page, type Locator } from '@playwright/test';
import { wp } from './wp-cli';

/**
 * Helpers for driving the WooCommerce block (Gutenberg) checkout, which is a
 * separate beast from the classic shortcode checkout the rest of the suite
 * exercises. The block checkout renders Hezarfen's fields through a React
 * integration + Store API extension rather than the `woocommerce_checkout_fields`
 * filter, so it needs its own page state and its own DOM drivers.
 */

/**
 * The classic-shortcode block the suite's global-setup pins the checkout page
 * to. We restore exactly this so the block spec leaves the page the way every
 * other (classic) spec expects to find it.
 */
const CLASSIC_CHECKOUT_CONTENT =
	'<!-- wp:woocommerce/classic-shortcode {"shortcode":"checkout","className":"wc-block-checkout"} /-->';

/**
 * Swap the /checkout/ page over to the WooCommerce block checkout. We reuse
 * WooCommerce's own canonical block markup (the same content `WC_Install`
 * writes for a fresh install) via reflection, so the page always carries every
 * inner block — including the contact-information / shipping-address /
 * billing-address blocks Hezarfen injects its fields into.
 */
export function setCheckoutToBlock(): void {
	const result = wp( [
		'eval',
		`
			$id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'checkout' ) : 0;
			if ( $id <= 0 || ! class_exists( 'WC_Install' ) ) { echo 'ERR_NO_PAGE'; return; }
			$method = new ReflectionMethod( 'WC_Install', 'get_checkout_block_content' );
			$method->setAccessible( true );
			$content = $method->invoke( null );
			wp_update_post( array( 'ID' => $id, 'post_content' => $content ) );
			echo 'OK';
		`,
	] ).trim();

	if ( result !== 'OK' ) {
		throw new Error( `setCheckoutToBlock failed: ${ result }` );
	}
}

/**
 * Restore the /checkout/ page to the classic shortcode block so the rest of
 * the (classic) suite is unaffected.
 */
export function restoreCheckoutToClassic(): void {
	const id = wp( [ 'option', 'get', 'woocommerce_checkout_page_id' ] ).trim();
	if ( ! id ) return;
	wp( [
		'post',
		'update',
		id,
		`--post_content=${ CLASSIC_CHECKOUT_CONTENT }`,
	] );
}

/**
 * Wait until the block checkout has mounted and is no longer in its loading
 * skeleton state. The block boots asynchronously, so clicks/fills before this
 * resolves land on nothing.
 */
export async function waitForBlockCheckoutReady( page: Page ): Promise< void > {
	await expect(
		page.locator( '.wc-block-checkout, .wp-block-woocommerce-checkout' ).first()
	).toBeVisible( { timeout: 30_000 } );
	await page.waitForFunction(
		() => {
			const root = document.querySelector( '.wp-block-woocommerce-checkout' );
			return !! root && ! root.classList.contains( 'is-loading' );
		},
		undefined,
		{ timeout: 30_000 }
	);
}

/**
 * The visible Hezarfen address-field group. With the block checkout's default
 * "use same address for billing" toggle, only one address form (shipping)
 * renders, so a single visible group is what we drive.
 */
export function hezAddressGroup( page: Page ): Locator {
	return page.locator( '.hezarfen-checkout-fields--address' ).first();
}

/**
 * Pick an option from one of Hezarfen's searchable comboboxes (il / ilçe /
 * mahalle). `fieldClass` is the stable per-level class
 * (e.g. `wc-block-components-address-form__hez-province`). When `query` is
 * given we type to filter first; `optionText` selects the row to click.
 */
export async function pickCombobox(
	page: Page,
	fieldClass: string,
	{ query, optionText }: { query?: string; optionText: string | RegExp }
): Promise< void > {
	const input = page
		.locator( `.${ fieldClass } .hezarfen-combobox__input` )
		.first();
	await input.scrollIntoViewIfNeeded();
	await input.click();
	if ( query ) {
		await input.fill( query );
	}
	const option = page
		.locator( `.${ fieldClass } .hezarfen-combobox__option`, {
			hasText: optionText,
		} )
		.first();
	await option.click();
}

/**
 * Pick the first available option from a combobox — used for the mahalle level
 * where the exact neighborhood list comes from the REST endpoint and we don't
 * want to couple the test to a specific name.
 */
export async function pickComboboxFirstOption(
	page: Page,
	fieldClass: string
): Promise< string > {
	const input = page
		.locator( `.${ fieldClass } .hezarfen-combobox__input` )
		.first();
	await input.scrollIntoViewIfNeeded();
	await input.click();
	const firstOption = page
		.locator(
			`.${ fieldClass } .hezarfen-combobox__option`
		)
		.first();
	await expect( firstOption ).toBeVisible( { timeout: 10_000 } );
	const text = ( await firstOption.textContent() )?.trim() || '';
	await firstOption.click();
	return text;
}

/**
 * Fill a block checkout field, trying the shipping- and billing-prefixed ids in
 * turn (which form is present depends on the "use same address" toggle).
 */
export async function fillBlockField(
	page: Page,
	baseId: string,
	value: string
): Promise< void > {
	for ( const id of [ `shipping-${ baseId }`, `billing-${ baseId }`, baseId ] ) {
		const loc = page.locator( `#${ id }` );
		if ( ( await loc.count() ) > 0 && ( await loc.isVisible() ) ) {
			await loc.fill( value );
			return;
		}
	}
}

/**
 * Read the Hezarfen-relevant meta off the most recently created order, so a
 * placement test can assert the Store API persisted everything under the same
 * keys the classic checkout uses.
 */
export function getLatestOrderHezData(): {
	id: string;
	invoice_type: string;
	tc_number: string;
	tc_decrypted: string;
	tax_number: string;
	tax_office: string;
	city: string;
	address_1: string;
} {
	const json = wp( [
		'eval',
		`
			$orders = wc_get_orders( array( 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC' ) );
			if ( empty( $orders ) ) { echo '{}'; return; }
			$o = $orders[0];
			$tc_raw = (string) $o->get_meta( '_billing_hez_TC_number' );
			$tc_dec = '';
			if ( $tc_raw ) {
				$enc = new \\Hezarfen\\Inc\\Data\\PostMetaEncryption();
				$tc_dec = $enc->health_check() ? (string) $enc->decrypt( $tc_raw ) : $tc_raw;
			}
			echo wp_json_encode( array(
				'id'           => (string) $o->get_id(),
				'invoice_type' => (string) $o->get_meta( '_billing_hez_invoice_type' ),
				'tc_number'    => $tc_raw,
				'tc_decrypted' => $tc_dec,
				'tax_number'   => (string) $o->get_meta( '_billing_hez_tax_number' ),
				'tax_office'   => (string) $o->get_meta( '_billing_hez_tax_office' ),
				'city'         => (string) $o->get_billing_city(),
				'address_1'    => (string) $o->get_billing_address_1(),
			) );
		`,
	] ).trim();

	return JSON.parse( json || '{}' );
}

/**
 * Wait until the block checkout has finished recalculating (shipping rates /
 * totals) so the place-order button is actually interactive. While a Store API
 * update is in flight WooCommerce sets `pointer-events: none` (and/or disables)
 * the button, so a click would silently no-op.
 */
export async function waitForBlockCheckoutIdle( page: Page ): Promise< void > {
	await page.waitForFunction(
		() => {
			const btn = document.querySelector< HTMLButtonElement >(
				'.wc-block-components-checkout-place-order-button'
			);
			if ( ! btn ) return false;
			if ( btn.disabled ) return false;
			if ( getComputedStyle( btn ).pointerEvents === 'none' ) return false;
			const spinners = document.querySelectorAll(
				'.wc-block-checkout .wc-block-components-spinner'
			);
			return spinners.length === 0;
		},
		undefined,
		{ timeout: 20_000 }
	);
}

/**
 * Submit the block checkout. The place-order button briefly toggles
 * `pointer-events: none` whenever a Store API cart/extension update is in
 * flight (every address/tax keystroke schedules one), so a single click can
 * silently no-op if it lands mid-recalc. We wait for idle, click, and confirm
 * a checkout POST actually fired — retrying if it didn't.
 */
export async function placeBlockOrder( page: Page ): Promise< void > {
	const button = page.locator(
		'.wc-block-components-checkout-place-order-button'
	);

	for ( let attempt = 0; attempt < 5; attempt++ ) {
		await waitForBlockCheckoutIdle( page );

		const checkoutPost = page
			.waitForResponse(
				( res ) =>
					/\/wc\/store\/v1\/checkout/.test( res.url() ) &&
					res.request().method() === 'POST',
				{ timeout: 6_000 }
			)
			.catch( () => null );

		await button.click();

		if ( await checkoutPost ) {
			return;
		}
	}

	throw new Error(
		'placeBlockOrder: checkout POST never fired after 5 attempts'
	);
}

/**
 * Click the place-order button exactly once (after waiting for idle) without
 * expecting a successful submission — used by negative-path tests where the
 * client blocks the order, so no checkout POST is fired.
 */
export async function clickPlaceOrderOnce( page: Page ): Promise< void > {
	await waitForBlockCheckoutIdle( page );
	await page
		.locator( '.wc-block-components-checkout-place-order-button' )
		.click();
}

/** Per-level class on Hezarfen's il / ilçe / mahalle comboboxes. */
export const HEZ_PROVINCE_CLASS =
	'wc-block-components-address-form__hez-province';
export const HEZ_DISTRICT_CLASS =
	'wc-block-components-address-form__hez-district';
export const HEZ_NEIGHBORHOOD_CLASS =
	'wc-block-components-address-form__hez-neighborhood';

/**
 * Fill the visible block address form with a valid TR address (contact +
 * il→ilçe→mahalle cascade + açık adres + posta kodu). Leaves the invoice-type
 * specific fields to the caller so person/company paths can vary them.
 */
export async function fillTrBlockAddress( page: Page ): Promise< void > {
	await fillBlockField( page, 'email', 'block-buyer@example.test' );
	await fillBlockField( page, 'first_name', 'Ada' );
	await fillBlockField( page, 'last_name', 'Lovelace' );
	await fillBlockField( page, 'phone', '5551112233' );

	await pickCombobox( page, HEZ_PROVINCE_CLASS, {
		query: 'İstanbul',
		optionText: /İstanbul/,
	} );

	// Selecting the district triggers the mahalle REST fetch — register the
	// listener before the click so a fast response can't outrun us.
	const neighborhoodResponse = page
		.waitForResponse(
			( res ) =>
				res.url().includes( '/hezarfen/v1/neighborhoods' ) &&
				res.status() === 200,
			{ timeout: 15_000 }
		)
		.catch( () => null );
	await pickCombobox( page, HEZ_DISTRICT_CLASS, {
		query: 'Kadıköy',
		optionText: /Kadıköy/,
	} );
	await neighborhoodResponse;
	await pickComboboxFirstOption( page, HEZ_NEIGHBORHOOD_CLASS );

	await fillBlockField( page, 'address_2', 'Ada Sk. No:1 D:2' );
	await fillBlockField( page, 'postcode', '34000' );
}

/**
 * Change the checkout country through the WooCommerce country field UI, so the
 * address form actually re-renders for the new locale (which is what drives
 * Hezarfen's TR-only AddressFields to mount/unmount).
 *
 * @param page        Playwright page.
 * @param countryName The visible country label, e.g. "Germany".
 */
export async function setCheckoutCountry(
	page: Page,
	countryName: string
): Promise< void > {
	const field = page
		.locator( '#shipping-country, #billing-country' )
		.first();
	await field.scrollIntoViewIfNeeded();

	const tag = await field.evaluate( ( el ) => el.tagName.toLowerCase() );

	if ( 'select' === tag ) {
		await field.selectOption( { label: countryName } );
		return;
	}

	// Combobox variant: type to filter, then pick the matching option.
	await field.click();
	await field.fill( countryName );
	await page
		.locator( '[role="option"]', { hasText: countryName } )
		.first()
		.click();
}

