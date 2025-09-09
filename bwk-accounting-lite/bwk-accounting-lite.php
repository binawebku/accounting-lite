<?php
/*
Plugin Name: BWK Accounting Lite
Plugin URI: https://binawebpro.com/bwk-accounting-lite
Description: Lightweight accounting and invoicing plugin with WooCommerce ledger sync.
Version: 1.0.0
Author: Wan Mohd Aiman Binawebpro.com
Author URI: https://binawebpro.com
Text Domain: bwk-accounting-lite
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'BWK_AL_PATH' ) ) {
    define( 'BWK_AL_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'BWK_AL_URL' ) ) {
    define( 'BWK_AL_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'BWK_AL_VERSION' ) ) {
    define( 'BWK_AL_VERSION', '1.0.0' );
}

require_once BWK_AL_PATH . 'includes/helpers.php';
require_once BWK_AL_PATH . 'includes/class-autoloader.php';

BWK_Autoloader::register();

register_activation_hook( __FILE__, array( 'BWK_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BWK_Deactivator', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( 'BWK_Uninstaller', 'uninstall' ) );

function bwk_accounting_lite_init() {
    BWK_Activator::upgrade();
    BWK_Settings::init();
    BWK_Admin_Menu::init();
    BWK_Invoices::init();
    BWK_Quotes_Table::init();
    BWK_Ledger::init();
    BWK_Sync_WooCommerce::init();
    BWK_Ajax::init();
    BWK_Rest::init();
}
add_action( 'plugins_loaded', 'bwk_accounting_lite_init' );
