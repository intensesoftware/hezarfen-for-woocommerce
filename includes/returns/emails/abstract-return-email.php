<?php
/**
 * Contains the Abstract_Return_Email base class.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Emails;

use Hezarfen\Inc\Returns\Core\Return_Request;
use Hezarfen\Inc\Returns\Core\Return_Status;
use Hezarfen\Inc\Returns\Core\Return_Event;
use Hezarfen\Inc\Returns\Core\Return_Event_Repository;
use Hezarfen\Inc\Returns\Frontend\Return_Access;

defined( 'ABSPATH' ) || exit();

/**
 * Shared plumbing for every return notification.
 *
 * Subclasses declare who the mail is for and what it says; the template,
 * the placeholders and the trigger sequence live here so adding a new
 * notification costs a few lines instead of a copy of WC_Email.
 */
abstract class Abstract_Return_Email extends \WC_Email {

	/**
	 * The request the mail is about.
	 *
	 * @var Return_Request|null
	 */
	protected $return_request = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->template_base  = HEZARFEN_RETURNS_TEMPLATE_PATH;
		$this->template_html  = 'emails/return-notification.php';
		$this->template_plain = 'emails/plain/return-notification.php';

		$this->placeholders = array(
			'{return_number}' => '',
			'{order_number}'  => '',
			'{return_status}' => '',
			'{site_title}'    => $this->get_blogname(),
		);

		parent::__construct();
	}

	/**
	 * The paragraph that opens the mail body.
	 *
	 * @return string
	 */
	abstract public function get_intro();

	/**
	 * A customer-visible message to quote inline in the body, e.g. the
	 * rejection reason or the merchant's follow-up question. Subclasses that
	 * carry one override this; the default is none.
	 *
	 * @return string Empty when the mail has no message to quote.
	 */
	protected function get_inline_message() {
		return '';
	}

	/**
	 * Heading shown above the quoted inline message.
	 *
	 * @return string
	 */
	protected function get_inline_message_heading() {
		return '';
	}

	/**
	 * The refund amount to show, already formatted for display. Only the
	 * mails that settle money override this; the default is none.
	 *
	 * @return string Empty when no amount should be shown.
	 */
	protected function get_refund_display() {
		return '';
	}

	/**
	 * The latest customer-visible timeline message of a given type.
	 *
	 * Events come back oldest first, so overwriting on each match leaves the
	 * most recent one — the reason the merchant just sent, not an earlier one.
	 *
	 * @param string $type      Event type to look for.
	 * @param string $to_status Optional status the change must target.
	 *
	 * @return string Empty when no matching entry exists.
	 */
	protected function latest_customer_message( $type, $to_status = '' ) {
		if ( ! $this->return_request ) {
			return '';
		}

		$events  = ( new Return_Event_Repository() )->get_for_return( $this->return_request->get_id(), true );
		$message = '';

		foreach ( $events as $event ) {
			if ( $type !== $event->get_type() ) {
				continue;
			}

			if ( '' !== $to_status && $to_status !== $event->get_to_status() ) {
				continue;
			}

			if ( '' === trim( $event->get_message() ) ) {
				continue;
			}

			$message = $event->get_message();
		}

		return $message;
	}

	/**
	 * Sends the notification for a request.
	 *
	 * @param Return_Request $request The request.
	 *
	 * @return void
	 */
	public function trigger( $request ) {
		$this->setup_locale();

		if ( $request instanceof Return_Request ) {
			$this->return_request = $request;
			$this->object         = $request->get_order();

			$this->placeholders['{return_number}'] = $request->get_return_number();
			$this->placeholders['{order_number}']  = $this->object ? $this->object->get_order_number() : (string) $request->get_order_id();
			$this->placeholders['{return_status}'] = Return_Status::get_customer_label( $request->get_status() );

			$this->recipient = $this->resolve_recipient( $request );

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}
		}

		$this->restore_locale();
	}

	/**
	 * Who receives this notification.
	 *
	 * @param Return_Request $request The request.
	 *
	 * @return string
	 */
	protected function resolve_recipient( $request ) {
		if ( $this->customer_email ) {
			return $request->get_customer_email();
		}

		return $this->get_option( 'recipient', get_option( 'admin_email' ) );
	}

	/**
	 * Renders the HTML body.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->get_template_args( false ), '', $this->template_base );
	}

	/**
	 * Renders the plain text body.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, $this->get_template_args( true ), '', $this->template_base );
	}

	/**
	 * Arguments both templates receive.
	 *
	 * @param bool $plain_text Whether the plain text template is rendering.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_template_args( $plain_text ) {
		return array(
			'request'                => $this->return_request,
			'order'                  => $this->object,
			'intro'                  => $this->get_intro(),
			'email_heading'          => $this->get_heading(),
			'view_url'               => $this->get_view_url(),
			'inline_message'         => $this->get_inline_message(),
			'inline_message_heading' => $this->get_inline_message_heading(),
			'refund_display'         => $this->get_refund_display(),
			'additional_content'     => $this->get_additional_content(),
			'sent_to_admin'          => ! $this->customer_email,
			'plain_text'             => $plain_text,
			'email'                  => $this,
		);
	}

	/**
	 * Where the "view request" button points.
	 *
	 * @return string
	 */
	protected function get_view_url() {
		if ( ! $this->return_request ) {
			return '';
		}

		if ( ! $this->customer_email ) {
			return admin_url( 'admin.php?page=hezarfen-returns&return_id=' . $this->return_request->get_id() );
		}

		$access = new Return_Access();

		// Account-only area: a logged-out customer following this link lands
		// on the login screen and is returned here afterwards.
		return $access->get_request_url( $this->return_request );
	}

	/**
	 * Adds the recipient field to admin notifications.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		parent::init_form_fields();

		if ( $this->customer_email ) {
			return;
		}

		$fields = $this->form_fields;

		$recipient = array(
			'recipient' => array(
				'title'       => __( 'Alıcı(lar)', 'hezarfen-for-woocommerce' ),
				'type'        => 'text',
				'description' => sprintf(
					/* translators: %s: default admin e-mail address. */
					__( 'Virgülle ayırarak birden fazla adres girebilirsiniz. Boş bırakırsanız %s kullanılır.', 'hezarfen-for-woocommerce' ),
					'<code>' . esc_html( get_option( 'admin_email' ) ) . '</code>'
				),
				'placeholder' => '',
				'default'     => '',
				'desc_tip'    => true,
			),
		);

		// Keep "recipient" next to "enabled", the way core admin mails do.
		$this->form_fields = array_merge(
			array_slice( $fields, 0, 1, true ),
			$recipient,
			array_slice( $fields, 1, null, true )
		);
	}
}
