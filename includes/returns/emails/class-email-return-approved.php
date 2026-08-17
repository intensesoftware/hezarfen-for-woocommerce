<?php
/**
 * Contains the Email_Return_Approved notification.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Emails;

defined( 'ABSPATH' ) || exit();

/**
 * Tells the customer their request was approved and how to ship the goods.
 */
class Email_Return_Approved extends Abstract_Return_Email {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'hezarfen_return_approved';
		$this->customer_email = true;
		$this->title          = __( 'İade talebi onaylandı (müşteri)', 'hezarfen-for-woocommerce' );
		$this->description    = __( 'İade talebi onaylandığında müşteriye gönderilir.', 'hezarfen-for-woocommerce' );

		parent::__construct();
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'İade talebiniz onaylandı: {return_number}', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'İade talebiniz onaylandı', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Opening paragraph.
	 *
	 * @return string
	 */
	public function get_intro() {
		return __( 'İade talebiniz onaylandı. Gönderim adımlarını aşağıda bulabilirsiniz.', 'hezarfen-for-woocommerce' );
	}
}
