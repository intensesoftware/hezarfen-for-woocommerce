<?php
/**
 * Contains the Return_Service application service.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

use Hezarfen\Inc\Returns\Shipping\Return_Shipping_Registry;

defined( 'ABSPATH' ) || exit();

/**
 * The only place return requests are created and moved between statuses.
 *
 * Controllers (My Account, guest page, admin screen) parse and sanitise
 * input, then hand it here; this class owns the rules and the timeline,
 * and fires the actions e-mails hook onto.
 */
class Return_Service {

	/**
	 * Request store.
	 *
	 * @var Return_Repository_Interface
	 */
	private $repository;

	/**
	 * Timeline store.
	 *
	 * @var Return_Event_Repository
	 */
	private $events;

	/**
	 * Eligibility calculator.
	 *
	 * @var Return_Eligibility
	 */
	private $eligibility;

	/**
	 * Reason registry.
	 *
	 * @var Return_Reasons
	 */
	private $reasons;

	/**
	 * Shipping method registry.
	 *
	 * @var Return_Shipping_Registry
	 */
	private $shipping;

	/**
	 * Constructor.
	 *
	 * @param Return_Repository_Interface $repository  Request store.
	 * @param Return_Event_Repository     $events      Timeline store.
	 * @param Return_Eligibility          $eligibility Eligibility calculator.
	 * @param Return_Reasons              $reasons     Reason registry.
	 * @param Return_Shipping_Registry    $shipping    Shipping registry.
	 */
	public function __construct( $repository, $events, $eligibility, $reasons, $shipping ) {
		$this->repository  = $repository;
		$this->events      = $events;
		$this->eligibility = $eligibility;
		$this->reasons     = $reasons;
		$this->shipping    = $shipping;
	}

	/**
	 * Creates a request for an order.
	 *
	 * @param \WC_Order            $order Order being returned from.
	 * @param array<string, mixed> $input Sanitised input: `lines` keyed by
	 *                                    order item ID with `quantity`,
	 *                                    `reason` and `note`, an optional
	 *                                    `customer_note`, and the
	 *                                    `pickup_address` parts when the
	 *                                    active method collects the parcel.
	 *
	 * @return Return_Request|\WP_Error
	 */
	public function create( $order, $input ) {
		$eligible = $this->eligibility->check_order( $order );

		if ( is_wp_error( $eligible ) ) {
			return $eligible;
		}

		$lines = isset( $input['lines'] ) && is_array( $input['lines'] ) ? $input['lines'] : array();
		$items = $this->build_items( $order, $lines );

		if ( is_wp_error( $items ) ) {
			return $items;
		}

		$method         = $this->shipping->get_active_method();
		$pickup_address = array();

		if ( $method->requires_pickup_address() ) {
			$pickup_address = isset( $input['pickup_address'] ) ? Return_Pickup_Address::normalize( $input['pickup_address'] ) : array();
			$valid          = Return_Pickup_Address::validate( $pickup_address );

			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
		}

		$request = new Return_Request(
			array(
				'order_id'          => $order->get_id(),
				'customer_id'       => (int) $order->get_customer_id(),
				'customer_email'    => $order->get_billing_email(),
				'status'            => Return_Status::PENDING,
				'shipping_method'   => $method->get_key(),
				'return_address_id' => 'default',
				'pickup_address'    => $pickup_address,
				'customer_note'     => isset( $input['customer_note'] ) ? (string) $input['customer_note'] : '',
				'currency'          => $order->get_currency(),
			)
		);

		$request->set_items( $items );
		$request->set_refund_amount( $this->calculate_refund_amount( $items ) );
		$request->set_return_number( $this->generate_return_number( $order ) );

		$saved = $this->repository->save( $request );

		if ( is_wp_error( $saved ) ) {
			// Two submits racing on the same order build the same reference,
			// and the unique index lets exactly one of them through. The
			// other is a duplicate of a request that now exists, not a
			// failure the customer should retry.
			if ( 'hezarfen_returns_duplicate_number' === $saved->get_error_code() ) {
				return new \WP_Error(
					'hezarfen_returns_duplicate_request',
					__( 'Bu sipariş için iade talebiniz az önce oluşturuldu. Talebi hesabınızdaki sipariş detayından görebilirsiniz.', 'hezarfen-for-woocommerce' )
				);
			}

			return $saved;
		}

		$this->log(
			$request,
			Return_Event::TYPE_CREATED,
			__( 'İade talebi oluşturuldu.', 'hezarfen-for-woocommerce' ),
			array( 'to_status' => Return_Status::PENDING )
		);

		/**
		 * Fires after a return request has been created.
		 *
		 * @param Return_Request $request The stored request.
		 * @param \WC_Order      $order   Parent order.
		 */
		do_action( 'hezarfen_return_created', $request, $order );

		return $request;
	}

