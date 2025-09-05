<?php
/**
 * Contains the Shipping_Details class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined('ABSPATH') || exit;

use WC_Countries;
use WC_Order;
use Exception;

class Shipping_Details {
    protected $order;

    public function __construct($order_id)
    {
        $this->order = wc_get_order( $order_id );

        if( ! ( $this->order instanceof WC_Order ) ) {
            throw new Exception();
        }
    }

    public function get_city() {
        $shipping_state       = $this->order->get_shipping_state();
        $countries_obj        = new WC_Countries();
        $country_states_array = $countries_obj->get_states();

        return $country_states_array['TR'][ $shipping_state ] ?? '';
    }

    public function get_city_code() {
        return $this->order->get_shipping_state();
    }

    public function get_name() {
        $shipping_full_name    = sprintf( '%s %s', $this->order->get_shipping_first_name(), $this->order->get_shipping_last_name() );
        $shipping_company_name = $this->order->get_shipping_company();

        if ( $shipping_company_name ) {
            $shipping_name = sprintf( '%s / %s', $shipping_full_name, $shipping_company_name );
        } else {
            $shipping_name = $shipping_full_name;
        }

        return $shipping_name;
    }

    public function get_neighborhood() {
        return $this->order->get_meta('_shipping_neighborhood', true);
    }

    public function get_address() {
        return sprintf( '%s %s', $this->order->get_shipping_address_1(), $this->order->get_shipping_address_2() );
    }

    public function get_district() {
        return $this->order->get_shipping_city();
    }

    public function get_phone() {
        $phone = $this->order->get_shipping_phone();

        if( ! $phone ) {
            $phone = $this->order->get_billing_phone();
        }

        return $this->normalize_phone( $phone );
    }

    public function get_email() {
        return $this->order->get_billing_email();
    }

    /**
     * Normalize phone number
     */
    private function normalize_phone( $phone ) {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        // If phone starts with 0, replace with +90
        if ( substr($phone, 0, 1) === '0' ) {
            $phone = '+90' . substr($phone, 1);
        }
        
        // If phone doesn't start with +, add +90
        if ( substr($phone, 0, 1) !== '+' ) {
            $phone = '+90' . $phone;
        }
        
        return $phone;
    }
}