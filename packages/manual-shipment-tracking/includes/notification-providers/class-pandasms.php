<?php
/**
 * Contains the Pandasms class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit();

use \Hezarfen\Inc\Helper as Hezarfen_Helper;

/**
 * Pandasms class.
 */
class Pandasms extends MST_Notification_Provider {
	const TRIGGER_NAME = 'hezarfen_mst_order_shipped';

	const COURIER_COMPANY_VAR = 'kargoFirmasi';
	const TRACKING_NUM_VAR    = 'kargoTakipNo';
	const TRACKING_URL_VAR    = 'kargoTakipLinki';

	/**
	 * Notification provider ID.
	 * 
	 * @var string
	 */
	public static $id = 'pandasms';

	/**
	 * Notification provider title.
	 * 
	 * @var string
	 */
	public static $title = 'PandaSMS';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'pandasms_wc_siparis_bildirim_tetikleyicileri', array( __CLASS__, 'add_new_trigger' ) );
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
		if ( function_exists( 'pandasms_wc_siparis_bildirimi' ) ) {
			parent::send( $order, $status_transition );
		}
	}

	/**
	 * Performs the actual sending.
	 * 
	 * @param \WC_Order     $order Order object.
	 * @param Shipment_Data $shipment_data Shipment data.
	 * 
	 * @return bool
	 */
	public function perform_sending( $order, $shipment_data ) {
		// @phpstan-ignore-next-line
		$result = pandasms_wc_siparis_bildirimi(
			$order,
			self::TRIGGER_NAME,
			array(
				self::COURIER_COMPANY_VAR => $shipment_data->courier_title,
				self::TRACKING_NUM_VAR    => $shipment_data->tracking_num,
				self::TRACKING_URL_VAR    => $shipment_data->tracking_url,
			)
		);

		return true === $result;
	}

	/**
	 * Adds new trigger to the PandaSMS plugin.
	 * 
	 * @param array<string, mixed> $triggers Triggers.
	 * 
	 * @return array<string, mixed>
	 */
	public static function add_new_trigger( $triggers ) {
		$triggers[ self::TRIGGER_NAME ] = array(
			'tanim'       => 'Sipariş kargoya verildiğinde',
			'kisa_kodlar' => array(
				self::COURIER_COMPANY_VAR => 'Kargo Firması',
				self::TRACKING_NUM_VAR    => 'Kargo Takip No',
				self::TRACKING_URL_VAR    => 'Kargo Takip Linki',
			),
			'saglayici'   => 'Hezarfen',
		);

		return $triggers;
	}

	/**
	 * Checks if the PandaSMS plugin is ready to be used as a notification provider.
	 * 
	 * @return bool
	 */
	public static function is_plugin_ready() {
		return Hezarfen_Helper::is_plugin_active( 'pandasms-for-woocommerce/pandasms-for-woocommerce.php' );
	}
}