	/**
	 * Moves a request to another status.
	 *
	 * @param Return_Request       $request    Request to move.
	 * @param string               $new_status Target status.
	 * @param array<string, mixed> $context    Optional `message` and `actor`.
	 *
	 * @return true|\WP_Error
	 */
	public function change_status( $request, $new_status, $context = array() ) {
		$old_status = $request->get_status();
		$allowed    = $this->check_transition( $old_status, $new_status );

		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		$request->set_status( $new_status );

		$shipping_error = null;
		$booking_error  = null;

		if ( Return_Status::APPROVED === $new_status ) {
			$result = $this->shipping->get_for_request( $request )->handle_approved( $request );

			if ( is_wp_error( $result ) ) {
				$shipping_error = $result;

				// The approval stands, but the promise the failed method
				// made to the customer ("we will collect the parcel") no
				// longer holds. Handing the request to the manual method
				// gives them a return address and a tracking form instead
				// of a pickup that is never coming.
				$request->set_shipping_method( $this->shipping->get_fallback_method()->get_key() );
			}
		} elseif (
			in_array( $new_status, Return_Status::get_releasing_statuses(), true )
			&& $this->holds_live_carrier_booking( $request, $old_status )
		) {
			// A pickup the carrier still holds must be called off before the
			// request is closed. Rejecting or cancelling it here otherwise
			// leaves a courier scheduled to collect a parcel for a return that
			// no longer exists, while the freed units are handed straight back
			// for a fresh request. Best effort: if the carrier cannot be
			// reached the closure still stands, but the merchant is told the
			// pickup may still be live so they can call it off by hand. This
			// also clears the tracking number, so the change is saved below.
			$released = $this->shipping->get_for_request( $request )->cancel_booking( $request );

			if ( is_wp_error( $released ) ) {
				$booking_error = $released;
			}
		}

		$saved = $this->repository->save( $request );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$message = isset( $context['message'] ) ? (string) $context['message'] : '';

		$this->log(
			$request,
			Return_Event::TYPE_STATUS_CHANGE,
			$message,
			array(
				'from_status' => $old_status,
				'to_status'   => $new_status,
				'actor'       => isset( $context['actor'] ) ? $context['actor'] : null,
			)
		);

		if ( $shipping_error ) {
			// Approval still stands; the request just moved to the manual
			// method, so the reason and the consequence are recorded
			// together — the merchant reads one line and knows the customer
			// will now be shown a return address instead of a pickup.
			// Customer visible on purpose: the customer filled in a pickup
			// address expecting a courier, and now sees the "ship it yourself"
			// screen instead. Saying why on the timeline — and that they can
			// send it themselves and enter a tracking number — keeps the switch
			// from looking like the request broke.
			$this->log(
				$request,
				Return_Event::TYPE_SHIPPING,
				sprintf(
					/* translators: %s: reason the carrier declined the pickup. */
					__( '%s Ürünü kendiniz gönderip kargo takip numarasını girebilirsiniz.', 'hezarfen-for-woocommerce' ),
					$shipping_error->get_error_message()
				),
				array(
					'actor'               => $this->system_actor(),
					'is_customer_visible' => true,
				)
			);
		}

		if ( $booking_error ) {
			// The status change was saved, but the carrier still believes a
			// pickup is due. Spelling that out on the timeline is the only way
			// the merchant learns a courier may arrive for a return they just
			// closed, and that they must cancel it at the carrier by hand.
			$this->log(
				$request,
				Return_Event::TYPE_SHIPPING,
				sprintf(
					/* translators: %s: reason the carrier pickup could not be cancelled. */
					__( 'Kargo alım randevusu otomatik iptal edilemedi: %s Kurye yine de gelebilir; randevuyu kargo firması üzerinden manuel iptal edin.', 'hezarfen-for-woocommerce' ),
					$booking_error->get_error_message()
				),
				array(
					'actor'               => $this->system_actor(),
					'is_customer_visible' => false,
				)
			);
		}

		/**
		 * Fires after a return request changed status.
		 *
		 * @param Return_Request $request    The request.
		 * @param string         $old_status Previous status.
		 * @param string         $new_status New status.
		 */
		do_action( 'hezarfen_return_status_changed', $request, $old_status, $new_status );

		/**
		 * Fires after a return request reached a specific status.
		 *
		 * @param Return_Request $request    The request.
		 * @param string         $old_status Previous status.
		 */
		do_action( 'hezarfen_return_status_' . $new_status, $request, $old_status );

		return true;
	}

	/**
	 * Whether the request still holds a carrier pickup that no courier has
	 * collected yet — the only case where closing the request must also call
	 * the appointment off.
	 *
	 * A booking only lives while the request is still approved: once the
	 * courier collects it the request moves on to shipped/received, and its
	 * tracking number then records a pickup that already happened rather than
	 * one still to come. Manual "customer ships" tracking is the customer's
	 * own number, with nothing to cancel at a carrier — so the method is asked
	 * whether it books the pickup itself before we try to unbook it.
	 *
	 * @param Return_Request $request    The request.
	 * @param string         $old_status Status the request is leaving.
	 *
	 * @return bool
	 */
	private function holds_live_carrier_booking( $request, $old_status ) {
		if ( Return_Status::APPROVED !== $old_status ) {
			return false;
		}

		if ( '' === $request->get_tracking_number() ) {
			return false;
		}

		return $this->shipping->get_for_request( $request )->requires_customer_booking();
	}

