<?php
/**
 * Admin product helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BWK_Admin_Products {
    /**
     * Boot hooks.
     */
    public static function init() {
        add_action( 'wp_ajax_bwk_search_products', array( __CLASS__, 'search_products' ) );
    }

    /**
     * AJAX handler for WooCommerce product lookup.
     */
    public static function search_products() {
        if ( ! bwk_current_user_can() ) {
            wp_send_json_error( array( 'message' => __( 'Access denied.', 'bwk-accounting-lite' ) ) );
        }

        check_ajax_referer( 'bwk_search_products', 'nonce' );

        $term  = isset( $_REQUEST['term'] ) ? $_REQUEST['term'] : '';
        $items = self::get_products_for_term( $term );

        if ( is_wp_error( $items ) ) {
            wp_send_json_error( array( 'message' => $items->get_error_message() ) );
        }

        wp_send_json_success( array( 'items' => $items ) );
    }

    /**
     * Lookup WooCommerce products for the provided term.
     *
     * @param string $term Search term.
     * @return array|WP_Error
     */
    public static function get_products_for_term( $term ) {
        if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_products' ) ) {
            return new WP_Error(
                'bwk_no_woocommerce',
                __( 'WooCommerce is not active.', 'bwk-accounting-lite' ),
                array(
                    'status' => 400,
                )
            );
        }

        $term = is_string( $term ) ? $term : '';
        $term = sanitize_text_field( wp_unslash( $term ) );
        $term = trim( $term );

        if ( strlen( $term ) < 2 ) {
            return array();
        }

        $query_args = array(
            'status'  => array( 'publish' ),
            'limit'   => 20,
            'orderby' => 'title',
            'order'   => 'ASC',
            'return'  => 'objects',
        );

        $products = wc_get_products(
            array_merge(
                $query_args,
                array(
                    'search' => '*' . $term . '*',
                )
            )
        );

        $sku_matches = wc_get_products(
            array_merge(
                $query_args,
                array(
                    'sku' => $term,
                )
            )
        );

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

        return $items;
    }
}
