<?php
/**
 * REST API endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BWK_Rest {
    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        register_rest_route( 'bwk-accounting/v1', '/invoice/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_invoice' ),
            'permission_callback' => '__return_true',
        ) );
        register_rest_route(
            'bwk-accounting/v1',
            '/products',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, 'search_products' ),
                    'permission_callback' => array( __CLASS__, 'can_manage_products' ),
                    'args'                => array(
                        'term' => array(
                            'description'       => __( 'Search term for product lookup.', 'bwk-accounting-lite' ),
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                    ),
                ),
            )
        );
    }

    public static function get_invoice( $request ) {
        $id = intval( $request['id'] );
        global $wpdb;
        $invoice = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . bwk_table_invoices() . ' WHERE id=%d', $id ), ARRAY_A );
        if ( ! $invoice ) {
            return new WP_Error( 'not_found', 'Invoice not found', array( 'status' => 404 ) );
        }
        $items = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . bwk_table_invoice_items() . ' WHERE invoice_id=%d ORDER BY line_no ASC', $id ), ARRAY_A );
        foreach ( $items as &$item ) {
            $product_id        = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
            $item['product_id'] = $product_id > 0 ? $product_id : null;

            if ( array_key_exists( 'product_sku', $item ) ) {
                $item['product_sku'] = '' !== $item['product_sku'] ? $item['product_sku'] : null;
            } else {
                $item['product_sku'] = null;
            }
        }
        unset( $item );
        $invoice['items'] = $items;
        return rest_ensure_response( $invoice );
    }

    /**
     * Check whether the current user can query products.
     *
     * @return bool
     */
    public static function can_manage_products( $request = null ) {
        return bwk_current_user_can();
    }

    /**
     * REST handler for WooCommerce product lookup.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response|WP_Error
     */
    public static function search_products( WP_REST_Request $request ) {
        $term  = $request->get_param( 'term' );
        $items = BWK_Admin_Products::get_products_for_term( $term );

        if ( is_wp_error( $items ) ) {
            return $items;
        }

        return rest_ensure_response( array( 'items' => $items ) );
    }
}
