import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth';
import { deleteOrder, seedShipmentTracking, seedTestOrder } from './helpers/orders';
import { wp } from './helpers/wp-cli';
import {
	applyOptions,
	restoreOptions,
	snapshotOptions,
} from './helpers/wp-options';

/**
 * Hezarfen registers two custom columns on the WooCommerce orders list
 * (HPOS screen `/wp-admin/admin.php?page=wc-orders`):
 *   - "Invoice Type" via [OrderListColumns.php](../../includes/admin/order/OrderListColumns.php)
 *     gated on `hezarfen_show_hezarfen_checkout_tax_fields = yes`
 *   - "Shipment" via the MST package
 *     [class-admin-orders.php](../../packages/manual-shipment-tracking/includes/admin/class-admin-orders.php)
 *     always registered while the package is active
 *
 * Both columns rendered raw HTML in the past — that's a bug class
 * (broken markup, missing column when option toggled, fatals on the
 * list screen when an order has malformed meta) we want covered.
 */
const TAX_FIELDS_OPTIONS = {
	hezarfen_show_hezarfen_checkout_tax_fields: 'yes',
};

let snapshot: Record< string, string >;
let personOrderId: string;
let companyOrderId: string;
let blankOrderId: string;
let shippedOrderId: string;

test.describe( 'Hezarfen admin sipariş listesi kolonları (HPOS)', () => {
	test.beforeAll( () => {
		snapshot = snapshotOptions( Object.keys( TAX_FIELDS_OPTIONS ) );
		applyOptions( TAX_FIELDS_OPTIONS );

		personOrderId = seedTestOrder( { status: 'on-hold' } );
		setInvoiceType( personOrderId, 'person' );

		companyOrderId = seedTestOrder( { status: 'on-hold' } );
		setInvoiceType( companyOrderId, 'company' );

		blankOrderId = seedTestOrder( { status: 'on-hold' } );

		shippedOrderId = seedTestOrder( { status: 'processing' } );
		seedShipmentTracking( {
			orderId: shippedOrderId,
			courierId: 'aras',
			trackingNum: 'TR987654321',
		} );
	} );
	test.afterAll( () => {
		deleteOrder( personOrderId );
		deleteOrder( companyOrderId );
		deleteOrder( blankOrderId );
		deleteOrder( shippedOrderId );
		restoreOptions( snapshot );
	} );

	test.beforeEach( async ( { page } ) => {
		await loginAsAdmin( page );
	} );

	test( 'Invoice Type kolonu vergi alanları açıkken listede görünüyor', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/admin.php?page=wc-orders' );
		await expect( page.locator( '.wp-list-table' ).first() ).toBeVisible();

		// Column header registered with key `invoice_type` -> the list
		// table renders it with class `manage-column column-invoice_type`.
		await expect(
			page.locator( 'thead th.manage-column.column-invoice_type' )
		).toBeAttached();

		const personRow = page.locator( `tr#order-${ personOrderId }` );
		await expect( personRow ).toBeVisible();
		// Person rows render a 👤 badge (see render_invoice_type_column),
		// company rows a 🏢 badge, blank rows a literal em-dash.
		await expect(
			personRow.locator( 'td.column-invoice_type' )
		).toContainText( '👤' );

		const companyRow = page.locator( `tr#order-${ companyOrderId }` );
		await expect(
			companyRow.locator( 'td.column-invoice_type' )
		).toContainText( '🏢' );

		const blankRow = page.locator( `tr#order-${ blankOrderId }` );
		await expect(
			blankRow.locator( 'td.column-invoice_type' )
		).toContainText( '—' );
	} );

	test( 'Invoice Type kolonu vergi alanları kapalıyken listeye eklenmiyor', async ( {
		page,
	} ) => {
		// Flip off for this test only and restore inside the test so a
		// failure mid-test still leaves the suite-level beforeAll value.
		applyOptions( { hezarfen_show_hezarfen_checkout_tax_fields: 'no' } );
		try {
			await page.goto( '/wp-admin/admin.php?page=wc-orders' );
			await expect(
				page.locator( '.wp-list-table' ).first()
			).toBeVisible();
			await expect(
				page.locator( 'thead th.manage-column.column-invoice_type' )
			).toHaveCount( 0 );
		} finally {
			applyOptions( TAX_FIELDS_OPTIONS );
		}
	} );

	test( 'Shipment kolonu kargo bilgisi olan siparişte kurye gösteriyor', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/admin.php?page=wc-orders' );
		await expect( page.locator( '.wp-list-table' ).first() ).toBeVisible();

		await expect(
			page.locator(
				'thead th.manage-column.column-hezarfen_mst_shipment_info'
			)
		).toBeAttached();

		const shippedRow = page.locator( `tr#order-${ shippedOrderId }` );
		const shippedCell = shippedRow.locator(
			'td.column-hezarfen_mst_shipment_info'
		);
		// Aras kargo carries a logo, so we expect an <img> with class
		// `courier-logo` plus the dashicons info-outline pointer.
		await expect(
			shippedCell.locator( 'img.courier-logo' )
		).toBeVisible();
		await expect(
			shippedCell.locator( '.shipment-info-icon' )
		).toBeAttached();

		// And a non-shipped order falls into the "No shipment" branch.
		const blankRow = page.locator( `tr#order-${ blankOrderId }` );
		await expect(
			blankRow.locator( 'td.column-hezarfen_mst_shipment_info' )
		).toContainText( /no shipment|gönderi verisi bulunamadı/i );
	} );
} );

function setInvoiceType( orderId: string, invoiceType: 'person' | 'company' ): void {
	wp( [
		'eval',
		`
			$order = wc_get_order( ${ orderId } );
			if ( ! $order ) { echo 'ERR'; return; }
			$order->update_meta_data( '_billing_hez_invoice_type', '${ invoiceType }' );
			$order->save();
			echo 'OK';
		`,
	] );
}