	/**
	 * Moves a request whose carrier could not produce a label onto the manual
	 * method, and tells the customer they can ship it themselves.
	 *
	 * Returned as a WP_Error on purpose: the caller (the form handler) shows the
	 * message and re-renders the detail page, which — now that the method is the
	 * manual one — presents the return address and the tracking form instead of
	 * a day picker the carrier keeps refusing.
	 *
	 * @param Return_Request $request The approved request.
	 * @param \WP_Error      $error   Why the carrier declined.
	 *
	 * @return \WP_Error
	 */
	private function fall_back_to_manual( $request, $error ) {
		$request->set_shipping_method( $this->shipping->get_fallback_method()->get_key() );

		$saved = $this->repository->save( $request );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$message = sprintf(
			/* translators: %s: reason the carrier could not create the label. */
			__( '%s Ürünü kendiniz gönderip kargo takip numarasını girebilirsiniz.', 'hezarfen-for-woocommerce' ),
			$error->get_error_message()
		);

		$this->log(
			$request,
			Return_Event::TYPE_SHIPPING,
			$message,
			array(
				'actor'               => $this->system_actor(),
				'is_customer_visible' => true,
			)
		);

		return new \WP_Error( 'hezarfen_returns_switched_to_manual', $message );
	}

	/**
	 * Marks a request completed and, when asked, records the refund.
	 *
	 * The two are separate steps on purpose. Completion is the merchant
	 * saying the return is settled; the refund is a WooCommerce record of
	 * money leaving the order, and a store that already refunded by hand
	 * must not have it recorded twice. So a failed refund never rolls the
	 * completion back — it is reported instead, and the merchant refunds
	 * from the order screen.
	 *
	 * COMPLETED is terminal, so a first attempt whose refund failed used to
	 * strand the request: re-running this action hit the transition guard and
	 * never reached the refund step again. Now the completion is skipped when
	 * the request is already closed, so the merchant can simply re-run it to
	 * record the refund once the cause is resolved. The whole thing runs under
	 * a per-request lock so a double-click cannot write two refunds.
	 *
	 * @param Return_Request       $request Request to complete.
	 * @param array<string, mixed> $context Optional `message`, `actor` and
	 *                                      `refund` (bool, defaults to the
	 *                                      store setting).
	 *
	 * @return true|\WP_Error
	 */
	public function complete( $request, $context = array() ) {
		return $this->with_request_lock(
			$request,
			function () use ( $request, $context ) {
				// Re-read under the lock: a completion racing with this one may
				// already have closed the request and written its refund, and
				// the in-memory copy would still show neither.
				$fresh = $request->get_id() ? $this->repository->get( $request->get_id() ) : $request;

				if ( ! $fresh ) {
					return new \WP_Error(
						'hezarfen_returns_not_found',
						__( 'İade talebi bulunamadı.', 'hezarfen-for-woocommerce' )
					);
				}

				return $this->do_complete( $fresh, $context );
			}
		);
	}

	/**
	 * The body of complete(), run against a freshly loaded request while the
	 * per-request lock is held.
	 *
	 * @param Return_Request       $request Fresh request loaded under lock.
	 * @param array<string, mixed> $context Optional `message`, `actor`, `refund`.
	 *
	 * @return true|\WP_Error
	 */
	private function do_complete( $request, $context ) {
		// A request already closed on an earlier attempt is not transitioned
		// again — COMPLETED has no outgoing transitions — but its refund can
		// still be recorded below.
		if ( Return_Status::COMPLETED !== $request->get_status() ) {
			$changed = $this->change_status( $request, Return_Status::COMPLETED, $context );

			if ( is_wp_error( $changed ) ) {
				return $changed;
			}
		}

		$refund = array_key_exists( 'refund', $context )
			? (bool) $context['refund']
			: Return_Settings::auto_refund_enabled();

		if ( ! $refund ) {
			return true;
		}

		$created = ( new Return_Refunds() )->create_for_request( $request, Return_Settings::restock_enabled() );

		if ( is_wp_error( $created ) ) {
			$this->log(
				$request,
				Return_Event::TYPE_NOTE,
				sprintf(
					/* translators: %s: reason the refund could not be recorded. */
					__( 'WooCommerce iade kaydı oluşturulamadı: %s', 'hezarfen-for-woocommerce' ),
					$created->get_error_message()
				),
				array(
					'actor'               => $this->system_actor(),
					'is_customer_visible' => false,
				)
			);

			// The goods came back and the request is closed; only the
			// bookkeeping is missing. Saying so plainly, and leaving the
			// request completed, lets the merchant fix the cause and re-run
			// the action to record the refund without re-opening anything.
			return new \WP_Error(
				$created->get_error_code(),
				sprintf(
					/* translators: %s: why the refund could not be recorded. */
					__( 'Talep tamamlandı, ancak WooCommerce iade kaydı oluşturulamadı: %s', 'hezarfen-for-woocommerce' ),
					$created->get_error_message()
				)
			);
		}

		$request->set_refund_id( $created->get_id() );

		$saved = $this->repository->save( $request );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$this->log(
			$request,
			Return_Event::TYPE_NOTE,
			sprintf(
				/* translators: %s: refunded amount, formatted. */
				__( 'Siparişe %s tutarında WooCommerce iade kaydı işlendi. Para transferini kendiniz yapmanız gerekir.', 'hezarfen-for-woocommerce' ),
				hezarfen_returns_plain_price( $created->get_amount(), $request->get_currency() )
			),
			array(
				'actor'               => isset( $context['actor'] ) ? $context['actor'] : null,
				'is_customer_visible' => false,
			)
		);

		/**
		 * Fires after a completed return was recorded as a WooCommerce refund.
		 *
		 * @param Return_Request   $request The request.
		 * @param \WC_Order_Refund $created The refund that was written.
		 */
		do_action( 'hezarfen_return_refund_created', $request, $created );

		return true;
	}

