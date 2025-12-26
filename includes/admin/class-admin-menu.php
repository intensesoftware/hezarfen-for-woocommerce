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
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
        add_filter( 'parent_file', array( $this, 'highlight_menu' ) );
        add_filter( 'submenu_file', array( $this, 'highlight_submenu' ) );
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

        // Remove the auto-created duplicate submenu
        remove_submenu_page( self::MENU_SLUG, self::MENU_SLUG );
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
