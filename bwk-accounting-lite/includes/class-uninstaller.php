<?php
/**
 * Uninstaller: remove data if opted.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BWK_Uninstaller {
    public static function uninstall() {
        if ( ! get_option( 'bwk_accounting_remove_data' ) ) {
            return;
        }
        global $wpdb;
        $wpdb->query( 'DROP TABLE IF EXISTS ' . bwk_table_invoice_items() );
        $wpdb->query( 'DROP TABLE IF EXISTS ' . bwk_table_invoices() );
        $wpdb->query( 'DROP TABLE IF EXISTS ' . bwk_table_ledger() );
    }
}