	/**
	 * Asks the customer for more information and parks the request.
	 *
	 * @param Return_Request       $request Request.
	 * @param string               $message What the merchant needs to know.
	 * @param array<string, mixed> $actor   Actor descriptor.
	 *
	 * @return true|\WP_Error
	 */
	public function request_info( $request, $message, $actor = null ) {
		$message = trim( (string) $message );

		if ( '' === $message ) {
			return new \WP_Error(
				'hezarfen_returns_empty_info_request',
				__( 'Müşteriden ne istediğinizi yazmalısınız.', 'hezarfen-for-woocommerce' )
			);
		}

		// Checked up front: the question below is customer visible, and an
		// invalid transition would leave them staring at something they can
		// never answer.
		$allowed = $this->check_transition( $request->get_status(), Return_Status::INFO_REQUIRED );

		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		$this->log(
			$request,
			Return_Event::TYPE_INFO_REQUEST,
			$message,
			array( 'actor' => $actor )
		);

		return $this->change_status( $request, Return_Status::INFO_REQUIRED, array( 'actor' => $actor ) );
	}

	/**
	 * Records the customer's answer and puts the request back in the queue.
	 *
	 * @param Return_Request $request Request.
	 * @param string         $message Customer's answer.
	 *
	 * @return true|\WP_Error
	 */
	public function respond_info( $request, $message ) {
		$message = trim( (string) $message );

		if ( '' === $message ) {
			return new \WP_Error(
				'hezarfen_returns_empty_info_response',
				__( 'Lütfen istenen bilgiyi yazın.', 'hezarfen-for-woocommerce' )
			);
		}

		if ( Return_Status::INFO_REQUIRED !== $request->get_status() ) {
			return new \WP_Error(
				'hezarfen_returns_no_info_pending',
				__( 'Bu talep için ek bilgi beklenmiyor.', 'hezarfen-for-woocommerce' )
			);
		}

		$this->log(
			$request,
			Return_Event::TYPE_INFO_RESPONSE,
			$message,
			array( 'actor' => $this->customer_actor( $request ) )
		);

		return $this->change_status(
			$request,
			Return_Status::PENDING,
			array( 'actor' => $this->customer_actor( $request ) )
		);
	}

	/**
	 * Cancels the carrier appointment the customer booked.
	 *
	 * Only the booking goes away — the request stays approved, so the
	 * customer lands back on the day picker instead of having to open a
	 * whole new request.
	 *
	 * @param Return_Request $request Booked request.
	 *
	 * @return true|\WP_Error
	 */
	public function cancel_booking_by_customer( $request ) {
		if ( ! $request->is_booking_cancellable_by_customer() ) {
			return new \WP_Error(
				'hezarfen_returns_booking_not_cancellable',
				__( 'Bu kargo randevusu artık iptal edilemez.', 'hezarfen-for-woocommerce' )
			);
		}

		$method    = $this->shipping->get_for_request( $request );
		$cancelled = $method->cancel_booking( $request );

		if ( is_wp_error( $cancelled ) ) {
			return $cancelled;
		}

		$saved = $this->repository->save( $request );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$this->log(
			$request,
			Return_Event::TYPE_SHIPPING,
			__( 'Müşteri kargo randevusunu iptal etti.', 'hezarfen-for-woocommerce' ),
			array( 'actor' => $this->customer_actor( $request ) )
		);

		return true;
	}

	/**
	 * Forgets a booking that no longer exists at the carrier.
	 *
	 * The carrier is not called: this is the path for a shipment that was
	 * already released somewhere else — the merchant cancelling it from the
	 * order screen, or the carrier dropping it — where the only thing left
	 * to do is stop showing the customer a barcode and a pickup day that
	 * nobody will honour. The request stays approved, so they can simply
	 * book another day.
	 *
	 * @param Return_Request $request Request holding the stale booking.
	 * @param string         $message What to write on the timeline.
	 *
	 * @return true|\WP_Error
	 */
	public function release_booking( $request, $message = '' ) {
		if ( '' === $request->get_tracking_number() ) {
			return new \WP_Error(
				'hezarfen_returns_no_booking',
				__( 'Bu talebe bağlı bir kargo randevusu yok.', 'hezarfen-for-woocommerce' )
			);
		}

		$request->set_tracking_number( '' );
		$request->set_courier( '' );
		$request->set_pickup_date( '' );

		$saved = $this->repository->save( $request );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$this->log(
			$request,
			Return_Event::TYPE_SHIPPING,
			'' !== $message
				? $message
				: __( 'Kargo randevusu iptal edildi. Dilerseniz yeni bir alım günü seçebilirsiniz.', 'hezarfen-for-woocommerce' ),
			array( 'actor' => $this->system_actor() )
		);

		return true;
	}

