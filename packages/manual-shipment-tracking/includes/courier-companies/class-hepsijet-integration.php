<?php
/**
 * Contains the Courier_Hepsijet_Integration class.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

require_once HEZARFEN_MST_PATH . 'includes/trait-log.php';
require_once HEZARFEN_MST_PATH . 'includes/trait-helper.php';

/**
 * Courier_Hepsijet_Integration class for API integration.
 */
class Courier_Hepsijet_Integration {
    use Helper_Trait, Log;

    const REQUEST_TIMEOUT = 30;
    const ADVANCED_TRACKING_SHIPPED_STATUS = 'COLLECTED';
    const ADVANCED_TRACKING_DELIVERED_STATUS = 'DELIVERED';
    const UNSUPPORTED_WC_GATEWAYS = ['cod'];
    const PRICING_CACHE_DURATION = 3600; // 1 hour in seconds

    private $relay_base_url;
    private $consumer_key;
    private $consumer_secret;

    public function __construct() {
        $this->relay_base_url = 'https://intense.com.tr/wp-json/hepsijet-relay/v1';
        $this->consumer_key = $this->get_setting( 'consumer_key', '' );
        $this->consumer_secret = $this->get_setting( 'consumer_secret', '' );
    }

    /**
     * Get setting value
     */
    private function get_setting( $key, $default = '' ) {
        return get_option( 'hezarfen_hepsijet_' . $key, $default );
    }

    /**
     * Make request to relay API
     */
    private function make_relay_request( $endpoint, $data = null, $method = 'POST' ) {
        $url = $this->relay_base_url . $endpoint;
        
        $headers = array(
            'Authorization' => 'Basic ' . base64_encode( $this->consumer_key . ':' . $this->consumer_secret ),
            'Content-Type' => 'application/json'
        );

        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => self::REQUEST_TIMEOUT
        );

        if ( $data && in_array( $method, array( 'POST', 'PUT' ) ) ) {
            $args['body'] = wp_json_encode( $data );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $http_code = wp_remote_retrieve_response_code( $response );
        $decoded = json_decode( $body, true );

        if ( $http_code >= 400 ) {
            $error_message = $decoded['message'] ?? 'API Error: ' . $http_code;
            return new \WP_Error( 'relay_api_error', $error_message );
        }

        return $decoded;
    }

