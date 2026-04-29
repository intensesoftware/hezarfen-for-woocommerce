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
        add_action( 'woocommerce_settings_hezarfen', array( $this, 'add_upgrade_button_to_settings' ) );
    }

    /**
     * Check if Hezarfen Pro is installed
     *
     * @return bool
     */
    private function is_pro_installed() {
        return false !== get_option( 'hezarfen_pro_db_version', false );
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

        // Only show upgrade menu if Hezarfen Pro is not installed
        if ( ! $this->is_pro_installed() ) {
            add_submenu_page(
                self::MENU_SLUG,
                __( 'Yükselt', 'hezarfen-for-woocommerce' ),
                __( 'Yükselt', 'hezarfen-for-woocommerce' ),
                'manage_options',
                self::UPGRADE_SLUG,
                array( $this, 'render_upgrade_page' )
            );
        }

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
        // Don't add upgrade styles if Hezarfen Pro is installed
        if ( $this->is_pro_installed() ) {
            return;
        }

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
            .hezarfen-package-sites {
                font-size: 12px;
                color: #666;
                margin-top: 5px;
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
            .hezarfen-hero-tagline {
                max-width: 720px;
                margin: 0 auto 50px;
                text-align: center;
                padding: 0 20px;
            }
            .hezarfen-hero-tagline p {
                margin: 0;
                font-size: 18px;
                line-height: 1.7;
                color: #1d2327;
                font-weight: 400;
                letter-spacing: -0.01em;
            }
            .hezarfen-hero-tagline .emphasis {
                color: #2271b1;
                font-weight: 600;
            }
            .hezarfen-hero-tagline .emphasis-dark {
                color: #1d2327;
                font-weight: 600;
            }
            .hezarfen-coming-soon {
                display: inline-block;
                background: #f0f0f1;
                color: #50575e;
                font-size: 10px;
                font-weight: 500;
                padding: 2px 6px;
                border-radius: 3px;
                margin-left: 6px;
                vertical-align: middle;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }
            .hezarfen-free-features {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
                gap: 20px;
                max-width: 1400px;
                margin: 0 auto;
                text-align: left;
            }
            .hezarfen-free-category {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                padding: 20px;
            }
            .hezarfen-free-category-title {
                font-size: 15px;
                font-weight: 600;
                color: #1d2327;
                margin: 0 0 15px 0;
                padding-bottom: 10px;
                border-bottom: 2px solid #46b450;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .hezarfen-free-category ul {
                list-style: none;
                padding: 0;
                margin: 0;
            }
            .hezarfen-free-category li {
                padding: 6px 0 6px 20px;
                position: relative;
                font-size: 13px;
                color: #50575e;
                line-height: 1.5;
            }
            .hezarfen-free-category li:before {
                content: "✓";
                position: absolute;
                left: 0;
                color: #46b450;
                font-weight: bold;
            }
            .hezarfen-free-category .sub-feature {
                padding-left: 35px;
                font-size: 12px;
                color: #666;
            }
            .hezarfen-free-category .sub-feature:before {
                content: "→";
                color: #999;
            }
            .hezarfen-free-category .highlight-note {
                background: #f0f6fc;
                border-left: 3px solid #2271b1;
                padding: 8px 12px;
                margin: 10px 0;
                font-size: 12px;
                color: #1d2327;
            }
            .hezarfen-free-category .price-highlight {
                background: #f7f7f7;
                padding: 10px 12px;
                border-radius: 4px;
                margin: 8px 0;
                font-size: 12px;
            }
            .hezarfen-packages-loading {
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 300px;
            }
            .hezarfen-packages-loading .spinner {
                float: none;
                margin: 0;
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
            <div class="hezarfen-hero-tagline">
                <p><?php echo wp_kses( __( 'Sipariş yönetiminde <span class="emphasis">manuel işlemlerle kaybettiğiniz</span> her dakika, <span class="emphasis-dark">işinizi büyütmek</span> için kullanabileceğiniz bir dakikadır. Hezarfen ile müşteri deneyimini iyileştirin, operasyonel süreçlerinizi otomatikleştirin.', 'hezarfen-for-woocommerce' ), array( 'span' => array( 'class' => array() ) ) ); ?></p>
            </div>

            <div class="hezarfen-packages" id="hezarfen-packages-container" data-admin-email="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
                <!-- Paketler JSON'dan dinamik olarak yüklenecek -->
                <div class="hezarfen-packages-loading">
                    <span class="spinner is-active"></span>
                </div>
            </div>

            <div style="margin-top: 40px; text-align: center;">
                <h2><?php esc_html_e( 'Ücretsiz Özellikler', 'hezarfen-for-woocommerce' ); ?></h2>
                <p style="color: #666; max-width: 800px; margin: 0 auto 20px;">
                    <?php esc_html_e( 'Aşağıdaki özellikler tüm kullanıcılar için ücretsizdir:', 'hezarfen-for-woocommerce' ); ?>
                </p>

                <div class="hezarfen-free-features">
                    <!-- Kargo & Gönderim -->
                    <div class="hezarfen-free-category">
                        <h3 class="hezarfen-free-category-title">🚚 <?php esc_html_e( 'Kargo & Gönderim', 'hezarfen-for-woocommerce' ); ?></h3>
                        <ul>
                            <li><?php esc_html_e( 'Kargokit anlaşmasıyla Hepsijet WooCommerce entegrasyonu', 'hezarfen-for-woocommerce' ); ?></li>
                        </ul>
                        <div class="price-highlight">
                            <?php esc_html_e( '1–4 Desi:', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-price-skeleton price-inline" data-price-key="kargokit_hepsijet"></span> <?php esc_html_e( '+KDV – Tüm Türkiye, adresten alım & adrese teslim', 'hezarfen-for-woocommerce' ); ?>
                        </div>
                        <div class="highlight-note">
                            <?php esc_html_e( 'Minimum gönderim limiti yoktur, ek sözleşme gerekmez.', 'hezarfen-for-woocommerce' ); ?>
                        </div>
                        <p style="font-size: 12px; color: #666; margin: 10px 0 5px;"><?php esc_html_e( 'Kargokit üzerinden oluşturulan gönderilerde:', 'hezarfen-for-woocommerce' ); ?></p>
                        <ul>
                            <li class="sub-feature"><?php esc_html_e( 'WooCommerce sipariş düzenleme ekranından kargo barkodu oluşturabilme', 'hezarfen-for-woocommerce' ); ?></li>
                            <li class="sub-feature"><?php esc_html_e( 'Kargo takip numarasının siparişe otomatik olarak işlenmesi', 'hezarfen-for-woocommerce' ); ?></li>
                            <li class="sub-feature"><?php esc_html_e( 'Sipariş durumunun otomatik olarak "Kargoya Verildi" ve ardından "Tamamlandı" olarak güncellenmesi', 'hezarfen-for-woocommerce' ); ?></li>
                            <li class="sub-feature"><?php esc_html_e( 'Müşteriye otomatik e-posta ve SMS bilgilendirmeleri', 'hezarfen-for-woocommerce' ); ?></li>
                        </ul>
                    </div>

                    <!-- Kargo Takip & Sipariş Yönetimi -->
                    <div class="hezarfen-free-category">
                        <h3 class="hezarfen-free-category-title">📦 <?php esc_html_e( 'Kargo Takip & Sipariş Yönetimi', 'hezarfen-for-woocommerce' ); ?></h3>
                        <ul>
                            <li><?php esc_html_e( '23 farklı kargo firması için kargo takip desteği', 'hezarfen-for-woocommerce' ); ?></li>
                            <li><?php esc_html_e( 'Tüm desteklenen kargo firmaları için manuel kargo takip numarası girişi', 'hezarfen-for-woocommerce' ); ?></li>
                        </ul>
                        <p style="font-size: 12px; color: #666; margin: 10px 0 5px;"><?php esc_html_e( 'Manuel takip numarası girildiğinde:', 'hezarfen-for-woocommerce' ); ?></p>
                        <ul>
                            <li class="sub-feature"><?php esc_html_e( 'Siparişin otomatik olarak "Kargoya Verildi" durumuna geçmesi', 'hezarfen-for-woocommerce' ); ?></li>
                            <li class="sub-feature"><?php esc_html_e( 'E-posta bildirimi', 'hezarfen-for-woocommerce' ); ?></li>
                            <li class="sub-feature"><?php esc_html_e( 'SMS gönderimi', 'hezarfen-for-woocommerce' ); ?></li>
                        </ul>
                        <p style="font-size: 12px; color: #666; margin: 10px 0 5px;"><?php esc_html_e( 'Müşteri hesabım sayfasında:', 'hezarfen-for-woocommerce' ); ?></p>
                        <ul>
                            <li class="sub-feature"><?php esc_html_e( 'Kargo firması', 'hezarfen-for-woocommerce' ); ?></li>
                            <li class="sub-feature"><?php esc_html_e( 'Kargo takip numarası', 'hezarfen-for-woocommerce' ); ?></li>
                            <li class="sub-feature"><?php esc_html_e( 'Takip linkinin görüntülenmesi', 'hezarfen-for-woocommerce' ); ?></li>
                        </ul>
                    </div>

                    <!-- SMS & Bildirim Otomasyonu -->
                    <div class="hezarfen-free-category">
                        <h3 class="hezarfen-free-category-title">📩 <?php esc_html_e( 'SMS & Bildirim Otomasyonu', 'hezarfen-for-woocommerce' ); ?></h3>
                        <ul>
                            <li><?php esc_html_e( 'NetGSM dahili entegrasyonu (NetGSM eklentisi gerektirmez)', 'hezarfen-for-woocommerce' ); ?></li>
                            <li><?php esc_html_e( 'Sipariş durumlarına göre otomatik SMS gönderimi', 'hezarfen-for-woocommerce' ); ?></li>
                            <li><?php esc_html_e( '"Kargoya Verildi" durumunda otomatik SMS bildirimi', 'hezarfen-for-woocommerce' ); ?></li>
                        </ul>
                    </div>

                    <!-- Sözleşme & Hukuki Uyum -->
                    <div class="hezarfen-free-category">
                        <h3 class="hezarfen-free-category-title">📋 <?php esc_html_e( 'Sözleşme & Hukuki Uyum (MSS)', 'hezarfen-for-woocommerce' ); ?></h3>
                        <ul>
                            <li><?php esc_html_e( 'Sınırsız sayıda sözleşme tipi ekleyebilme (Mesafeli Satış, Ön Bilgilendirme, Cayma Hakkı vb.)', 'hezarfen-for-woocommerce' ); ?></li>
                            <li><?php esc_html_e( 'WordPress sayfalarını sözleşme şablonu olarak kullanabilme', 'hezarfen-for-woocommerce' ); ?></li>
                        </ul>
                        <p style="font-size: 12px; color: #666; margin: 10px 0 5px;"><?php esc_html_e( 'Ödeme ekranında sözleşmelerin:', 'hezarfen-for-woocommerce' ); ?></p>
                        <ul>
                            <li class="sub-feature"><?php esc_html_e( 'Sayfa içi veya modal olarak gösterilmesi', 'hezarfen-for-woocommerce' ); ?></li>
                            <li class="sub-feature"><?php esc_html_e( 'Anlık ve dinamik olarak güncellenmesi', 'hezarfen-for-woocommerce' ); ?></li>
                            <li class="sub-feature"><?php esc_html_e( 'Siparişe özel değişkenlerin otomatik işlenmesi (müşteri adı, ürün bilgileri, tarih vb.)', 'hezarfen-for-woocommerce' ); ?></li>
                        </ul>
                        <ul>
                            <li><?php esc_html_e( 'Hangi sözleşmelerin zorunlu onay gerektirdiğini belirleyebilme', 'hezarfen-for-woocommerce' ); ?></li>
                            <li><?php esc_html_e( 'Sipariş bazlı sözleşme arşivleme', 'hezarfen-for-woocommerce' ); ?></li>
                            <li><?php esc_html_e( 'Sözleşmelerin sipariş e-postalarına otomatik eklenmesi', 'hezarfen-for-woocommerce' ); ?></li>
                            <li><?php esc_html_e( 'Müşteri hesabım sayfasında sözleşmelere erişim', 'hezarfen-for-woocommerce' ); ?></li>
                        </ul>
                    </div>

                    <!-- Türkiye'ye Özel Checkout Çözümleri -->
                    <div class="hezarfen-free-category">
                        <h3 class="hezarfen-free-category-title">🇹🇷 <?php esc_html_e( "Türkiye'ye Özel Checkout Çözümleri", 'hezarfen-for-woocommerce' ); ?></h3>
                        <ul>
                            <li><?php esc_html_e( 'İl / ilçe / mahalle alanlarının ödeme ekranında gösterimi', 'hezarfen-for-woocommerce' ); ?></li>
                            <li><?php esc_html_e( 'Türkiye standartlarına uygun adres alanı sıralaması', 'hezarfen-for-woocommerce' ); ?></li>
                            <li><?php esc_html_e( 'Posta kodu alanını tek tıkla kaldırabilme', 'hezarfen-for-woocommerce' ); ?></li>
                            <li><?php esc_html_e( "Ödeme formunun Türkiye'ye göre otomatik optimize edilmesi", 'hezarfen-for-woocommerce' ); ?></li>
                        </ul>
                    </div>

                    <!-- Fatura & Kimlik Alanları -->
                    <div class="hezarfen-free-category">
                        <h3 class="hezarfen-free-category-title">💼 <?php esc_html_e( 'Fatura & Kimlik Alanları', 'hezarfen-for-woocommerce' ); ?></h3>
                        <ul>
                            <li><?php esc_html_e( 'Bireysel / Kurumsal fatura seçimi', 'hezarfen-for-woocommerce' ); ?></li>
                            <li><?php esc_html_e( 'TC Kimlik No alanı', 'hezarfen-for-woocommerce' ); ?></li>
                            <li><?php esc_html_e( '11 haneli TC Kimlik No doğrulama', 'hezarfen-for-woocommerce' ); ?></li>
                            <li><?php esc_html_e( 'Vergi No ve Vergi Dairesi alanları', 'hezarfen-for-woocommerce' ); ?></li>
                            <li><?php esc_html_e( 'Fatura alanlarının ödeme ekranında dinamik gösterimi', 'hezarfen-for-woocommerce' ); ?></li>
                        </ul>
                    </div>

                    <!-- Güvenlik -->
                    <div class="hezarfen-free-category">
                        <h3 class="hezarfen-free-category-title">🔒 <?php esc_html_e( 'Güvenlik', 'hezarfen-for-woocommerce' ); ?></h3>
                        <ul>
                            <li><?php esc_html_e( 'TC Kimlik Numaralarının şifrelenerek saklanması', 'hezarfen-for-woocommerce' ); ?></li>
                            <li><?php esc_html_e( 'Güvenli anahtar yönetimi', 'hezarfen-for-woocommerce' ); ?></li>
                            <li><?php esc_html_e( 'Hassas veriler için WordPress standartlarına uygun koruma', 'hezarfen-for-woocommerce' ); ?></li>
                        </ul>
                    </div>

                    <!-- Performans & Stabilite -->
                    <div class="hezarfen-free-category">
                        <h3 class="hezarfen-free-category-title">⚡ <?php esc_html_e( 'Performans & Stabilite', 'hezarfen-for-woocommerce' ); ?></h3>
                        <ul>
                            <li><?php esc_html_e( 'WooCommerce ve yüksek trafikli sitelerle uyumlu mimari', 'hezarfen-for-woocommerce' ); ?></li>
                            <li><?php esc_html_e( 'Hafif, stabil ve ölçeklenebilir yapı', 'hezarfen-for-woocommerce' ); ?></li>
                        </ul>
                    </div>

                    <!-- KVKK Uyumluluğu -->
                    <div class="hezarfen-free-category">
                        <h3 class="hezarfen-free-category-title">🛡️ <?php esc_html_e( 'KVKK Uyumluluğu', 'hezarfen-for-woocommerce' ); ?></h3>
                        <ul>
                            <li><?php esc_html_e( 'Yurt dışı sunuculara veri aktaran SaaS çözümlerine alternatif güvenli yapı', 'hezarfen-for-woocommerce' ); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function() {
            const PRICING_URL = 'https://hezarfen-r2.intense.com.tr/plugin-assets/pricing.json';
            const AJAX_URL = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
            const NONCE = '<?php echo esc_js( wp_create_nonce( 'hezarfen_demand_nonce' ) ); ?>';
            const BASE_URL = 'https://intense.com.tr';

            const TEXTS = {
                buy: '<?php echo esc_js( __( 'Satın Al', 'hezarfen-for-woocommerce' ) ); ?>',
                demand: '<?php echo esc_js( __( 'Talep Bırak', 'hezarfen-for-woocommerce' ) ); ?>',
                sending: '<?php echo esc_js( __( 'Gönderiliyor...', 'hezarfen-for-woocommerce' ) ); ?>',
                sent: '<?php echo esc_js( __( 'Gönderildi!', 'hezarfen-for-woocommerce' ) ); ?>',
                emailLabel: '<?php echo esc_js( __( 'Bildirim e-postası', 'hezarfen-for-woocommerce' ) ); ?>',
                emailLabelHint: '<?php echo esc_js( __( '(satışa açıldığında haber verelim)', 'hezarfen-for-woocommerce' ) ); ?>',
                emailPlaceholder: '<?php echo esc_js( __( 'ornek@siteniz.com', 'hezarfen-for-woocommerce' ) ); ?>',
                emailHintLine1: '<?php echo esc_js( __( 'Sitenizin e-posta altyapısı kullanılarak info@intense.com.tr adresine gönderilir.', 'hezarfen-for-woocommerce' ) ); ?>',
                emailHintLine2: '<?php echo esc_js( __( 'Paylaşılan: site URL, e-posta, seçtiğiniz paket.', 'hezarfen-for-woocommerce' ) ); ?>',
                demandNotice: '<?php echo esc_js( __( 'Yeterli talep gelirse Aralık Sonu - Ocak ilk haftası satışa açılacaktır.', 'hezarfen-for-woocommerce' ) ); ?>',
                invalidEmail: '<?php echo esc_js( __( 'Geçerli bir e-posta adresi girin.', 'hezarfen-for-woocommerce' ) ); ?>',
                comingSoon: '<?php echo esc_js( __( 'Yakında', 'hezarfen-for-woocommerce' ) ); ?>',
                site: '<?php echo esc_js( __( 'site', 'hezarfen-for-woocommerce' ) ); ?>',
                featureGroups: {
                    entegrasyonlar: '<?php echo esc_js( __( 'Entegrasyonlar', 'hezarfen-for-woocommerce' ) ); ?>',
                    hesabim_sayfasi: '<?php echo esc_js( __( 'Hesabım Sayfası', 'hezarfen-for-woocommerce' ) ); ?>',
                    bildirimler: '<?php echo esc_js( __( 'Bildirimler', 'hezarfen-for-woocommerce' ) ); ?>',
                    kargo_entegrasyonu: '<?php echo esc_js( __( 'Kargo Entegrasyonu', 'hezarfen-for-woocommerce' ) ); ?>'
                }
            };

            const PACKAGE_ORDER = ['standard', 'growth', 'pro'];

            function formatPrice(price) {
                return new Intl.NumberFormat('tr-TR', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 2
                }).format(price) + '₺';
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function renderFeatureItem(feature) {
                let html = '<li>' + escapeHtml(feature.text);
                if (feature.coming_soon) {
                    html += ' <span class="hezarfen-coming-soon">' + TEXTS.comingSoon + '</span>';
                } else if (feature.tooltip) {
                    html += ' <span class="hezarfen-tooltip" data-tooltip="' + escapeHtml(feature.tooltip) + '">ⓘ</span>';
                }
                html += '</li>';
                return html;
            }

            function renderFeatures(features) {
                if (!features) return '';
                let html = '';
                const groupOrder = ['entegrasyonlar', 'hesabim_sayfasi', 'bildirimler', 'kargo_entegrasyonu'];

                groupOrder.forEach(function(groupKey) {
                    if (features[groupKey] && features[groupKey].length > 0) {
                        html += '<div class="hezarfen-feature-group">' + (TEXTS.featureGroups[groupKey] || groupKey) + '</div>';
                        html += '<ul>';
                        features[groupKey].forEach(function(feature) {
                            html += renderFeatureItem(feature);
                        });
                        html += '</ul>';
                    }
                });
                return html;
            }

            function renderCtaContainer(packageKey, packageData, adminEmail) {
                const availability = packageData.availability || {};
                const isOnSale = availability.status === 'on_sale' && availability.purchase_enabled;
                const isPreorder = availability.status === 'preorder';

                if (isOnSale && availability.link_path) {
                    const url = BASE_URL + availability.link_path;
                    return '<div class="hezarfen-cta-container"><a href="' + url + '" class="hezarfen-cta" target="_blank" rel="noopener noreferrer nofollow">' + TEXTS.buy + '</a></div>';
                } else if (isPreorder) {
                    return `
                        <div class="hezarfen-cta-container" data-package="${packageKey}" data-admin-email="${adminEmail}">
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
                        </div>
                    `;
                }
                return '<div class="hezarfen-cta-container"></div>';
            }

            function renderPackage(key, packageData, adminEmail) {
                const isFeatured = packageData.featured === true;
                const sites = packageData.sites || 1;
                const packageName = key.charAt(0).toUpperCase() + key.slice(1);

                let html = '<div class="hezarfen-package' + (isFeatured ? ' featured' : '') + '" data-package-container="' + key + '">';
                html += '<div class="hezarfen-package-header">';
                html += '<h2>' + escapeHtml(packageName) + '</h2>';
                html += '<div class="hezarfen-package-price">';
                html += '<span>' + formatPrice(packageData.price) + '</span>';
                html += '<small>+KDV / 1 yıllık</small>';
                html += '</div>';
                html += '<div class="hezarfen-package-sites">' + sites + ' ' + TEXTS.site + '</div>';
                html += '</div>';
                html += renderFeatures(packageData.features);
                html += renderCtaContainer(key, packageData, adminEmail);
                html += '</div>';
                return html;
            }

            function renderPackages(data) {
                const container = document.getElementById('hezarfen-packages-container');
                if (!container) return;

                const adminEmail = container.dataset.adminEmail || '';
                let html = '';

                PACKAGE_ORDER.forEach(function(key) {
                    if (data[key]) {
                        html += renderPackage(key, data[key], adminEmail);
                    }
                });

                container.innerHTML = html;

                // Add click handlers for demand buttons
                container.querySelectorAll('.hezarfen-cta.demand').forEach(function(btn) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        submitDemand(btn.dataset.package);
                    });
                });

                // Update Kargokit Hepsijet price
                const kargokitPrice = document.querySelector('[data-price-key="kargokit_hepsijet"]');
                if (kargokitPrice && data.kargokit_pricing && data.kargokit_pricing.hepsijet) {
                    kargokitPrice.textContent = formatPrice(data.kargokit_pricing.hepsijet['0_4desi']);
                    kargokitPrice.classList.remove('hezarfen-price-skeleton', 'price-inline');
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

            function fetchAndRender() {
                fetch(PRICING_URL)
                    .then(response => response.json())
                    .then(data => renderPackages(data))
                    .catch(error => {
                        console.error('Hezarfen pricing fetch error:', error);
                        const container = document.getElementById('hezarfen-packages-container');
                        if (container) {
                            container.innerHTML = '<p style="text-align: center; color: #666;">Paket bilgileri yüklenemedi. Lütfen sayfayı yenileyin.</p>';
                        }
                    });
            }

            // Fetch and render when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', fetchAndRender);
            } else {
                fetchAndRender();
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
            // Intentionally overriding the WP global to prevent WooCommerce menu from being highlighted on our settings tab.
            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
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

    /**
     * Add upgrade button to WooCommerce Hezarfen settings page
     *
     * @return void
     */
    public function add_upgrade_button_to_settings() {
        // Don't show if Pro is installed
        if ( $this->is_pro_installed() ) {
            return;
        }

        $upgrade_url = admin_url( 'admin.php?page=' . self::UPGRADE_SLUG );
        ?>
        <style>
            .hezarfen-settings-upgrade-badge {
                display: inline-block;
                margin-left: 10px;
            }
            .hezarfen-settings-upgrade-badge a {
                display: inline-block;
                background: #46b450;
                color: #fff;
                padding: 6px 12px;
                border-radius: 4px;
                text-decoration: none;
                font-weight: 600;
                font-size: 12px;
                transition: all 0.2s ease;
                vertical-align: middle;
            }
            .hezarfen-settings-upgrade-badge a:hover {
                background: #3a9a42;
                color: #fff;
            }
        </style>
        <script>
        (function() {
            function addUpgradeBadge() {
                var badge = document.querySelector('.hezarfen-settings-upgrade-badge');
                var subsubsub = document.querySelector('.woocommerce .subsubsub');
                if (badge && subsubsub) {
                    subsubsub.appendChild(badge);
                }
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', addUpgradeBadge);
            } else {
                addUpgradeBadge();
            }
        })();
        </script>
        <div class="hezarfen-settings-upgrade-badge">
            <a href="<?php echo esc_url( $upgrade_url ); ?>">
                <?php esc_html_e( 'Yükselt', 'hezarfen-for-woocommerce' ); ?>
            </a>
        </div>
        <?php
    }
}
