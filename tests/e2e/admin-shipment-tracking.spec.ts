import { expect, test } from '@playwright/test';
import { E2E_CUSTOMER } from './global-setup';
import { loginAsCustomer } from './helpers/auth';
import {
	deleteOrder,
	seedShipmentTracking,
	seedTestOrder,
} from './helpers/orders';
import { wp } from './helpers/wp-cli';

/**
 * Manual shipment tracking persists `Shipment_Data` against the order
 * (courier_id, courier_title, tracking_num, tracking_url) via
 * `Helper::new_order_shipment_data`. The customer's view-order page
 * surfaces it through `class-my-account.php::add_tracking_info_to_order_details`.
 *
 * The admin metabox UI is hepsijet-aware and quite involved; we
 * exercise the underlying Helper API (the same call the metabox AJAX
 * handler ends up making) and assert the customer-facing rendering,
 * which is what actually matters when something regresses.
 */
let orderId: string;
let customerId: string;

test.describe( 'Hezarfen manuel kargo takibi', () => {
	test.beforeAll( () => {
		customerId = wp( [
			'user',
			'get',
			E2E_CUSTOMER.username,
			'--field=ID',
		] ).trim();
		orderId = seedTestOrder( {
			status: 'processing',
			customerEmail: E2E_CUSTOMER.email,
			customerId,
		} );
	} );
	test.afterAll( () => {
		deleteOrder( orderId );
	} );

	test( 'admin shipment metabox sipariş düzenleme ekranında görünür', async ( {
		page,
	} ) => {
		// Login via customer login form would not work for admin; we
		// reuse the login helper for the e2e admin user defined in
		// global-setup.
		const { loginAsAdmin } = await import( './helpers/auth' );
		await loginAsAdmin( page );
		await page.goto(
			`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }`
		);
		await expect(
			page.locator( '#hez-order-shipments' )
		).toBeAttached();
		// The pro/lite tab toggle only renders when Hezarfen Pro is
		// active, so we skip asserting on the tab buttons. The manual
		// courier picker is always present and is the bit that
		// actually drives the regression check.
		await expect(
			page.locator( '#shipping-companies' ).first()
		).toBeAttached();
	} );

	test( 'kaydedilen tracking bilgisi müşterinin view-order sayfasında görünüyor', async ( {
		page,
	} ) => {
		seedShipmentTracking( {
			orderId,
			courierId: 'aras',
			trackingNum: 'TR123456789',
		} );

		await loginAsCustomer( page );
		await page.goto( `/my-account/view-order/${ orderId }/` );

		// "Aras Kargo" ile "TR123456789" sayfada görünmeli.
		await expect( page.locator( 'body' ) ).toContainText( /aras/i );
		await expect( page.locator( 'body' ) ).toContainText( 'TR123456789' );
		// Tracking link da olmalı.
		await expect(
			page.locator( 'a.tracking-url, a[href*="aras"]' ).first()
		).toBeVisible();
	} );
} );