    /**
     * Make request to relay API for return dates (GET request with authentication)
     */
    public function make_relay_request_for_return_dates( $params ) {
        $url = $this->relay_base_url . '/return/dates';
        
        $headers = array(
            'Authorization' => 'Basic ' . base64_encode( $this->consumer_key . ':' . $this->consumer_secret ),
            'Content-Type' => 'application/json'
        );

        $args = array(
            'method' => 'GET',
            'headers' => $headers,
            'timeout' => self::REQUEST_TIMEOUT
        );

        // Add query parameters for GET request
        if ( $params ) {
            $url = add_query_arg( $params, $url );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $http_code = wp_remote_retrieve_response_code( $response );
        $decoded = json_decode( $body, true );

        if ( $http_code >= 400 ) {
            $error_message = $decoded['message'] ?? 'API Error: ' . $http_code;
            return new \WP_Error( 'relay_api_error', $error_message );
        }

        return $decoded;
    }

    /**
     * Create return barcode
     */
    public function api_create_return_barcode( $order_id, $delivery_date_original ) {
        $order = wc_get_order($order_id);
        if ( ! $order ) {
            return new \WP_Error( 'hepsijet_error', 'Order not found' );
        }

        $delivery_barcode_no = $this->delivery_barcode_uret();
        $shipping_details = new Shipping_Details( $order_id );

        $customerCompanyAddressId = sprintf('%s-%s', $order->get_id(), wp_generate_uuid4());
        $customerCompanyCustomerId = sprintf('%s-%s', $order->get_id(), wp_generate_uuid4());

        // Get shipments to get package count and desi
        $shipments_data = Helper::get_all_shipment_data( $order->get_id() );
        
        if( count( $shipments_data ) < 1 ) {
            return false;
        }

        // Use data from first shipment
        $package_count = 1; // Default value
        $desi = 1; // Default value

        $params = array(
            'company' => [
                'name' => $this->get_setting( 'sender_company_name', '' ),
                'abbreviationCode' => $this->get_setting( 'company_abbreviation_code', '' ),
            ],
            'delivery'=>[
                'customerDeliveryNo' => $delivery_barcode_no,
                'customerOrderId' => $order->get_order_number(),
                'totalParcels'       => $package_count,
                'desi'       => $desi,
                'deliverySlotOriginal'=>"0",
                'deliveryDateOriginal'=>$delivery_date_original,
                'deliveryType'       => 'RETURNED',
                'receiver'=>[
                    'companyCustomerId'=>$customerCompanyCustomerId,
                    'phone1'=>$this->get_setting( 'sender_company_phone', '' ),
                ],
                'product'=>[
                    'productCode'=>'HX_STD'
                ],
                'senderAddress'=>[
                    'companyAddressID'=>$customerCompanyAddressId,
                    'country'=>[
                        'name'=>'Türkiye'
                    ],
                    'city'=>[
                        'name'=>$shipping_details->get_city()
                    ],
                    'town'=>[
                        'name'=>$shipping_details->get_district()
                    ],
                    'district'=>[
                        'name'=>$shipping_details->get_neighborhood()
                    ],
                    'addressLine1'=>$shipping_details->get_address()
                ],
                'recipientAddress'=>[
                    'companyAddressId'=>$this->get_setting( 'sender_company_address_id', '' ),
                    'country'=>[
                        'name'=>'Türkiye'
                    ],
                    'city'=>[
                        'name'=>$this->get_setting('sender_company_city', '')
                    ],
                    'town'=>[
                        'name'=>$this->get_setting('sender_company_district', '')
                    ],
                    'district'=>[
                        'name'=>$this->get_setting('sender_company_neighborhood', '')
                    ],
                    'addressLine1'=>$this->get_setting( 'sender_company_address', '' ),
                ],
                'recipientPerson'=>$this->get_setting( 'authorized_person_fullname', '' ),
                'recipientPersonPhone1'=>$this->get_setting( 'authorized_person_phone', '' ),

            ]
        );

        $share_email = 'yes' === $this->get_setting( 'share_customer_email_with_hepsijet', 'no' );
        if ( $share_email ) {
            $params['delivery']['receiver']['email'] = $shipping_details->get_email();
        }

        $response = $this->send_request(
            'delivery/sendDeliveryOrderEnhanced',
            $params
        );

        if ( is_wp_error( $response ) || !array_key_exists('status', $response) || $response['status'] !== "OK" ) {
            return new \WP_Error( 'hepsijet_error', is_wp_error( $response ) ? $response->get_error_message() : 'API Error' );
        }

        if( ! array_key_exists('data', $response) ) {
            return new \WP_Error( 'hepsijet_error', 'Bilinmeyen Hata' );
        }

        $response_data = $response['data'];

        // Save return shipment data
        $shipment_data = Helper::new_order_shipment_data(
            $order,
            null,
            'hepsijet-entegrasyon',
            $response_data['customerDeliveryNo']
        );

        // Save return shipment response data to order meta
        if ( isset( $response_data['zplBarcodeDTOList'] ) && is_array( $response_data['zplBarcodeDTOList'] ) && count( $response_data['zplBarcodeDTOList'] ) > 0 ) {
            $barcode_data = $response_data['zplBarcodeDTOList'][0];
            
            // Save return shipment data with suffix
            $order->update_meta_data( '_hezarfen_hepsijet_return_barcode_no', $response_data['customerDeliveryNo'] );
            
            if ( isset( $barcode_data['barcodePrintDate'] ) ) {
                $mysql_date = $this->convert_turkish_date_to_mysql( $barcode_data['barcodePrintDate'] );
                $order->update_meta_data( '_hezarfen_hepsijet_return_barcode_print_date', $mysql_date );
            }
            
            if ( isset( $barcode_data['zplBarcode'] ) ) {
                $order->update_meta_data( '_hezarfen_hepsijet_return_zpl_barcode', $barcode_data['zplBarcode'] );
            }
            
            $order->update_meta_data( '_hezarfen_hepsijet_return_complete_response', $response_data );
            $order->save_meta_data();
        }

        return true;
    }

    /**
     * Create shipment via Relay API
     */
    public function api_create_barcode( $order_id, $package_count, $desi, $type = 'standard', $delivery_slot = '', $delivery_date = '' ) {
        $order = wc_get_order($order_id);
        if ( ! $order ) {
            return new \WP_Error( 'hepsijet_error', 'Order not found' );
        }

        // Check if payment method is COD (Cash on Delivery)
        $payment_method = $order->get_payment_method();
        if ( $payment_method === 'cod' ) {
            return new \WP_Error( 'hepsijet_cod_not_supported', esc_html__( 'Payment on delivery is not supported', 'hezarfen-for-woocommerce' ) );
        }

        $shipping_details = new Shipping_Details( $order_id );

        // Prepare receiver data for relay API
        $receiver = array(
            'firstName' => $order->get_shipping_first_name(),
            'lastName' => $order->get_shipping_last_name(),
            'phone1' => preg_replace('/\D/', '', $order->get_billing_phone()),
            'email' => $order->get_billing_email(),
            'address' => array(
                'city' => $shipping_details->get_city(),
                'town' => $shipping_details->get_district(),
                'district' => $shipping_details->get_neighborhood(),
                'addressLine1' => $shipping_details->get_address()
            )
        );

        // Add company if exists
        if ( $order->get_shipping_company() ) {
            $receiver['company'] = $order->get_shipping_company();
        }

        // Determine delivery date based on type
        $final_delivery_date = '';
        if ( $type === 'returned' && $delivery_date ) {
            $final_delivery_date = $delivery_date;
        } else {
            // For all other types, use order creation date
            $final_delivery_date = $order->get_date_created()->format('Y-m-d');
        }

        $params = array(
            'order_id' => $order_id,
            'package_count' => $package_count,
            'desi' => $desi,
            'type' => $type,
            'delivery_slot' => $delivery_slot,
            'delivery_date' => $final_delivery_date,
            'receiver' => $receiver,
            'domain' => home_url()
        );

        $this->log( 'Hepsijet Relay Create Barcode Request', $params );

        $response = $this->make_relay_request( '/barcode/create', $params );

        $this->log( 'Hepsijet Relay Create Barcode Response', $response );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( ! $response['success'] ) {
            return new \WP_Error( 'hepsijet_error', $response['message'] ?? 'API Error' );
        }

        // Relay API returns data directly
        $delivery_no = $response['delivery_no'];
        $zpl_data = $response['zpl'];
        $print_date = $response['print_date'];

        // Save all shipment details in a single encapsulated JSON meta field
        $shipment_details = array(
            'package_count' => $package_count,
            'desi' => $desi,
            'delivery_no' => $delivery_no,
            'created_at' => current_time('mysql'),
            'status' => 'active',
            'cancelled_at' => null,
            'cancel_reason' => null,
            'print_date' => $print_date
        );
        
        $order->update_meta_data( '_hezarfen_hepsijet_shipment_' . $delivery_no, $shipment_details );

        // Log the response data
        $this->log( 'Hepsijet relay response data', array(
            'order_id' => $order_id,
            'delivery_no' => $delivery_no,
            'print_date' => $print_date,
            'has_zpl' => !empty( $zpl_data )
        ));
        
        // Save order meta data
        $order->save_meta_data();

        // Schedule monitoring for this shipment
        $this->schedule_shipment_monitoring( $order_id, $delivery_no );

        return array(
            'success' => true,
            'tracking_number' => $delivery_no,
            'message' => __( 'Shipment created successfully', 'hezarfen-for-woocommerce' ),
            'response_data' => $response
        );
    }



    /**
     * Get shipping details for tracking via Relay API
     */
    public function api_get_shipping_details($delivery_no) {
        $this->log( 'Hepsijet Relay Get Shipping Details Request', array( 'delivery_no' => $delivery_no ) );

        $response = $this->make_relay_request( '/tracking/' . $delivery_no, null, 'GET' );

        $this->log( 'Hepsijet Relay Get Shipping Details Response', $response );

        return $response;
    }

    /**
     * Cancel shipment via Relay API
     */
    public function api_cancel_shipment($delivery_no) {
        $this->log( 'Hepsijet Relay Cancel Shipment Request', array( 'delivery_no' => $delivery_no ) );

        $response = $this->make_relay_request( '/shipment/' . $delivery_no, null, 'DELETE' );

        $this->log( 'Hepsijet Relay Cancel Shipment Response', $response );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( $response['success'] ) {
            return true;
        } else {
            // Return WP_Error instead of just the message string
            $error_message = $response['message'] ?? 'Gönderi iptal edilemedi.';
            return new \WP_Error( 'cancel_failed', $error_message, array( 'status' => 400 ) );
        }
    }

    /**
     * Get Barcode Labels via Relay API
     *
     * @param  string $delivery_barcode_no
     * @return string[]|false
     */
    public function get_barcode( $delivery_barcode_no ) {
        $this->log( 'Hepsijet Relay Get Barcode Request', array( 'delivery_no' => $delivery_barcode_no ) );

        $response = $this->make_relay_request( '/barcode/' . $delivery_barcode_no . '/label', null, 'GET' );

        $this->log( 'Hepsijet Relay Get Barcode Response', $response );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        // Debug: Log the full response structure
        $this->log( 'Hepsijet Relay Get Barcode Response Debug', array(
            'response_keys' => array_keys( $response ),
            'has_body' => isset( $response['body'] ),
            'body_type' => isset( $response['body'] ) ? gettype( $response['body'] ) : 'not_set',
            'body_length' => isset( $response['body'] ) ? strlen( $response['body'] ) : 0,
            'body_preview' => isset( $response['body'] ) ? substr( $response['body'], 0, 100 ) : 'not_set'
        ) );

        // The relay API returns the barcode data directly as an array
        if ( is_array( $response ) ) {
            $this->log( 'Hepsijet Relay Get Barcode Direct Array', array(
                'response_type' => gettype( $response ),
                'response_keys' => array_keys( $response ),
                'response_count' => count( $response )
            ) );
            return $response;
        }

        // Fallback: Check if response has body field (legacy format)
        if ( isset( $response['body'] ) ) {
            // The body contains JSON string, decode it
            $barcode_data = json_decode( $response['body'], true );
            $json_error = json_last_error();
            
            $this->log( 'Hepsijet Relay Get Barcode JSON Decode', array(
                'json_error' => $json_error,
                'json_error_msg' => json_last_error_msg(),
                'decoded_type' => gettype( $barcode_data ),
                'is_array' => is_array( $barcode_data )
            ) );
            
            if ( $json_error === JSON_ERROR_NONE && is_array( $barcode_data ) ) {
                return $barcode_data;
            }
        }

        return false;
    }

    /**
     * Get tracking number from cargo details
     *
     * @param array<string, mixed> $cargo_details Gönderi detayları.
     *
     * @return string|false
     */
    public function get_tracking_no( $cargo_details ) {
        $details = self::get_cargo_status( $cargo_details );

        if( ! $details ) {
            return false;
        }

        return $details['tracking_number'];
    }

    /**
     * Check if shipment is shipped
     *
     * @param array<string, mixed> $cargo_details Gönderi detayları.
     *
     * @return bool
     */
    public static function is_shipped( $cargo_details ) {
        $details = self::get_cargo_status( $cargo_details );

        if( ! $details ) {
            return false;
        }

        return ( in_array(self::ADVANCED_TRACKING_SHIPPED_STATUS, $details['history'], true) || in_array('READY', $details['history'], true) );
    }

    /**
     * Check if shipment is delivered
     *
     * @param array<string, mixed> $cargo_details Gönderi detayları.
     *
     * @return bool
     */
    public static function is_delivered( $cargo_details ) {
        $details = self::get_cargo_status( $cargo_details );

        if( ! $details ) {
            return false;
        }

        return $details['status'] === self::ADVANCED_TRACKING_DELIVERED_STATUS;
    }

    /**
     * Get available dates for return shipments
     */
    public function get_available_dates_for_return($start_date, $end_date, $city, $district) {
        $params = array(
            'startDate' => $start_date,
            'endDate' => $end_date,
            'deliveryType' => 'RETURNED',
            'city' => $city,
            'town' => $district
        );

        $response = $this->send_request( add_query_arg( $params, '/rest/delivery/findAvailableDeliveryDatesV2' ), array(), 'GET' );

        if ( array_key_exists( 'message', $response ) ) {
            return new \WP_Error( 'error', $response['message'] );
        }else if ( ! array_key_exists( 'data', $response ) ) {
            return new \WP_Error( 'unknown_error', esc_html( 'Available dates cannot be queried', 'hezarfen-for-woocommerce' ) );
        }

        $available_dates = array();

        foreach( $response['data'] as $city_response ) {
            foreach( $city_response['towns'] as $town_response ) {
                foreach( $town_response['xDock'] as $xdock_details ) {
                    $xdock_name = $xdock_details['xDockName'];
                    $days = $xdock_details['days'];

                    if( ! array_key_exists( $xdock_name, $available_dates ) ) {
                        $available_dates[$xdock_name] = array();
                    }

                    foreach($days as $day_args) {
                        if( $day_args['returnedLimit'] > 0 ) {
                            $available_dates[$xdock_name][] = $day_args['date'];
                        }
                    }
                }
            }
        }

        return $available_dates;
    }

    /**
     * Get cargo status details
     *
     * @param array<string, mixed> $cargo_details Kargo detayları.
     *
     * @return array|false
     */
    private static function get_cargo_status( $cargo_details ) {
        if(
            ! array_key_exists('status', $cargo_details)
            || $cargo_details['status'] !== 'OK'
            || ! is_array($cargo_details)
            || ! array_key_exists( 'data', $cargo_details )
            || ! ( count( $cargo_details['data'] ) > 0 )
        ) {
            return false;
        }

        $data = $cargo_details['data'][0];

        $transactions = array_map(function($transaction){
            if( ! array_key_exists( 'deliveryStatus', $transaction ) ) {
                return '';
            }

            return $transaction['deliveryStatus'];
        }, $data['transactions']);

        return [
            'status'=>$data['deliveryStatus'],
            'tracking_number'=>$data['customerDeliveryNo'],
            'history'=>$transactions
        ];
    }

    /**
     * Auto shipment creation handler
     */
    public function auto_shipment_create_handler( $order_id ) {
        // This method should not be called directly for Hepsijet
        // Use the automatic tasks system which will call with proper parameters
        throw new \Exception('Hepsijet auto shipment creation requires package_count and desi parameters. Use automatic tasks system.');
    }
    
    /**
     * Auto shipment creation handler with parameters
     */
    public function auto_shipment_create_handler_with_params( $order_id, $package_count, $desi, $type = 'standard', $delivery_slot = '', $delivery_date = '' ) {
        return $this->api_create_barcode( $order_id, $package_count, $desi, $type, $delivery_slot, $delivery_date );
    }

    /**
     * Check if auto shipment is supported
     */
    public function is_auto_shipment_supported(): bool {
        return true;
    }

    /**
     * Convert Turkish date format to MySQL datetime format
     * 
     * @param string $turkish_date Date in format "31.08.2025 22:47"
     * @return string Date in MySQL format "2025-08-31 22:47:00"
     */
    private function convert_turkish_date_to_mysql( $turkish_date ) {
        if ( empty( $turkish_date ) ) {
            return '';
        }

        try {
            // Turkish format: "31.08.2025 22:47"
            // Parse the date
            $date_parts = explode( ' ', $turkish_date );
            if ( count( $date_parts ) !== 2 ) {
                return $turkish_date; // Return original if format is unexpected
            }

            $date_part = $date_parts[0]; // "31.08.2025"
            $time_part = $date_parts[1]; // "22:47"

            $date_components = explode( '.', $date_part );
            if ( count( $date_components ) !== 3 ) {
                return $turkish_date; // Return original if format is unexpected
            }

            $day = $date_components[0];
            $month = $date_components[1];
            $year = $date_components[2];

            // Create MySQL format: "2025-08-31 22:47:00"
            $mysql_format = sprintf( '%s-%s-%s %s:00', $year, $month, $day, $time_part );

            // Validate the date
            $timestamp = strtotime( $mysql_format );
            if ( $timestamp === false ) {
                return $turkish_date; // Return original if invalid
            }

            return $mysql_format;

        } catch ( Exception $e ) {
            $this->log( 'Date conversion error', array(
                'input' => $turkish_date,
                'error' => $e->getMessage()
            ));
            return $turkish_date; // Return original on error
        }
    }

    /**
     * Get shipment details by delivery number
     * 
     * @param int $order_id Order ID
     * @param string $delivery_no Delivery number
     * @return array|null Shipment details or null if not found
     */
    public function get_shipment_details_by_delivery_no($order_id, $delivery_no) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return null;
        }
        
