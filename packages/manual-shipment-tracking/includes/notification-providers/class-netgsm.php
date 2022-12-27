<?php
/**
 * Contains the Netgsm class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit();

use \Hezarfen\Inc\Helper as Hezarfen_Helper;

/**
 * Netgsm class.
 */
class Netgsm extends \Hezarfen\Inc\Notification_Provider {
	const COURIER_TITLE_META_KEY = '_hezarfen_mst_netgsm_sms_courier_title';
	const TRACKING_NUM_META_KEY  = '_hezarfen_mst_netgsm_sms_tracking_num';
	const TRACKING_URL_META_KEY  = '_hezarfen_mst_netgsm_sms_tracking_url';

	const COURIER_COMPANY_VAR = '[hezarfen_kargo_firmasi]';
	const TRACKING_NUM_VAR    = '[hezarfen_kargo_takip_kodu]';
	const TRACKING_URL_VAR    = '[hezarfen_kargo_takip_linki]';
	const AVAILABLE_VARIABLES = array(
		'[siparis_no]',
		'[uye_adi]',
		'[uye_soyadi]',
		'[uye_telefonu]',
		'[uye_epostasi]',
		'[kullanici_adi]',
		'[tarih]',
		'[saat]',
		self::COURIER_COMPANY_VAR,
		self::TRACKING_NUM_VAR,
		self::TRACKING_URL_VAR,
	);

	/**
	 * Notification provider ID.
	 * 
	 * @var string
	 */
	public static $id = 'netgsm';

	/**
	 * Notification provider title.
	 * 
	 * @var string
	 */
	public static $title = 'NetGSM';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'pre_option_netgsm_order_status_text_' . Manual_Shipment_Tracking::DB_SHIPPED_ORDER_STATUS, array( __CLASS__, 'override_netgsm_sms_content' ), PHP_INT_MAX - 1, 3 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'remove_netgsm_callback' ), 1, 4 );
	}

	/**
	 * Sends SMS.
	 * 
	 * @param \WC_Order $order Order instance.
	 * @param string    $status_transition Status transition.
	 * 
	 * @return void
	 */
	public function send( $order, $status_transition = '' ) {
		if ( ! get_option( 'netgsm_status' ) ) {
			return; // return if NetGSM status is "Closed".
		}

		if ( function_exists( 'netgsm_order_status_changed_sendSMS' ) && get_option( Settings::OPT_NETGSM_CONTENT ) ) {
			$order_id      = $order->get_id();
			$shipment_data = Helper::get_all_shipment_data( $order_id );
			foreach ( $shipment_data as $data ) {
				if ( $data->sms_sent ) {
					continue;
				}

				update_post_meta( $order_id, self::COURIER_TITLE_META_KEY, $data->courier_title );
				update_post_meta( $order_id, self::TRACKING_NUM_META_KEY, $data->tracking_num );
				update_post_meta( $order_id, self::TRACKING_URL_META_KEY, $data->tracking_url );

				netgsm_order_status_changed_sendSMS( $order_id, 'netgsm_order_status_text_' . $status_transition, $status_transition );
				$data->sms_sent = true;

				update_post_meta( $order_id, Manual_Shipment_Tracking::SHIPMENT_DATA_KEY, $data->prapare_for_db(), $data->raw_data );
			}
		}
	}

	/**
	 * Overrides the NetGSM plugin's "netgsm_order_status_text_wc-hezarfen-shipped" option.
	 * 
	 * @param bool   $boolean_false Boolean "false" value.
	 * @param string $option     Option name.
	 * @param mixed  $default    The fallback value to return if the option does not exist.
	 * 
	 * @return string|false
	 */
	public static function override_netgsm_sms_content( $boolean_false, $option, $default ) {
		return get_option( Settings::OPT_NETGSM_CONTENT );
	}

	/**
	 * Removes a Netgsm callback that runs when order status changes to Manual_Shipment_Tracking::SHIPPED_ORDER_STATUS.
	 * We need to remove that callback to prevent sending SMS twice.
	 * 
	 * @param string|int $order_id Order ID.
	 * @param string     $from Status transition from.
	 * @param string     $to Status transition to.
	 * @param \WC_Order  $order Order instance.
	 * 
	 * @return void
	 */
	public static function remove_netgsm_callback( $order_id, $from, $to, $order ) {
		if ( Manual_Shipment_Tracking::SHIPPED_ORDER_STATUS === $to ) {
			remove_action( 'woocommerce_order_status_changed', 'netgsm_order_status_changed' );
		}
	}

	/**
	 * Converts hezarfen SMS variables to NetGSM metas.
	 * 
	 * @param string $sms_content SMS content.
	 * 
	 * @return string
	 */
	public static function convert_hezarfen_variables_to_netgsm_metas( $sms_content ) {
		$sms_content = str_replace( self::COURIER_COMPANY_VAR, '[meta:' . self::COURIER_TITLE_META_KEY . ']', $sms_content );
		$sms_content = str_replace( self::TRACKING_NUM_VAR, '[meta:' . self::TRACKING_NUM_META_KEY . ']', $sms_content );
		$sms_content = str_replace( self::TRACKING_URL_VAR, '[meta:' . self::TRACKING_URL_META_KEY . ']', $sms_content );
		return $sms_content;
	}

	/**
	 * Converts NetGSM metas to hezarfen SMS variables.
	 * 
	 * @param string $db_sms_content SMS content from the database.
	 * 
	 * @return string
	 */
	public static function convert_netgsm_metas_to_hezarfen_variables( $db_sms_content ) {
		$db_sms_content = str_replace( '[meta:' . self::COURIER_TITLE_META_KEY . ']', self::COURIER_COMPANY_VAR, $db_sms_content );
		$db_sms_content = str_replace( '[meta:' . self::TRACKING_NUM_META_KEY . ']', self::TRACKING_NUM_VAR, $db_sms_content );
		$db_sms_content = str_replace( '[meta:' . self::TRACKING_URL_META_KEY . ']', self::TRACKING_URL_VAR, $db_sms_content );
		return $db_sms_content;
	}

	/**
	 * Checks if the NetGSM plugin is ready to be used as a notification provider.
	 * 
	 * @return bool
	 */
	public static function is_plugin_ready() {
		return self::is_netgsm_active() && self::is_netgsm_order_status_change_notif_active();
	}

	/**
	 * Checks if the NetGSM plugin is active.
	 * 
	 * @return bool
	 */
	public static function is_netgsm_active() {
		return Hezarfen_Helper::is_plugin_active( 'netgsm/index.php' );
	}

	/**
	 * Checks if the NetGSM plugin's "order status change" notification is active.
	 * 
	 * @return string|false
	 */
	public static function is_netgsm_order_status_change_notif_active() {
		return get_option( 'netgsm_orderstatus_change_customer_control' );
	}
}
