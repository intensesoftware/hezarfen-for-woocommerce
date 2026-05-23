import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth';

/**
 * Hezarfen registers a top-level admin menu (`MENU_SLUG = 'hezarfen'`)
 * whose first submenu item links to the WooCommerce settings tab at
 * `admin.php?page=wc-settings&tab=hezarfen`. WooCommerce normally
 * paints the "WooCommerce" top-level menu as the active one on any
 * `page=wc-settings` URL, which would visually unanchor an admin who
 * navigated into Hezarfen via its own menu.
 *
 * To compensate, `Admin_Menu::highlight_menu`
 * ([class-admin-menu.php](../../includes/admin/class-admin-menu.php))
 * filters `parent_file` to return `'hezarfen'` whenever the request is
 * `wc-settings&tab=hezarfen`. WP core then paints the Hezarfen
 * top-level menu as active (CSS class `wp-has-current-submenu`) and
 * leaves the WooCommerce one unhighlighted.
 *
 * Two specific things have to keep working:
 *   - The Hezarfen menu item is marked active.
 *   - The WooCommerce menu item is NOT marked active.
 */
test.describe( 'Hezarfen settings sekmesi açıkken admin menü highlight', () => {
	test( 'wc-settings&tab=hezarfen URL\'inde Hezarfen top-level menüsü vurgulanıyor', async ( {
		page,
	} ) => {
		await loginAsAdmin( page );
		await page.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=hezarfen'
		);

		const hezTopLevel = page.locator( '#toplevel_page_hezarfen' );
		await expect( hezTopLevel ).toBeVisible();

		// WP core adds `wp-has-current-submenu` to the top-level <li>
		// whose menu_slug matches `$parent_file`. The companion class
		// `wp-not-current-submenu` is removed in that same path —
		// asserting both ensures we caught a real highlight flip, not
		// just a permissive class list.
		const hezClasses = ( await hezTopLevel.getAttribute( 'class' ) ) || '';
		expect( hezClasses ).toContain( 'wp-has-current-submenu' );
		expect( hezClasses ).not.toContain( 'wp-not-current-submenu' );

		// And the WooCommerce top-level menu must NOT pick up the
		// active class. If it does the user sees two highlighted
		// parents at the same time, which means the `parent_file`
		// filter didn't run (or returned the wrong slug).
		const wooTopLevel = page.locator( '#toplevel_page_woocommerce' );
		if ( await wooTopLevel.count() ) {
			const wooClasses =
				( await wooTopLevel.getAttribute( 'class' ) ) || '';
			expect( wooClasses ).not.toContain( 'wp-has-current-submenu' );
		}
	} );

	test( 'WooCommerce settings\'in başka bir tab\'ında Hezarfen menüsü vurgulanmıyor', async ( {
		page,
	} ) => {
		await loginAsAdmin( page );
		await page.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=general'
		);

		// Negative control: the highlight override must only fire when
		// the URL is the Hezarfen tab. On any other `wc-settings`
		// landing page the WooCommerce menu owns the highlight.
		const hezTopLevel = page.locator( '#toplevel_page_hezarfen' );
		if ( await hezTopLevel.count() ) {
			const hezClasses =
				( await hezTopLevel.getAttribute( 'class' ) ) || '';
			expect( hezClasses ).not.toContain( 'wp-has-current-submenu' );
		}

		const wooTopLevel = page.locator( '#toplevel_page_woocommerce' );
		await expect( wooTopLevel ).toBeVisible();
		const wooClasses = ( await wooTopLevel.getAttribute( 'class' ) ) || '';
		expect( wooClasses ).toContain( 'wp-has-current-submenu' );
	} );
} );
