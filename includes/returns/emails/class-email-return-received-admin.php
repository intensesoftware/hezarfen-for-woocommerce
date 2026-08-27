<?php
/**
 * Contains the Email_Return_Received_Admin notification.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Emails;

defined( 'ABSPATH' ) || exit();

/**
 * Tells the shop that a customer opened a return request.
 */
class Email_Return_Received_Admin extends Abstract_Return_Email {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'hezarfen_return_received_admin';
		$this->customer_email = false;
		$this->title          = __( 'Yeni iade talebi (yönetici)', 'hezarfen-for-woocommerce' );
		$this->description    = __( 'Bir müşteri iade talebi oluşturduğunda mağaza yöneticisine gönderilir.', 'hezarfen-for-woocommerce' );

		parent::__construct();
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( '[{site_title}] Yeni iade talebi: {return_number}', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Yeni iade talebi', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Opening paragraph.
	 *
	 * @return string
	 */
	public function get_intro() {
		return __( '{order_number} numaralı sipariş için yeni bir iade talebi oluşturuldu. Talebi inceleyip onaylayabilir veya reddedebilirsiniz.', 'hezarfen-for-woocommerce' );
	}
}
