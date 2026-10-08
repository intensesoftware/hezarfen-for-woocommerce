import { expect, test } from '@playwright/test';
import { deleteOrder } from './helpers/orders';
import {
	clearReturns,
	enableReturns,
	getReturnStatus,
	holdForReview,
	seedReturnableOrder,
	useAutoApproval,
} from './helpers/returns';
import { restoreOptions, snapshotOptions } from './helpers/wp-options';
import { wp } from './helpers/wp-cli';

/**
 * Talep onayı: varsayılan otomatik onay ve yönetici onayı.
 *
 * Talep servis üzerinden oluşturulup giden e-postalar `pre_wp_mail`'de
 * yakalanıyor; böylece hem durum hem de müşteriye/mağazaya giden bildirim
 * seti tek çağrıda sınanıyor.
 */

const OPTION_KEYS = [
	'hezarfen_returns_enabled',
	'hezarfen_returns_window_days',
];

let optionSnapshot: Record< string, string >;
const seededOrders: string[] = [];

interface CreatedReturn {
	id: string;
	status: string;
	subjects: string[];
	events: string[];
}

function createCapturingMail(): CreatedReturn {
	const orderId = seedReturnableOrder();
	seededOrders.push( orderId );

	const out = wp( [
		'eval',
		`
			$subjects = array();
			add_filter( 'pre_wp_mail', function ( $short_circuit, $atts ) use ( &$subjects ) {
				$subjects[] = (string) $atts['subject'];
				return true;
			}, 10, 2 );
			add_filter( 'woocommerce_email_enabled_hezarfen_return_received_customer', '__return_true' );
			add_filter( 'woocommerce_email_enabled_hezarfen_return_received_admin', '__return_true' );
			add_filter( 'woocommerce_email_enabled_hezarfen_return_approved', '__return_true' );

			$module  = \\Hezarfen\\Inc\\Returns\\Returns_Module::instance();
			$order   = wc_get_order( ${ orderId } );
			$lines   = $module->eligibility()->get_returnable_lines( $order );
			$request = $module->service()->create( $order, array(
				'lines' => array( key( $lines ) => array(
					'quantity' => 1,
					'reason'   => 'defective',
					'note'     => 'E2E açıklaması',
				) ),
			) );
			if ( is_wp_error( $request ) ) { echo 'ERR:' . $request->get_error_message(); return; }

			$events = array();
			foreach ( ( new \\Hezarfen\\Inc\\Returns\\Core\\Return_Event_Repository() )->get_for_return( $request->get_id() ) as $event ) {
				$events[] = $event->get_type() . ':' . $event->get_actor_type() . ':' . $event->get_to_status();
			}

			echo wp_json_encode( array(
				'id'       => (string) $request->get_id(),
				'status'   => $request->get_status(),
				'subjects' => $subjects,
				'events'   => $events,
			) );
		`,
	] );

	const line = out
		.split( '\n' )
		.map( ( value ) => value.trim() )
		.filter( Boolean )
		.pop();

	if ( ! line || line.startsWith( 'ERR:' ) ) {
		throw new Error( `createCapturingMail failed: ${ line }` );
	}

	return JSON.parse( line ) as CreatedReturn;
}

test.describe( 'Hezarfen iade — talep onayı', () => {
	test.beforeAll( () => {
		optionSnapshot = snapshotOptions( OPTION_KEYS );
		enableReturns();
	} );

	test.afterAll( () => {
		holdForReview();
		for ( const orderId of seededOrders ) {
			deleteOrder( orderId );
		}
		clearReturns();
		restoreOptions( optionSnapshot );
	} );

	test( 'varsayılan: talep oluşturulduğu anda sistem tarafından onaylanıyor', () => {
		useAutoApproval();

		const created = createCapturingMail();

		expect( created.status ).toBe( 'approved' );
		expect( getReturnStatus( created.id ) ).toBe( 'approved' );
		expect( created.events ).toContain( 'status-change:system:approved' );

		// Müşteri "alındı" ve "onaylandı" e-postalarını aynı saniyede almıyor;
		// yalnızca onay gidiyor. Mağaza her durumda haberdar ediliyor.
		expect( created.subjects.some( ( s ) => s.includes( 'onaylandı' ) ) ).toBe( true );
		expect( created.subjects.some( ( s ) => s.includes( 'alındı' ) ) ).toBe( false );
		expect( created.subjects.some( ( s ) => s.includes( 'Yeni iade talebi' ) ) ).toBe( true );
	} );

	test( 'yönetici onayında talep beklemede kalıyor', () => {
		holdForReview();

		const created = createCapturingMail();

		expect( created.status ).toBe( 'pending' );
		expect( created.events ).not.toContain( 'status-change:system:approved' );
		expect( created.subjects.some( ( s ) => s.includes( 'alındı' ) ) ).toBe( true );
		expect( created.subjects.some( ( s ) => s.includes( 'onaylandı' ) ) ).toBe( false );
	} );

	test( 'Pro kapısı kapalıyken kayıtlı yönetici onayı yok sayılıyor', () => {
		useAutoApproval();

		const mode = wp( [
			'eval',
			`
				update_option( 'hezarfen_returns_approval_mode', 'manual' );
				add_filter( 'hezarfen_returns_manual_approval_enabled', '__return_false', 99 );
				$auto = \\Hezarfen\\Inc\\Returns\\Core\\Return_Settings::approves_automatically();
				delete_option( 'hezarfen_returns_approval_mode' );
				echo $auto ? 'auto' : 'manual';
			`,
		] )
			.trim()
			.split( '\n' )
			.pop();

		expect( mode ).toBe( 'auto' );
	} );
} );
