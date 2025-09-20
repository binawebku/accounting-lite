<?php
/**
 * Admin menu and pages.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BWK_Admin_Menu {
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 100 );
    }

    public static function register_menu() {
        $cap = 'manage_options';
        add_menu_page( __( 'BWK Accounting', 'bwk-accounting-lite' ), __( 'BWK Accounting', 'bwk-accounting-lite' ), $cap, 'bwk-accounting', array( 'BWK_Invoices', 'render_list_page' ), 'dashicons-media-spreadsheet', 56 );
        add_submenu_page( 'bwk-accounting', __( 'Invoices', 'bwk-accounting-lite' ), __( 'Invoices', 'bwk-accounting-lite' ), $cap, 'bwk-accounting', array( 'BWK_Invoices', 'render_list_page' ) );
        add_submenu_page( 'bwk-accounting', __( 'Add New Invoice', 'bwk-accounting-lite' ), __( 'Add New', 'bwk-accounting-lite' ), $cap, 'bwk-invoice-add', array( 'BWK_Invoices', 'render_edit_page' ) );
        add_submenu_page( 'bwk-accounting', __( 'Quotes', 'bwk-accounting-lite' ), __( 'Quotes', 'bwk-accounting-lite' ), $cap, 'bwk-quotes', array( 'BWK_Quotes_Table', 'render_list_page' ) );
        add_submenu_page( 'bwk-accounting', __( 'Add New Quote', 'bwk-accounting-lite' ), __( 'Add Quote', 'bwk-accounting-lite' ), $cap, 'bwk-quote-add', array( 'BWK_Quotes_Table', 'render_edit_page' ) );
        add_submenu_page( 'bwk-accounting', __( 'Ledger', 'bwk-accounting-lite' ), __( 'Ledger', 'bwk-accounting-lite' ), $cap, 'bwk-ledger', array( 'BWK_Ledger', 'render_list_page' ) );
        add_submenu_page( 'bwk-accounting', __( 'Settings', 'bwk-accounting-lite' ), __( 'Settings', 'bwk-accounting-lite' ), $cap, 'bwk-settings', array( 'BWK_Settings', 'render_settings_page' ) );
    }

    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'bwk' ) === false ) {
            return;
        }
        wp_enqueue_style( 'bwk-admin', BWK_AL_URL . 'admin/css/admin.css', array(), BWK_AL_VERSION );
        wp_enqueue_script( 'bwk-admin', BWK_AL_URL . 'admin/js/admin.js', array( 'jquery' ), BWK_AL_VERSION, true );
        wp_localize_script(
            'bwk-admin',
            'bwkAdminData',
            array(
                'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
                'searchNonce'      => wp_create_nonce( 'bwk_search_products' ),
                'productsEnabled'  => class_exists( 'WooCommerce' ),
                'i18n'             => array(
                    'useProductLabel'   => __( 'Use existing product', 'bwk-accounting-lite' ),
                    'searchPlaceholder' => __( 'Search for a product…', 'bwk-accounting-lite' ),
                    'searchHelp'        => __( 'Start typing to search WooCommerce products.', 'bwk-accounting-lite' ),
                    'selectPrompt'      => __( 'Select a product', 'bwk-accounting-lite' ),
                    'searching'         => __( 'Searching…', 'bwk-accounting-lite' ),
                    'noResults'         => __( 'No products found.', 'bwk-accounting-lite' ),
                    'error'             => __( 'Unable to load products.', 'bwk-accounting-lite' ),
                    'noWooCommerce'     => __( 'WooCommerce must be active to search products.', 'bwk-accounting-lite' ),
                ),
            )
        );
    }
    public static function admin_bar($wp_admin_bar) {
        if ( ! bwk_current_user_can() ) {
            return;
        }
        include BWK_AL_PATH . 'admin/partials/topbar-shortcuts.php';
    }
}
