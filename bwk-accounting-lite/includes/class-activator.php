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
            zakat_total decimal(18,2) NOT NULL DEFAULT 0,
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
            product_id bigint(20) unsigned NULL,
            product_sku varchar(100) NULL,
            qty decimal(18,2) NOT NULL DEFAULT 1,
            unit_price decimal(18,2) NOT NULL DEFAULT 0,
            line_discount decimal(18,2) NOT NULL DEFAULT 0,
            line_tax decimal(18,2) NOT NULL DEFAULT 0,
            line_total decimal(18,2) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY invoice_id (invoice_id),
            KEY product_id (product_id)
        ) $charset_collate;";

        $sql_quotes = "CREATE TABLE " . bwk_table_quotes() . " (
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
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY number (number)
        ) $charset_collate;";

        $sql_quote_items = "CREATE TABLE " . bwk_table_quote_items() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            quote_id bigint(20) unsigned NOT NULL,
            line_no int NOT NULL,
            item_name varchar(200) NOT NULL,
            product_id bigint(20) unsigned NULL,
            product_sku varchar(100) NULL,
            qty decimal(18,2) NOT NULL DEFAULT 1,
            unit_price decimal(18,2) NOT NULL DEFAULT 0,
            line_total decimal(18,2) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY quote_id (quote_id),
            KEY product_id (product_id)
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
        dbDelta( $sql_quotes );
        dbDelta( $sql_quote_items );
        dbDelta( $sql_ledger );
    }

    public static function upgrade() {
        global $wpdb;
        $quotes = bwk_table_quotes();
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $quotes ) ) !== $quotes ) {
            self::activate();
            return;
        }
        $needs_upgrade = false;

        $table = bwk_table_invoices();
        $col   = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM $table LIKE %s", 'zakat_total' ) );
        if ( ! $col ) {
            $needs_upgrade = true;
        }

        $invoice_items = bwk_table_invoice_items();
        if ( ! $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM $invoice_items LIKE %s", 'product_id' ) ) ) {
            $needs_upgrade = true;
        }
        if ( ! $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM $invoice_items LIKE %s", 'product_sku' ) ) ) {
            $needs_upgrade = true;
        }

        $quote_items = bwk_table_quote_items();
        if ( ! $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM $quote_items LIKE %s", 'product_id' ) ) ) {
            $needs_upgrade = true;
        }
        if ( ! $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM $quote_items LIKE %s", 'product_sku' ) ) ) {
            $needs_upgrade = true;
        }

        if ( $needs_upgrade ) {
            self::activate();
        }
    }
}
