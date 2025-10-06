<?php

namespace Hezarfen\ManualShipmentTracking;

defined('ABSPATH') || exit;

class Deactivation_Wizard {
    
    public function __construct() {
        error_log( 'Deactivation_Wizard: Constructor called' );
        add_action( 'admin_footer', array( $this, 'add_deactivation_modal' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_deactivation_assets' ) );
        add_action( 'wp_ajax_hez_pro_move_shipped_to_processing', array( $this, 'ajax_move_shipped_to_processing' ) );
    }

    /**
     * Enqueue JavaScript and CSS for the deactivation modal.
     */
    public function enqueue_deactivation_assets( $hook ) {
        // Only load on plugins page
        if ( 'plugins.php' !== $hook ) {
            error_log( 'Deactivation wizard - Hook: ' . $hook );
            return;
        }

        error_log( 'Deactivation wizard - Enqueuing assets on plugins page' );

        wp_enqueue_script(
            'hez-pro-deactivation-wizard',
            WC_HEZARFEN_UYGULAMA_URL . 'assets/js/deactivation-wizard.js',
            array( 'jquery' ),
            WC_HEZARFEN_VERSION,
            true
        );

        wp_localize_script(
            'hez-pro-deactivation-wizard',
            'hezProDeactivation',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'hez_pro_deactivation_wizard' ),
                'pluginSlug' => 'hezarfen-for-woocommerce/hezarfen-for-woocommerce.php',
            )
        );

        wp_enqueue_style(
            'hez-pro-deactivation-wizard',
            WC_HEZARFEN_UYGULAMA_URL . 'assets/css/deactivation-wizard.css',
            array(),
            WC_HEZARFEN_VERSION
        );

        error_log( 'Deactivation wizard - Plugin slug: hezarfen-for-woocommerce/hezarfen-for-woocommerce.php' );
    }

