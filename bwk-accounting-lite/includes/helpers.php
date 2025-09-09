<?php
/**
 * Helper functions for BWK Accounting Lite.
 *
 * @package BWK Accounting Lite
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bwk_table_invoices() {
    global $wpdb;
    return $wpdb->prefix . 'bwk_invoices';
}

function bwk_table_invoice_items() {
    global $wpdb;
    return $wpdb->prefix . 'bwk_invoice_items';
}

function bwk_table_ledger() {
    global $wpdb;
    return $wpdb->prefix . 'bwk_ledger';
}

function bwk_get_option( $key, $default = '' ) {
    $value = get_option( 'bwk_accounting_' . $key, $default );
    return $value;
}

function bwk_update_option( $key, $value ) {
    update_option( 'bwk_accounting_' . $key, $value );
}

function bwk_next_invoice_number() {
    $seq = (int) bwk_get_option( 'invoice_seq', 0 );
    $seq++;
    bwk_update_option( 'invoice_seq', $seq );
    $prefix = bwk_get_option( 'number_prefix', 'INV-' );
    $pad    = (int) bwk_get_option( 'number_padding', 4 );
    return $prefix . str_pad( (string) $seq, $pad, '0', STR_PAD_LEFT );
}

function bwk_current_user_can( $cap = 'manage_options' ) {
    return current_user_can( $cap );
}
