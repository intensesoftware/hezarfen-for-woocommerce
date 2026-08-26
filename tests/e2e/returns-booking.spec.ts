import { expect, test } from '@playwright/test';
import { deleteOrder } from './helpers/orders';
import { deleteMuPlugin, writeMuPlugin } from './helpers/mu-plugin';
import { NOTICE_SUCCESS } from './helpers/notices';
import {
	clearReturns,
	customerBookingError,
	enableReturns,
	getReturnShipment,
	getReturnStatus,
	loginAsReturnsCustomer,
	returnDetailUrl,
	seedReturn,
	seedReturnableOrder,
	setOption,
} from './helpers/returns';
import { wp } from './helpers/wp-cli';
import { snapshotOptions, restoreOptions } from './helpers/wp-options';

/**
 * Booking the return shipment is the customer's job, not the merchant's:
 * the courier collects the parcel from their door, so approval only
 * unlocks the picker and the customer chooses the day themselves.
 *
 * The carrier itself stays out of these specs. A fixture method registered
 * through `hezarfen_returns_shipping_methods` offers two fixed days and
 * hands back a made-up barcode, which is exactly the seam a real store's
 * own carrier contract would plug into — so what is exercised here is the
 * module's flow, not hepsiJET's uptime.
 */

const FIXTURE_SLUG = 'hezarfen-e2e-returns-booking-method';
const FIXTURE_OPTION = 'hezarfen_e2e_returns_booking';
const FIXTURE_KEY = 'e2e-booking';

const OFFERED_DAY = '2099-01-06';
const UNOFFERED_DAY = '2098-12-31';

const FIXTURE_PHP = `<?php
/**
 * E2E fixture: a return shipping method the customer books themselves.
 * Inert unless the gating option is set to 'yes'.
 */
add_action( 'hezarfen_returns_loaded', function () {
	if ( get_option( '${ FIXTURE_OPTION }' ) !== 'yes' ) {
		return;
	}

	if ( ! class_exists( 'Hezarfen_E2E_Booking_Return_Method' ) ) {
		class Hezarfen_E2E_Booking_Return_Method implements \\Hezarfen\\Inc\\Returns\\Shipping\\Return_Shipping_Method_Interface {
			const KEY = '${ FIXTURE_KEY }';

			public function get_key() {
				return self::KEY;
			}

			public function get_label() {
				return 'E2E randevulu iade kargosu';
			}

			public function get_description() {
				return 'E2E fixture: müşteri kargo alım gününü kendi seçer.';
			}

			public function is_available() {
				return true;
			}

			public function requires_customer_tracking() {
				return false;
			}

			public function requires_customer_booking() {
				return true;
			}

			public function get_booking_options( $request ) {
				return array(
					'2099-01-05' => '5 Ocak 2099',
					'${ OFFERED_DAY }' => '6 Ocak 2099',
				);
			}

			public function book( $request, $choice ) {
				$options = $this->get_booking_options( $request );

				if ( ! isset( $options[ $choice ] ) ) {
					return new \\WP_Error( 'e2e_slot_taken', 'Seçtiğiniz gün artık müsait değil.' );
				}

				$request->set_tracking_number( 'E2E-BARKOD-' . $request->get_id() );
				$request->set_courier( 'e2e-kargo' );
				$request->set_pickup_date( $choice );

				return true;
			}

			public function handle_approved( $request ) {
				return true;
			}

			public function get_customer_instructions( $request ) {
				return '<p>E2E: kargo alım gününü seçin.</p>';
			}
		}
	}

	add_filter( 'hezarfen_returns_shipping_methods', function ( $methods ) {
		$methods[] = new Hezarfen_E2E_Booking_Return_Method();

		return $methods;
	} );
} );
`;

const OPTION_KEYS = [
	'hezarfen_returns_enabled',
	'hezarfen_returns_window_days',
	'hezarfen_returns_window_reference',
	'hezarfen_returns_shipping_method',
];

let optionSnapshot: Record< string, string >;
const seededOrders: string[] = [];

