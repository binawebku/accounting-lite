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
        add_action( 'wp_ajax_bwk_search_products', array( __CLASS__, 'search_products' ) );
    }

    public static function import_wc_orders() {
        if ( ! bwk_current_user_can() ) {
            wp_send_json_error( 'permission' );
        }
        check_ajax_referer( 'bwk_import_wc_orders' );
        // Stub - actual import would run in batches.
        wp_send_json_success( array( 'done' => true ) );
    }

    public static function search_products() {
        if ( ! bwk_current_user_can() ) {
            wp_send_json_error( array( 'message' => __( 'Access denied.', 'bwk-accounting-lite' ) ) );
        }

        check_ajax_referer( 'bwk_search_products', 'nonce' );

        if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_products' ) ) {
            wp_send_json_error( array( 'message' => __( 'WooCommerce is not active.', 'bwk-accounting-lite' ) ) );
        }

        $term = isset( $_REQUEST['term'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['term'] ) ) : '';
        if ( strlen( $term ) < 2 ) {
            wp_send_json_success( array( 'items' => array() ) );
        }

        $args = array(
            'status'  => array( 'publish' ),
            'limit'   => 20,
            'orderby' => 'title',
            'order'   => 'ASC',
            'return'  => 'objects',
        );

        $products = wc_get_products( array_merge( $args, array(
            'search' => '*' . $term . '*',
        ) ) );

        $sku_matches = wc_get_products( array_merge( $args, array(
            'sku' => $term,
        ) ) );

        if ( $sku_matches ) {
            $products = array_merge( $products, $sku_matches );
        }

        $items    = array();
        $seen_ids = array();

        foreach ( $products as $product ) {
            if ( ! $product instanceof WC_Product ) {
                continue;
            }

            $product_id = $product->get_id();
            if ( isset( $seen_ids[ $product_id ] ) ) {
                continue;
            }

            $price = function_exists( 'wc_get_price_to_display' ) ? wc_get_price_to_display( $product ) : $product->get_price();
            $price = is_numeric( $price ) ? (float) $price : 0.0;

            $items[] = array(
                'id'    => $product_id,
                'name'  => $product->get_name(),
                'sku'   => $product->get_sku(),
                'price' => $price,
            );

            $seen_ids[ $product_id ] = true;
        }

        wp_send_json_success( array( 'items' => $items ) );
    }
}
