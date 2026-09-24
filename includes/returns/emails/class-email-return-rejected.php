<?php
/**
 * Contains the Email_Return_Rejected notification.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Emails;

use Hezarfen\Inc\Returns\Core\Return_Event;
use Hezarfen\Inc\Returns\Core\Return_Status;

defined( 'ABSPATH' ) || exit();

/**
 * Tells the customer their request was declined.
 */
class Email_Return_Rejected extends Abstract_Return_Email {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'hezarfen_return_rejected';
		$this->customer_email = true;
		$this->title          = __( 'İade talebi reddedildi (müşteri)', 'hezarfen-for-woocommerce' );
		$this->description    = __( 'İade talebi reddedildiğinde müşteriye gönderilir.', 'hezarfen-for-woocommerce' );

		parent::__construct();
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'İade talebiniz hakkında: {return_number}', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'İade talebiniz reddedildi', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Opening paragraph.
	 *
	 * @return string
	 */
	public function get_intro() {
		if ( '' !== $this->get_inline_message() ) {
			return __( 'İade talebinizi maalesef onaylayamadık. Gerekçeyi bu e-postada aşağıda bulabilirsiniz.', 'hezarfen-for-woocommerce' );
		}

		return __( 'İade talebinizi maalesef onaylayamadık. Ayrıntılar için aşağıdaki "İade talebimi görüntüle" bağlantısını kullanabilirsiniz.', 'hezarfen-for-woocommerce' );
	}

	/**
	 * The customer-visible reason the request was declined.
	 *
	 * @return string
	 */
	protected function get_inline_message() {
		return $this->latest_customer_message( Return_Event::TYPE_STATUS_CHANGE, Return_Status::REJECTED );
	}

	/**
	 * Heading shown above the rejection reason.
	 *
	 * @return string
	 */
	protected function get_inline_message_heading() {
		return __( 'Gerekçe', 'hezarfen-for-woocommerce' );
	}
}