	/**
	 * Replaces the address the carrier will collect the parcel from.
	 *
	 * Allowed only while the shipment is still unbooked: once the carrier
	 * holds an appointment, the address it holds is the one the courier
	 * drives to, and changing it here would only disagree with them.
	 *
	 * @param Return_Request        $request The request.
	 * @param array<string, string> $address Address parts.
	 *
	 * @return true|\WP_Error
	 */
	public function update_pickup_address( $request, $address ) {
		if ( ! $request->is_bookable_by_customer() ) {
			return new \WP_Error(
				'hezarfen_returns_address_locked',
				__( 'Kargo randevunuz alındığı için alım adresi artık değiştirilemez.', 'hezarfen-for-woocommerce' )
			);
		}

		$valid = Return_Pickup_Address::validate( $address );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$request->set_pickup_address( $address );

		$saved = $this->repository->save( $request );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$this->log(
			$request,
			Return_Event::TYPE_SHIPPING,
			__( 'Müşteri kargo alım adresini güncelledi.', 'hezarfen-for-woocommerce' ),
			array( 'actor' => $this->customer_actor( $request ) )
		);

		return true;
	}

	/**
	 * Books the return shipment for the day the customer picked.
	 *
	 * The carrier call and the option list belong to the shipping method;
	 * what this class owns is when a booking may happen at all — an
	 * approved request whose method expects one and that has no label yet.
	 * Without that guard a crafted POST could book a second pickup, or one
	 * for a request the merchant already rejected.
	 *
	 * The request deliberately stays approved afterwards: the parcel is not
	 * on its way until the courier has actually collected it, and that is
	 * what moves it to "shipped".
	 *
	 * @param Return_Request $request Approved request.
	 * @param string         $choice  Value of the picked option.
	 *
	 * @return true|\WP_Error
	 */
	public function book_shipment_by_customer( $request, $choice ) {
		return $this->with_request_lock(
			$request,
			function () use ( $request, $choice ) {
				// Authoritative gate against the stored row: two bookings
				// racing on one request each hold an in-memory copy that still
				// looks unbooked, so both would pass the check below and the
				// carrier would be called twice. Under the lock the first
				// booking has already written its tracking number, and the
				// second reads that here and stops before the carrier call.
				$fresh = $request->get_id() ? $this->repository->get( $request->get_id() ) : null;

				if ( $fresh && ! $fresh->is_bookable_by_customer() ) {
					return new \WP_Error(
						'hezarfen_returns_not_bookable',
						__( 'Bu talep için kargo randevusu alınamaz.', 'hezarfen-for-woocommerce' )
					);
				}

				return $this->do_book_shipment_by_customer( $request, $choice );
			}
		);
	}

	/**
	 * The body of book_shipment_by_customer(), run while the per-request lock
	 * is held. Mutates and saves the caller's request so the booking details
	 * it filled in are visible to the caller afterwards.
	 *
	 * @param Return_Request $request Approved request.
	 * @param string         $choice  Value of the picked option.
	 *
	 * @return true|\WP_Error
	 */
	private function do_book_shipment_by_customer( $request, $choice ) {
		$method = $this->shipping->get_for_request( $request );

		if ( ! $method->requires_customer_booking() ) {
			return new \WP_Error(
				'hezarfen_returns_booking_not_expected',
				__( 'Bu talebin kargo gönderimi mağaza tarafından yönetiliyor.', 'hezarfen-for-woocommerce' )
			);
		}

		if ( ! $request->is_bookable_by_customer() ) {
			return new \WP_Error(
				'hezarfen_returns_not_bookable',
				__( 'Bu talep için kargo randevusu alınamaz.', 'hezarfen-for-woocommerce' )
			);
		}

		$choice = trim( (string) $choice );

		if ( '' === $choice ) {
			return new \WP_Error(
				'hezarfen_returns_empty_booking_choice',
				__( 'Lütfen kargonuzun alınmasını istediğiniz günü seçin.', 'hezarfen-for-woocommerce' )
			);
		}

		$booked = $method->book( $request, $choice );

		if ( is_wp_error( $booked ) ) {
			// A day that was just taken, or an unreadable/empty choice, is worth
			// another go: the request stays approved and the picker reappears.
			// Anything else means the carrier will not serve this request at all
			// (address not covered, relay refusing it, no barcode) — looping the
			// same picker would strand the customer, so the request falls back
			// to the manual method and they can send it themselves.
			$retryable = array(
				'hezarfen_returns_empty_booking_choice',
				'hezarfen_returns_kargokit_slot_taken',
				'hez_pro_returns_hepsijet_slot_taken',
				'hez_pro_returns_hepsijet_bad_choice',
			);

			if ( ! in_array( $booked->get_error_code(), $retryable, true ) ) {
				return $this->fall_back_to_manual( $request, $booked );
			}

			// Nothing was written: the request is still an approved one
			// waiting for a booking, so the customer can simply try again.
			$this->log(
				$request,
				Return_Event::TYPE_SHIPPING,
				$booked->get_error_message(),
				array(
					'actor'               => $this->system_actor(),
					'is_customer_visible' => false,
				)
			);

			return $booked;
		}

		$saved = $this->repository->save( $request );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$this->log(
			$request,
			Return_Event::TYPE_SHIPPING,
			sprintf(
				/* translators: 1: pickup date, 2: return shipment tracking number. */
				__( 'İade kargo randevusu alındı: %1$s. Kargo kodu: %2$s', 'hezarfen-for-woocommerce' ),
				hezarfen_returns_format_date( $request->get_pickup_date() ),
				$request->get_tracking_number()
			),
			array( 'actor' => $this->customer_actor( $request ) )
		);

		/**
		 * Fires after the customer booked the return shipment.
		 *
		 * @param Return_Request $request The request, with its tracking
		 *                                details and pickup date filled in.
		 * @param string         $choice  Value of the picked option.
		 */
		do_action( 'hezarfen_return_shipment_booked', $request, $choice );

		return true;
	}

