<?php
/**
 * Dashboard metrics and admin page controller.
 *
 * @author Wan Mohd Aiman Binawebpro.com
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BWK_Dashboard {
    /**
     * Hook registrations.
     */
    public static function init() {
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'wp_ajax_bwk_dashboard_chart', array( __CLASS__, 'ajax_chart_data' ) );
    }

    /**
     * Render the dashboard page.
     */
    public static function render_page() {
        if ( ! bwk_current_user_can() ) {
            return;
        }

        $invoice_status_totals = self::get_invoice_status_totals();
        $quote_status_totals   = self::get_quote_status_totals();
        $recent_activity       = self::get_recent_activity();
        $invoice_summary       = self::summarize_invoice_totals( $invoice_status_totals );
        $primary_currency      = self::get_primary_currency();
        $profit_window         = self::get_profit_window_args( $primary_currency );
        $profit_summary        = BWK_Ledger::calculate_profit( $profit_window );
        $profit_window_label   = isset( $profit_window['label'] ) ? $profit_window['label'] : '';

        include BWK_AL_PATH . 'admin/views-dashboard.php';
    }

    /**
     * Enqueue dashboard-specific assets.
     *
     * @param string $hook Current admin hook suffix.
     */
    public static function enqueue_assets( $hook ) {
        if ( 'toplevel_page_bwk-dashboard' !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'bwk-dashboard',
            BWK_AL_URL . 'admin/js/dashboard.js',
            array(),
            BWK_AL_VERSION,
            true
        );

        wp_localize_script(
            'bwk-dashboard',
            'bwkDashboardData',
            array(
                'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
                'chartNonce' => wp_create_nonce( 'bwk_dashboard_chart' ),
                'i18n'       => array(
                    'loading' => __( 'Loading…', 'bwk-accounting-lite' ),
                    'empty'   => __( 'Not enough data yet.', 'bwk-accounting-lite' ),
                    'error'   => __( 'Unable to load chart data.', 'bwk-accounting-lite' ),
                ),
            )
        );
    }

    /**
     * AJAX handler for revenue chart data.
     */
    public static function ajax_chart_data() {
        if ( ! bwk_current_user_can() ) {
            wp_send_json_error( array( 'message' => __( 'Access denied.', 'bwk-accounting-lite' ) ), 403 );
        }

        check_ajax_referer( 'bwk_dashboard_chart', 'nonce' );

        $months = isset( $_REQUEST['months'] ) ? absint( $_REQUEST['months'] ) : 6;
        if ( $months < 3 ) {
            $months = 3;
        }
        if ( $months > 24 ) {
            $months = 24;
        }

        $currency = '';

        if ( isset( $_REQUEST['currency'] ) ) {
            $currency = self::normalize_currency( wp_unslash( $_REQUEST['currency'] ) );
        }

        if ( ! $currency ) {
            $currency = self::get_primary_currency();
        }

        $series = self::get_profit_series( $months, $currency );
        wp_send_json_success( $series );
    }

    /**
     * Retrieve invoice totals grouped by status.
     *
     * @return array
     */
    protected static function get_invoice_status_totals() {
        global $wpdb;
        $table  = bwk_table_invoices();
        $rows   = $wpdb->get_results( "SELECT status, COUNT(*) AS count, SUM(grand_total) AS total FROM $table GROUP BY status", ARRAY_A );
        $totals = array();

        if ( $rows ) {
            foreach ( $rows as $row ) {
                $status = isset( $row['status'] ) ? sanitize_key( $row['status'] ) : 'unknown';
                $totals[ $status ] = array(
                    'status' => $status,
                    'label'  => self::format_status_label( $status ),
                    'count'  => isset( $row['count'] ) ? absint( $row['count'] ) : 0,
                    'total'  => isset( $row['total'] ) ? (float) $row['total'] : 0.0,
                );
            }
        }

        $defaults = array( 'paid', 'sent', 'partial', 'draft', 'void' );
        return self::order_status_data( $totals, $defaults );
    }

    /**
     * Retrieve quote totals grouped by status.
     *
     * @return array
     */
    protected static function get_quote_status_totals() {
        global $wpdb;
        $table  = bwk_table_quotes();
        $rows   = $wpdb->get_results( "SELECT status, COUNT(*) AS count, SUM(grand_total) AS total FROM $table GROUP BY status", ARRAY_A );
        $totals = array();

        if ( $rows ) {
            foreach ( $rows as $row ) {
                $status = isset( $row['status'] ) ? sanitize_key( $row['status'] ) : 'unknown';
                $totals[ $status ] = array(
                    'status' => $status,
                    'label'  => self::format_status_label( $status ),
                    'count'  => isset( $row['count'] ) ? absint( $row['count'] ) : 0,
                    'total'  => isset( $row['total'] ) ? (float) $row['total'] : 0.0,
                );
            }
        }

        $defaults = array( 'accepted', 'sent', 'draft', 'declined' );
        return self::order_status_data( $totals, $defaults );
    }

    /**
     * Build a combined recent activity feed.
     *
     * @param int $limit Number of records to return.
     *
     * @return array
     */
    protected static function get_recent_activity( $limit = 6 ) {
        global $wpdb;
        $limit        = max( 1, absint( $limit ) );
        $fetch_amount = max( 1, $limit * 2 );
        $items        = array();

        $invoice_query = $wpdb->prepare(
            'SELECT id, number, status, grand_total, currency, updated_at FROM ' . bwk_table_invoices() . ' ORDER BY updated_at DESC LIMIT %d',
            $fetch_amount
        );
        $invoice_rows  = $wpdb->get_results( $invoice_query, ARRAY_A );

        if ( $invoice_rows ) {
            foreach ( $invoice_rows as $row ) {
                $timestamp = isset( $row['updated_at'] ) ? strtotime( $row['updated_at'] ) : 0;
                $items[]   = array(
                    'type'         => 'invoice',
                    'type_label'   => __( 'Invoice', 'bwk-accounting-lite' ),
                    'title'        => isset( $row['number'] ) && $row['number'] ? sprintf( __( 'Invoice %s', 'bwk-accounting-lite' ), $row['number'] ) : __( 'Invoice', 'bwk-accounting-lite' ),
                    'status'       => isset( $row['status'] ) ? sanitize_key( $row['status'] ) : '',
                    'status_label' => isset( $row['status'] ) ? self::format_status_label( $row['status'] ) : '',
                    'total'        => isset( $row['grand_total'] ) ? (float) $row['grand_total'] : 0.0,
                    'currency'     => isset( $row['currency'] ) ? self::normalize_currency( $row['currency'] ) : '',
                    'timestamp'    => $timestamp ? $timestamp : 0,
                    'url'          => admin_url( 'admin.php?page=bwk-invoice-add&id=' . absint( $row['id'] ) ),
                );
            }
        }

        $quote_query = $wpdb->prepare(
            'SELECT id, number, status, grand_total, currency, updated_at FROM ' . bwk_table_quotes() . ' ORDER BY updated_at DESC LIMIT %d',
            $fetch_amount
        );
        $quote_rows  = $wpdb->get_results( $quote_query, ARRAY_A );

        if ( $quote_rows ) {
            foreach ( $quote_rows as $row ) {
                $timestamp = isset( $row['updated_at'] ) ? strtotime( $row['updated_at'] ) : 0;
                $items[]   = array(
                    'type'         => 'quote',
                    'type_label'   => __( 'Quote', 'bwk-accounting-lite' ),
                    'title'        => isset( $row['number'] ) && $row['number'] ? sprintf( __( 'Quote %s', 'bwk-accounting-lite' ), $row['number'] ) : __( 'Quote', 'bwk-accounting-lite' ),
                    'status'       => isset( $row['status'] ) ? sanitize_key( $row['status'] ) : '',
                    'status_label' => isset( $row['status'] ) ? self::format_status_label( $row['status'] ) : '',
                    'total'        => isset( $row['grand_total'] ) ? (float) $row['grand_total'] : 0.0,
                    'currency'     => isset( $row['currency'] ) ? self::normalize_currency( $row['currency'] ) : '',
                    'timestamp'    => $timestamp ? $timestamp : 0,
                    'url'          => admin_url( 'admin.php?page=bwk-quote-add&id=' . absint( $row['id'] ) ),
                );
            }
        }

        $ledger_query = $wpdb->prepare(
            'SELECT id, txn_type, amount, currency, txn_date FROM ' . bwk_table_ledger() . ' ORDER BY txn_date DESC LIMIT %d',
            $limit
        );
        $ledger_rows  = $wpdb->get_results( $ledger_query, ARRAY_A );

        if ( $ledger_rows ) {
            foreach ( $ledger_rows as $row ) {
                $timestamp = isset( $row['txn_date'] ) ? strtotime( $row['txn_date'] ) : 0;
                $items[]   = array(
                    'type'         => 'ledger',
                    'type_label'   => __( 'Ledger', 'bwk-accounting-lite' ),
                    'title'        => isset( $row['txn_type'] ) ? self::format_status_label( $row['txn_type'] ) : __( 'Ledger entry', 'bwk-accounting-lite' ),
                    'status'       => isset( $row['txn_type'] ) ? sanitize_key( $row['txn_type'] ) : '',
                    'status_label' => isset( $row['txn_type'] ) ? self::format_status_label( $row['txn_type'] ) : '',
                    'total'        => isset( $row['amount'] ) ? (float) $row['amount'] : 0.0,
                    'currency'     => isset( $row['currency'] ) ? self::normalize_currency( $row['currency'] ) : '',
                    'timestamp'    => $timestamp ? $timestamp : 0,
                    'url'          => admin_url( 'admin.php?page=bwk-ledger' ),
                );
            }
        }

        if ( $items ) {
            usort(
                $items,
                function ( $a, $b ) {
                    $ta = isset( $a['timestamp'] ) ? (int) $a['timestamp'] : 0;
                    $tb = isset( $b['timestamp'] ) ? (int) $b['timestamp'] : 0;

                    if ( $ta === $tb ) {
                        return 0;
                    }

                    return ( $ta > $tb ) ? -1 : 1;
                }
            );
        }

        return array_slice( $items, 0, $limit );
    }

    /**
     * Summarise invoice totals for key metrics.
     *
     * @param array $status_totals Invoice totals by status.
     *
     * @return array
     */
    protected static function summarize_invoice_totals( $status_totals ) {
        $summary = array(
            'count'       => 0,
            'amount'      => 0.0,
            'paid'        => 0.0,
            'outstanding' => 0.0,
        );

        if ( empty( $status_totals ) || ! is_array( $status_totals ) ) {
            return $summary;
        }

        foreach ( $status_totals as $status => $row ) {
            $count = isset( $row['count'] ) ? (int) $row['count'] : 0;
            $total = isset( $row['total'] ) ? (float) $row['total'] : 0.0;

            $summary['count']  += $count;
            $summary['amount'] += $total;

            if ( in_array( $status, array( 'paid' ), true ) ) {
                $summary['paid'] += $total;
            } elseif ( in_array( $status, array( 'void', 'cancelled' ), true ) ) {
                continue;
            } else {
                $summary['outstanding'] += $total;
            }
        }

        return $summary;
    }

    /**
     * Create a human-friendly label for a status string.
     *
     * @param string $status Status value.
     *
     * @return string
     */
    protected static function format_status_label( $status ) {
        $status = is_string( $status ) ? strtolower( $status ) : '';
        $status = str_replace( array( '-', '_' ), ' ', $status );

        return ucwords( trim( $status ) );
    }

    /**
     * Normalise a currency code string.
     *
     * @param string $currency Raw currency value.
     *
     * @return string
     */
    protected static function normalize_currency( $currency ) {
        if ( ! is_string( $currency ) ) {
            return '';
        }

        $normalized = strtoupper( preg_replace( '/[^A-Z]/', '', $currency ) );

        return $normalized;
    }

    /**
     * Determine the primary currency to display within the dashboard.
     *
     * @return string
     */
    protected static function get_primary_currency() {
        global $wpdb;

        $currency = $wpdb->get_var( 'SELECT currency FROM ' . bwk_table_invoices() . ' ORDER BY updated_at DESC LIMIT 1' );

        if ( ! $currency ) {
            $currency = $wpdb->get_var( 'SELECT currency FROM ' . bwk_table_quotes() . ' ORDER BY updated_at DESC LIMIT 1' );
        }

        if ( ! $currency ) {
            $currency = bwk_get_option( 'default_currency', 'USD' );
        }

        $currency = self::normalize_currency( $currency );

        return $currency ? $currency : 'USD';
    }

    /**
     * Retrieve the revenue series grouped by month.
     *
     * @param int $months Number of months to include.
     *
     * @return array
     */
    protected static function get_profit_series( $months = 6, $currency = '' ) {
        $months   = max( 1, absint( $months ) );
        $currency = self::normalize_currency( $currency );

        if ( ! $currency ) {
            $currency = self::get_primary_currency();
        }

        $args = array(
            'months'   => $months,
            'currency' => $currency,
        );

        /**
         * Filter the arguments used when generating the profit series for the dashboard.
         *
         * @since 1.0.0
         *
         * @param array $args     Series arguments.
         * @param int   $months   Number of months requested.
         * @param string $currency Currency code.
         */
        $args = apply_filters( 'bwk_dashboard_profit_series_args', $args, $months, $currency );

        if ( isset( $args['currency'] ) ) {
            $args['currency'] = self::normalize_currency( $args['currency'] );
        }

        $series = BWK_Ledger::get_profit_series( $args );

        if ( empty( $series['currency'] ) && ! empty( $args['currency'] ) ) {
            $series['currency'] = $args['currency'];
        }

        return $series;
    }

    /**
     * Determine the profit window used for the dashboard summary card.
     *
     * @param string $currency Currency code.
     *
     * @return array
     */
    protected static function get_profit_window_args( $currency ) {
        $currency = self::normalize_currency( $currency );
        $days     = (int) apply_filters( 'bwk_dashboard_profit_window_days', 30 );

        if ( $days < 1 ) {
            $days = 30;
        }

        $end_ts   = current_time( 'timestamp' );
        $start_ts = strtotime( '-' . ( $days - 1 ) . ' days', $end_ts );
        $start_ts = $start_ts ? $start_ts : $end_ts;

        $range = array(
            'start'    => wp_date( 'Y-m-d 00:00:00', $start_ts ),
            'end'      => wp_date( 'Y-m-d 23:59:59', $end_ts ),
            'currency' => $currency,
        );

        $range['label'] = sprintf(
            /* translators: %s: number of days in the profit window. */
            _n( 'Last %s day', 'Last %s days', $days, 'bwk-accounting-lite' ),
            number_format_i18n( $days )
        );

        /**
         * Filter the arguments passed to the profit calculation for the dashboard card.
         *
         * @since 1.0.0
         *
         * @param array  $range    Profit range arguments.
         * @param string $currency Currency code.
         */
        $range = apply_filters( 'bwk_dashboard_profit_range', $range, $currency );

        return $range;
    }

    /**
     * Ensure status arrays contain sensible ordering and defaults.
     *
     * @param array $data     Status data keyed by status.
     * @param array $defaults Default order.
     *
     * @return array
     */
    protected static function order_status_data( $data, $defaults ) {
        $ordered = array();

        if ( ! is_array( $data ) ) {
            $data = array();
        }

        if ( ! is_array( $defaults ) ) {
            $defaults = array();
        }

        foreach ( $defaults as $status ) {
            $key = sanitize_key( $status );
            if ( isset( $data[ $key ] ) ) {
                $ordered[ $key ] = $data[ $key ];
                unset( $data[ $key ] );
            } else {
                $ordered[ $key ] = array(
                    'status' => $key,
                    'label'  => self::format_status_label( $key ),
                    'count'  => 0,
                    'total'  => 0.0,
                );
            }
        }

        foreach ( $data as $status => $row ) {
            $ordered[ $status ] = $row;
        }

        return $ordered;
    }
}
