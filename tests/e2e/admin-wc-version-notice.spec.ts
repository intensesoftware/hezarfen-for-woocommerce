import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth';
import { deleteMuPlugin, writeMuPlugin } from './helpers/mu-plugin';
import {
	applyOptions,
	restoreOptions,
	snapshotOptions,
} from './helpers/wp-options';

/**
 * `hezarfen_wc_version_notice()` (in
 * [hezarfen-for-woocommerce.php](../../hezarfen-for-woocommerce.php))
 * is hooked on `admin_notices` whenever the active WooCommerce version
 * is below `WC_HEZARFEN_MIN_WC_VERSION`. The notice uses a translated
 * format string with three positional placeholders — plugin name,
 * minimum version, current version — and wraps the plugin name in a
 * `<strong>` tag so it reads as headline text.
 *
 * The pitfall here is that any sanitizer applied to the format string
 * (e.g., to make PHPCS happy) can strip the `<strong>` tag or eat the
 * placeholder tokens, leaving the admin with a half-rendered notice
 * like "<strong></strong> requires WooCommerce version  or higher."
 *
 * Drive the notice unconditionally via a tiny mu-plugin so we can
 * assert the rendered HTML without having to roll back WooCommerce to
 * an old version on the test site.
 */
const MU_SLUG = 'hezarfen-e2e-force-wc-version-notice';
const FLAG_OPTION = 'hezarfen_e2e_force_wc_version_notice';
let snapshot: Record< string, string >;

test.describe( 'Hezarfen WC sürüm uyarı bandı render bütünlüğü', () => {
	test.beforeAll( () => {
		writeMuPlugin(
			MU_SLUG,
			`<?php
defined( 'ABSPATH' ) || exit;

if ( 'yes' !== get_option( 'hezarfen_e2e_force_wc_version_notice' ) ) {
	return;
}

add_action(
	'admin_notices',
	function () {
		if ( function_exists( 'hezarfen_wc_version_notice' ) ) {
			hezarfen_wc_version_notice();
		}
	},
	1
);`
		);
		snapshot = snapshotOptions( [ FLAG_OPTION ] );
		applyOptions( { [ FLAG_OPTION ]: 'yes' } );
	} );
	test.afterAll( () => {
		restoreOptions( snapshot );
		deleteMuPlugin( MU_SLUG );
	} );

	test( 'uyarı placeholder\'ları gerçek sürüm değerleriyle ve <strong> ile render ediliyor', async ( {
		page,
	} ) => {
		await loginAsAdmin( page );
		await page.goto( '/wp-admin/' );

		const notice = page
			.locator( '.notice.notice-error' )
			.filter( { hasText: /requires WooCommerce version/i } );
		await expect( notice ).toBeVisible();

		// `<strong>Hezarfen</strong>` is the headline wrapper around
		// the plugin name. If wp_kses_post drops `<strong>` from the
		// allowed-tags list the headline collapses into the body text.
		await expect( notice.locator( 'strong' ) ).toHaveText( 'Hezarfen' );

		const text = ( await notice.textContent() ) ?? '';

		// Three positional placeholders must each resolve to a real
		// value. A `printf` regression that loses `%2$s` or `%3$s`
		// would leave them as literal "%s" tokens in the output.
		expect( text ).not.toContain( '%s' );
		expect( text ).not.toContain( '%1$s' );
		expect( text ).not.toContain( '%2$s' );
		expect( text ).not.toContain( '%3$s' );

		// `WC_HEZARFEN_MIN_WC_VERSION` follows semver (e.g. 6.9.0).
		// We don't pin the literal value — that drifts as we bump the
		// floor — but we do assert that the rendered body contains a
		// version-looking number in both the "requires …" and
		// "running …" positions.
		expect( text ).toMatch(
			/requires WooCommerce version\s+\d+\.\d+(?:\.\d+)?/i
		);
		expect( text ).toMatch(
			/running version\s+\d+\.\d+(?:\.\d+)?/i
		);
		expect( text ).toMatch( /Please update WooCommerce/i );
	} );
} );
