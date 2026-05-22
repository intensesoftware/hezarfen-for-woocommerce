import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth';
import { deleteOrder, seedTestOrder } from './helpers/orders';
import { wp } from './helpers/wp-cli';

/**
 * The plugin's deactivation wizard
 * ([class-deactivation-wizard.php](../../packages/manual-shipment-tracking/includes/class-deactivation-wizard.php))
 * hooks `admin_footer` on `plugins.php` and renders a modal that warns
 * the admin about orders currently in the custom `wc-hezarfen-shipped`
 * status (these become invisible to WC once the plugin is deactivated).
 *
 * The headline copy embeds the result of `get_shipped_orders_count()`
 * via `sprintf( __( 'Toplam %s adet …' ), number_format_i18n( … ) )`,
 * so two things have to remain true:
 *   1. The PHP query that backs the count finds orders by status
 *      regardless of whether HPOS or the legacy post-status storage is
 *      in effect.
 *   2. The rendered string actually substitutes the number into the
 *      `%s` placeholder (a misplaced `esc_html_e` would emit the
 *      untranslated source with a literal `%s` in it).
 *
 * Seed three orders directly into the active orders table, load the
 * plugins screen, and read the modal markup.
 */
const SHIPPED_COUNT = 3;
let shippedOrderIds: string[] = [];

test.describe( 'Hezarfen MST deaktivasyon sihirbazı — Kargoya Verildi sayımı', () => {
	test.beforeAll( () => {
		shippedOrderIds = [];
		for ( let i = 0; i < SHIPPED_COUNT; i++ ) {
			const orderId = seedTestOrder( { status: 'processing' } );
			// Set the order status to the Hezarfen-specific value at
			// the storage layer. `set_status()` would refuse a status
			// the test runtime hasn't registered yet, so we write the
			// row directly through OrderUtil's HPOS-aware path.
			wp( [
				'eval',
				`
					global $wpdb;
					if (
						class_exists( 'Automattic\\\\WooCommerce\\\\Utilities\\\\OrderUtil' ) &&
						\\Automattic\\WooCommerce\\Utilities\\OrderUtil::custom_orders_table_usage_is_enabled()
					) {
						$wpdb->update(
							$wpdb->prefix . 'wc_orders',
							array( 'status' => 'wc-hezarfen-shipped' ),
							array( 'id' => ${ orderId } )
						);
					} else {
						$wpdb->update(
							$wpdb->posts,
							array( 'post_status' => 'wc-hezarfen-shipped' ),
							array( 'ID' => ${ orderId } )
						);
					}
					echo 'OK';
				`,
			] );
			shippedOrderIds.push( orderId );
		}
	} );
	test.afterAll( () => {
		for ( const id of shippedOrderIds ) {
			deleteOrder( id );
		}
	} );

	test( 'plugins.php modal seed edilen kargolanmış sipariş sayısını gösteriyor', async ( {
		page,
	} ) => {
		await loginAsAdmin( page );
		await page.goto( '/wp-admin/plugins.php' );

		// Modal is rendered hidden until the admin clicks "Deactivate"
		// — `toBeAttached` keeps the assertion robust against the
		// `display: none` styling without forcing us to open it.
		const modal = page.locator( '#hez-pro-deactivation-modal' );
		await expect( modal ).toBeAttached();

		// The count line is wrapped in a <strong> inside
		// `.hez-pro-order-count`. We assert the inner text contains
		// the seeded count exactly, not just any digit — a stale
		// cached count or a botched query would slip past a loose
		// regex.
		const countLine = modal.locator( '.hez-pro-order-count strong' );
		await expect( countLine ).toBeAttached();

		const html = ( await countLine.innerHTML() ) ?? '';
		const text = ( await countLine.textContent() ) ?? '';

		// `%s` and `%1$s` must NOT survive the printf — if they do,
		// sprintf was skipped (a common regression when someone swaps
		// `sprintf` for `esc_html_e`).
		expect( html ).not.toContain( '%s' );
		expect( html ).not.toContain( '%1$s' );

		// `number_format_i18n` on the Turkish locale renders "3" the
		// same as on en_US for this magnitude, so the raw count is
		// what we assert against. The trailing wording check protects
		// against the placeholder appearing in isolation.
		expect( text ).toMatch(
			new RegExp( `Toplam\\s+${ SHIPPED_COUNT }\\s+adet`, 'i' )
		);
		expect( text ).toMatch( /Kargoya Verildi/i );
	} );
} );
