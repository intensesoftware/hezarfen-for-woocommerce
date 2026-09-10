import { expect, test } from '@playwright/test';
import { deleteOrder } from './helpers/orders';
import {
	clearReturns,
	enableReturns,
	RETURNS_CUSTOMER,
	seedReturn,
	seedReturnableOrder,
} from './helpers/returns';
import { restoreOptions, snapshotOptions } from './helpers/wp-options';
import { wp } from './helpers/wp-cli';

/**
 * The standard return notifications.
 *
 * Rather than asserting on rendered HTML in isolation, each spec triggers
 * the real WC_Email and intercepts the outgoing message at `pre_wp_mail`.
 * That covers the whole path a merchant depends on — registration,
 * recipient resolution, placeholder expansion and template lookup —
 * instead of only the parts a unit-style render would touch.
 */

const OPTION_KEYS = [
	'hezarfen_returns_enabled',
	'hezarfen_returns_window_days',
];

let optionSnapshot: Record< string, string >;
const seededOrders: string[] = [];

interface CapturedMail {
	to: string;
	subject: string;
	body: string;
}

/**
 * Trigger one notification for a request and capture what WordPress was
 * asked to send, without actually sending it.
 */
function triggerEmail( emailId: string, returnId: string ): CapturedMail {
	const out = wp( [
		'eval',
		`
			$request = \\Hezarfen\\Inc\\Returns\\Returns_Module::instance()->repository()->get( ${ returnId } );
			if ( ! $request ) { echo 'ERR:no-request'; return; }

			$captured = array();
			add_filter( 'pre_wp_mail', function ( $short_circuit, $atts ) use ( &$captured ) {
				$captured = $atts;
				return true;
			}, 10, 2 );

			// A merchant may have switched the notification off; the test is
			// about content, so force it on for this call only.
			add_filter( 'woocommerce_email_enabled_${ emailId }', '__return_true' );

			$found = null;
			foreach ( WC()->mailer()->get_emails() as $email ) {
				if ( '${ emailId }' === $email->id ) { $found = $email; break; }
			}
			if ( ! $found ) { echo 'ERR:email-not-registered'; return; }

			$found->trigger( $request );

			if ( ! $captured ) { echo 'ERR:nothing-sent'; return; }

			echo wp_json_encode( array(
				'to'      => is_array( $captured['to'] ) ? implode( ',', $captured['to'] ) : (string) $captured['to'],
				'subject' => (string) $captured['subject'],
				'body'    => (string) $captured['message'],
			) );
		`,
	] );

	const line = out
		.split( '\n' )
		.map( ( value ) => value.trim() )
		.filter( Boolean )
		.pop();

	if ( ! line || line.startsWith( 'ERR:' ) ) {
		throw new Error( `triggerEmail failed: ${ line }` );
	}

	return JSON.parse( line ) as CapturedMail;
}

function seedRequest( status?: string ): { id: string; number: string } {
	const orderId = seedReturnableOrder();
	seededOrders.push( orderId );

	return seedReturn( { orderId, reason: 'defective', status } );
}

