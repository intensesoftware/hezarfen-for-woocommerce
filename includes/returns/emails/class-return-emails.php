<?php
/**
 * Contains the Return_Emails registrar.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Emails;

use Hezarfen\Inc\Returns\Core\Return_Status;

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
		add_action( 'hezarfen_return_created', array( $this, 'on_created' ) );
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
		require_once HEZARFEN_RETURNS_PATH . 'emails/class-email-return-info-required.php';
		require_once HEZARFEN_RETURNS_PATH . 'emails/class-email-return-completed.php';

		$emails['Hezarfen_Return_Received_Admin']    = new Email_Return_Received_Admin();
		$emails['Hezarfen_Return_Received_Customer'] = new Email_Return_Received_Customer();
		$emails['Hezarfen_Return_Approved']          = new Email_Return_Approved();
		$emails['Hezarfen_Return_Rejected']          = new Email_Return_Rejected();
		$emails['Hezarfen_Return_Info_Required']     = new Email_Return_Info_Required();
		$emails['Hezarfen_Return_Completed']         = new Email_Return_Completed();

		return $emails;
	}

	/**
	 * Sends the pair of "we got it" notifications.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request The new request.
	 *
	 * @return void
	 */
	public function on_created( $request ) {
		$this->send( 'hezarfen_return_received_customer', $request );
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