    /**
     * Add the deactivation modal HTML to admin footer.
     */
    public function add_deactivation_modal() {
        $screen = get_current_screen();
        
        error_log( 'Deactivation_Wizard: add_deactivation_modal called. Screen: ' . ( $screen ? $screen->id : 'NULL' ) );
        
        // Only show on plugins page
        if ( ! $screen || 'plugins' !== $screen->id ) {
            return;
        }
        
        error_log( 'Deactivation_Wizard: Rendering modal on plugins page' );

        // Count orders with shipped status
        $shipped_count = $this->get_shipped_orders_count();
        
        ?>
        <div id="hez-pro-deactivation-modal" class="hez-pro-modal" style="display: none;">
            <div class="hez-pro-modal-overlay"></div>
            <div class="hez-pro-modal-content">
                <div class="hez-pro-modal-header">
                    <h2>⚠️ <?php esc_html_e( 'Siparişlerinizin görünmez olmasını önleyelim', 'hezarfen-pro-for-woocommerce' ); ?></h2>
                </div>
                
                <div class="hez-pro-modal-body">
                    <p>
                        <?php esc_html_e( 'Hezarfen devre dışı bırakıldığında, "Kargoya Verildi" sipariş durumu WooCommerce tarafından tanınmaz.', 'hezarfen-pro-for-woocommerce' ); ?>
                    </p>
                    <p>
                        <?php esc_html_e( 'Bu yüzden Hezarfen\'i tekrar aktif edene kadar "Kargoya Verildi" durumundaki siparişleriniz geçici olarak görünmez olabilir.', 'hezarfen-pro-for-woocommerce' ); ?>
                    </p>
                    <p>
                        <?php esc_html_e( 'Bunu önlemek için, bu siparişleri otomatik olarak "Hazırlanıyor" durumuna taşıyabiliriz, böylelikle Hezarfen kapalıyken de bu siparişleriniz hazırlanıyor durumunda görünmeye devam eder.', 'hezarfen-pro-for-woocommerce' ); ?>
                    </p>
                    
                    <?php if ( $shipped_count > 0 ) : ?>
                    <div class="hez-pro-order-count">
                        <strong><?php echo sprintf( esc_html__( 'Toplam %s adet "Kargoya Verildi" durumunda sipariş bulundu.', 'hezarfen-pro-for-woocommerce' ), number_format_i18n( $shipped_count ) ); ?></strong>
                    </div>
                    <?php endif; ?>

                    <div class="hez-pro-processing-message" style="display: none;">
                        <div class="hez-pro-spinner"></div>
                        <p><?php esc_html_e( 'Siparişler taşınıyor, lütfen bekleyin...', 'hezarfen-pro-for-woocommerce' ); ?></p>
                    </div>
                </div>
                
                <div class="hez-pro-modal-footer">
                    <button type="button" class="button button-primary button-hero hez-pro-move-orders" data-action="move-and-deactivate">
                        🟢 <?php esc_html_e( '"Kargoya Verildi" Siparişlerini " "Hazırlanıyor"a Taşı', 'hezarfen-pro-for-woocommerce' ); ?>
                    </button>
                    <button type="button" class="button button-secondary hez-pro-deactivate-anyway" data-action="deactivate-only">
                        ⚪ <?php esc_html_e( 'Olduğu Gibi Hezarfen\'i Devre Dışı Bırak', 'hezarfen-pro-for-woocommerce' ); ?>
                    </button>
                    <button type="button" class="button button-link hez-pro-cancel-deactivation" data-action="cancel">
                        🔵 <?php esc_html_e( 'Hezarfen\'i kapatma', 'hezarfen-pro-for-woocommerce' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
        error_log( 'Deactivation_Wizard: Modal HTML rendered successfully' );
    }

    /**
     * AJAX handler to move shipped orders to processing status.
     */
    public function ajax_move_shipped_to_processing() {
        check_ajax_referer( 'hez_pro_deactivation_wizard', 'nonce' );

        if ( ! current_user_can( 'activate_plugins' ) ) {
            wp_send_json_error( array( 'message' => __( 'Yetersiz izinler.', 'hezarfen-pro-for-woocommerce' ) ) );
            exit;
        }

        $orders = $this->get_shipped_orders();
        $count = 0;
        $errors = array();

        foreach ( $orders as $order_id ) {
            $order = wc_get_order( $order_id );
            
            if ( ! $order ) {
                continue;
            }

            try {
                // Move order to processing status
                $updated = $order->update_status( 'processing', __( 'Sipariş durumu Hezarfen Pro devre dışı bırakılmadan önce otomatik olarak Hazırlanıyor\'a taşındı.', 'hezarfen-pro-for-woocommerce' ) );
                
                if ( $updated ) {
                    $count++;
                }
            } catch ( \Exception $e ) {
                $errors[] = sprintf( 
                    /* translators: 1: order ID, 2: error message */
                    __( 'Sipariş #%1$s taşınırken hata: %2$s', 'hezarfen-pro-for-woocommerce' ), 
                    $order_id, 
                    $e->getMessage() 
                );
            }
        }

        if ( ! empty( $errors ) ) {
            wp_send_json_error( array( 
                'message' => implode( '<br>', $errors ),
                'moved_count' => $count
            ) );
        }

        wp_send_json_success( array( 
            'message' => sprintf( 
                /* translators: %s: number of orders */
                __( '%s sipariş başarıyla Hazırlanıyor durumuna taşındı.', 'hezarfen-pro-for-woocommerce' ), 
                number_format_i18n( $count ) 
            ),
            'count' => $count
        ) );
    }

    /**
     * Get all order IDs with shipped status.
     *
     * @return array
     */
    private function get_shipped_orders() {
        global $wpdb;

        // Check if HPOS is enabled
        if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) && 
             \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
            // HPOS query
            $orders_table = $wpdb->prefix . 'wc_orders';
            $query = $wpdb->prepare(
                "SELECT id FROM {$orders_table} WHERE status = %s",
                'wc-hezarfen-shipped'
            );
        } else {
            // Legacy post query
            $query = $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status = %s",
                'wc-hezarfen-shipped'
            );
        }

        $results = $wpdb->get_col( $query );
        
        return $results ? $results : array();
    }

    /**
     * Get count of orders with shipped status.
     *
     * @return int
     */
    private function get_shipped_orders_count() {
        return count( $this->get_shipped_orders() );
    }
}