test.describe( 'Hezarfen iade — standart e-postalar', () => {
	test.beforeAll( () => {
		optionSnapshot = snapshotOptions( OPTION_KEYS );
		enableReturns();
	} );

	test.afterAll( () => {
		for ( const orderId of seededOrders ) {
			deleteOrder( orderId );
		}
		clearReturns();
		restoreOptions( optionSnapshot );
	} );

	test( 'altı bildirim WooCommerce e-posta ayarlarına kaydediliyor', () => {
		const ids = wp( [
			'eval',
			`
				$ids = array();
				foreach ( WC()->mailer()->get_emails() as $email ) {
					if ( 0 === strpos( $email->id, 'hezarfen_return_' ) ) {
						$ids[] = $email->id;
					}
				}
				sort( $ids );
				echo implode( ',', $ids );
			`,
		] )
			.trim()
			.split( '\n' )
			.pop() as string;

		expect( ids.split( ',' ) ).toEqual( [
			'hezarfen_return_approved',
			'hezarfen_return_completed',
			'hezarfen_return_info_required',
			'hezarfen_return_received_admin',
			'hezarfen_return_received_customer',
			'hezarfen_return_rejected',
		] );
	} );

	test( 'talep alındı e-postası müşteriye gidiyor ve ürünleri listeliyor', () => {
		const seeded = seedRequest();
		const mail = triggerEmail( 'hezarfen_return_received_customer', seeded.id );

		expect( mail.to ).toBe( RETURNS_CUSTOMER.email );
		expect( mail.subject ).toContain( seeded.number );
		expect( mail.body ).toContain( 'Hezarfen E2E İade Ürünü' );
		expect( mail.body ).toContain( 'Ürün arızalı' );
	} );

	test( 'yeni talep e-postası mağaza yöneticisine gidiyor', () => {
		const seeded = seedRequest();
		const adminEmail = wp( [ 'option', 'get', 'admin_email' ] )
			.trim()
			.split( '\n' )
			.pop() as string;

		const mail = triggerEmail( 'hezarfen_return_received_admin', seeded.id );

		expect( mail.to ).toBe( adminEmail );
		expect( mail.subject ).toContain( seeded.number );
		// The admin link has to point at the moderation screen, not the shop.
		expect( mail.body ).toContain( 'page=hezarfen-returns' );
	} );

	test( 'onay e-postası müşteriye talebin yeni durumunu bildiriyor', () => {
		const seeded = seedRequest( 'approved' );
		const mail = triggerEmail( 'hezarfen_return_approved', seeded.id );

		expect( mail.to ).toBe( RETURNS_CUSTOMER.email );
		expect( mail.subject ).toContain( seeded.number );
		expect( mail.body ).toContain( 'Talebiniz onaylandı' );
	} );

	test( 'red e-postası müşteriyi kendi talep sayfasına bağlıyor', () => {
		const seeded = seedRequest( 'rejected' );
		const mail = triggerEmail( 'hezarfen_return_rejected', seeded.id );

		expect( mail.to ).toBe( RETURNS_CUSTOMER.email );
		expect( mail.subject ).toContain( seeded.number );
		// Account-only area: the link goes to the customer's own view.
		expect( mail.body ).toContain( `iadelerim/${ seeded.id }` );
	} );

	test( 'durum değişince e-posta otomatik tetikleniyor', () => {
		const seeded = seedRequest();

		// No explicit trigger here: only the service call, so this proves the
		// `hezarfen_return_status_changed` wiring, not just the mail class.
		const out = wp( [
			'eval',
			`
				$captured = array();
				add_filter( 'pre_wp_mail', function ( $short_circuit, $atts ) use ( &$captured ) {
					$captured[] = $atts['subject'];
					return true;
				}, 10, 2 );

				$module  = \\Hezarfen\\Inc\\Returns\\Returns_Module::instance();
				$request = $module->repository()->get( ${ seeded.id } );
				$module->service()->change_status( $request, 'approved' );

				echo wp_json_encode( $captured );
			`,
		] )
			.trim()
			.split( '\n' )
			.pop() as string;

		const subjects = JSON.parse( out ) as string[];
		expect( subjects.join( ' | ' ) ).toContain( 'onaylandı' );
	} );

	test( 'düz metin şablonu markup sızdırmıyor', () => {
		const seeded = seedRequest();

		const plain = wp( [
			'eval',
			`
				$request = \\Hezarfen\\Inc\\Returns\\Returns_Module::instance()->repository()->get( ${ seeded.id } );
				add_filter( 'woocommerce_email_enabled_hezarfen_return_received_customer', '__return_true' );
				add_filter( 'pre_wp_mail', '__return_true' );

				foreach ( WC()->mailer()->get_emails() as $email ) {
					if ( 'hezarfen_return_received_customer' !== $email->id ) { continue; }
					$email->trigger( $request );
					echo $email->get_content_plain();
					return;
				}
			`,
		] );

		expect( plain ).toContain( seeded.number );
		expect( plain ).toContain( 'Hezarfen E2E İade Ürünü' );
		expect( plain ).not.toContain( '<table' );
	} );
} );
