import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth';
import {
	deleteOrder,
	seedShipmentTracking,
	seedTestOrder,
} from './helpers/orders';

/**
 * The MST package's admin surfaces (`Admin_Orders::add_shipment_column`
 * + `Admin_Orders::add_meta_box`) are wired against the HPOS screen
 * (`wc_get_page_screen_id( 'shop-order' )` →
 * `woocommerce_page_wc-orders`) when `WC_HEZARFEN_HPOS_ENABLED` is
 * true. Two HPOS-screen scenarios that no other spec pins, both
 * realistic regression vectors after a WooCommerce major:
 *
 *   1. **Multi-shipment column**: `render_shipment_column` switches
 *      from a courier logo to the literal "Shipment in pieces" string
 *      as soon as `Helper::get_all_shipment_data` returns 2+ rows.
 *      `admin-order-list-columns.spec.ts` only seeds one row, so the
 *      multi-shipment branch is untested.
 *   2. **Existing-shipments table inside the metabox**: when an order
 *      already has saved shipments, the metabox template renders a
 *      "Manual Tracking Numbers" table listing each courier + tracking
 *      number. `admin-mst-metabox.spec.ts` covers the *add* flow but
 *      not the *display* flow — i.e. it never opens an order with
 *      pre-existing shipments and asserts they're visible.
 */
let multiShipmentOrderId: string;

test.describe( 'Hezarfen kargo takip HPOS compatibility (admin)', () => {
	test.beforeAll( () => {
		// Two shipments on the same order so the "Shipment in pieces"
		// branch in `render_shipment_column` (count > 1) is exercised
		// AND the metabox's manual-tracking table renders 2 rows.
		multiShipmentOrderId = seedTestOrder( { status: 'processing' } );
		seedShipmentTracking( {
			orderId: multiShipmentOrderId,
			courierId: 'aras',
			trackingNum: 'TR-HPOS-AAA-1',
		} );
		seedShipmentTracking( {
			orderId: multiShipmentOrderId,
			courierId: 'yurtici',
			trackingNum: 'TR-HPOS-YYY-2',
		} );
	} );
	test.afterAll( () => {
		deleteOrder( multiShipmentOrderId );
	} );

	test.beforeEach( async ( { page } ) => {
		await loginAsAdmin( page );
	} );

	test( 'HPOS sipariş listesinde 2+ kargolu sipariş "Shipment in pieces" gösteriyor', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/admin.php?page=wc-orders' );
		await expect( page.locator( '.wp-list-table' ).first() ).toBeVisible();

		const row = page.locator( `tr#order-${ multiShipmentOrderId }` );
		await expect( row ).toBeVisible();

		const cell = row.locator( 'td.column-hezarfen_mst_shipment_info' );
		// Falls into the count > 1 branch — no courier logo, plain text.
		// Turkish translation in the bundled .mo flips the word order
		// to "Parçalı gönderi"; accept both readings so a future re-tr
		// pass doesn't flip the assertion.
		await expect( cell ).toContainText(
			/shipment in pieces|parçalı gönderi|gönderi parçalı/i
		);
		await expect( cell.locator( 'img.courier-logo' ) ).toHaveCount( 0 );
		// The info-outline pointer is still rendered alongside the
		// "in pieces" text so the customer-facing tooltip continues
		// to work — pin that wire too.
		await expect( cell.locator( '.shipment-info-icon' ) ).toBeAttached();
	} );

	test( 'HPOS sipariş edit metabox\'ı kayıtlı manual tracking satırlarını listeliyor', async ( {
		page,
	} ) => {
		await page.goto(
			`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ multiShipmentOrderId }`
		);
		await expect(
			page.locator( '#hezarfen-mst-order-edit-metabox' )
		).toBeVisible();

		// `add-to-tracking-list` is the "create new" button; the
		// existing-shipments table is its sibling, rendered only when
		// `Helper::get_all_shipment_data` returns rows. The body of
		// that table carries one <tr> per shipment with the tracking
		// number in the second cell.
		const trackingNumbers = page
			.locator( '#hezarfen-mst-order-edit-metabox table tbody tr td' )
			.filter( {
				hasText: /TR-HPOS-(AAA-1|YYY-2)/,
			} );
		await expect( trackingNumbers ).toHaveCount( 2 );

		// Spot-check both seeded values render — a regression that
		// dropped escaping or reordered the columns would silently
		// blank out the second row.
		await expect(
			page.locator( '#hezarfen-mst-order-edit-metabox' )
		).toContainText( 'TR-HPOS-AAA-1' );
		await expect(
			page.locator( '#hezarfen-mst-order-edit-metabox' )
		).toContainText( 'TR-HPOS-YYY-2' );
	} );
} );
