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

function bwk_table_quotes() {
    global $wpdb;
    return $wpdb->prefix . 'bwk_quotes';
}

function bwk_table_quote_items() {
    global $wpdb;
    return $wpdb->prefix . 'bwk_quote_items';
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

function bwk_preview_invoice_number() {
    $seq = (int) bwk_get_option( 'invoice_seq', 0 );
    $seq++;
    $prefix = bwk_get_option( 'number_prefix', 'INV-' );
    $pad    = (int) bwk_get_option( 'number_padding', 4 );
    return $prefix . str_pad( (string) $seq, $pad, '0', STR_PAD_LEFT );
}

function bwk_next_quote_number() {
    $seq = (int) bwk_get_option( 'quote_seq', 0 );
    $seq++;
    bwk_update_option( 'quote_seq', $seq );
    $prefix = bwk_get_option( 'quote_prefix', 'QT-' );
    $pad    = (int) bwk_get_option( 'number_padding', 4 );
    return $prefix . str_pad( (string) $seq, $pad, '0', STR_PAD_LEFT );
}

function bwk_current_user_can( $cap = 'manage_options' ) {
    return current_user_can( $cap );
}

/**
 * Calculate profit for a window of ledger activity.
 *
 * @param array $args {
 *     Optional. Arguments to filter the ledger window.
 *
 *     @type string $start_date MySQL datetime or strtotime parsable string for lower bound.
 *     @type string $end_date   MySQL datetime or strtotime parsable string for upper bound.
 *     @type string $currency   Currency code to limit the calculation.
 * }
 * @return array {
 *     Calculated totals for the requested window.
 *
 *     @type float $income     Sum of incoming sales.
 *     @type float $deductions Sum of refunds and expenses.
 *     @type float $profit     Net profit (income minus deductions).
 * }
 */
function bwk_calculate_profit_window( $args = array() ) {
    global $wpdb;

    $defaults = array(
        'start_date' => null,
        'end_date'   => null,
        'currency'   => null,
    );

    $args = wp_parse_args( $args, $defaults );

    $clauses = array();
    $values  = array();

    if ( ! empty( $args['start_date'] ) ) {
        $start = strtotime( $args['start_date'] );
        if ( $start ) {
            $clauses[] = 'txn_date >= %s';
            $values[]  = gmdate( 'Y-m-d H:i:s', $start );
        }
    }

    if ( ! empty( $args['end_date'] ) ) {
        $end = strtotime( $args['end_date'] );
        if ( $end ) {
            $clauses[] = 'txn_date <= %s';
            $values[]  = gmdate( 'Y-m-d H:i:s', $end );
        }
    }

    if ( ! empty( $args['currency'] ) ) {
        $clauses[] = 'currency = %s';
        $values[]  = strtoupper( sanitize_text_field( $args['currency'] ) );
    }

    $table = bwk_table_ledger();
    $sql   = "SELECT txn_type, SUM(amount) AS total FROM $table";

    if ( $clauses ) {
        $sql .= ' WHERE ' . implode( ' AND ', $clauses );
    }

    $sql .= ' GROUP BY txn_type';

    $rows = $values ? $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

    $income     = 0.0;
    $deductions = 0.0;

    foreach ( (array) $rows as $row ) {
        $type  = isset( $row['txn_type'] ) ? $row['txn_type'] : '';
        $total = isset( $row['total'] ) ? floatval( $row['total'] ) : 0.0;

        switch ( $type ) {
            case 'sale':
                $income += $total;
                break;
            case 'refund':
            case 'expense':
                $deductions += abs( $total );
                break;
        }
    }

    return array(
        'income'     => $income,
        'deductions' => $deductions,
        'profit'     => $income - $deductions,
    );
}

/**
 * Calculate the zakat due based on profit and configured rate.
 *
 * @param array $args Optional arguments passed to the profit helper.
 * @return float
 */
function bwk_calculate_zakat_due( $args = array() ) {
    if ( ! get_option( 'bwk_accounting_enable_zakat' ) ) {
        return 0.0;
    }

    $profit_window = bwk_calculate_profit_window( $args );
    $profit        = isset( $profit_window['profit'] ) ? floatval( $profit_window['profit'] ) : 0.0;
    $rate          = floatval( bwk_get_option( 'zakat_rate', 2.5 ) );

    $due = $profit * ( $rate / 100 );

    return $due > 0 ? $due : 0.0;
}
