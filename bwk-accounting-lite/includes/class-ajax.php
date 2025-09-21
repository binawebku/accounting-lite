<?php
/**
 * AJAX handlers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BWK_Ajax {
    public static function init() {
        add_action( 'wp_ajax_bwk_import_wc_orders', array( __CLASS__, 'import_wc_orders' ) );
    }

    public static function import_wc_orders() {
        if ( ! bwk_current_user_can() ) {
            wp_send_json_error( 'permission' );
        }
        check_ajax_referer( 'bwk_import_wc_orders' );
        // Stub - actual import would run in batches.
        wp_send_json_success( array( 'done' => true ) );
    }

}
