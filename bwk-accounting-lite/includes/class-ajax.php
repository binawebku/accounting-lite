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
        add_action( 'wp_ajax_bwk_wc_product_details', array( __CLASS__, 'get_wc_product_details' ) );
    }

    public static function import_wc_orders() {
        if ( ! bwk_current_user_can() ) {
            wp_send_json_error( 'permission' );
        }
        check_ajax_referer( 'bwk_import_wc_orders' );
        // Stub - actual import would run in batches.
        wp_send_json_success( array( 'done' => true ) );
    }

    public static function get_wc_product_details() {
        if ( ! bwk_current_user_can() ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to access product data.', 'bwk-accounting-lite' ) ) );
        }

        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'bwk_wc_product_details' ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request. Please refresh and try again.', 'bwk-accounting-lite' ) ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid product selection.', 'bwk-accounting-lite' ) ) );
        }

        if ( ! function_exists( 'wc_get_product' ) ) {
            wp_send_json_error( array( 'message' => __( 'WooCommerce is required for product search.', 'bwk-accounting-lite' ) ) );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( array( 'message' => __( 'Product not found.', 'bwk-accounting-lite' ) ) );
        }

        $price = function_exists( 'wc_get_price_to_display' ) ? wc_get_price_to_display( $product ) : $product->get_price();

        if ( function_exists( 'wc_format_decimal' ) && function_exists( 'wc_get_price_decimals' ) ) {
            $formatted = wc_format_decimal( $price, wc_get_price_decimals() );
        } else {
            $formatted = is_numeric( $price ) ? (string) $price : '';
        }

        wp_send_json_success(
            array(
                'id'    => $product_id,
                'name'  => $product->get_name(),
                'price' => $formatted,
            )
        );
    }
}
