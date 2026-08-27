<?php
/**
 * Contains the Email_Return_Rejected notification.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Emails;

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
		return __( 'İade talebinizi maalesef onaylayamadık. Gerekçeyi aşağıdaki talep geçmişinde bulabilirsiniz.', 'hezarfen-for-woocommerce' );
	}
}
