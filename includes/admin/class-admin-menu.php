<?php
/**
 * Admin Menu for Hezarfen
 *
 * @package Hezarfen\Inc\Admin
 */

namespace Hezarfen\Inc\Admin;

defined( 'ABSPATH' ) || exit();

/**
 * Admin_Menu class - Adds Hezarfen menu to WordPress admin
 */
class Admin_Menu {

    /**
     * Menu slug
     */
    const MENU_SLUG = 'hezarfen';

    /**
     * Settings submenu slug
     */
    const SETTINGS_SLUG = 'wc-settings';

    /**
     * Upgrade submenu slug
     */
    const UPGRADE_SLUG = 'hezarfen-upgrade';

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
        add_filter( 'parent_file', array( $this, 'highlight_menu' ) );
        add_filter( 'submenu_file', array( $this, 'highlight_submenu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_upgrade_styles' ) );
        add_action( 'wp_ajax_hezarfen_submit_demand', array( $this, 'handle_demand_submission' ) );
    }

    /**
     * Handle demand form submission via AJAX
     *
     * @return void
     */
    public function handle_demand_submission() {
        check_ajax_referer( 'hezarfen_demand_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Yetkiniz yok.', 'hezarfen-for-woocommerce' ) ) );
        }

        $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
        $package = isset( $_POST['package'] ) ? sanitize_text_field( $_POST['package'] ) : '';

        if ( empty( $email ) || ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => __( 'Geçerli bir e-posta adresi girin.', 'hezarfen-for-woocommerce' ) ) );
        }

        $package_names = array(
            'kanat' => 'Kanat',
            'ucus'  => 'Uçuş',
            'pro'   => 'Pro',
        );

        $package_name = isset( $package_names[ $package ] ) ? $package_names[ $package ] : $package;
        $site_url = home_url();

        $to = 'info@intense.com.tr';
        $subject = sprintf( '[Hezarfen Talep] %s Paketi', $package_name );
        $message = sprintf(
            "Yeni bir paket talebi alındı:\n\n" .
            "Paket: %s\n" .
            "E-posta: %s\n" .
            "Site URL: %s",
            $package_name,
            $email,
            $site_url
        );

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: ' . $email,
        );

        $sent = wp_mail( $to, $subject, $message, $headers );

        if ( $sent ) {
            wp_send_json_success( array( 'message' => __( 'Talebiniz başarıyla gönderildi!', 'hezarfen-for-woocommerce' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'E-posta gönderilemedi. Lütfen daha sonra tekrar deneyin.', 'hezarfen-for-woocommerce' ) ) );
        }
    }

    /**
     * Register admin menu
     *
     * @return void
     */
    public function register_menu() {
        add_menu_page(
            __( 'Hezarfen', 'hezarfen-for-woocommerce' ),
            __( 'Hezarfen', 'hezarfen-for-woocommerce' ),
            'manage_options',
            self::MENU_SLUG,
            null,
            '',
            4 // Position 4 to appear right after Hezarfen Pro (position 3)
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Ayarlar', 'hezarfen-for-woocommerce' ),
            __( 'Ayarlar', 'hezarfen-for-woocommerce' ),
            'manage_options',
            'admin.php?page=wc-settings&tab=hezarfen'
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Yükselt', 'hezarfen-for-woocommerce' ),
            __( 'Yükselt', 'hezarfen-for-woocommerce' ),
            'manage_options',
            self::UPGRADE_SLUG,
            array( $this, 'render_upgrade_page' )
        );

        // Remove the auto-created duplicate submenu
        remove_submenu_page( self::MENU_SLUG, self::MENU_SLUG );
    }

    /**
     * Enqueue upgrade page styles
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_upgrade_styles( $hook ) {
        // Always add menu styling for Yükselt button
        wp_add_inline_style( 'wp-admin', $this->get_menu_styles() );

        if ( 'hezarfen_page_' . self::UPGRADE_SLUG !== $hook ) {
            return;
        }

        wp_add_inline_style( 'wp-admin', $this->get_upgrade_page_styles() );
    }

    /**
     * Get menu inline styles for Yükselt button
     *
     * @return string
     */
    private function get_menu_styles() {
        return '
            #adminmenu #toplevel_page_hezarfen ul.wp-submenu li a[href="admin.php?page=hezarfen-upgrade"] {
                background: #46b450 !important;
                color: #fff !important;
                font-weight: 600;
                border-radius: 3px;
                margin: 5px 10px;
                padding: 5px 10px;
            }
            #adminmenu #toplevel_page_hezarfen ul.wp-submenu li a[href="admin.php?page=hezarfen-upgrade"]:hover {
                background: #3a9a42 !important;
                color: #fff !important;
            }
        ';
    }

    /**
     * Get upgrade page inline styles
     *
     * @return string
     */
    private function get_upgrade_page_styles() {
        return '
            .hezarfen-upgrade-wrap {
                max-width: 1200px;
                margin: 20px auto;
            }
            .hezarfen-upgrade-wrap h1 {
                text-align: center;
                margin-bottom: 30px;
            }
            .hezarfen-packages {
                display: flex;
                gap: 20px;
                flex-wrap: wrap;
                justify-content: center;
            }
            .hezarfen-package {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 25px;
                flex: 1;
                min-width: 300px;
                max-width: 380px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .hezarfen-package.featured {
                border-color: #2271b1;
                border-width: 2px;
                transform: scale(1.02);
            }
            .hezarfen-package-header {
                text-align: center;
                padding-bottom: 20px;
                border-bottom: 1px solid #eee;
                margin-bottom: 20px;
            }
            .hezarfen-package-header h2 {
                margin: 0 0 10px 0;
                font-size: 24px;
            }
            .hezarfen-package-price {
                font-size: 28px;
                font-weight: bold;
                color: #2271b1;
            }
            .hezarfen-package-price small {
                font-size: 14px;
                font-weight: normal;
                color: #666;
            }
            .hezarfen-package ul {
                list-style: none;
                padding: 0;
                margin: 0;
            }
            .hezarfen-package li {
                padding: 8px 0;
                padding-left: 25px;
                position: relative;
                border-bottom: 1px solid #f0f0f0;
            }
            .hezarfen-package li:last-child {
                border-bottom: none;
            }
            .hezarfen-package li:before {
                content: "✓";
                position: absolute;
                left: 0;
                color: #46b450;
                font-weight: bold;
            }
            .hezarfen-tooltip {
                display: inline-block;
                width: 16px;
                height: 16px;
                line-height: 16px;
                text-align: center;
                background: #ddd;
                color: #666;
                border-radius: 50%;
                font-size: 11px;
                cursor: help;
                margin-left: 5px;
                vertical-align: middle;
                position: relative;
            }
            .hezarfen-tooltip:hover {
                background: #2271b1;
                color: #fff;
            }
            .hezarfen-tooltip:hover::after {
                content: attr(data-tooltip);
                position: absolute;
                bottom: 100%;
                left: 50%;
                transform: translateX(-50%);
                background: #1d2327;
                color: #fff;
                padding: 8px 12px;
                border-radius: 4px;
                font-size: 12px;
                white-space: normal;
                width: 250px;
                text-align: left;
                z-index: 1000;
                margin-bottom: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                line-height: 1.4;
            }
            .hezarfen-tooltip:hover::before {
                content: "";
                position: absolute;
                bottom: 100%;
                left: 50%;
                transform: translateX(-50%);
                border: 6px solid transparent;
                border-top-color: #1d2327;
                margin-bottom: -4px;
                z-index: 1001;
            }
            .hezarfen-feature-group {
                font-weight: 600;
                color: #1d2327;
                font-size: 13px;
                margin-top: 15px;
                margin-bottom: 5px;
                padding-bottom: 5px;
                border-bottom: 1px solid #ddd;
            }
            .hezarfen-feature-group:first-child {
                margin-top: 0;
            }
            .hezarfen-package .hezarfen-cta {
                display: block;
                text-align: center;
                background: #2271b1;
                color: #fff;
                padding: 12px 20px;
                text-decoration: none;
                border-radius: 4px;
                margin-top: 20px;
                font-weight: bold;
            }
            .hezarfen-package .hezarfen-cta:hover {
                background: #135e96;
                color: #fff;
            }
            .hezarfen-package.free .hezarfen-package-price {
                color: #46b450;
            }
            .hezarfen-price-skeleton {
                display: inline-block;
                background: linear-gradient(90deg, #e0e0e0 25%, #f0f0f0 50%, #e0e0e0 75%);
                background-size: 200% 100%;
                animation: hezarfen-skeleton-loading 1.5s infinite;
                border-radius: 4px;
                height: 1.2em;
                min-width: 80px;
            }
            .hezarfen-price-skeleton.price-main {
                width: 100px;
                height: 32px;
            }
            .hezarfen-price-skeleton.price-small {
                width: 120px;
                height: 16px;
                margin-left: 5px;
            }
            .hezarfen-price-skeleton.price-inline {
                width: 60px;
                height: 1em;
                vertical-align: middle;
            }
            @keyframes hezarfen-skeleton-loading {
                0% { background-position: 200% 0; }
                100% { background-position: -200% 0; }
            }
            .hezarfen-package .hezarfen-cta.demand {
                background: #f0ad4e;
            }
            .hezarfen-package .hezarfen-cta.demand:hover {
                background: #ec971f;
            }
            .hezarfen-demand-notice {
                font-size: 12px;
                color: #666;
                margin-top: 10px;
                font-style: italic;
                text-align: center;
            }
            .hezarfen-demand-form {
                margin-top: 20px;
                background: #fef9f3;
                border: 1px solid #f0ad4e;
                border-radius: 6px;
                padding: 15px;
            }
            .hezarfen-demand-form label {
                display: block;
                font-size: 13px;
                font-weight: 600;
                color: #1d2327;
                margin-bottom: 8px;
            }
            .hezarfen-demand-form label span {
                font-weight: normal;
                color: #666;
                font-size: 12px;
            }
            .hezarfen-demand-email-wrap {
                position: relative;
            }
            .hezarfen-demand-email {
                width: 100%;
                padding: 10px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
                box-sizing: border-box;
                background: #fff;
                transition: border-color 0.2s, box-shadow 0.2s;
            }
            .hezarfen-demand-email:focus {
                border-color: #f0ad4e;
                outline: none;
                box-shadow: 0 0 0 3px rgba(240, 173, 78, 0.15);
            }
            .hezarfen-demand-email::placeholder {
                color: #aaa;
            }
            .hezarfen-package .hezarfen-cta.demand {
                margin-top: 12px;
                cursor: pointer;
                border: none;
            }
            .hezarfen-package .hezarfen-cta.demand:disabled {
                opacity: 0.7;
                cursor: not-allowed;
            }
            .hezarfen-demand-hint {
                font-size: 11px;
                color: #888;
                margin-top: 6px;
                display: flex;
                align-items: center;
                gap: 4px;
            }
            .hezarfen-demand-hint::before {
                content: "\f528";
                font-family: dashicons;
                font-size: 14px;
            }
            .hezarfen-upgrade-wrap .notice {
                display: none !important;
            }
        ';
    }

    /**
     * Render upgrade page
     *
     * @return void
     */
    public function render_upgrade_page() {
        ?>
        <div class="wrap hezarfen-upgrade-wrap">
            <h1><?php esc_html_e( 'Hezarfen Paketleri', 'hezarfen-for-woocommerce' ); ?></h1>

            <div class="hezarfen-packages">
                <!-- Kanat Paket -->
                <div class="hezarfen-package" data-package-container="kanat" style="display: none;">
                    <div class="hezarfen-package-header">
                        <h2><?php esc_html_e( 'Kanat', 'hezarfen-for-woocommerce' ); ?></h2>
                        <div class="hezarfen-package-price">
                            <span class="hezarfen-price-skeleton price-main" data-price-key="kanat"></span>
                            <small><span class="hezarfen-price-skeleton price-small" data-price-suffix="kanat"></span></small>
                        </div>
                    </div>
                    <div class="hezarfen-feature-group"><?php esc_html_e( 'Kargo Entegrasyonu', 'hezarfen-for-woocommerce' ); ?></div>
                    <ul>
                        <li><?php esc_html_e( 'Yılda 500 sipariş için kargo entegrasyonu', 'hezarfen-for-woocommerce' ); ?></li>
                        <li><?php esc_html_e( 'Yurtiçi, Aras, Sürat, Hepsijet, DHL E-Com, Kolay Gelsin', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Kendi anlaşmanızla bu kargo firmalarına entegrasyon sağlayabilirsiniz.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Kargo barkodu oluşturma', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş adresi barkod ile kargo firmasına iletilir, hatalı adres girişi engellenir.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Barkod okutulunca sipariş durumu otomatik "Kargoya Verildi" olur', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Kargo firması barkodu okuttuğunda sipariş durumu otomatik olarak "Kargoya Verildi" olur.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Teslimatta sipariş durumu otomatik "Tamamlandı" olur', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş alıcıya teslim edilince sipariş durumu otomatik olarak "Tamamlandı" durumuna geçer.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Kargo takip numarası siparişe otomatik girilir', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Kargo takip numarası otomatik olarak siparişe (Hezarfen kargo takip alanına) girilir.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Detaylı kargo hareketleri sipariş detayında ve kargo takip ekranında görüntülenir', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Kargo firmasının sitesine gitmeden, kargonuzun anlık olarak nerede olduğunu zaman çizelgesi şeklinde görüntüleyin.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                    </ul>

                    <div class="hezarfen-feature-group"><?php esc_html_e( 'Bildirimler', 'hezarfen-for-woocommerce' ); ?></div>
                    <ul>
                        <li><?php esc_html_e( 'Kargoya verildi SMS bildirimi', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş kargoya verildiğinde müşteriye otomatik SMS gönderilir.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Kargoya verildi e-posta bildirimi', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş kargoya verildiğinde müşteriye otomatik e-posta gönderilir.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Yaymail ile "kargoya verildi" e-posta içeriğini özelleştirme', 'hezarfen-for-woocommerce' ); ?></li>
                        <li><?php esc_html_e( 'Kapıda ödemeli siparişlere SMS doğrulaması', 'hezarfen-for-woocommerce' ); ?></li>
                    </ul>

                    <div class="hezarfen-feature-group"><?php esc_html_e( 'Hesabım Sayfası', 'hezarfen-for-woocommerce' ); ?></div>
                    <ul>
                        <li><?php esc_html_e( 'Kurumsal/bireysel ve fatura alanları', 'hezarfen-for-woocommerce' ); ?></li>
                    </ul>
                    <div class="hezarfen-cta-container" data-package="kanat" data-admin-email="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"></div>
                </div>

                <!-- Uçuş Paket -->
                <div class="hezarfen-package featured" data-package-container="ucus" style="display: none;">
                    <div class="hezarfen-package-header">
                        <h2><?php esc_html_e( 'Uçuş', 'hezarfen-for-woocommerce' ); ?></h2>
                        <div class="hezarfen-package-price">
                            <span class="hezarfen-price-skeleton price-main" data-price-key="ucus"></span>
                            <small><span class="hezarfen-price-skeleton price-small" data-price-suffix="ucus"></span></small>
                        </div>
                    </div>
                    <div class="hezarfen-feature-group"><?php esc_html_e( 'Kargo Entegrasyonu', 'hezarfen-for-woocommerce' ); ?></div>
                    <ul>
                        <li><?php esc_html_e( 'Yılda 1.200 sipariş için kargo entegrasyonu', 'hezarfen-for-woocommerce' ); ?></li>
                        <li><?php esc_html_e( 'Yurtiçi, Aras, Sürat, Hepsijet, DHL E-Com, Kolay Gelsin', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Kendi anlaşmanızla bu kargo firmalarına entegrasyon sağlayabilirsiniz.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Kargo barkodu oluşturma', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş adresi barkod ile kargo firmasına iletilir, hatalı adres girişi engellenir.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Barkod okutulunca sipariş durumu otomatik "Kargoya Verildi" olur', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Kargo firması barkodu okuttuğunda sipariş durumu otomatik olarak "Kargoya Verildi" olur.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Teslimatta sipariş durumu otomatik "Tamamlandı" olur', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş alıcıya teslim edilince sipariş durumu otomatik olarak "Tamamlandı" durumuna geçer.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Kargo takip numarası siparişe otomatik girilir', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Kargo takip numarası otomatik olarak siparişe (Hezarfen kargo takip alanına) girilir.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Detaylı kargo hareketleri sipariş detayında ve kargo takip ekranında görüntülenir', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Kargo firmasının sitesine gitmeden, kargonuzun anlık olarak nerede olduğunu zaman çizelgesi şeklinde görüntüleyin.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                    </ul>

                    <div class="hezarfen-feature-group"><?php esc_html_e( 'Bildirimler', 'hezarfen-for-woocommerce' ); ?></div>
                    <ul>
                        <li><?php esc_html_e( 'Kargoya verildi SMS bildirimi', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş kargoya verildiğinde müşteriye otomatik SMS gönderilir.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Kargoya verildi e-posta bildirimi', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş kargoya verildiğinde müşteriye otomatik e-posta gönderilir.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Yaymail ile "kargoya verildi" e-posta içeriğini özelleştirme', 'hezarfen-for-woocommerce' ); ?></li>
                        <li><?php esc_html_e( 'Kapıda ödemeli siparişlere SMS doğrulaması', 'hezarfen-for-woocommerce' ); ?></li>
                    </ul>

                    <div class="hezarfen-feature-group"><?php esc_html_e( 'Hesabım Sayfası', 'hezarfen-for-woocommerce' ); ?></div>
                    <ul>
                        <li><?php esc_html_e( 'Kurumsal/bireysel ve fatura alanları', 'hezarfen-for-woocommerce' ); ?></li>
                        <li><?php esc_html_e( 'SMS ile giriş (telefon numarasıyla giriş, şifre gerekmeden)', 'hezarfen-for-woocommerce' ); ?></li>
                    </ul>

                    <div class="hezarfen-feature-group"><?php esc_html_e( 'Entegrasyonlar', 'hezarfen-for-woocommerce' ); ?></div>
                    <ul>
                        <li><?php esc_html_e( 'FunnelKit ödeme ekranına ilçe/mahalle desteği', 'hezarfen-for-woocommerce' ); ?></li>
                    </ul>
                    <div class="hezarfen-cta-container" data-package="ucus" data-admin-email="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"></div>
                </div>

                <!-- Pro Paket -->
                <div class="hezarfen-package">
                    <div class="hezarfen-package-header">
                        <h2><?php esc_html_e( 'Pro', 'hezarfen-for-woocommerce' ); ?></h2>
                        <div class="hezarfen-package-price">
                            <span class="hezarfen-price-skeleton price-main" data-price-key="pro"></span>
                            <small><span class="hezarfen-price-skeleton price-small" data-price-suffix="pro"></span></small>
                        </div>
                    </div>
                    <div class="hezarfen-feature-group"><?php esc_html_e( 'Kargo Entegrasyonu', 'hezarfen-for-woocommerce' ); ?></div>
                    <ul>
                        <li><?php esc_html_e( 'Sınırsız sipariş için kargo entegrasyonu', 'hezarfen-for-woocommerce' ); ?></li>
                        <li><?php esc_html_e( 'Yurtiçi, Aras, Sürat, Hepsijet, DHL E-Com, Kolay Gelsin', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Kendi anlaşmanızla bu kargo firmalarına entegrasyon sağlayabilirsiniz.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Kargo barkodu oluşturma', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş adresi barkod ile kargo firmasına iletilir, hatalı adres girişi engellenir.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Barkod okutulunca sipariş durumu otomatik "Kargoya Verildi" olur', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Kargo firması barkodu okuttuğunda sipariş durumu otomatik olarak "Kargoya Verildi" olur.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Teslimatta sipariş durumu otomatik "Tamamlandı" olur', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş alıcıya teslim edilince sipariş durumu otomatik olarak "Tamamlandı" durumuna geçer.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Kargo takip numarası siparişe otomatik girilir', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Kargo takip numarası otomatik olarak siparişe (Hezarfen kargo takip alanına) girilir.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Detaylı kargo hareketleri sipariş detayında ve kargo takip ekranında görüntülenir', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Kargo firmasının sitesine gitmeden, kargonuzun anlık olarak nerede olduğunu zaman çizelgesi şeklinde görüntüleyin.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                    </ul>

                    <div class="hezarfen-feature-group"><?php esc_html_e( 'Bildirimler', 'hezarfen-for-woocommerce' ); ?></div>
                    <ul>
                        <li><?php esc_html_e( 'Kargoya verildi SMS bildirimi', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş kargoya verildiğinde müşteriye otomatik SMS gönderilir.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Kargoya verildi e-posta bildirimi', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş kargoya verildiğinde müşteriye otomatik e-posta gönderilir.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Yaymail ile "kargoya verildi" e-posta içeriğini özelleştirme', 'hezarfen-for-woocommerce' ); ?></li>
                        <li><?php esc_html_e( 'Kapıda ödemeli siparişlere SMS doğrulaması', 'hezarfen-for-woocommerce' ); ?></li>
                    </ul>

                    <div class="hezarfen-feature-group"><?php esc_html_e( 'Hesabım Sayfası', 'hezarfen-for-woocommerce' ); ?></div>
                    <ul>
                        <li><?php esc_html_e( 'Kurumsal/bireysel ve fatura alanları', 'hezarfen-for-woocommerce' ); ?></li>
                        <li><?php esc_html_e( 'SMS ile giriş (telefon numarasıyla giriş, şifre gerekmeden)', 'hezarfen-for-woocommerce' ); ?></li>
                    </ul>

                    <div class="hezarfen-feature-group"><?php esc_html_e( 'Entegrasyonlar', 'hezarfen-for-woocommerce' ); ?></div>
                    <ul>
                        <li><?php esc_html_e( 'FunnelKit ödeme ekranına ilçe/mahalle desteği', 'hezarfen-for-woocommerce' ); ?></li>
                        <li><?php esc_html_e( 'Paraşüt ile fatura kesme entegrasyonu', 'hezarfen-for-woocommerce' ); ?></li>
                    </ul>
                    <div class="hezarfen-cta-container" data-package="pro" data-admin-email="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"></div>
                </div>
            </div>

            <div style="margin-top: 40px; text-align: center;">
                <h2><?php esc_html_e( 'Ücretsiz Özellikler', 'hezarfen-for-woocommerce' ); ?></h2>
                <p style="color: #666; max-width: 800px; margin: 0 auto 20px;">
                    <?php esc_html_e( 'Aşağıdaki özellikler tüm kullanıcılar için ücretsizdir:', 'hezarfen-for-woocommerce' ); ?>
                </p>
                <div class="hezarfen-package free" style="max-width: 600px; margin: 0 auto;">
                    <ul>
                        <li><?php esc_html_e( 'Kargokit anlaşmasıyla Hepsijet Entegrasyonu (0-4 desi:', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-price-skeleton price-inline" data-price-key="kargokit_hepsijet"></span><?php esc_html_e( '+KDV - Tüm Türkiye, adresten alım adrese gönderim)', 'hezarfen-for-woocommerce' ); ?></li>
                        <li><?php esc_html_e( 'Sipariş durumlarına göre SMS oluşturabilme', 'hezarfen-for-woocommerce' ); ?></li>
                        <li><?php esc_html_e( 'Kargoya verildi durumunda SMS oluşturabilme', 'hezarfen-for-woocommerce' ); ?></li>
                        <li><?php esc_html_e( 'Sınırsız farklı ödeme ekranı sözleşme tipi ekleyebilme', 'hezarfen-for-woocommerce' ); ?></li>
                        <li><?php esc_html_e( 'Sözleşmelerin anlık olarak ödeme ekranında güncellenmesi', 'hezarfen-for-woocommerce' ); ?></li>
                        <li><?php esc_html_e( 'İlçe ve mahalle alanının ödeme ekranında gösterimi', 'hezarfen-for-woocommerce' ); ?></li>
                        <li><?php esc_html_e( 'Bireysel/kurumsal ve vergi bilgileri alanları', 'hezarfen-for-woocommerce' ); ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <script>
        (function() {
            const PRICING_URL = 'https://hezarfen-r2.intense.com.tr/plugin-assets/pricing.json';
            const AJAX_URL = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
            const NONCE = '<?php echo esc_js( wp_create_nonce( 'hezarfen_demand_nonce' ) ); ?>';
            const PACKAGE_URLS = {
                kanat: 'https://intense.com.tr/hezarfen-kanat',
                ucus: 'https://intense.com.tr/hezarfen-ucus',
                pro: 'https://intense.com.tr/hezarfen-pro'
            };

            const TEXTS = {
                buy: '<?php echo esc_js( __( 'Satın Al', 'hezarfen-for-woocommerce' ) ); ?>',
                demand: '<?php echo esc_js( __( 'Talep Bırak', 'hezarfen-for-woocommerce' ) ); ?>',
                sending: '<?php echo esc_js( __( 'Gönderiliyor...', 'hezarfen-for-woocommerce' ) ); ?>',
                sent: '<?php echo esc_js( __( 'Gönderildi!', 'hezarfen-for-woocommerce' ) ); ?>',
                emailLabel: '<?php echo esc_js( __( 'Bildirim e-postası', 'hezarfen-for-woocommerce' ) ); ?>',
                emailLabelHint: '<?php echo esc_js( __( '(satışa açıldığında haber verelim)', 'hezarfen-for-woocommerce' ) ); ?>',
                emailPlaceholder: '<?php echo esc_js( __( 'ornek@siteniz.com', 'hezarfen-for-woocommerce' ) ); ?>',
                emailHintLine1: '<?php echo esc_js( __( 'Sitenizin e-posta altyapısı kullanılarak info@intense.com.tr adresine gönderilir.', 'hezarfen-for-woocommerce' ) ); ?>',
                emailHintLine2: '<?php echo esc_js( __( 'Paylaşılan: site URL, e-posta, paket.', 'hezarfen-for-woocommerce' ) ); ?>',
                demandNotice: '<?php echo esc_js( __( 'Yeterli talep gelirse Aralık Sonu - Ocak ilk haftası satışa açılacaktır.', 'hezarfen-for-woocommerce' ) ); ?>',
                invalidEmail: '<?php echo esc_js( __( 'Geçerli bir e-posta adresi girin.', 'hezarfen-for-woocommerce' ) ); ?>'
            };

            function formatPrice(price) {
                return new Intl.NumberFormat('tr-TR', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 2
                }).format(price) + '₺';
            }

            function renderCtaContainer(packageKey, packageData) {
                const container = document.querySelector('[data-package="' + packageKey + '"]');
                if (!container || !packageData) return;

                const adminEmail = container.dataset.adminEmail || '';
                const url = PACKAGE_URLS[packageKey];
                const isPreorder = packageData.availability && packageData.availability.status === 'preorder';

                if (isPreorder) {
                    container.innerHTML = `
                        <div class="hezarfen-demand-form">
                            <label for="hezarfen_demand_email_${packageKey}">
                                ${TEXTS.emailLabel}
                                <span>${TEXTS.emailLabelHint}</span>
                            </label>
                            <div class="hezarfen-demand-email-wrap">
                                <input type="email" class="hezarfen-demand-email" id="hezarfen_demand_email_${packageKey}" name="hezarfen_demand_email_${packageKey}" value="${adminEmail}" placeholder="${TEXTS.emailPlaceholder}" />
                            </div>
                            <p class="hezarfen-demand-hint">${TEXTS.emailHintLine1}<br>${TEXTS.emailHintLine2}</p>
                            <button type="button" class="hezarfen-cta demand" data-package="${packageKey}">${TEXTS.demand}</button>
                        </div>
                        <p class="hezarfen-demand-notice">${TEXTS.demandNotice}</p>
                    `;

                    // Add click handler for demand button
                    const btn = container.querySelector('.hezarfen-cta.demand');
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        submitDemand(packageKey);
                    });
                } else {
                    container.innerHTML = `
                        <a href="${url}" class="hezarfen-cta" target="_blank">${TEXTS.buy}</a>
                    `;
                }
            }

            function submitDemand(packageKey) {
                const emailInput = document.getElementById('hezarfen_demand_email_' + packageKey);
                const btn = document.querySelector('[data-package="' + packageKey + '"] .hezarfen-cta.demand');
                const email = emailInput ? emailInput.value.trim() : '';

                // Simple email validation
                if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    alert(TEXTS.invalidEmail);
                    emailInput.focus();
                    return;
                }

                // Disable button and show loading
                btn.disabled = true;
                btn.textContent = TEXTS.sending;

                const formData = new FormData();
                formData.append('action', 'hezarfen_submit_demand');
                formData.append('nonce', NONCE);
                formData.append('email', email);
                formData.append('package', packageKey);

                fetch(AJAX_URL, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        btn.textContent = TEXTS.sent;
                        btn.style.background = '#46b450';
                        emailInput.disabled = true;
                    } else {
                        alert(data.data.message || 'Bir hata oluştu.');
                        btn.disabled = false;
                        btn.textContent = TEXTS.demand;
                    }
                })
                .catch(error => {
                    console.error('Demand submission error:', error);
                    alert('Bir hata oluştu. Lütfen tekrar deneyin.');
                    btn.disabled = false;
                    btn.textContent = TEXTS.demand;
                });
            }

            function updatePrices(data) {
                // Update prices for each package
                ['kanat', 'ucus', 'pro'].forEach(function(key) {
                    const packageContainer = document.querySelector('[data-package-container="' + key + '"]');
                    const priceEl = document.querySelector('[data-price-key="' + key + '"]');
                    const suffixEl = document.querySelector('[data-price-suffix="' + key + '"]');

                    // Show/hide package container based on JSON data (only for kanat and ucus)
                    if (packageContainer && (key === 'kanat' || key === 'ucus')) {
                        if (data[key]) {
                            packageContainer.style.display = '';
                        } else {
                            packageContainer.style.display = 'none';
                        }
                    }

                    if (priceEl && data[key]) {
                        priceEl.textContent = formatPrice(data[key].price);
                        priceEl.classList.remove('hezarfen-price-skeleton', 'price-main');
                    }
                    if (suffixEl && data[key]) {
                        suffixEl.textContent = '+KDV / 1 yıllık';
                        suffixEl.classList.remove('hezarfen-price-skeleton', 'price-small');
                    }
                    // Render CTA based on availability
                    if (data[key]) {
                        renderCtaContainer(key, data[key]);
                    }
                });

                // Update Kargokit Hepsijet price
                const kargokitPrice = document.querySelector('[data-price-key="kargokit_hepsijet"]');
                if (kargokitPrice && data.kargokit_pricing && data.kargokit_pricing.hepsijet) {
                    kargokitPrice.textContent = formatPrice(data.kargokit_pricing.hepsijet['0_4desi']);
                    kargokitPrice.classList.remove('hezarfen-price-skeleton', 'price-inline');
                }
            }

            function fetchPrices() {
                fetch(PRICING_URL)
                    .then(response => response.json())
                    .then(data => updatePrices(data))
                    .catch(error => {
                        console.error('Hezarfen pricing fetch error:', error);
                    });
            }

            // Fetch prices when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', fetchPrices);
            } else {
                fetchPrices();
            }
        })();
        </script>
        <?php
    }

    /**
     * Highlight parent menu when on WooCommerce Hezarfen settings
     *
     * @param string $parent_file Parent file.
     * @return string
     */
    public function highlight_menu( $parent_file ) {
        global $plugin_page, $submenu_file;

        if ( isset( $_GET['page'] ) && $_GET['page'] === 'wc-settings' && isset( $_GET['tab'] ) && $_GET['tab'] === 'hezarfen' ) {
            // Override plugin_page to prevent WooCommerce menu from being highlighted
            $plugin_page = 'admin.php?page=wc-settings&tab=hezarfen';
            return self::MENU_SLUG;
        }

        return $parent_file;
    }

    /**
     * Highlight submenu when on WooCommerce Hezarfen settings
     *
     * @param string $submenu_file Submenu file.
     * @return string
     */
    public function highlight_submenu( $submenu_file ) {
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'wc-settings' && isset( $_GET['tab'] ) && $_GET['tab'] === 'hezarfen' ) {
            return 'admin.php?page=wc-settings&tab=hezarfen';
        }

        return $submenu_file;
    }
}
