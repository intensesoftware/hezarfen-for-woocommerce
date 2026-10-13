<?php
/**
 * Contains the Carrier_Sync listener.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Shipping;

use Hezarfen\Inc\Returns\Core\Return_Status;

defined( 'ABSPATH' ) || exit();

/**
 * Keeps a return request in step with the carrier shipment it booked.
 *
 * A carrier booking exists twice: as the tracking number on the return
 * request, and as the shipment stored on the order. The order screen can
 * cancel that shipment without knowing a return is attached to it, and a
 * request that keeps showing a barcode and a pickup day for a shipment
 * nobody will collect is worse than one with no booking at all — the
 * customer waits at home for a courier that was called off.
 *
 * So the cancellation is listened for and the request is freed, which also
 * puts the pickup day picker back in front of the customer.
 */
class Carrier_Sync {

	/**
	 * Whether the module is itself mid-cancellation.
	 *
	 * @var bool
	 */
	private static $suspended = false;

	/**
	 * Module container.
	 *
	 * @var \Hezarfen\Inc\Returns\Returns_Module
	 */
	private $module;

	/**
	 * Constructor.
	 *
	 * @param \Hezarfen\Inc\Returns\Returns_Module $module Module container.
	 */
	public function __construct( $module ) {
		$this->module = $module;

		add_action( 'hezarfen_hepsijet_shipment_cancelled', array( $this, 'on_shipment_cancelled' ), 10, 2 );
	}

	/**
	 * Runs a cancellation the module is driving itself.
	 *
	 * The module's own cancel path marks the same shipment cancelled, which
	 * fires the very action this class listens for. Without this the request
	 * would be freed twice and the timeline would carry the same event under
	 * two different names.
	 *
	 * @param callable $callback What to run.
	 *
	 * @return mixed Whatever the callback returned.
	 */
	public static function without_sync( $callback ) {
		self::$suspended = true;

		try {
			return $callback();
		} finally {
			self::$suspended = false;
		}
	}

	/**
	 * Frees the request that booked a shipment cancelled elsewhere.
	 *
	 * @param int    $order_id    Order the shipment belongs to.
	 * @param string $delivery_no Carrier delivery number.
	 *
	 * @return void
	 */
	public function on_shipment_cancelled( $order_id, $delivery_no ) {
		if ( self::$suspended ) {
			return;
		}

		$delivery_no = trim( (string) $delivery_no );

		if ( '' === $delivery_no ) {
			return;
		}

		// Only a request still waiting for its pickup has a booking to lose.
		// Once the courier has been and the request moved on, that tracking
		// number is history: clearing it there would erase the record of a
		// parcel that did arrive, and tell a customer whose return is closed
		// to go and pick another pickup day.
		$requests = $this->module->repository()->query(
			array(
				'order_id'        => (int) $order_id,
				'tracking_number' => $delivery_no,
				'status'          => Return_Status::APPROVED,
				'limit'           => 5,
			)
		);

		foreach ( $requests as $request ) {
			$this->module->service()->release_booking(
				$request,
				__( 'Kargo randevusu mağaza tarafından iptal edildi. Dilerseniz yeni bir alım günü seçebilirsiniz.', 'hezarfen-for-woocommerce' )
			);
		}
	}
}
