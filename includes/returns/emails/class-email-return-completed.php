<?php
/**
 * Contains the Email_Return_Completed notification.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Emails;

defined( 'ABSPATH' ) || exit();

/**
 * Closes the loop once the return is finished.
 */
class Email_Return_Completed extends Abstract_Return_Email {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'hezarfen_return_completed';
		$this->customer_email = true;
		$this->title          = __( 'İade tamamlandı (müşteri)', 'hezarfen-for-woocommerce' );
		$this->description    = __( 'İade süreci tamamlandığında müşteriye gönderilir.', 'hezarfen-for-woocommerce' );

		parent::__construct();
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'İadeniz tamamlandı: {return_number}', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'İadeniz tamamlandı', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Opening paragraph.
	 *
	 * @return string
	 */
	public function get_intro() {
		return __( 'İade süreciniz tamamlandı. Bizi tercih ettiğiniz için teşekkür ederiz.', 'hezarfen-for-woocommerce' );
	}

	/**
	 * The refunded amount, formatted for display, when one was recorded.
	 *
	 * @return string
	 */
	protected function get_refund_display() {
		if ( ! $this->return_request ) {
			return '';
		}

		$amount = (float) $this->return_request->get_refund_amount();

		if ( $amount <= 0 ) {
			return '';
		}

		return hezarfen_returns_plain_price( $amount, $this->return_request->get_currency() );
	}
}
