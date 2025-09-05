<?php
/**
 * Contains the Log trait.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

trait Log {
    private function log($title, $data) {
        if( ! $this->is_debug_mode_enabled() ) {
            return;
        }

        wc_get_logger()->debug($title . ' start', array('source'=>'hezarfen-hepsijet'));
        wc_get_logger()->debug(wc_print_r($data, true), array('source'=>'hezarfen-hepsijet'));
        wc_get_logger()->debug($title . ' end', array('source'=>'hezarfen-hepsijet'));
    }

    private function basic_log($title, $message) {
        if( ! $this->is_debug_mode_enabled() ) {
            return;
        }

        wc_get_logger()->debug($title . ':' . $message, array('source'=>'hezarfen-hepsijet'));
    }

    private function is_debug_mode_enabled() {
        return 'yes' === get_option( 'hezarfen_hepsijet_enable_debug_mode', 'no' );
    }
}