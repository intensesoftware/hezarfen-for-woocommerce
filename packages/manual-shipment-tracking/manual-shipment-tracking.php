<?php
/**
 * Manual Shipment Tracking package main file.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

define( 'HEZARFEN_MST_PATH', plugin_dir_path( __FILE__ ) );
define( 'HEZARFEN_MST_ASSETS_URL', plugins_url( 'assets/', __FILE__ ) );
define( 'HEZARFEN_MST_COURIER_LOGO_URL', HEZARFEN_MST_ASSETS_URL . 'img/courier-companies/' );

require_once 'includes/class-manual-shipment-tracking.php';

Manual_Shipment_Tracking::init();
