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
                <div class="hezarfen-package">
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
                        <li><?php esc_html_e( 'Otomatik sipariş durumu güncelleme', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş kargoya verildiğinde otomatik olarak sipariş durumu \"Kargoya Verildi\" olur.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Otomatik takip numarası aktarımı', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş kargoya verildiğinde takip numarası otomatik olarak Hezarfen\'e iletilir.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Kargo takip ekranında detaylı kargo hareketleri', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Kargo firmasının API\'sinden çekilen bilgilerle, kargonuzun anlık olarak nerede olduğunu zaman çizelgesi şeklinde görüntüleyin.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                    </ul>

                    <div class="hezarfen-feature-group"><?php esc_html_e( 'Bildirimler', 'hezarfen-for-woocommerce' ); ?></div>
                    <ul>
                        <li><?php esc_html_e( 'Kargoya verildi SMS bildirimi', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş kargoya verildiğinde müşteriye otomatik SMS gönderilir.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Kargoya verildi e-posta bildirimi', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş kargoya verildiğinde müşteriye otomatik e-posta gönderilir.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Yaymail ile e-posta özelleştirme', 'hezarfen-for-woocommerce' ); ?></li>
                        <li><?php esc_html_e( 'Kapıda ödemeli siparişlere SMS doğrulaması', 'hezarfen-for-woocommerce' ); ?></li>
                    </ul>

                    <div class="hezarfen-feature-group"><?php esc_html_e( 'Hesabım Sayfası', 'hezarfen-for-woocommerce' ); ?></div>
                    <ul>
                        <li><?php esc_html_e( 'Kurumsal/bireysel ve fatura alanları', 'hezarfen-for-woocommerce' ); ?></li>
                    </ul>
                    <a href="https://intense.com.tr/hezarfen-kanat" class="hezarfen-cta" target="_blank">
                        <?php esc_html_e( 'Satın Al', 'hezarfen-for-woocommerce' ); ?>
                    </a>
                </div>

                <!-- Uçuş Paket -->
                <div class="hezarfen-package featured">
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
                        <li><?php esc_html_e( 'Otomatik sipariş durumu güncelleme', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş kargoya verildiğinde otomatik olarak sipariş durumu \"Kargoya Verildi\" olur.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Otomatik takip numarası aktarımı', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş kargoya verildiğinde takip numarası otomatik olarak Hezarfen\'e iletilir.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Kargo takip ekranında detaylı kargo hareketleri', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Kargo firmasının API\'sinden çekilen bilgilerle, kargonuzun anlık olarak nerede olduğunu zaman çizelgesi şeklinde görüntüleyin.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                    </ul>

                    <div class="hezarfen-feature-group"><?php esc_html_e( 'Bildirimler', 'hezarfen-for-woocommerce' ); ?></div>
                    <ul>
                        <li><?php esc_html_e( 'Kargoya verildi SMS bildirimi', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş kargoya verildiğinde müşteriye otomatik SMS gönderilir.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Kargoya verildi e-posta bildirimi', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş kargoya verildiğinde müşteriye otomatik e-posta gönderilir.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Yaymail ile e-posta özelleştirme', 'hezarfen-for-woocommerce' ); ?></li>
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
                    <a href="https://intense.com.tr/hezarfen-ucus" class="hezarfen-cta" target="_blank">
                        <?php esc_html_e( 'Satın Al', 'hezarfen-for-woocommerce' ); ?>
                    </a>
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
                        <li><?php esc_html_e( 'Otomatik sipariş durumu güncelleme', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş kargoya verildiğinde otomatik olarak sipariş durumu \"Kargoya Verildi\" olur.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Otomatik takip numarası aktarımı', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş kargoya verildiğinde takip numarası otomatik olarak Hezarfen\'e iletilir.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Kargo takip ekranında detaylı kargo hareketleri', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Kargo firmasının API\'sinden çekilen bilgilerle, kargonuzun anlık olarak nerede olduğunu zaman çizelgesi şeklinde görüntüleyin.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                    </ul>

                    <div class="hezarfen-feature-group"><?php esc_html_e( 'Bildirimler', 'hezarfen-for-woocommerce' ); ?></div>
                    <ul>
                        <li><?php esc_html_e( 'Kargoya verildi SMS bildirimi', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş kargoya verildiğinde müşteriye otomatik SMS gönderilir.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Kargoya verildi e-posta bildirimi', 'hezarfen-for-woocommerce' ); ?> <span class="hezarfen-tooltip" data-tooltip="<?php esc_attr_e( 'Sipariş kargoya verildiğinde müşteriye otomatik e-posta gönderilir.', 'hezarfen-for-woocommerce' ); ?>">ⓘ</span></li>
                        <li><?php esc_html_e( 'Yaymail ile e-posta özelleştirme', 'hezarfen-for-woocommerce' ); ?></li>
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
                    <a href="https://intense.com.tr/hezarfen-pro" class="hezarfen-cta" target="_blank">
                        <?php esc_html_e( 'Satın Al', 'hezarfen-for-woocommerce' ); ?>
                    </a>
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
                        <li><?php esc_html_e( 'Sözleşmelerin sipariş durumuna göre güncellenmesi', 'hezarfen-for-woocommerce' ); ?></li>
                        <li><?php esc_html_e( 'İlçe ve mahalle alanının ödeme ekranında gösterimi', 'hezarfen-for-woocommerce' ); ?></li>
                        <li><?php esc_html_e( 'Bireysel/kurumsal ve vergi bilgileri alanları', 'hezarfen-for-woocommerce' ); ?></li>
                        <li><?php esc_html_e( 'Hesabım sayfasında telefon + şifre ile giriş', 'hezarfen-for-woocommerce' ); ?></li>
                        <li><?php esc_html_e( 'Kullanıcı dostu kargo takip ekranı', 'hezarfen-for-woocommerce' ); ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <script>
        (function() {
            const PRICING_URL = 'https://hezarfen-r2.intense.com.tr/plugin-assets/pricing.json';

            function formatPrice(price) {
                return new Intl.NumberFormat('tr-TR', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 2
                }).format(price) + '₺';
            }

            function updatePrices(data) {
                // Update Kanat price
                const kanatPrice = document.querySelector('[data-price-key="kanat"]');
                const kanatSuffix = document.querySelector('[data-price-suffix="kanat"]');
                if (kanatPrice && data.kanat) {
                    kanatPrice.textContent = formatPrice(data.kanat.price);
                    kanatPrice.classList.remove('hezarfen-price-skeleton', 'price-main');
                }
                if (kanatSuffix && data.kanat) {
                    kanatSuffix.textContent = '+KDV / 1 yıllık';
                    kanatSuffix.classList.remove('hezarfen-price-skeleton', 'price-small');
                }

                // Update Ucus price
                const ucusPrice = document.querySelector('[data-price-key="ucus"]');
                const ucusSuffix = document.querySelector('[data-price-suffix="ucus"]');
                if (ucusPrice && data.ucus) {
                    ucusPrice.textContent = formatPrice(data.ucus.price);
                    ucusPrice.classList.remove('hezarfen-price-skeleton', 'price-main');
                }
                if (ucusSuffix && data.ucus) {
                    ucusSuffix.textContent = '+KDV / 1 yıllık';
                    ucusSuffix.classList.remove('hezarfen-price-skeleton', 'price-small');
                }

                // Update Pro price
                const proPrice = document.querySelector('[data-price-key="pro"]');
                const proSuffix = document.querySelector('[data-price-suffix="pro"]');
                if (proPrice && data.pro) {
                    proPrice.textContent = formatPrice(data.pro.price);
                    proPrice.classList.remove('hezarfen-price-skeleton', 'price-main');
                }
                if (proSuffix && data.pro) {
                    proSuffix.textContent = '+KDV / 1 yıllık';
                    proSuffix.classList.remove('hezarfen-price-skeleton', 'price-small');
                }

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
                        // Fallback prices if fetch fails
                        updatePrices({
                            kanat: { price: 1500 },
                            ucus: { price: 3000 },
                            pro: { price: 6000 },
                            kargokit_pricing: { hepsijet: { '0_4desi': 66.60 } }
                        });
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
