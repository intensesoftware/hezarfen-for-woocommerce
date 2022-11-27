<?php
/**
 * Contains the Email_Order_Shipped class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Email_Order_Shipped class.
 */
class Email_Order_Shipped extends \WC_Email {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'hezarfen_mst_order_shipped_email';
		$this->customer_email = true;

		$this->title       = __( 'Order Shipped', 'hezarfen-for-woocommerce' );
		$this->description = __( 'Order Shipped emails are sent when an order have been shipped.', 'hezarfen-for-woocommerce' );

		$this->template_base = HEZARFEN_MST_PATH . 'templates/';
		$this->template_html = 'emails/email-order-shipped.php';
		$this->placeholders  = array(
			'{order_date}'   => '',
			'{order_number}' => '',
		);

		parent::__construct();

		$this->init_additional_form_fields();
	}

	/**
	 * Returns the default subject.
	 * 
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'Your Order Has Been Shipped', 'hezarfen-for-woocommerce' );
	}

	/**
	 * Returns the default heading.
	 * 
	 * @return string
	 */
	public function get_default_heading() {
		return $this->get_default_subject();
	}

	/**
	 * Trigger the sending of this email.
	 *
	 * @param \WC_Order|false $order Order object.
	 * 
	 * @return void
	 */
	public function trigger( $order ) {
		$this->setup_locale();

		if ( is_a( $order, 'WC_Order' ) ) {
			$this->object                         = $order;
			$this->recipient                      = $this->object->get_billing_email();
			$this->placeholders['{order_date}']   = wc_format_datetime( $this->object->get_date_created() );
			$this->placeholders['{order_number}'] = $this->object->get_order_number();

			if ( $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );  
			}
		}

		$this->restore_locale();
	}

	/**
	 * Get content html.
	 *
	 * @return string
	 */
	public function get_content_html() {
		$order_id = $this->object->get_id(); // @phpstan-ignore-line

		return wc_get_template_html(
			$this->template_html,
			array(
				'order'                 => $this->object,
				'email_heading'         => $this->get_heading(),
				'additional_content'    => $this->get_additional_content(),
				'sent_to_admin'         => false,
				'plain_text'            => false,
				'email'                 => $this,
				'courier_company_title' => Helper::get_courier_class( $order_id )::get_title(),
				'tracking_number'       => Helper::get_tracking_num( $order_id ),
			),
			'hezarfen-for-woocommerce/',
			HEZARFEN_MST_PATH . 'templates/'
		);
	}

	/**
	 * Get email headers.
	 *
	 * @return string
	 */
	public function get_headers() {
		$headers = parent::get_headers();
		$bcc     = $this->get_option( 'recipient_bcc' );
		$cc      = $this->get_option( 'recipient_cc' );

		if ( $bcc ) {
			$headers .= 'Bcc: ' . $bcc . "\r\n";
		}

		if ( $cc ) {
			$headers .= 'Cc: ' . $cc . "\r\n";
		}

		return $headers;
	}

	/**
	 * Adds additional setting fields.
	 * 
	 * @return void
	 */
	public function init_additional_form_fields() {
		$intense_kargotakip_settings = get_option( 'intense_kargotakip_ayarlar' );

		$this->form_fields['recipient_bcc'] = array(
			'title'       => __( 'BCC', 'hezarfen-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'If you want to send the shipment notification email to additional recipients, type the emails separated with comma.', 'hezarfen-for-woocommerce' ),
			'placeholder' => '',
			'default'     => $intense_kargotakip_settings['bcc'] ?? '', // Use BCC option in the Intense Kargo Takip plugin as default.
			'desc_tip'    => true,
		);

		$this->form_fields['recipient_cc'] = array(
			'title'       => __( 'CC', 'hezarfen-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'If you want to send the shipment notification email to additional recipients, type the emails separated with comma.', 'hezarfen-for-woocommerce' ),
			'placeholder' => '',
			'default'     => $intense_kargotakip_settings['cc'] ?? '', // Use CC option in the Intense Kargo Takip plugin as default.
			'desc_tip'    => true,
		);
	}

	/**
	 * Email type options.
	 *
	 * @return array<string, string>
	 */
	public function get_email_type_options() {
		$email_types = parent::get_email_type_options();
		return isset( $email_types['html'] ) ? array( 'html' => $email_types['html'] ) : array();
	}
}

return new Email_Order_Shipped();
