<?php
/**
 * Contains the Email_Return_Info_Required notification.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Emails;

defined( 'ABSPATH' ) || exit();

/**
 * Asks the customer for the extra detail the merchant needs.
 */
class Email_Return_Info_Required extends Abstract_Return_Email {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'hezarfen_return_info_required';
		$this->customer_email = true;
		$this->title          = __( 'İade talebi için ek bilgi (müşteri)', 'hezarfen-for-woocommerce' );
		$this->description    = __( 'Yönetici iade talebi için ek bilgi istediğinde müşteriye gönderilir.', 'hezarfen-for-woocommerce' );

		parent::__construct();
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'İade talebiniz için ek bilgi gerekiyor: {return_number}', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Sizden ek bilgi bekliyoruz', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Opening paragraph.
	 *
	 * @return string
	 */
	public function get_intro() {
		return __( 'İade talebinizi değerlendirebilmemiz için birkaç ayrıntıya daha ihtiyacımız var. Aşağıdaki bağlantıdan yanıtlayabilirsiniz.', 'hezarfen-for-woocommerce' );
	}
}