	/**
	 * Stores the tracking details the customer entered themselves.
	 *
	 * Separate from set_tracking() because the merchant may correct the
	 * shipping details of any request, while the customer may only fill in
	 * the one the store is actually waiting on: a request that has been
	 * approved and whose shipping method asks them for a tracking number.
	 * Without that guard a crafted POST could overwrite a carrier issued
	 * barcode, or attach a number to a rejected request.
	 *
	 * @param Return_Request $request Request.
	 * @param string         $courier Carrier name.
	 * @param string         $number  Tracking number.
	 *
	 * @return true|\WP_Error
	 */
	public function set_tracking_by_customer( $request, $courier, $number ) {
		if ( ! $this->shipping->get_for_request( $request )->requires_customer_tracking() ) {
			return new \WP_Error(
				'hezarfen_returns_tracking_not_expected',
				__( 'Bu talebin kargo bilgisi mağaza tarafından yönetiliyor.', 'hezarfen-for-woocommerce' )
			);
		}

		if ( ! $request->is_tracking_editable_by_customer() ) {
			return new \WP_Error(
				'hezarfen_returns_tracking_not_editable',
				__( 'Bu talep için kargo bilgisi girilemez.', 'hezarfen-for-woocommerce' )
			);
		}

		return $this->set_tracking( $request, $courier, $number, $this->customer_actor( $request ) );
	}

	/**
	 * Stores the tracking details of the return parcel.
	 *
	 * @param Return_Request       $request Request.
	 * @param string               $courier Carrier name.
	 * @param string               $number  Tracking number.
	 * @param array<string, mixed> $actor   Actor descriptor.
	 *
	 * @return true|\WP_Error
	 */
	public function set_tracking( $request, $courier, $number, $actor = null ) {
		$number = trim( (string) $number );

		if ( '' === $number ) {
			return new \WP_Error(
				'hezarfen_returns_empty_tracking',
				__( 'Kargo takip numarası boş olamaz.', 'hezarfen-for-woocommerce' )
			);
		}

		$request->set_courier( trim( (string) $courier ) );
		$request->set_tracking_number( $number );

		$saved = $this->repository->save( $request );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$this->log(
			$request,
			Return_Event::TYPE_SHIPPING,
			sprintf(
				/* translators: 1: carrier name, 2: tracking number. */
				__( 'Kargo bilgisi girildi: %1$s - %2$s', 'hezarfen-for-woocommerce' ),
				$request->get_courier() ? $request->get_courier() : __( 'Kargo firması belirtilmedi', 'hezarfen-for-woocommerce' ),
				$number
			),
			array( 'actor' => $actor )
		);

		// Handing the parcel over is what "shipped" means, so record it
		// automatically instead of asking the customer to also flip a
		// status they do not understand.
		if ( Return_Status::can_transition( $request->get_status(), Return_Status::SHIPPED ) ) {
			return $this->change_status( $request, Return_Status::SHIPPED, array( 'actor' => $actor ) );
		}

		return true;
	}

	/**
	 * Appends a note to the timeline.
	 *
	 * @param Return_Request       $request          Request.
	 * @param string               $message          Note body.
	 * @param array<string, mixed> $actor            Actor descriptor.
	 * @param bool                 $customer_visible Whether the customer sees it.
	 *
	 * @return true|\WP_Error
	 */
	public function add_note( $request, $message, $actor = null, $customer_visible = false ) {
		$message = trim( (string) $message );

		if ( '' === $message ) {
			return new \WP_Error( 'hezarfen_returns_empty_note', __( 'Not boş olamaz.', 'hezarfen-for-woocommerce' ) );
		}

		$this->log(
			$request,
			Return_Event::TYPE_NOTE,
			$message,
			array(
				'actor'               => $actor,
				'is_customer_visible' => $customer_visible,
			)
		);

		return true;
	}

	/**
	 * Cancels a request on the customer's own request.
	 *
	 * @param Return_Request $request Request.
	 *
	 * @return true|\WP_Error
	 */
	public function cancel_by_customer( $request ) {
		if ( ! $request->is_cancellable_by_customer() ) {
			return new \WP_Error(
				'hezarfen_returns_not_cancellable',
				__( 'Bu talep artık iptal edilemez.', 'hezarfen-for-woocommerce' )
			);
		}

		return $this->change_status(
			$request,
			Return_Status::CANCELLED,
			array(
				'actor'   => $this->customer_actor( $request ),
				'message' => __( 'Talep müşteri tarafından iptal edildi.', 'hezarfen-for-woocommerce' ),
			)
		);
	}

