<?php
/**
 * Activation tasks: create tables.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BWK_Activator {
    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $sql_invoices = "CREATE TABLE " . bwk_table_invoices() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            number varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft',
            customer_name varchar(200) NOT NULL,
            customer_email varchar(200) NOT NULL,
            billing_address text NULL,
            currency varchar(10) NOT NULL DEFAULT 'USD',
            subtotal decimal(18,2) NOT NULL DEFAULT 0,
            discount_total decimal(18,2) NOT NULL DEFAULT 0,
            tax_total decimal(18,2) NOT NULL DEFAULT 0,
            shipping_total decimal(18,2) NOT NULL DEFAULT 0,
            grand_total decimal(18,2) NOT NULL DEFAULT 0,
            notes text NULL,
            wc_order_id bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY number (number),
            KEY wc_order_id (wc_order_id)
        ) $charset_collate;";

        $sql_items = "CREATE TABLE " . bwk_table_invoice_items() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            invoice_id bigint(20) unsigned NOT NULL,
            line_no int NOT NULL,
            item_name varchar(200) NOT NULL,
            qty decimal(18,2) NOT NULL DEFAULT 1,
            unit_price decimal(18,2) NOT NULL DEFAULT 0,
            line_discount decimal(18,2) NOT NULL DEFAULT 0,
            line_tax decimal(18,2) NOT NULL DEFAULT 0,
            line_total decimal(18,2) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY invoice_id (invoice_id)
        ) $charset_collate;";

        $sql_ledger = "CREATE TABLE " . bwk_table_ledger() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source varchar(20) NOT NULL,
            source_id bigint(20) unsigned NOT NULL,
            txn_type varchar(20) NOT NULL,
            amount decimal(18,2) NOT NULL DEFAULT 0,
            currency varchar(10) NOT NULL,
            txn_date datetime NOT NULL,
            meta_json longtext NULL,
            PRIMARY KEY (id),
            KEY source (source),
            KEY source_id (source_id),
            KEY txn_date (txn_date)
        ) $charset_collate;";

        dbDelta( $sql_invoices );
        dbDelta( $sql_items );
        dbDelta( $sql_ledger );
    }
}