        $meta_key = '_hezarfen_hepsijet_shipment_' . $delivery_no;
        $shipment_details = $order->get_meta($meta_key);
        
        if (is_array($shipment_details) && isset($shipment_details['delivery_no'])) {
            return $shipment_details;
        }
        
        return null;
    }

    /**
     * Get Hepsijet pricing tiers information
     * 
     * @return array|WP_Error Pricing tiers information or error
     */
    public function get_pricing() {
        $cache_option_key = 'hepsijet_pricing_cache';
        $cached_data = get_option($cache_option_key, null);
        
        // Check if we have valid cached data
        if ($cached_data !== null) {
            $cache_data = json_decode($cached_data, true);
            
            // Validate cache structure and check expiration
            if ($cache_data && 
                isset($cache_data['expires_gmt']) && 
                isset($cache_data['pricing_data'])) {
                
                $current_gmt = gmdate('Y-m-d H:i:s');
                
                if ($current_gmt < $cache_data['expires_gmt']) {
                    $this->log('Hepsijet Relay Get Pricing Tiers Cache Hit', array(
                        'expires_gmt' => $cache_data['expires_gmt'],
                        'current_gmt' => $current_gmt,
                        'pricing_data' => $cache_data['pricing_data']
                    ));
                    return $cache_data['pricing_data'];
                } else {
                    $this->log('Hepsijet Relay Get Pricing Tiers Cache Expired', array(
                        'expires_gmt' => $cache_data['expires_gmt'],
                        'current_gmt' => $current_gmt
                    ));
                }
            }
        }
        
        $this->log('Hepsijet Relay Get Pricing Tiers Request', array('cache_status' => 'miss'));
        
        // Use direct WordPress HTTP request since pricing endpoint is public
        $url = home_url('/wp-json/hepsijet-relay/v1/pricing');
        
        $response = wp_remote_get($url, array(
            'timeout' => self::REQUEST_TIMEOUT
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);
        $decoded = json_decode($body, true);
        
        $this->log('Hepsijet Relay Get Pricing Tiers Raw Response', array(
            'http_code' => $http_code,
            'body' => $body,
            'decoded' => $decoded
        ));
        
        if ($http_code >= 400) {
            $error_message = $decoded['message'] ?? 'API Error: ' . $http_code;
            $this->log('Hepsijet Relay Get Pricing Tiers HTTP Error', array('code' => $http_code, 'message' => $error_message));
            return new \WP_Error('pricing_api_error', $error_message);
        }
        
        $this->log('Hepsijet Relay Get Pricing Tiers Response', $decoded);
        
        // Calculate expiration time in GMT
        $expires_gmt = gmdate('Y-m-d H:i:s', time() + self::PRICING_CACHE_DURATION);
        
        // Store pricing data and expiration in single JSON structure
        $cache_structure = array(
            'expires_gmt' => $expires_gmt,
            'pricing_data' => $decoded,
            'cached_at_gmt' => gmdate('Y-m-d H:i:s')
        );
        
        update_option($cache_option_key, wp_json_encode($cache_structure));
        
        $this->log('Hepsijet Relay Pricing Cached', array(
            'cache_option_key' => $cache_option_key,
            'duration_seconds' => self::PRICING_CACHE_DURATION,
            'expires_gmt' => $expires_gmt,
            'cached_at_gmt' => $cache_structure['cached_at_gmt']
        ));
        
        return $decoded;
    }

    /**
     * Get price for specific desi count from pricing tiers
     * 
     * @param float $desi_count Desi count
     * @return float|false Price for the desi count or false if not found
     */
    public function get_price_for_desi($desi_count) {
        $pricing_data = $this->get_pricing();
        
        if (is_wp_error($pricing_data) || !isset($pricing_data['pricing_tiers'])) {
            return false;
        }
        
        $pricing_tiers = $pricing_data['pricing_tiers'];
        
        // Find the appropriate tier for the given desi count
        foreach ($pricing_tiers as $tier) {
            if ($desi_count >= $tier['min'] && $desi_count <= $tier['max']) {
                return $tier['price'];
            }
        }
        
        // If no tier matches, return the last tier's price (for cases where desi > max tier)
        if (!empty($pricing_tiers)) {
            $last_tier = end($pricing_tiers);
            if ($desi_count > $last_tier['max']) {
                return $last_tier['price'];
            }
        }
        
        return false;
    }




    /**
     * Get pricing information for 1 and 4 desi to determine price range
     * 
     * @return array|false Array with price info or false on error
     */
    public function get_pricing_range_info() {
        $pricing_data = $this->get_pricing();
        
        if (is_wp_error($pricing_data) || !isset($pricing_data['pricing_tiers'])) {
            return false;
        }
        
        $price_1 = $this->get_price_for_desi(1.0);
        $price_4 = $this->get_price_for_desi(4.0);
        
        if ($price_1 === false || $price_4 === false) {
            return false;
        }
        
        return array(
            'price_1_desi' => $price_1,
            'price_4_desi' => $price_4,
            'same_price' => ($price_1 == $price_4),
            'display_text' => ($price_1 == $price_4) ? '1-4 desi' : '1 desi',
            'pricing_tiers' => $pricing_data['pricing_tiers'],
            'currency' => $pricing_data['currency'] ?? 'TRY',
            'notes' => $pricing_data['notes'] ?? [],
            'last_updated' => $pricing_data['last_updated'] ?? '',
            'source' => $pricing_data['source'] ?? 'Hepsijet API Relay'
        );
    }

    /**
     * Get Hepsijet ile Avantajlı Kargo Fiyatları wallet balance
     *
     * @return array|WP_Error
     */
    public function get_kargogate_balance() {
        // Use Hepsijet ile Avantajlı Kargo Fiyatları namespace for wallet balance
        $kargogate_url = 'https://intense.com.tr/wp-json/kargogate/v1/wallet/balance?format=formatted';
        
        $headers = array(
            'Authorization' => 'Basic ' . base64_encode( $this->consumer_key . ':' . $this->consumer_secret ),
            'Content-Type' => 'application/json',
        );

        $response = wp_remote_get( $kargogate_url, array(
            'timeout' => self::REQUEST_TIMEOUT,
            'headers' => $headers,
        ));

        if ( is_wp_error( $response ) ) {
            $this->log( 'Hepsijet ile Avantajlı Kargo Fiyatları Balance Request Error', $response->get_error_message() );
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $status_code = wp_remote_retrieve_response_code( $response );

        if ( $status_code !== 200 ) {
            $this->log( 'Hepsijet ile Avantajlı Kargo Fiyatları Balance Request Failed', array(
                'status_code' => $status_code,
                'body' => $body
            ));
            return new \WP_Error( 'api_error', sprintf( 'API request failed with status %d', $status_code ) );
        }

        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $this->log( 'Hepsijet ile Avantajlı Kargo Fiyatları Balance JSON Decode Error', json_last_error_msg() );
            return new \WP_Error( 'json_error', 'Invalid JSON response' );
        }

        $this->log( 'Hepsijet ile Avantajlı Kargo Fiyatları Balance Response', $data );

        if ( ! isset( $data['balance'] ) ) {
            return new \WP_Error( 'invalid_response', __( 'Invalid balance response', 'hezarfen-for-woocommerce' ) );
        }

        return $data;
    }

    /**
     * Schedule monitoring for a shipment
     *
     * @param int    $order_id    Order ID
     * @param string $delivery_no Delivery number
     * @return void
     */
    private function schedule_shipment_monitoring( $order_id, $delivery_no ) {
        // Check if ActionScheduler is available
        if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
            $this->log( 'ActionScheduler not available for monitoring', array(
                'order_id' => $order_id,
                'delivery_no' => $delivery_no
            ));
            return;
        }

        // Schedule recurring action every hour
        $hook = 'hezarfen_monitor_hepsijet_shipment';
        $args = array( $order_id, $delivery_no );
        $group = 'hezarfen-shipment-monitoring';
        
        // Cancel any existing scheduled actions for this specific shipment only
        $this->unschedule_shipment_monitoring( $order_id, $delivery_no );
        
        // Check if ActionScheduler is available before scheduling
        if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
            $this->log( 'ActionScheduler function not available', array(
                'order_id' => $order_id,
                'delivery_no' => $delivery_no
            ));
            return;
        }
        
        // Schedule single action that will reschedule itself
        $action_id = as_schedule_single_action(
            time() + HOUR_IN_SECONDS, // Start in 1 hour
            $hook,
            $args,
            $group
        );

        // Store the action ID in shipment meta for later reference
        $this->store_monitoring_action_id( $order_id, $delivery_no, $action_id );

        $this->log( 'Scheduled shipment monitoring', array(
            'order_id' => $order_id,
            'delivery_no' => $delivery_no,
            'action_id' => $action_id,
            'hook' => $hook,
            'args' => $args,
            'group' => $group
        ));
    }

    /**
     * Unschedule monitoring for a specific shipment
     *
     * @param int    $order_id    Order ID
     * @param string $delivery_no Delivery number
     * @return void
     */
    private function unschedule_shipment_monitoring( $order_id, $delivery_no ) {
        // Get stored action ID from shipment meta
        $action_id = $this->get_monitoring_action_id( $order_id, $delivery_no );
        
        if ( $action_id ) {
            // Unschedule specific action by ID
            $result = as_unschedule_action( $action_id );
            $this->log( 'Unscheduled specific monitoring action', array(
                'order_id' => $order_id,
                'delivery_no' => $delivery_no,
                'action_id' => $action_id,
                'unschedule_result' => $result
            ));
        } else {
            $this->log( 'No action ID found for unscheduling', array(
                'order_id' => $order_id,
                'delivery_no' => $delivery_no
            ));
        }
        
        // Also try to unschedule by args as fallback (but only for this specific shipment)
        $fallback_result = as_unschedule_all_actions( 'hezarfen_monitor_hepsijet_shipment', array( $order_id, $delivery_no ), 'hezarfen-shipment-monitoring' );
        $this->log( 'Fallback unschedule result', array(
            'order_id' => $order_id,
            'delivery_no' => $delivery_no,
            'fallback_result' => $fallback_result
        ));
    }

    /**
     * Store monitoring action ID in shipment meta
     *
     * @param int    $order_id    Order ID
     * @param string $delivery_no Delivery number
     * @param int    $action_id   ActionScheduler action ID
     * @return void
     */
    private function store_monitoring_action_id( $order_id, $delivery_no, $action_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $meta_key = '_hezarfen_hepsijet_shipment_' . $delivery_no;
        $shipment_details = $order->get_meta( $meta_key );
        
        if ( $shipment_details && is_array( $shipment_details ) ) {
            $shipment_details['monitoring_action_id'] = $action_id;
            $order->update_meta_data( $meta_key, $shipment_details );
            $order->save_meta_data();
        }
    }

    /**
     * Get monitoring action ID from shipment meta
     *
     * @param int    $order_id    Order ID
     * @param string $delivery_no Delivery number
     * @return int|null
     */
    private function get_monitoring_action_id( $order_id, $delivery_no ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return null;
        }

        $meta_key = '_hezarfen_hepsijet_shipment_' . $delivery_no;
        $shipment_details = $order->get_meta( $meta_key );
        
        if ( $shipment_details && is_array( $shipment_details ) && isset( $shipment_details['monitoring_action_id'] ) ) {
            return $shipment_details['monitoring_action_id'];
        }
        
        return null;
    }

    /**
     * Monitor shipment status (called by ActionScheduler)
     *
     * @param int    $order_id    Order ID
     * @param string $delivery_no Delivery number
     * @return void
     */
    public static function monitor_shipment_status( $order_id, $delivery_no ) {
        $instance = new self();
        
        $instance->log( 'Monitoring shipment status', array(
            'order_id' => $order_id,
            'delivery_no' => $delivery_no
        ));

        // Get shipment details
        $shipment_details = $instance->get_shipment_details_by_delivery_no( $order_id, $delivery_no );
        
        if ( ! $shipment_details ) {
            $instance->log( 'Shipment details not found, stopping monitoring', array(
                'order_id' => $order_id,
                'delivery_no' => $delivery_no
            ));
            
            // Stop monitoring if shipment not found
            $instance->unschedule_shipment_monitoring( $order_id, $delivery_no );
            return;
        }

        $current_status = $shipment_details['status'] ?? 'active';
        
        // If shipment is cancelled, stop monitoring
        if ( $current_status === 'cancelled' ) {
            $instance->log( 'Shipment is cancelled, stopping monitoring', array(
                'order_id' => $order_id,
                'delivery_no' => $delivery_no
            ));
            
            $instance->unschedule_shipment_monitoring( $order_id, $delivery_no );
            return;
        }

        // Get tracking details from Hepsijet
        $tracking_details = $instance->api_get_shipping_details( $delivery_no );
        
        if ( is_wp_error( $tracking_details ) ) {
            $instance->log( 'Error getting tracking details', array(
                'order_id' => $order_id,
                'delivery_no' => $delivery_no,
                'error' => $tracking_details->get_error_message()
            ));
            return;
        }

        // Check if shipment is shipped
        if ( $current_status === 'active' ) {
            $is_shipped = $instance->is_shipped( $tracking_details );
            
            if ( $is_shipped ) {
                $instance->log( 'Shipment is now shipped, updating status', array(
                    'order_id' => $order_id,
                    'delivery_no' => $delivery_no
                ));
                
                // Update shipment status to shipped
                $instance->update_shipment_status( $order_id, $delivery_no, 'shipped' );
                
                // Use ship_order to save tracking data
                $instance->process_shipment_shipped( $order_id, $delivery_no, $tracking_details );
                
                // Reschedule monitoring to check for delivery
                $instance->reschedule_monitoring( $order_id, $delivery_no );
            } else {
                // Shipment is still active (not shipped yet), reschedule monitoring
                $instance->reschedule_monitoring( $order_id, $delivery_no );
            }
        }
        // Check if shipped shipment is delivered
        elseif ( $current_status === 'shipped' ) {
            $is_delivered = $instance->is_delivered( $tracking_details );
            
            if ( $is_delivered ) {
                $instance->log( 'Shipment is now delivered, updating status', array(
                    'order_id' => $order_id,
                    'delivery_no' => $delivery_no
                ));
                
                // Update shipment status to delivered
                $instance->update_shipment_status( $order_id, $delivery_no, 'delivered' );
                
                // Mark order as completed
                $instance->process_shipment_delivered( $order_id, $delivery_no );
                
                // Stop monitoring as shipment is delivered
                $instance->unschedule_shipment_monitoring( $order_id, $delivery_no );
            } else {
                // Shipment is shipped but not delivered yet, reschedule monitoring
                $instance->reschedule_monitoring( $order_id, $delivery_no );
            }
        }
    }

    /**
     * Reschedule monitoring for a shipment
     *
     * @param int    $order_id    Order ID
     * @param string $delivery_no Delivery number
     * @return void
     */
    private function reschedule_monitoring( $order_id, $delivery_no ) {
        // Check if ActionScheduler is available
        if ( ! function_exists( 'as_schedule_single_action' ) ) {
            return;
        }

        // Schedule next monitoring in 1 hour
        $hook = 'hezarfen_monitor_hepsijet_shipment';
        $args = array( $order_id, $delivery_no );
        $group = 'hezarfen-shipment-monitoring';
        
        $action_id = as_schedule_single_action(
            time() + HOUR_IN_SECONDS, // Next check in 1 hour
            $hook,
            $args,
            $group
        );

        // Update the stored action ID
        $this->store_monitoring_action_id( $order_id, $delivery_no, $action_id );

        $this->log( 'Rescheduled shipment monitoring', array(
            'order_id' => $order_id,
            'delivery_no' => $delivery_no,
            'action_id' => $action_id
        ));
    }

    /**
     * Update shipment status in meta
     *
     * @param int    $order_id    Order ID
     * @param string $delivery_no Delivery number
     * @param string $status      New status
     * @return void
     */
    private function update_shipment_status( $order_id, $delivery_no, $status ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $meta_key = '_hezarfen_hepsijet_shipment_' . $delivery_no;
        $shipment_details = $order->get_meta( $meta_key );
        
        if ( $shipment_details && is_array( $shipment_details ) ) {
            $shipment_details['status'] = $status;
            $shipment_details['status_updated_at'] = current_time('mysql');
            
            $order->update_meta_data( $meta_key, $shipment_details );
            $order->save_meta_data();
        }
    }

    /**
     * Process shipment when it's shipped
     *
     * @param int    $order_id         Order ID
     * @param string $delivery_no      Delivery number
     * @param array  $tracking_details Tracking details from API
     * @return void
     */
    private function process_shipment_shipped( $order_id, $delivery_no, $tracking_details ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Create Shipment_Data object for ship_order
        $shipment_data = new \Hezarfen\ManualShipmentTracking\Shipment_Data();
        $shipment_data->tracking_num = $delivery_no;
        $shipment_data->courier_id = 'hepsijet';
        $shipment_data->courier_title = 'Hepsijet';
        
        // Use the ship_order method to save tracking data
        \Hezarfen\ManualShipmentTracking\Manual_Shipment_Tracking::ship_order( $order, $shipment_data );
        
        $this->log( 'Processed shipment as shipped', array(
            'order_id' => $order_id,
            'delivery_no' => $delivery_no
        ));
    }

    /**
     * Process shipment when it's delivered
     *
     * @param int    $order_id    Order ID
     * @param string $delivery_no Delivery number
     * @return void
     */
    private function process_shipment_delivered( $order_id, $delivery_no ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Update order status to completed
        $order->update_status( 'completed', __( 'Order completed - shipment delivered', 'hezarfen-for-woocommerce' ) );
        
        $this->log( 'Processed shipment as delivered', array(
            'order_id' => $order_id,
            'delivery_no' => $delivery_no
        ));
    }

    /**
     * Clear pricing cache manually
     * This method can be called to force refresh pricing data
     * 
     * @return bool True if cache was cleared, false otherwise
     */
    public function clear_pricing_cache() {
        $cache_option_key = 'hepsijet_pricing_cache';
        $result = delete_option($cache_option_key);
        
        $this->log('Hepsijet Pricing Cache Cleared', array(
            'cache_option_key' => $cache_option_key,
            'success' => $result,
            'timestamp_gmt' => gmdate('Y-m-d H:i:s')
        ));
        
        return $result;
    }
}