	/**
	 * Turns submitted line input into validated entities.
	 *
	 * @param \WC_Order                        $order Parent order.
	 * @param array<int, array<string, mixed>> $lines Submitted lines keyed by order item ID.
	 *
	 * @return Return_Item[]|\WP_Error
	 */
	private function build_items( $order, $lines ) {
		$returnable = $this->eligibility->get_returnable_lines( $order );
		$items      = array();

		foreach ( $lines as $item_id => $line ) {
			$item_id  = (int) $item_id;
			$quantity = isset( $line['quantity'] ) ? (int) $line['quantity'] : 0;

			if ( $quantity < 1 ) {
				continue;
			}

			if ( ! isset( $returnable[ $item_id ] ) ) {
				return new \WP_Error(
					'hezarfen_returns_line_not_returnable',
					__( 'Seçtiğiniz ürünlerden biri iade edilebilir durumda değil.', 'hezarfen-for-woocommerce' )
				);
			}

			$max = (int) $returnable[ $item_id ]['max_qty'];

			if ( $quantity > $max ) {
				return new \WP_Error(
					'hezarfen_returns_quantity_too_high',
					sprintf(
						/* translators: 1: product name, 2: maximum returnable quantity. */
						__( '"%1$s" için en fazla %2$d adet iade edebilirsiniz.', 'hezarfen-for-woocommerce' ),
						$returnable[ $item_id ]['item']->get_name(),
						$max
					)
				);
			}

			$reason = isset( $line['reason'] ) ? (string) $line['reason'] : '';

			if ( ! $this->reasons->exists( $reason ) ) {
				return new \WP_Error(
					'hezarfen_returns_invalid_reason',
					__( 'Lütfen her ürün için geçerli bir iade sebebi seçin.', 'hezarfen-for-woocommerce' )
				);
			}

			$note = isset( $line['note'] ) ? trim( (string) $line['note'] ) : '';

			if ( $this->reasons->requires_note( $reason ) && '' === $note ) {
				return new \WP_Error(
					'hezarfen_returns_note_required',
					sprintf(
						/* translators: %s: reason label. */
						__( '"%s" sebebini seçtiğinizde açıklama yazmanız gerekiyor.', 'hezarfen-for-woocommerce' ),
						$this->reasons->get_label( $reason )
					)
				);
			}

			$order_item = $returnable[ $item_id ]['item'];
			$product    = is_callable( array( $order_item, 'get_product' ) ) ? $order_item->get_product() : null;

			$items[] = new Return_Item(
				array(
					'order_item_id' => $item_id,
					'product_id'    => (int) $order_item->get_product_id(),
					'variation_id'  => (int) $order_item->get_variation_id(),
					'product_name'  => $order_item->get_name(),
					'sku'           => $product ? (string) $product->get_sku() : '',
					'quantity'      => $quantity,
					'line_total'    => (float) $returnable[ $item_id ]['unit_price'] * $quantity,
					'reason_key'    => $reason,
					'reason_note'   => $note,
				)
			);
		}

		if ( ! $items ) {
			return new \WP_Error(
				'hezarfen_returns_no_lines',
				__( 'İade etmek istediğiniz en az bir ürün seçin.', 'hezarfen-for-woocommerce' )
			);
		}

		return $items;
	}

	/**
	 * Sums the value of the returned lines.
	 *
	 * @param Return_Item[] $items Lines.
	 *
	 * @return float
	 */
	private function calculate_refund_amount( $items ) {
		$total = 0.0;

		foreach ( $items as $item ) {
			$total += $item->get_line_total();
		}

		return round( $total, wc_get_price_decimals() );
	}

