<?php
/**
 * Ledger handling.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BWK_Ledger {
    public static function init() {
        // placeholder for hooks.
    }

    public static function insert_entry( $data ) {
        global $wpdb;
        $defaults = array(
            'source'    => 'wc',
            'source_id' => 0,
            'txn_type'  => 'sale',
            'amount'    => 0,
            'currency'  => 'USD',
            'txn_date'  => current_time( 'mysql' ),
            'meta_json' => '',
        );
        $data = wp_parse_args( $data, $defaults );
        $wpdb->insert( bwk_table_ledger(), $data );
    }

    public static function render_list_page() {
        if ( ! bwk_current_user_can() ) {
            return;
        }
        global $wpdb;
        $entries = $wpdb->get_results( 'SELECT * FROM ' . bwk_table_ledger() . ' ORDER BY txn_date DESC LIMIT 200' );
        include BWK_AL_PATH . 'admin/views-ledger-list.php';
    }
}
