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
class Pandasms extends \Hezarfen\Inc\Notification_Provider {
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
	 * Notification type
	 * 
	 * @var string
	 */
	public static $notif_type = 'sms';

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
		$order_id = $order->get_id();

		if ( function_exists( 'pandasms_wc_siparis_bildirimi' ) ) {
			$shipment_data = Helper::get_all_shipment_data( $order_id );
			foreach ( $shipment_data as $data ) {
				if ( $data->sms_sent ) {
					continue;
				}

				$result = pandasms_wc_siparis_bildirimi(
					$order,
					self::TRIGGER_NAME,
					array(
						self::COURIER_COMPANY_VAR => $data->courier_title,
						self::TRACKING_NUM_VAR    => $data->tracking_num,
						self::TRACKING_URL_VAR    => $data->tracking_url,
					)
				);

				if ( true === $result ) {
					$data->sms_sent = true;
					update_post_meta( $order_id, Manual_Shipment_Tracking::SHIPMENT_DATA_KEY, $data->prapare_for_db(), $data->raw_data );

					$this->add_order_note( $order );
				}
			}
		}
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