	/**
	 * Runs a callback while holding an exclusive lock on one request.
	 *
	 * The money and carrier side effects — recording a refund, booking a
	 * pickup — must not run twice for the same request, and an in-memory
	 * precondition is not enough: two requests that both read the request as
	 * eligible each pass it and both call the gateway or the carrier. A MySQL
	 * advisory lock serialises them so the second sees what the first wrote.
	 * The lock is released on both the normal and the error path. If it cannot
	 * be taken within a few seconds the caller is told the request is busy
	 * rather than left to race.
	 *
	 * The lock is connection-scoped. On a standard single-connection wpdb it
	 * fully serialises the two requests. Behind a connection-splitting drop-in
	 * (HyperDB/LudicrousDB) the lock query can land on a read replica while the
	 * write runs on the primary, so there it degrades to the in-callback
	 * fresh-reload guard — which still stops the ordinary sequential
	 * double-click (the second request reloads and sees the first's write), but
	 * not two genuinely simultaneous ones. The name is qualified with the
	 * database so installs sharing a MySQL server do not serialise against each
	 * other on a shared return id.
	 *
	 * @param Return_Request $request  Request to lock on.
	 * @param callable       $callback Work to run under the lock.
	 *
	 * @return mixed Whatever the callback returns, or a WP_Error when the lock
	 *               could not be acquired.
	 */
	private function with_request_lock( $request, $callback ) {
		global $wpdb;

		$id = (int) $request->get_id();

		if ( ! $id ) {
			// Not persisted yet, so nothing else can be racing it.
			return $callback();
		}

		// GET_LOCK names are server-global (not schema-scoped), so the key is
		// qualified with the database and prefix; hashed to stay within the
		// 64-char name limit.
		$key = 'hezret_' . md5( $wpdb->dbname . '|' . $wpdb->prefix . '|' . $id );
		$got = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $key, 5 ) );

		// GET_LOCK returns '1' when acquired, '0' on wait timeout, and NULL on
		// error — including databases/proxies where it is unsupported. Only a
		// timeout means genuine contention (another request is mid-operation on
		// this very request); NULL must degrade to running WITHOUT the mutex
		// rather than blocking a legitimate action forever. The in-callback
		// fresh-reload guards still hold either way, just without cross-request
		// serialisation.
		if ( null === $got ) {
			return $callback();
		}

		if ( '1' !== (string) $got ) {
			return new \WP_Error(
				'hezarfen_returns_busy',
				__( 'Bu talep üzerinde başka bir işlem sürüyor. Lütfen birkaç saniye sonra tekrar deneyin.', 'hezarfen-for-woocommerce' )
			);
		}

		try {
			return $callback();
		} finally {
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) );
		}
	}

	/**
	 * Whether a status change is allowed, as a WP_Error when it is not.
	 *
	 * @param string $from Current status.
	 * @param string $to   Target status.
	 *
	 * @return true|\WP_Error
	 */
	private function check_transition( $from, $to ) {
		if ( Return_Status::can_transition( $from, $to ) ) {
			return true;
		}

		return new \WP_Error(
			'hezarfen_returns_invalid_transition',
			sprintf(
				/* translators: 1: current status label, 2: target status label. */
				__( '"%1$s" durumundan "%2$s" durumuna geçilemez.', 'hezarfen-for-woocommerce' ),
				Return_Status::get_label( $from ),
				Return_Status::get_label( $to )
			)
		);
	}

	/**
	 * Builds the human readable reference of a new request.
	 *
	 * The counter is read outside a transaction on purpose: `return_number`
	 * carries a unique index, so two submits racing on the same order build
	 * the same reference and the database lets exactly one of them through.
	 * The guard against a double submit is that index, not this count.
	 *
	 * @param \WC_Order $order Parent order.
	 *
	 * @return string
	 */
	private function generate_return_number( $order ) {
		$existing = $this->repository->count( array( 'order_id' => $order->get_id() ) );

		$number = sprintf( 'IADE-%s-%d', $order->get_order_number(), $existing + 1 );

		/**
		 * Filters the generated return reference.
		 *
		 * @param string    $number Generated reference.
		 * @param \WC_Order $order  Parent order.
		 */
		return (string) apply_filters( 'hezarfen_returns_return_number', $number, $order );
	}

	/**
	 * Appends a timeline entry, filling in the actor when not supplied.
	 *
	 * @param Return_Request       $request Parent request.
	 * @param string               $type    Event type.
	 * @param string               $message Message body.
	 * @param array<string, mixed> $args    Optional from/to status, actor and visibility.
	 *
	 * @return void
	 */
	private function log( $request, $type, $message, $args = array() ) {
		$actor = isset( $args['actor'] ) && is_array( $args['actor'] ) ? $args['actor'] : $this->current_actor( $request );

		$event = new Return_Event(
			array(
				'return_id'           => $request->get_id(),
				'type'                => $type,
				'actor_type'          => $actor['type'],
				'actor_id'            => $actor['id'],
				'actor_name'          => $actor['name'],
				'from_status'         => isset( $args['from_status'] ) ? (string) $args['from_status'] : '',
				'to_status'           => isset( $args['to_status'] ) ? (string) $args['to_status'] : '',
				'message'             => $message,
				'is_customer_visible' => isset( $args['is_customer_visible'] ) ? (bool) $args['is_customer_visible'] : true,
				'created_at'          => current_time( 'mysql' ),
			)
		);

		$this->events->add( $event );
	}

	/**
	 * Best guess at who is acting, based on the current request context.
	 *
	 * @param Return_Request $request Parent request.
	 *
	 * @return array<string, mixed>
	 */
	private function current_actor( $request ) {
		if ( is_admin() && current_user_can( 'manage_woocommerce' ) ) {
			$user = wp_get_current_user();

			return array(
				'type' => Return_Event::ACTOR_ADMIN,
				'id'   => (int) $user->ID,
				'name' => $user->display_name,
			);
		}

		if ( is_user_logged_in() ) {
			return $this->customer_actor( $request );
		}

		return $this->system_actor();
	}

	/**
	 * Actor descriptor for the customer who owns a request.
	 *
	 * @param Return_Request $request Parent request.
	 *
	 * @return array<string, mixed>
	 */
	private function customer_actor( $request ) {
		$user = wp_get_current_user();

		return array(
			'type' => Return_Event::ACTOR_CUSTOMER,
			'id'   => (int) $user->ID,
			'name' => $user->ID ? $user->display_name : $request->get_customer_email(),
		);
	}

	/**
	 * Actor descriptor for automated changes.
	 *
	 * @return array<string, mixed>
	 */
	private function system_actor() {
		return array(
			'type' => Return_Event::ACTOR_SYSTEM,
			'id'   => 0,
			'name' => '',
		);
	}
}
