<?php
/**
 * Contains the Return_Emails registrar.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Emails;

use Hezarfen\Inc\Returns\Core\Return_Status;
use Hezarfen\Inc\Returns\Core\Return_Features;

defined( 'ABSPATH' ) || exit();

/**
 * Registers the return notifications with WooCommerce and connects them
 * to the module's domain events.
 *
 * Mails are plain WC_Email instances, so merchants manage them from
 * WooCommerce → Ayarlar → E-postalar like every other notification, and
 * template overrides work the usual way.
 */
class Return_Emails {

	/**
	 * Which e-mail id reacts to which return status.
	 *
	 * @var array<string, string>
	 */
	private $status_map = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->status_map = array(
			Return_Status::APPROVED      => 'hezarfen_return_approved',
			Return_Status::REJECTED      => 'hezarfen_return_rejected',
			Return_Status::INFO_REQUIRED => 'hezarfen_return_info_required',
			Return_Status::COMPLETED     => 'hezarfen_return_completed',
		);

		add_filter( 'woocommerce_email_classes', array( $this, 'register_emails' ) );
		// Not `hezarfen_return_created`: that fires before an automatic
		// approval, while the status is still about to change. By the time
		// this one fires the request is in the state the customer sees.
		add_action( 'hezarfen_return_submitted', array( $this, 'on_submitted' ) );
		add_action( 'hezarfen_return_status_changed', array( $this, 'on_status_changed' ), 10, 3 );
	}

	/**
	 * Adds the module's notifications to WooCommerce.
	 *
	 * @param array<string, \WC_Email> $emails Registered notifications.
	 *
	 * @return array<string, \WC_Email>
	 */
	public function register_emails( $emails ) {
		require_once HEZARFEN_RETURNS_PATH . 'emails/abstract-return-email.php';
		require_once HEZARFEN_RETURNS_PATH . 'emails/class-email-return-received-admin.php';
		require_once HEZARFEN_RETURNS_PATH . 'emails/class-email-return-received-customer.php';
		require_once HEZARFEN_RETURNS_PATH . 'emails/class-email-return-approved.php';
		require_once HEZARFEN_RETURNS_PATH . 'emails/class-email-return-rejected.php';
		require_once HEZARFEN_RETURNS_PATH . 'emails/class-email-return-completed.php';

		$emails['Hezarfen_Return_Received_Admin']    = new Email_Return_Received_Admin();
		$emails['Hezarfen_Return_Received_Customer'] = new Email_Return_Received_Customer();
		$emails['Hezarfen_Return_Approved']          = new Email_Return_Approved();
		$emails['Hezarfen_Return_Rejected']          = new Email_Return_Rejected();
		$emails['Hezarfen_Return_Completed']         = new Email_Return_Completed();

		// Bildirim ancak gönderilebiliyorsa kaydediliyor; aksi hâlde
		// WooCommerce'in e-posta ayarlarında hiç tetiklenmeyecek bir satır
		// dururdu. Kayıt burada, kurucuda değil: yetenek filtresi bu noktada
		// kesinlikle bağlanmış oluyor.
		if ( Return_Features::info_requests() ) {
			require_once HEZARFEN_RETURNS_PATH . 'emails/class-email-return-info-required.php';

			$emails['Hezarfen_Return_Info_Required'] = new Email_Return_Info_Required();
		}

		return $emails;
	}

	/**
	 * Sends the "we got it" notifications for a new request.
	 *
	 * An automatically approved request already sent the customer the
	 * "approved" mail from the status change, so a "received, we will
	 * review it" mail on top would arrive in the same second and contradict
	 * it. The merchant is told either way; the mail's wording follows the
	 * status.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request The new request.
	 *
	 * @return void
	 */
	public function on_submitted( $request ) {
		if ( Return_Status::PENDING === $request->get_status() ) {
			$this->send( 'hezarfen_return_received_customer', $request );
		}

		$this->send( 'hezarfen_return_received_admin', $request );
	}

	/**
	 * Sends the notification that matches the new status, if any.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request    The request.
	 * @param string                                    $old_status Previous status.
	 * @param string                                    $new_status New status.
	 *
	 * @return void
	 */
	public function on_status_changed( $request, $old_status, $new_status ) {
		unset( $old_status );

		if ( isset( $this->status_map[ $new_status ] ) ) {
			$this->send( $this->status_map[ $new_status ], $request );
		}
	}

	/**
	 * Triggers one notification by its WooCommerce e-mail id.
	 *
	 * @param string                                    $email_id E-mail identifier.
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request  The request.
	 *
	 * @return void
	 */
	private function send( $email_id, $request ) {
		$mailer = \WC()->mailer();
		$emails = $mailer->get_emails();

		foreach ( $emails as $email ) {
			if ( $email instanceof Abstract_Return_Email && $email->id === $email_id ) {
				$email->trigger( $request );

				return;
			}
		}
	}
}
