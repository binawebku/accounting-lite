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

    /**
     * Calculate net profit for a given range.
     *
     * @param array $args {
     *     Optional. Calculation arguments.
     *
     *     @type string|int $start    Start datetime (string parsable by strtotime) or timestamp.
     *     @type string|int $end      End datetime (string parsable by strtotime) or timestamp.
     *     @type string     $currency Limit results to a specific currency code.
     *     @type string     $source   Limit results to a specific source identifier.
     * }
     *
     * @return array
     */
    public static function calculate_profit( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'start'    => null,
            'end'      => null,
            'currency' => null,
            'source'   => null,
        );

        $args     = wp_parse_args( $args, $defaults );
        $currency = self::normalize_currency( $args['currency'] );
        $start    = self::normalize_datetime( $args['start'], 'start' );
        $end      = self::normalize_datetime( $args['end'], 'end' );

        $where  = array();
        $params = array();

        if ( $start ) {
            $where[]  = 'txn_date >= %s';
            $params[] = $start;
        }

        if ( $end ) {
            $where[]  = 'txn_date <= %s';
            $params[] = $end;
        }

        if ( $currency ) {
            $where[]  = 'currency = %s';
            $params[] = $currency;
        }

        if ( ! empty( $args['source'] ) ) {
            $where[]  = 'source = %s';
            $params[] = sanitize_key( $args['source'] );
        }

        $sql = 'SELECT txn_type, amount, currency, txn_date, meta_json FROM ' . bwk_table_ledger() . ' WHERE 1=1';

        if ( $where ) {
            $sql .= ' AND ' . implode( ' AND ', $where );
        }

        $sql .= ' ORDER BY txn_date ASC';

        $rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

        if ( ! $currency && $rows ) {
            $first_row = reset( $rows );

            if ( $first_row && isset( $first_row['currency'] ) ) {
                $currency = self::normalize_currency( $first_row['currency'] );
            }
        }

        $summary = self::summarize_profit_rows(
            $rows,
            array(
                'currency' => $currency,
                'start'    => $start,
                'end'      => $end,
            )
        );

        return apply_filters( 'bwk_ledger_calculate_profit', $summary, $args, $rows );
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

    /**
     * Build a profit series grouped by month.
     *
     * @param array $args {
     *     Optional. Series arguments.
     *
     *     @type int    $months   Number of months to include. Defaults to 6.
     *     @type string $currency Currency code to constrain results.
     * }
     *
     * @return array
     */
    public static function get_profit_series( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'months'   => 6,
            'currency' => null,
        );

        $args     = wp_parse_args( $args, $defaults );
        $months   = max( 1, absint( $args['months'] ) );
        $currency = self::normalize_currency( $args['currency'] );

        $end_ts   = current_time( 'timestamp' );
        $start_ts = strtotime( '-' . ( $months - 1 ) . ' months', $end_ts );
        $start_ts = $start_ts ? $start_ts : $end_ts;

        $start_date = wp_date( 'Y-m-01 00:00:00', $start_ts );
        $end_date   = wp_date( 'Y-m-t 23:59:59', $end_ts );

        $where  = array( 'txn_date >= %s', 'txn_date <= %s' );
        $params = array( $start_date, $end_date );

        if ( $currency ) {
            $where[]  = 'currency = %s';
            $params[] = $currency;
        }

        $sql   = 'SELECT txn_type, amount, currency, txn_date, meta_json FROM ' . bwk_table_ledger() . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY txn_date ASC';
        $rows  = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

        if ( ! $currency && $rows ) {
            $first_row = reset( $rows );

            if ( $first_row && isset( $first_row['currency'] ) ) {
                $currency = self::normalize_currency( $first_row['currency'] );
            }
        }
        $buckets = array();

        if ( $rows ) {
            foreach ( $rows as $row ) {
                if ( empty( $row['txn_date'] ) ) {
                    continue;
                }

                $timestamp = strtotime( $row['txn_date'] );

                if ( false === $timestamp ) {
                    continue;
                }

                $period_key = wp_date( 'Y-m-01', $timestamp );

                if ( ! isset( $buckets[ $period_key ] ) ) {
                    $buckets[ $period_key ] = array();
                }

                $buckets[ $period_key ][] = $row;
            }
        }

        $labels = array();
        $values = array();
        $series = array();

        for ( $i = $months - 1; $i >= 0; $i-- ) {
            $month_ts  = strtotime( '-' . $i . ' months', $end_ts );
            $month_ts  = $month_ts ? $month_ts : $end_ts;
            $period_key = wp_date( 'Y-m-01', $month_ts );
            $period_rows = isset( $buckets[ $period_key ] ) ? $buckets[ $period_key ] : array();

            $summary = self::summarize_profit_rows(
                $period_rows,
                array(
                    'currency' => $currency,
                    'start'    => wp_date( 'Y-m-01 00:00:00', $month_ts ),
                    'end'      => wp_date( 'Y-m-t 23:59:59', $month_ts ),
                )
            );

            $labels[]        = wp_date( 'M Y', $month_ts );
            $values[]        = isset( $summary['net'] ) ? (float) $summary['net'] : 0.0;
            $series[ $period_key ] = $summary;
        }

        $currency_value = $currency ? $currency : '';

        $result = array(
            'labels'   => $labels,
            'values'   => array_map(
                static function ( $value ) {
                    return round( (float) $value, 2 );
                },
                $values
            ),
            'currency' => $currency_value,
            'series'   => $series,
            'start'    => $start_date,
            'end'      => $end_date,
        );

        return apply_filters( 'bwk_ledger_profit_series', $result, $args, $rows );
    }

    public static function insert_zakat( $invoice_id, $amount, $currency ) {
        self::insert_entry( array(
            'source'    => 'invoice',
            'source_id' => $invoice_id,
            'txn_type'  => 'zakat',
            'amount'    => $amount,
            'currency'  => $currency,
        ) );
    }

    public static function render_list_page() {
        if ( ! bwk_current_user_can() ) {
            return;
        }
        global $wpdb;
        $entries = $wpdb->get_results( 'SELECT * FROM ' . bwk_table_ledger() . ' ORDER BY txn_date DESC LIMIT 200' );
        include BWK_AL_PATH . 'admin/views-ledger-list.php';
    }

    /**
     * Convert ledger rows into profit totals.
     *
     * @param array $rows    Ledger rows.
     * @param array $context Context data.
     *
     * @return array
     */
    protected static function summarize_profit_rows( $rows, $context = array() ) {
        $rows = is_array( $rows ) ? $rows : array();

        $sales           = 0.0;
        $refunds         = 0.0;
        $expenses        = 0.0;
        $costs           = 0.0;
        $refunded_costs  = 0.0;

        $expense_types = apply_filters(
            'bwk_ledger_profit_expense_types',
            array( 'expense', 'fee', 'payout', 'tax', 'zakat' ),
            $rows,
            $context
        );

        foreach ( $rows as $row ) {
            $txn_type = isset( $row['txn_type'] ) ? sanitize_key( $row['txn_type'] ) : '';
            $amount   = isset( $row['amount'] ) ? (float) $row['amount'] : 0.0;
            $meta     = self::decode_meta( isset( $row['meta_json'] ) ? $row['meta_json'] : '' );
            $cost     = self::extract_cost_from_meta( $meta );

            if ( 'sale' === $txn_type ) {
                if ( $amount > 0 ) {
                    $sales += $amount;
                } else {
                    $sales += abs( $amount );
                }

                if ( $cost > 0 ) {
                    $costs += $cost;
                }

                continue;
            }

            if ( 'refund' === $txn_type ) {
                $refunds += abs( $amount );

                if ( $cost > 0 ) {
                    $refunded_costs += $cost;
                }

                continue;
            }

            if ( in_array( $txn_type, $expense_types, true ) ) {
                $expenses += abs( $amount );

                continue;
            }

            /**
             * Allow custom handling of unrecognised ledger types.
             *
             * @since 1.0.0
             *
             * @param array $row     Ledger row.
             * @param array $context Context array.
             */
            do_action( 'bwk_ledger_profit_unhandled_row', $row, $context );
        }

        $net_costs = $costs - $refunded_costs;

        if ( $net_costs < 0 ) {
            $net_costs = 0.0;
        }

        $net = $sales - $refunds - $expenses - $net_costs;

        $currency = isset( $context['currency'] ) ? self::normalize_currency( $context['currency'] ) : '';

        $summary = array(
            'currency'        => $currency,
            'sales'           => round( $sales, 2 ),
            'refunds'         => round( $refunds, 2 ),
            'expenses'        => round( $expenses, 2 ),
            'costs'           => round( $net_costs, 2 ),
            'costs_gross'     => round( $costs, 2 ),
            'refunded_costs'  => round( $refunded_costs, 2 ),
            'net'             => round( $net, 2 ),
            'start'           => isset( $context['start'] ) ? $context['start'] : null,
            'end'             => isset( $context['end'] ) ? $context['end'] : null,
        );

        return apply_filters( 'bwk_ledger_profit_summary', $summary, $rows, $context );
    }

    /**
     * Decode a meta JSON string into an array.
     *
     * @param string $meta_json Meta JSON string.
     *
     * @return array
     */
    protected static function decode_meta( $meta_json ) {
        if ( empty( $meta_json ) ) {
            return array();
        }

        $decoded = json_decode( wp_unslash( $meta_json ), true );

        return is_array( $decoded ) ? $decoded : array();
    }

    /**
     * Attempt to extract a cost total value from decoded metadata.
     *
     * @param array $meta Decoded metadata array.
     *
     * @return float
     */
    protected static function extract_cost_from_meta( $meta ) {
        if ( empty( $meta ) || ! is_array( $meta ) ) {
            return 0.0;
        }

        $keys = array( 'cost_total', 'total_cost', 'cost', 'cogs', 'cost_of_goods' );

        foreach ( $keys as $key ) {
            if ( isset( $meta[ $key ] ) && is_numeric( $meta[ $key ] ) ) {
                return (float) $meta[ $key ];
            }
        }

        if ( isset( $meta['totals'] ) && is_array( $meta['totals'] ) ) {
            foreach ( $keys as $key ) {
                if ( isset( $meta['totals'][ $key ] ) && is_numeric( $meta['totals'][ $key ] ) ) {
                    return (float) $meta['totals'][ $key ];
                }
            }
        }

        if ( isset( $meta['costs'] ) && is_array( $meta['costs'] ) && isset( $meta['costs']['total'] ) && is_numeric( $meta['costs']['total'] ) ) {
            return (float) $meta['costs']['total'];
        }

        return 0.0;
    }

    /**
     * Normalise a currency string into ISO-style uppercase letters.
     *
     * @param string $currency Currency string.
     *
     * @return string
     */
    protected static function normalize_currency( $currency ) {
        if ( ! is_string( $currency ) ) {
            return '';
        }

        $currency = strtoupper( preg_replace( '/[^A-Z]/', '', $currency ) );

        return $currency;
    }

    /**
     * Normalise a datetime value into MySQL format.
     *
     * @param mixed  $value    Datetime value.
     * @param string $boundary Accepts 'start' or 'end' to control rounding.
     *
     * @return string|null
     */
    protected static function normalize_datetime( $value, $boundary = 'start' ) {
        if ( empty( $value ) && 0 !== $value ) {
            return null;
        }

        if ( $value instanceof DateTimeInterface ) {
            $timestamp = $value->getTimestamp();
        } elseif ( is_numeric( $value ) ) {
            $timestamp = (int) $value;
        } elseif ( is_string( $value ) ) {
            $timestamp = strtotime( $value );
        } else {
            $timestamp = false;
        }

        if ( false === $timestamp ) {
            return null;
        }

        if ( 'end' === $boundary ) {
            $date_string = wp_date( 'Y-m-d 23:59:59', $timestamp );
        } else {
            $date_string = wp_date( 'Y-m-d 00:00:00', $timestamp );
        }

        return $date_string;
    }
}
