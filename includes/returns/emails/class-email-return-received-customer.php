<?php
/**
 * Contains the Email_Return_Received_Customer notification.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Emails;

defined( 'ABSPATH' ) || exit();

/**
 * Confirms to the customer that their request arrived.
 */
class Email_Return_Received_Customer extends Abstract_Return_Email {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'hezarfen_return_received_customer';
		$this->customer_email = true;
		$this->title          = __( 'İade talebi alındı (müşteri)', 'hezarfen-for-woocommerce' );
		$this->description    = __( 'Müşteri iade talebi oluşturduğunda kendisine gönderilen onay e-postası.', 'hezarfen-for-woocommerce' );

		parent::__construct();
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'İade talebiniz alındı: {return_number}', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'İade talebiniz alındı', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Opening paragraph.
	 *
	 * @return string
	 */
	public function get_intro() {
		return __( 'İade talebinizi aldık. Ekibimiz talebinizi en kısa sürede inceleyecek ve sonucu size e-posta ile bildireceğiz.', 'hezarfen-for-woocommerce' );
	}
}
