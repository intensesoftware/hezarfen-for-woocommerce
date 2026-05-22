import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth';
import { deleteMuPlugin, writeMuPlugin } from './helpers/mu-plugin';
import { deleteOrder, seedTestOrder } from './helpers/orders';
import { wp } from './helpers/wp-cli';
import {
	applyOptions,
	restoreOptions,
	snapshotOptions,
} from './helpers/wp-options';

/**
 * The shipment column in the admin orders list exposes
 * `hezarfen_shop_order_no_shipment_found_msg`
 * ([class-admin-orders.php](../../packages/manual-shipment-tracking/includes/admin/class-admin-orders.php))
 * so third-party plugins can replace the default "No shipment data
 * found" placeholder with rich HTML (an inline button, a link to a
 * helpdesk article, an em-dash, etc.).
 *
 * The render pipeline runs the filter output through `wp_kses_post`,
 * which means downstream consumers can rely on a few invariants:
 *   1. Safe inline tags like `<em>` and `<a href="…">` survive.
 *   2. Script-like attack vectors are stripped — the filter is not a
 *      bypass for the column-renderer's escape policy.
 *
 * If the rendering pipeline changes (e.g. switches back to a raw
 * `echo`, or drops the `wp_kses_post`), both invariants flip and we
 * either ship XSS or break every plugin that has been using this
 * filter for years.
 */
const MU_SLUG = 'hezarfen-e2e-no-shipment-msg';
const FLAG_OPTION = 'hezarfen_e2e_no_shipment_msg_active';
let blankOrderId: string;
let snapshot: Record< string, string >;

test.describe( 'Hezarfen no-shipment-msg filter HTML kontratı', () => {
	test.beforeAll( () => {
		writeMuPlugin(
			MU_SLUG,
			`<?php
defined( 'ABSPATH' ) || exit;

add_filter(
	'hezarfen_shop_order_no_shipment_found_msg',
	function ( $default, $order_id ) {
		if ( 'yes' !== get_option( 'hezarfen_e2e_no_shipment_msg_active' ) ) {
			return $default;
		}
		// Mix of:
		//   - a safe inline tag wp_kses_post must keep
		//   - a link wp_kses_post must keep with its href intact
		//   - a <script> tag wp_kses_post must strip
		return '<em class="hez-e2e-msg">Henüz kargoya verilmedi</em>'
			. ' <a class="hez-e2e-link" href="https://example.com/help">Yardım</a>'
			. '<script class="hez-e2e-xss">window._hezE2eXss = true;</script>';
	},
	10,
	2
);`
		);
		snapshot = snapshotOptions( [ FLAG_OPTION ] );
		applyOptions( { [ FLAG_OPTION ]: 'yes' } );

		blankOrderId = seedTestOrder( { status: 'on-hold' } );
	} );
	test.afterAll( () => {
		deleteOrder( blankOrderId );
		restoreOptions( snapshot );
		deleteMuPlugin( MU_SLUG );
	} );

	test( 'filtreden dönen güvenli HTML render ediliyor, <script> kırpılıyor', async ( {
		page,
	} ) => {
		// Probe whether the fixture mu-plugin is actually loaded in
		// the environment under test. On some wp-env CI configurations
		// runtime-written mu-plugins (via `writeMuPlugin`) don't reach
		// the HTTP request — most likely the cli container and the
		// web container don't share the same writable mu-plugins
		// volume layer. Skip cleanly instead of false-failing on
		// fixture-load issues that are orthogonal to the wp_kses_post
		// regression we're guarding.
		const filterPriority = wp( [
			'eval',
			"echo (int) has_filter( 'hezarfen_shop_order_no_shipment_found_msg' );",
		] ).trim();
		test.skip(
			filterPriority === '0',
			`Fixture mu-plugin ${ MU_SLUG } not active — has_filter('hezarfen_shop_order_no_shipment_found_msg') returned 0. Skipping wp_kses_post contract check.`
		);

		await loginAsAdmin( page );
		await page.goto( '/wp-admin/admin.php?page=wc-orders' );
		await expect( page.locator( '.wp-list-table' ).first() ).toBeVisible();

		const cell = page
			.locator( `tr#order-${ blankOrderId }` )
			.locator( 'td.column-hezarfen_mst_shipment_info' );
		await expect( cell ).toBeVisible();

		// Safe tags must round-trip — both the <em> wrapper and the
		// <a href> survive wp_kses_post.
		await expect( cell.locator( 'em.hez-e2e-msg' ) ).toHaveText(
			/Henüz kargoya verilmedi/
		);
		await expect( cell.locator( 'a.hez-e2e-link' ) ).toHaveAttribute(
			'href',
			'https://example.com/help'
		);

		// `<script>` is not on `wp_kses_post`'s allowed tag list. It
		// should be stripped entirely — neither the tag nor its
		// payload may make it into the rendered DOM.
		await expect( cell.locator( 'script.hez-e2e-xss' ) ).toHaveCount( 0 );

		// Belt-and-braces: if the renderer ever swapped back to a raw
		// `echo`, the `<script>` tag would not just be in the DOM, it
		// would actually execute and set the window flag.
		const xssRan = await page.evaluate(
			() => ( window as any )._hezE2eXss === true
		);
		expect( xssRan ).toBe( false );

		// And: the default "No shipment data found" placeholder must
		// NOT appear when a non-null filter return is in play — the
		// filter result is supposed to replace, not append to, the
		// default text.
		await expect( cell ).not.toContainText(
			/no shipment data found|gönderi verisi bulunamadı/i
		);
	} );
} );
