<?php
/**
 * Dashboard metrics and admin page controller.
 *
 * @package BWK Accounting Lite
 * @author Wan Mohd Aiman Binawebpro.com
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BWK_Dashboard {
    /**
     * Cached metrics for the current request.
     *
     * @var array|null
     */
    protected static $metrics = null;

    /**
     * Screen hook suffix for the dashboard.
     *
     * @var string
     */
    protected static $screen_hook = 'toplevel_page_bwk-dashboard';

    /**
     * Whether the load hook has already been registered.
     *
     * @var bool
     */
    protected static $load_hook_registered = false;

    /**
     * Hook registrations.
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_screen_hooks' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'wp_ajax_bwk_dashboard_chart', array( __CLASS__, 'ajax_chart_data' ) );
    }

    /**
     * Update the stored screen hook suffix.
     *
     * @param string $hook Screen hook returned from add_menu_page.
     */
    public static function set_screen_hook( $hook ) {
        if ( ! is_string( $hook ) || '' === $hook ) {
            return;
        }

        if ( self::$screen_hook === $hook && self::$load_hook_registered ) {
            return;
        }

        self::$screen_hook           = $hook;
        self::$load_hook_registered = false;
        self::register_screen_hooks();
    }

    /**
     * Register load-* hooks so we can prime metrics before rendering.
     */
    public static function register_screen_hooks() {
        if ( ! self::$screen_hook || self::$load_hook_registered ) {
            return;
        }

        add_action( 'load-' . self::$screen_hook, array( __CLASS__, 'prepare_metrics' ) );
        self::$load_hook_registered = true;
    }

    /**
     * Gather metrics ahead of time for the dashboard screen.
     */
    public static function prepare_metrics() {
        if ( ! bwk_current_user_can() ) {
            return;
        }

        self::$metrics = self::gather_metrics();
    }

    /**
     * Retrieve metrics, computing them on demand when necessary.
     *
     * @return array
     */
    public static function get_metrics() {
        if ( null === self::$metrics ) {
            self::$metrics = self::gather_metrics();
        }

        return self::$metrics;
    }

    /**
     * Render the dashboard page.
     */
    public static function render_page() {
        if ( ! bwk_current_user_can() ) {
            return;
        }

        $metrics = self::get_metrics();

        $invoice_status_totals = isset( $metrics['invoice_status_totals'] ) ? $metrics['invoice_status_totals'] : array();
        $quote_status_totals   = isset( $metrics['quote_status_totals'] ) ? $metrics['quote_status_totals'] : array();
        $recent_activity       = isset( $metrics['recent_activity'] ) ? $metrics['recent_activity'] : array();
        $invoice_summary       = isset( $metrics['invoice_summary'] ) ? $metrics['invoice_summary'] : array();
        $profit_summary        = isset( $metrics['profit_summary'] ) ? $metrics['profit_summary'] : array();
        $zakat_summary         = isset( $metrics['zakat_summary'] ) ? $metrics['zakat_summary'] : array();
        $primary_currency      = isset( $metrics['currency'] ) ? $metrics['currency'] : 'USD';

        include BWK_AL_PATH . 'admin/views-dashboard.php';
    }

    /**
     * Enqueue dashboard-specific assets.
     *
     * @param string $hook Current admin hook suffix.
     */
    public static function enqueue_assets( $hook ) {
        $target_hook = self::$screen_hook ? self::$screen_hook : 'toplevel_page_bwk-dashboard';
        if ( $hook !== $target_hook ) {
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

        $series = self::get_revenue_series( $months );
        wp_send_json_success( $series );
    }

    /**
     * Gather all metrics required by the dashboard.
     *
     * @return array
     */
    protected static function gather_metrics() {
        $invoice_status_totals = self::get_invoice_status_totals();
        $quote_status_totals   = self::get_quote_status_totals();
        $invoice_summary       = self::summarize_invoice_totals( $invoice_status_totals );
        $currency              = self::get_primary_currency();

        return array(
            'currency'              => $currency,
            'invoice_status_totals' => $invoice_status_totals,
            'quote_status_totals'   => $quote_status_totals,
            'recent_activity'       => self::get_recent_activity(),
            'invoice_summary'       => $invoice_summary,
            'profit_summary'        => self::calculate_profit_summary( $invoice_summary ),
            'zakat_summary'         => self::calculate_zakat_summary(),
        );
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
                    'direction'    => 'credit',
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
                    'direction'    => 'neutral',
                );
            }
        }

        $ledger_query = $wpdb->prepare(
            'SELECT id, txn_type, amount, currency, txn_date FROM ' . bwk_table_ledger() . ' ORDER BY txn_date DESC LIMIT %d',
            $fetch_amount
        );
        $ledger_rows  = $wpdb->get_results( $ledger_query, ARRAY_A );

        if ( $ledger_rows ) {
            foreach ( $ledger_rows as $row ) {
                $timestamp = isset( $row['txn_date'] ) ? strtotime( $row['txn_date'] ) : 0;
                $amount    = isset( $row['amount'] ) ? (float) $row['amount'] : 0.0;
                $items[]   = array(
                    'type'         => 'ledger',
                    'type_label'   => __( 'Ledger', 'bwk-accounting-lite' ),
                    'title'        => isset( $row['txn_type'] ) ? self::format_status_label( $row['txn_type'] ) : __( 'Ledger entry', 'bwk-accounting-lite' ),
                    'status'       => isset( $row['txn_type'] ) ? sanitize_key( $row['txn_type'] ) : '',
                    'status_label' => isset( $row['txn_type'] ) ? self::format_status_label( $row['txn_type'] ) : '',
                    'total'        => $amount,
                    'currency'     => isset( $row['currency'] ) ? self::normalize_currency( $row['currency'] ) : '',
                    'timestamp'    => $timestamp ? $timestamp : 0,
                    'url'          => admin_url( 'admin.php?page=bwk-ledger' ),
                    'direction'    => $amount >= 0 ? 'credit' : 'debit',
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

        $summary['amount']      = self::round_money( $summary['amount'] );
        $summary['paid']        = self::round_money( $summary['paid'] );
        $summary['outstanding'] = self::round_money( $summary['outstanding'] );

        return $summary;
    }

    /**
     * Calculate profit metrics using ledger data with invoice fallbacks.
     *
     * @param array $invoice_summary Invoice summary metrics.
     *
     * @return array
     */
    protected static function calculate_profit_summary( $invoice_summary ) {
        $ledger_totals = self::get_ledger_totals_by_type();

        $revenue_types = array( 'sale', 'income', 'revenue', 'payment', 'deposit' );
        $expense_types = array( 'expense', 'purchase', 'cost', 'payout', 'withdrawal' );
        $zakat_types   = array( 'zakat' );

        $revenue = 0.0;
        $expenses = 0.0;
        $zakat = 0.0;

        foreach ( $ledger_totals as $type => $data ) {
            $total = isset( $data['total'] ) ? (float) $data['total'] : 0.0;
            if ( in_array( $type, $revenue_types, true ) ) {
                $revenue += $total;
            } elseif ( in_array( $type, $expense_types, true ) ) {
                $expenses += abs( $total );
            } elseif ( in_array( $type, $zakat_types, true ) ) {
                $zakat += abs( $total );
            } else {
                if ( $total >= 0 ) {
                    $revenue += $total;
                } else {
                    $expenses += abs( $total );
                }
            }
        }

        if ( $revenue <= 0 && isset( $invoice_summary['paid'] ) ) {
            $revenue = (float) $invoice_summary['paid'];
        }

        $profit = $revenue - $expenses - $zakat;
        $margin = $revenue > 0 ? ( $profit / $revenue ) * 100 : 0;

        return array(
            'revenue' => self::round_money( $revenue ),
            'expenses'=> self::round_money( $expenses ),
            'zakat'   => self::round_money( $zakat ),
            'profit'  => self::round_money( $profit ),
            'margin'  => round( $margin, 2 ),
        );
    }

    /**
     * Calculate zakat accumulation and outstanding amounts.
     *
     * @return array
     */
    protected static function calculate_zakat_summary() {
        global $wpdb;

        $expected = (float) $wpdb->get_var( "SELECT SUM(zakat_total) FROM " . bwk_table_invoices() . " WHERE status IN ('paid','partial')" );

        $ledger_table = bwk_table_ledger();
        $ledger_rows  = $wpdb->get_results( "SELECT amount, currency, txn_date FROM $ledger_table WHERE LOWER(txn_type) = 'zakat' ORDER BY txn_date DESC", ARRAY_A );

        $recorded    = 0.0;
        $last_entry  = null;

        if ( $ledger_rows ) {
            foreach ( $ledger_rows as $row ) {
                $amount     = isset( $row['amount'] ) ? (float) $row['amount'] : 0.0;
                $recorded  += abs( $amount );

                if ( ! $last_entry ) {
                    $timestamp = isset( $row['txn_date'] ) ? strtotime( $row['txn_date'] ) : 0;
                    $last_entry = array(
                        'amount'    => self::round_money( $amount ),
                        'currency'  => isset( $row['currency'] ) ? self::normalize_currency( $row['currency'] ) : '',
                        'timestamp' => $timestamp ? $timestamp : 0,
                    );
                }
            }
        }

        $pending  = $expected - $recorded;
        if ( $pending < 0 ) {
            $pending = 0;
        }

        $progress = 0;
        if ( $expected > 0 ) {
            $progress = ( $recorded / $expected ) * 100;
        } elseif ( $recorded > 0 ) {
            $progress = 100;
        }

        return array(
            'expected'          => self::round_money( $expected ),
            'recorded'          => self::round_money( $recorded ),
            'pending'           => self::round_money( $pending ),
            'rate'              => round( (float) bwk_get_option( 'zakat_rate', 2.5 ), 2 ),
            'progress'          => round( $progress, 2 ),
            'last_contribution' => $last_entry,
        );
    }

    /**
     * Retrieve ledger totals grouped by type.
     *
     * @return array
     */
    protected static function get_ledger_totals_by_type() {
        global $wpdb;

        $table = bwk_table_ledger();
        $rows  = $wpdb->get_results( "SELECT LOWER(txn_type) AS txn_type, COUNT(*) AS count, SUM(amount) AS total FROM $table GROUP BY txn_type", ARRAY_A );

        $totals = array();
        if ( $rows ) {
            foreach ( $rows as $row ) {
                $type = isset( $row['txn_type'] ) ? sanitize_key( $row['txn_type'] ) : '';
                if ( ! $type ) {
                    continue;
                }
                $totals[ $type ] = array(
                    'count' => isset( $row['count'] ) ? absint( $row['count'] ) : 0,
                    'total' => isset( $row['total'] ) ? (float) $row['total'] : 0.0,
                );
            }
        }

        return $totals;
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
            $currency = $wpdb->get_var( "SELECT currency FROM " . bwk_table_ledger() . " ORDER BY txn_date DESC LIMIT 1" );
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
    protected static function get_revenue_series( $months = 6 ) {
        global $wpdb;

        $months = max( 1, absint( $months ) );
        $end    = current_time( 'timestamp' );
        $start  = strtotime( '-' . ( $months - 1 ) . ' months', $end );
        $start  = $start ? $start : $end;
        $start_date = wp_date( 'Y-m-01 00:00:00', $start );

        $statuses      = array( 'paid' );
        $placeholders  = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $params        = array( $start_date );
        $sql_condition = '';

        if ( $statuses ) {
            $sql_condition = ' AND status IN (' . $placeholders . ')';
            $params        = array_merge( $params, $statuses );
        }

        $sql = 'SELECT DATE_FORMAT(created_at, "%%Y-%%m-01") AS period, SUM(grand_total) AS total FROM ' . bwk_table_invoices() . ' WHERE created_at >= %s' . $sql_condition . ' GROUP BY period ORDER BY period ASC';

        $prepared = $wpdb->prepare( $sql, $params );
        $rows     = $wpdb->get_results( $prepared, ARRAY_A );
        $map      = array();

        if ( $rows ) {
            foreach ( $rows as $row ) {
                if ( empty( $row['period'] ) ) {
                    continue;
                }
                $key         = $row['period'];
                $map[ $key ] = isset( $row['total'] ) ? (float) $row['total'] : 0.0;
            }
        }

        $labels = array();
        $values = array();

        for ( $i = $months - 1; $i >= 0; $i-- ) {
            $month_ts  = strtotime( '-' . $i . ' months', $end );
            $month_ts  = $month_ts ? $month_ts : $end;
            $period    = wp_date( 'Y-m-01', $month_ts );
            $labels[]  = wp_date( 'M Y', $month_ts );
            $values[]  = isset( $map[ $period ] ) ? round( (float) $map[ $period ], 2 ) : 0.0;
        }

        return array(
            'labels'   => $labels,
            'values'   => $values,
            'currency' => self::get_primary_currency(),
        );
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

    /**
     * Round a monetary value and avoid negative zero output.
     *
     * @param float $value Raw numeric value.
     *
     * @return float
     */
    protected static function round_money( $value ) {
        $rounded = round( (float) $value, 2 );
        if ( abs( $rounded ) < 0.005 ) {
            $rounded = 0.0;
        }

        return $rounded;
    }
}