function seedApprovedReturn(): { id: string; number: string } {
	const orderId = seedReturnableOrder();
	seededOrders.push( orderId );

	return seedReturn( { orderId, status: 'approved' } );
}

test.describe( 'Hezarfen iade — müşteri kargo randevusu', () => {
	test.beforeAll( () => {
		optionSnapshot = snapshotOptions( OPTION_KEYS );
		enableReturns();

		writeMuPlugin( FIXTURE_SLUG, FIXTURE_PHP );
		wp( [ 'option', 'update', FIXTURE_OPTION, 'yes' ] );

		// Requests store the method they were created with, so the store
		// has to be on the fixture method before anything is seeded.
		setOption( 'hezarfen_returns_shipping_method', FIXTURE_KEY );
	} );

	test.afterAll( () => {
		for ( const orderId of seededOrders ) {
			deleteOrder( orderId );
		}
		clearReturns();
		wp( [ 'option', 'delete', FIXTURE_OPTION ], { allowFailure: true } );
		deleteMuPlugin( FIXTURE_SLUG );
		restoreOptions( optionSnapshot );
	} );

	test.beforeEach( () => {
		clearReturns();
	} );

	test( 'onaylanan talepte müşteri günü seçip iade kargo kodunu alır', async ( {
		page,
	} ) => {
		const request = seedApprovedReturn();

		await loginAsReturnsCustomer( page );
		await page.goto( returnDetailUrl( request.id ) );

		const picker = page.locator( '#hez-pickup-date' );
		await expect( picker ).toBeVisible();

		await picker.selectOption( OFFERED_DAY );
		await page
			.locator( '.hez-inline-form--booking button[type="submit"]' )
			.click();

		await expect( page.locator( NOTICE_SUCCESS ) ).toBeVisible();

		// The barcode and the day are what the customer came for, so they
		// have to be on the page — not only in the database.
		await expect(
			page.locator( '.hez-tracking__value' ).first()
		).toContainText( `E2E-BARKOD-${ request.id }` );
		await expect( page.locator( '.hez-panel' ) ).toContainText( '2099' );

		// One booking per request: the picker is gone once it is made.
		await expect( page.locator( '#hez-pickup-date' ) ).toHaveCount( 0 );

		const shipment = getReturnShipment( request.id );
		expect( shipment.tracking ).toBe( `E2E-BARKOD-${ request.id }` );
		expect( shipment.pickup ).toBe( OFFERED_DAY );

		// The parcel is not on its way until the courier has it, so the
		// booking must not move the request along on its own.
		expect( getReturnStatus( request.id ) ).toBe( 'approved' );
	} );

	test( 'onaylanmamış talepte randevu formu çıkmaz ve POST reddedilir', async ( {
		page,
	} ) => {
		const orderId = seedReturnableOrder();
		seededOrders.push( orderId );
		const request = seedReturn( { orderId } );

		await loginAsReturnsCustomer( page );
		await page.goto( returnDetailUrl( request.id ) );

		await expect( page.locator( '#hez-pickup-date' ) ).toHaveCount( 0 );

		expect( customerBookingError( request.id, OFFERED_DAY ) ).toBe(
			'hezarfen_returns_not_bookable'
		);
	} );

	test( 'randevusu alınmış talebe ikinci randevu alınamaz', () => {
		const request = seedApprovedReturn();

		expect( customerBookingError( request.id, OFFERED_DAY ) ).toBe( '' );
		expect( customerBookingError( request.id, '2099-01-05' ) ).toBe(
			'hezarfen_returns_not_bookable'
		);

		// The first booking stands untouched.
		expect( getReturnShipment( request.id ).pickup ).toBe( OFFERED_DAY );
	} );

	test( 'sunulmayan bir gün kargo yöntemi tarafından reddedilir', () => {
		const request = seedApprovedReturn();

		expect( customerBookingError( request.id, UNOFFERED_DAY ) ).toBe(
			'e2e_slot_taken'
		);
		expect( getReturnShipment( request.id ).tracking ).toBe( '' );
	} );
} );
