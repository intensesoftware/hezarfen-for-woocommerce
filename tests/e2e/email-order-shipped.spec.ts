import { expect, test } from '@playwright/test';
import { deleteOrder, seedShipmentTracking, seedTestOrder } from './helpers/orders';
import { wp } from './helpers/wp-cli';

/**
 * The MST package ships a "Order Shipped" customer email
 * ([class-email-order-shipped.php](../../packages/manual-shipment-tracking/includes/email/class-email-order-shipped.php)).
 * It hooks `hezarfen_mst_order_shipped`, which fires whenever
 * `Helper::new_order_shipment_data` is called — i.e. every time admin
 * saves a courier + tracking number.
 *
 * Two failure modes worth covering:
 *   1. The hook stops firing (refactor regression) so customers never
 *      get the "we shipped your order" email — the order note in
 *      [class-email.php](../../packages/manual-shipment-tracking/includes/email/class-email.php)
 *      `Email::send_email` is the cheap proof the trigger ran.
 *   2. The HTML body silently drops the tracking number / courier title
 *      because of a template change. We render `get_content_html()`
 *      directly and assert the bits that matter for the customer.
 */
const EMAIL_OPTION_KEY = 'woocommerce_hezarfen_mst_order_shipped_email_settings';

let orderId: string;
let originalSettings: string;

test.describe( 'Hezarfen "Kargoya verildi" e-postası', () => {
	test.beforeAll( () => {
		// Snapshot whatever the email settings were (JSON-encoded so an
		// array option round-trips cleanly) and force-enable for this
		// describe. The email is opt-in by default on a fresh install.
		originalSettings = wp(
			[ 'option', 'get', EMAIL_OPTION_KEY, '--format=json' ],
			{ allowFailure: true }
		).trim();
		wp( [
			'eval',
			`update_option( '${ EMAIL_OPTION_KEY }', array( 'enabled' => 'yes' ) );`,
		] );
	} );
	test.afterAll( () => {
		if ( originalSettings ) {
			wp(
				[
					'option',
					'update',
					EMAIL_OPTION_KEY,
					originalSettings,
					'--format=json',
				],
				{ allowFailure: true }
			);
		} else {
			wp( [ 'option', 'delete', EMAIL_OPTION_KEY ], {
				allowFailure: true,
			} );
		}
	} );

	test.beforeEach( () => {
		orderId = seedTestOrder( {
			status: 'processing',
			customerEmail: 'shipped-buyer@example.test',
		} );
	} );
	test.afterEach( () => {
		deleteOrder( orderId );
	} );

	test( 'kargo bilgisi kaydedilince order note "Tracking information sent" yazıyor', async () => {
		// Trigger the hook by saving shipment data the same way the
		// admin metabox AJAX endpoint does. Status transitions to
		// `wc-hezarfen-shipped`, `hezarfen_mst_order_shipped` fires,
		// `Email::send_email` runs, and on success it stamps an order
		// note with the recipient.
		seedShipmentTracking( {
			orderId,
			courierId: 'aras',
			trackingNum: 'AR-EMAIL-1',
		} );

		// `Email::send_email` writes a note with the literal recipient
		// email — find any note that mentions our buyer and assert the
		// "tracking" wording (English source or the Turkish translation
		// "kargo takip bilgileri").
		const note = wp( [
			'eval',
			`
				$args = array(
					'order_id' => ${ orderId },
					'limit' => 50,
					'orderby' => 'date_created_gmt',
					'order' => 'DESC',
				);
				$notes = wc_get_order_notes( $args );
				foreach ( $notes as $n ) {
					if ( strpos( $n->content, 'shipped-buyer@example.test' ) !== false ) {
						echo $n->content;
						return;
					}
				}
				echo 'NO_MATCHING_NOTE';
			`,
		] ).trim();
		expect( note ).not.toBe( 'NO_MATCHING_NOTE' );
		expect( note ).toContain( 'shipped-buyer@example.test' );
		expect( note ).toMatch(
			/(Tracking information sent|kargo takip bilgileri gönderil)/i
		);
	} );

	test( 'render edilen e-posta gövdesi takip no + kurye adı + tracking link içeriyor', async () => {
		seedShipmentTracking( {
			orderId,
			courierId: 'aras',
			trackingNum: 'AR-CONTENT-9',
		} );

		// Render the customer-facing HTML directly. We don't intercept
		// wp_mail because the `WC_Email` class lets us call
		// `get_content_html()` after assigning `->object`, which renders
		// the same template the live send would.
		const html = wp( [
			'eval',
			`
				$order = wc_get_order( ${ orderId } );
				$email = WC_Emails::instance()->get_emails()['Hezarfen_MST_Email_Order_Shipped'] ?? null;
				if ( ! $email ) { echo 'ERR_NO_EMAIL'; return; }
				$email->object = $order;
				$email->placeholders['{order_number}'] = $order->get_order_number();
				$email->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );
				echo $email->get_content_html();
			`,
		] );

		expect( html ).toContain( 'AR-CONTENT-9' );
		// `class-aras.php::get_title` returns "Aras Kargo" by default.
		expect( html ).toMatch( /aras/i );
		// And the tracking URL builder for Aras emits the kargotakip
		// host (see class-aras.php::create_tracking_url).
		expect( html ).toContain(
			'kargotakip.araskargo.com.tr/mainpage.aspx?code=AR-CONTENT-9'
		);
	} );
} );
