<?php
/**
 * Contains the Email_Return_Approved notification.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Emails;

use Hezarfen\Inc\Returns\Returns_Module;

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
	 * Approval is only half of what the customer has to do when the carrier
	 * collects the parcel: the pickup day is theirs to pick, so the mail
	 * that announces the approval is also what asks them for it.
	 *
	 * @return string
	 */
	public function get_intro() {
		if ( $this->return_request && Returns_Module::instance()->shipping()->get_for_request( $this->return_request )->requires_customer_booking() ) {
			return __( 'İade talebiniz onaylandı. Kargonuzun adresinizden alınmasını istediğiniz günü seçerek iade kargo kodunuzu oluşturabilirsiniz.', 'hezarfen-for-woocommerce' );
		}

		return __( 'İade talebiniz onaylandı. Gönderim adımlarını aşağıda bulabilirsiniz.', 'hezarfen-for-woocommerce' );
	}
}
