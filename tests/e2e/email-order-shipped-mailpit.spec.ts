import { expect, test } from '@playwright/test';
import {
	clearMailpit,
	getMailpitMessage,
	waitForMailpitMessage,
} from './helpers/mailpit';
import {
	deleteOrder,
	seedShipmentTracking,
	seedTestOrder,
} from './helpers/orders';
import { wp } from './helpers/wp-cli';

/**
 * The cheap-and-cheerful `email-order-shipped.spec.ts` runs everywhere
 * (LocalWP + wp-env CI) and asserts on the order note + a direct
 * `get_content_html()` render. What it can't assert on is the
 * **actual SMTP envelope** — subject, From header, and BCC/CC
 * recipients that come from
 * [class-email-order-shipped.php::get_headers](../../packages/manual-shipment-tracking/includes/email/class-email-order-shipped.php).
 *
 * This spec opts into Mailpit when `HEZARFEN_E2E_MAILPIT_API` is set
 * (CI only — the workflow connects an `axllent/mailpit` container to
 * wp-env's docker network). On a developer's LocalWP it's skipped.
 */
const HAS_MAILPIT = !! process.env.HEZARFEN_E2E_MAILPIT_API;

const EMAIL_OPTION_KEY = 'woocommerce_hezarfen_mst_order_shipped_email_settings';

let originalSettings: string;
let orderId: string;

test.describe( 'Hezarfen Kargoya verildi e-postası — Mailpit (gerçek SMTP)', () => {
	test.skip(
		! HAS_MAILPIT,
		'requires HEZARFEN_E2E_MAILPIT_API (CI workflow only)'
	);

	test.beforeAll( () => {
		originalSettings = wp(
			[ 'option', 'get', EMAIL_OPTION_KEY, '--format=json' ],
			{ allowFailure: true }
		).trim();
		// Enable + add BCC/CC so we can assert the recipient envelope
		// is what `Email_Order_Shipped::get_headers` builds.
		wp( [
			'eval',
			`
				update_option( '${ EMAIL_OPTION_KEY }', array(
					'enabled'       => 'yes',
					'recipient_bcc' => 'archive@example.test',
					'recipient_cc'  => 'team@example.test',
				) );
			`,
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

	test.beforeEach( async () => {
		orderId = seedTestOrder( {
			status: 'processing',
			customerEmail: 'mailpit-buyer@example.test',
		} );
		// Each test starts from an empty Mailpit inbox so
		// `waitForMailpitMessage` can't match a leftover from another
		// test's run.
		await clearMailpit();
	} );
	test.afterEach( () => {
		deleteOrder( orderId );
	} );

	test( 'Mailpit gönderiyi yakalıyor — To + Cc + Bcc + subject ve gövde tam', async () => {
		seedShipmentTracking( {
			orderId,
			courierId: 'aras',
			trackingNum: 'AR-MAILPIT-1',
		} );

		const summary = await waitForMailpitMessage( ( m ) =>
			m.To.some( ( t ) => t.Address === 'mailpit-buyer@example.test' )
		);

		// Envelope-level assertions: these only show up when we go
		// through real SMTP (the cheap render-only spec can't see them).
		expect( summary.To.map( ( t ) => t.Address ) ).toContain(
			'mailpit-buyer@example.test'
		);
		expect( summary.Cc?.map( ( c ) => c.Address ) ?? [] ).toContain(
			'team@example.test'
		);
		expect( summary.Bcc?.map( ( b ) => b.Address ) ?? [] ).toContain(
			'archive@example.test'
		);
		// Subject defaults to "Your Order Has Been Shipped" — accept the
		// English source or the Turkish translation Hezarfen ships.
		expect( summary.Subject ).toMatch(
			/(Your Order Has Been Shipped|Siparişiniz Kargoya|Kargoya Verildi)/i
		);

		// Body-level: tracking number + Aras URL appear in the HTML.
		const full = await getMailpitMessage( summary.ID );
		expect( full.HTML ).toContain( 'AR-MAILPIT-1' );
		expect( full.HTML ).toContain(
			'kargotakip.araskargo.com.tr/mainpage.aspx?code=AR-MAILPIT-1'
		);
		// And the From header was set by the mu-plugin to a stable
		// test sender, not whatever wp_mail's default would be.
		expect( full.From.Address ).toBe( 'wordpress@hezarfen-e2e.test' );
	} );

	test( 'email kapalıysa Mailpit\'e hiçbir şey düşmez', async () => {
		// Disable, trigger, assert nothing arrives. This catches a
		// silent regression where `Email_Order_Shipped::is_enabled()`
		// stops gating the send.
		wp( [
			'eval',
			`update_option( '${ EMAIL_OPTION_KEY }', array( 'enabled' => 'no' ) );`,
		] );
		try {
			seedShipmentTracking( {
				orderId,
				courierId: 'aras',
				trackingNum: 'AR-MAILPIT-DISABLED',
			} );

			// Give the (hypothetical) mailer a moment, then assert empty.
			await new Promise( ( r ) => setTimeout( r, 1500 ) );
			const { listMailpitMessages } = await import( './helpers/mailpit' );
			const seen = await listMailpitMessages();
			expect(
				seen.filter( ( m ) =>
					m.To.some(
						( t ) => t.Address === 'mailpit-buyer@example.test'
					)
				)
			).toHaveLength( 0 );
		} finally {
			// Re-enable so the next test's beforeAll-scoped settings are
			// the ones that win.
			wp( [
				'eval',
				`
					update_option( '${ EMAIL_OPTION_KEY }', array(
						'enabled'       => 'yes',
						'recipient_bcc' => 'archive@example.test',
						'recipient_cc'  => 'team@example.test',
					) );
				`,
			] );
		}
	} );
} );
