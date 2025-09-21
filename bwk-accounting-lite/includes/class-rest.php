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
            '/profit',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, 'get_profit' ),
                    'permission_callback' => array( __CLASS__, 'permissions_check' ),
                    'args'                => array(
                        'start'    => array(
                            'description' => __( 'Start datetime for the profit calculation window.', 'bwk-accounting-lite' ),
                            'type'        => 'string',
                            'required'    => false,
                        ),
                        'end'      => array(
                            'description' => __( 'End datetime for the profit calculation window.', 'bwk-accounting-lite' ),
                            'type'        => 'string',
                            'required'    => false,
                        ),
                        'currency' => array(
                            'description' => __( 'Currency code used to filter profit rows.', 'bwk-accounting-lite' ),
                            'type'        => 'string',
                            'required'    => false,
                        ),
                    ),
                ),
            )
        );

        register_rest_route(
            'bwk-accounting/v1',
            '/profit/series',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, 'get_profit_series' ),
                    'permission_callback' => array( __CLASS__, 'permissions_check' ),
                    'args'                => array(
                        'months'   => array(
                            'description' => __( 'Number of months to include in the profit series.', 'bwk-accounting-lite' ),
                            'type'        => 'integer',
                            'default'     => 6,
                            'minimum'     => 1,
                            'maximum'     => 24,
                        ),
                        'currency' => array(
                            'description' => __( 'Currency code used to filter profit rows.', 'bwk-accounting-lite' ),
                            'type'        => 'string',
                            'required'    => false,
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
     * Verify access for REST resources.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return bool|WP_Error
     */
    public static function permissions_check( $request ) {
        if ( bwk_current_user_can() ) {
            return true;
        }

        return new WP_Error(
            'rest_forbidden',
            __( 'Sorry, you are not allowed to access this resource.', 'bwk-accounting-lite' ),
            array( 'status' => rest_authorization_required_code() )
        );
    }

    /**
     * Return a profit summary for the requested window.
     *
     * @param WP_REST_Request $request Request data.
     *
     * @return WP_REST_Response|array
     */
    public static function get_profit( $request ) {
        $currency = self::sanitize_currency( $request->get_param( 'currency' ) );
        $start    = $request->get_param( 'start' );
        $end      = $request->get_param( 'end' );

        $args = array();

        if ( $currency ) {
            $args['currency'] = $currency;
        }

        if ( null !== $start && '' !== $start ) {
            if ( is_numeric( $start ) ) {
                $args['start'] = (int) $start;
            } else {
                $args['start'] = sanitize_text_field( wp_unslash( $start ) );
            }
        }

        if ( null !== $end && '' !== $end ) {
            if ( is_numeric( $end ) ) {
                $args['end'] = (int) $end;
            } else {
                $args['end'] = sanitize_text_field( wp_unslash( $end ) );
            }
        }

        $summary = BWK_Ledger::calculate_profit( $args );

        if ( empty( $summary['currency'] ) && $currency ) {
            $summary['currency'] = $currency;
        }

        return rest_ensure_response( $summary );
    }

    /**
     * Return a monthly profit series for charting interfaces.
     *
     * @param WP_REST_Request $request Request data.
     *
     * @return WP_REST_Response|array
     */
    public static function get_profit_series( $request ) {
        $months   = (int) $request->get_param( 'months' );
        $currency = self::sanitize_currency( $request->get_param( 'currency' ) );

        if ( $months < 1 ) {
            $months = 1;
        }

        if ( $months > 24 ) {
            $months = 24;
        }

        $args = array( 'months' => $months );

        if ( $currency ) {
            $args['currency'] = $currency;
        }

        $series = BWK_Ledger::get_profit_series( $args );

        if ( empty( $series['currency'] ) && $currency ) {
            $series['currency'] = $currency;
        }

        return rest_ensure_response( $series );
    }

    /**
     * Clean a currency string received from REST requests.
     *
     * @param mixed $currency Raw currency value.
     *
     * @return string
     */
    protected static function sanitize_currency( $currency ) {
        if ( null === $currency || '' === $currency ) {
            return '';
        }

        if ( is_array( $currency ) ) {
            return '';
        }

        $currency = sanitize_text_field( wp_unslash( $currency ) );
        $currency = strtoupper( preg_replace( '/[^A-Z]/', '', $currency ) );

        return $currency;
    }
}
