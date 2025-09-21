<?php
/**
 * Ledger handling.
 *
 * @package BWK Accounting Lite
 * @author Wan Mohd Aiman Binawebpro.com
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BWK_Ledger {
    /**
     * Transaction type buckets used when calculating profit and loss.
     *
     * @var array
     */
    protected static $revenue_types = array( 'sale', 'income', 'revenue', 'payment', 'deposit' );

    /**
     * Expense oriented transaction types.
     *
     * @var array
     */
    protected static $expense_types = array( 'expense', 'purchase', 'cost', 'payout', 'withdrawal', 'refund' );

    /**
     * Zakat transaction types.
     *
     * @var array
     */
    protected static $zakat_types = array( 'zakat' );

    public static function init() {
        // placeholder for hooks.
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
     * Summarise profit metrics for a date range.
     *
     * @param array $args Range arguments.
     *
     * @return array
     */
    public static function get_profit_summary( $args = array() ) {
        global $wpdb;

        $range = self::normalise_range( $args );
        $meta  = $range['meta'];
        $sql   = $range['sql'];

        $table = bwk_table_ledger();
        $query = 'SELECT LOWER(txn_type) AS txn_type, SUM(amount) AS total FROM ' . $table . ' WHERE 1=1';
        $params = array();

        if ( ! empty( $sql['start'] ) ) {
            $query   .= ' AND txn_date >= %s';
            $params[] = $sql['start'];
        }

        if ( ! empty( $sql['end'] ) ) {
            $query   .= ' AND txn_date <= %s';
            $params[] = $sql['end'];
        }

        $query .= ' GROUP BY txn_type';

        $prepared = self::prepare_query( $query, $params );
        $rows     = $wpdb->get_results( $prepared, ARRAY_A );

        $totals = array(
            'revenue'  => 0.0,
            'expenses' => 0.0,
            'zakat'    => 0.0,
        );

        if ( $rows ) {
            foreach ( $rows as $row ) {
                $type   = isset( $row['txn_type'] ) ? self::normalise_key( $row['txn_type'] ) : '';
                $amount = isset( $row['total'] ) ? (float) $row['total'] : 0.0;

                foreach ( self::map_amount_to_buckets( $type, $amount ) as $bucket => $value ) {
                    if ( isset( $totals[ $bucket ] ) ) {
                        $totals[ $bucket ] += $value;
                    }
                }
            }
        }

        $revenue  = self::round_money( $totals['revenue'] );
        $expenses = self::round_money( $totals['expenses'] );
        $zakat    = self::round_money( $totals['zakat'] );
        $profit   = self::round_money( $revenue - $expenses - $zakat );
        $margin   = $revenue > 0 ? round( ( $profit / $revenue ) * 100, 2 ) : 0.0;

        return array(
            'range'    => $meta,
            'currency' => self::get_default_currency(),
            'revenue'  => $revenue,
            'expenses' => $expenses,
            'zakat'    => $zakat,
            'profit'   => $profit,
            'margin'   => $margin,
        );
    }

    /**
     * Retrieve profit series data grouped by day.
     *
     * @param array $args Range arguments.
     *
     * @return array
     */
    public static function get_profit_series( $args = array() ) {
        global $wpdb;

        $range    = self::normalise_range( $args );
        $meta     = $range['meta'];
        $sql      = $range['sql'];
        $bounds   = $range['bounds'];
        $timezone = self::get_timezone();

        $table  = bwk_table_ledger();
        $query  = 'SELECT txn_type, amount, currency, txn_date FROM ' . $table . ' WHERE 1=1';
        $params = array();

        if ( ! empty( $sql['start'] ) ) {
            $query   .= ' AND txn_date >= %s';
            $params[] = $sql['start'];
        }

        if ( ! empty( $sql['end'] ) ) {
            $query   .= ' AND txn_date <= %s';
            $params[] = $sql['end'];
        }

        $query .= ' ORDER BY txn_date ASC';

        $prepared = self::prepare_query( $query, $params );
        $rows     = $wpdb->get_results( $prepared, ARRAY_A );

        $buckets = self::create_daily_buckets( $bounds['start'], $bounds['end'] );

        if ( $rows ) {
            foreach ( $rows as $row ) {
                $txn_date = isset( $row['txn_date'] ) ? $row['txn_date'] : '';
                $datetime = self::create_local_from_gmt( $txn_date, $timezone );

                if ( ! $datetime ) {
                    continue;
                }

                $key = $datetime->format( 'Y-m-d' );

                if ( ! isset( $buckets[ $key ] ) ) {
                    continue;
                }

                $type   = isset( $row['txn_type'] ) ? self::normalise_key( $row['txn_type'] ) : '';
                $amount = isset( $row['amount'] ) ? (float) $row['amount'] : 0.0;

                foreach ( self::map_amount_to_buckets( $type, $amount ) as $bucket => $value ) {
                    if ( isset( $buckets[ $key ][ $bucket ] ) ) {
                        $buckets[ $key ][ $bucket ] += $value;
                    }
                }
            }
        }

        $labels   = array();
        $revenue  = array();
        $expenses = array();
        $zakat    = array();
        $profit   = array();

        foreach ( $buckets as $key => $data ) {
            $labels[]   = $key;
            $revenue[]  = self::round_money( $data['revenue'] );
            $expenses[] = self::round_money( $data['expenses'] );
            $zakat[]    = self::round_money( $data['zakat'] );
            $profit[]   = self::round_money( $data['revenue'] - $data['expenses'] - $data['zakat'] );
        }

        return array(
            'range'    => $meta,
            'currency' => self::get_default_currency(),
            'labels'   => $labels,
            'revenue'  => $revenue,
            'expenses' => $expenses,
            'zakat'    => $zakat,
            'profit'   => $profit,
        );
    }

    /**
     * Normalise range arguments, returning local metadata and GMT bounds.
     *
     * @param array $args Arguments supplied to the query.
     *
     * @return array
     */
    protected static function normalise_range( $args ) {
        $defaults = array(
            'range' => 'this_month',
            'start' => '',
            'end'   => '',
        );

        $args     = self::parse_args( $args, $defaults );
        $range    = isset( $args['range'] ) ? strtolower( (string) $args['range'] ) : 'this_month';
        $timezone = self::get_timezone();
        $now      = new DateTimeImmutable( 'now', $timezone );

        $start = null;
        $end   = null;
        $label = '';

        switch ( $range ) {
            case 'custom':
                $start = self::parse_user_datetime( isset( $args['start'] ) ? $args['start'] : '', $timezone, false );
                $end   = self::parse_user_datetime( isset( $args['end'] ) ? $args['end'] : '', $timezone, true );
                $label = 'custom';
                break;
            case 'last_7_days':
            case 'last_30_days':
            case 'last_90_days':
                $days  = (int) preg_replace( '/[^0-9]/', '', $range );
                $label = $range;
                $end   = $now->setTime( 23, 59, 59 );
                $start = $end->modify( '-' . max( 0, $days - 1 ) . ' days' )->setTime( 0, 0, 0 );
                break;
            case 'previous_month':
            case 'last_month':
                $label = 'previous_month';
                $end   = $now->modify( 'first day of this month -1 second' )->setTime( 23, 59, 59 );
                $start = $end->modify( 'first day of this month' )->setTime( 0, 0, 0 );
                break;
            case 'this_month':
            default:
                $label = 'this_month';
                $start = $now->modify( 'first day of this month' )->setTime( 0, 0, 0 );
                $end   = $start->modify( 'last day of this month' )->setTime( 23, 59, 59 );
                break;
        }

        if ( ! $start ) {
            $start = $now->setTime( 0, 0, 0 );
        }

        if ( ! $end ) {
            $end = $now->setTime( 23, 59, 59 );
        }

        if ( $start > $end ) {
            $temp  = $start;
            $start = $end;
            $end   = $temp;
        }

        $start_local = $start->format( 'Y-m-d H:i:s' );
        $end_local   = $end->format( 'Y-m-d H:i:s' );

        $start_gmt = self::to_gmt( $start_local, $timezone );
        $end_gmt   = self::to_gmt( $end_local, $timezone );

        return array(
            'meta'   => array(
                'range'    => $label,
                'start'    => $start_local,
                'end'      => $end_local,
                'timezone' => $timezone->getName(),
            ),
            'sql'    => array(
                'start' => $start_gmt,
                'end'   => $end_gmt,
            ),
            'bounds' => array(
                'start' => $start,
                'end'   => $end,
            ),
        );
    }

    /**
     * Prepare a WordPress style query using the provided parameters.
     *
     * @param string $query  SQL query with placeholders.
     * @param array  $params Parameters to bind.
     *
     * @return string|array
     */
    protected static function prepare_query( $query, $params ) {
        global $wpdb;

        if ( empty( $params ) ) {
            return $query;
        }

        if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'prepare' ) ) {
            return $wpdb->prepare( $query, $params );
        }

        foreach ( $params as $param ) {
            $safe = addslashes( $param );
            $query = preg_replace( '/%s/', "'" . $safe . "'", $query, 1 );
        }

        return $query;
    }

    /**
     * Create empty buckets for each day in the supplied range.
     *
     * @param DateTimeImmutable $start Start datetime.
     * @param DateTimeImmutable $end   End datetime.
     *
     * @return array
     */
    protected static function create_daily_buckets( DateTimeImmutable $start, DateTimeImmutable $end ) {
        $buckets = array();

        if ( $start > $end ) {
            return $buckets;
        }

        $current = $start;

        while ( $current <= $end ) {
            $key            = $current->format( 'Y-m-d' );
            $buckets[ $key ] = array(
                'timestamp' => $current->getTimestamp(),
                'revenue'   => 0.0,
                'expenses'  => 0.0,
                'zakat'     => 0.0,
            );
            $current = $current->modify( '+1 day' );
        }

        return $buckets;
    }

    /**
     * Map a ledger amount to revenue, expenses, or zakat buckets.
     *
     * @param string $type   Transaction type.
     * @param float  $amount Amount associated with the transaction.
     *
     * @return array
     */
    protected static function map_amount_to_buckets( $type, $amount ) {
        $type   = self::normalise_key( $type );
        $amount = (float) $amount;

        if ( in_array( $type, self::$zakat_types, true ) ) {
            return array( 'zakat' => abs( $amount ) );
        }

        if ( in_array( $type, self::$revenue_types, true ) ) {
            if ( $amount >= 0 ) {
                return array( 'revenue' => $amount );
            }

            return array( 'expenses' => abs( $amount ) );
        }

        if ( in_array( $type, self::$expense_types, true ) ) {
            return array( 'expenses' => abs( $amount ) );
        }

        if ( $amount >= 0 ) {
            return array( 'revenue' => $amount );
        }

        return array( 'expenses' => abs( $amount ) );
    }

    /**
     * Convert a local datetime string into GMT.
     *
     * @param string         $datetime Local datetime string.
     * @param DateTimeZone   $timezone Local timezone.
     *
     * @return string
     */
    protected static function to_gmt( $datetime, DateTimeZone $timezone ) {
        if ( ! $datetime ) {
            return $datetime;
        }

        if ( function_exists( 'get_gmt_from_date' ) ) {
            return get_gmt_from_date( $datetime, 'Y-m-d H:i:s' );
        }

        try {
            $local = new DateTimeImmutable( $datetime, $timezone );
            return $local->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
        } catch ( Exception $exception ) {
            return $datetime;
        }
    }

    /**
     * Create a local timezone datetime from a GMT string.
     *
     * @param string       $datetime GMT datetime string.
     * @param DateTimeZone $timezone Target timezone.
     *
     * @return DateTimeImmutable|null
     */
    protected static function create_local_from_gmt( $datetime, DateTimeZone $timezone ) {
        if ( ! $datetime ) {
            return null;
        }

        try {
            $utc = new DateTimeImmutable( $datetime, new DateTimeZone( 'UTC' ) );
            return $utc->setTimezone( $timezone );
        } catch ( Exception $exception ) {
            return null;
        }
    }

    /**
     * Parse a user supplied datetime string in the site timezone.
     *
     * @param string       $value      Raw value.
     * @param DateTimeZone $timezone   Target timezone.
     * @param bool         $end_of_day Whether to clamp to the end of the day.
     *
     * @return DateTimeImmutable|null
     */
    protected static function parse_user_datetime( $value, DateTimeZone $timezone, $end_of_day ) {
        if ( ! is_string( $value ) ) {
            return null;
        }

        $value = trim( $value );

        if ( '' === $value ) {
            return null;
        }

        $formats = array( 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d' );

        foreach ( $formats as $format ) {
            $datetime = DateTimeImmutable::createFromFormat( $format, $value, $timezone );
            if ( false !== $datetime ) {
                if ( 'Y-m-d' === $format ) {
                    return $end_of_day ? $datetime->setTime( 23, 59, 59 ) : $datetime->setTime( 0, 0, 0 );
                }

                if ( 'Y-m-d H:i' === $format ) {
                    $hour   = (int) $datetime->format( 'H' );
                    $minute = (int) $datetime->format( 'i' );
                    $second = $end_of_day ? 59 : 0;
                    return $datetime->setTime( $hour, $minute, $second );
                }

                return $datetime;
            }
        }

        try {
            $datetime = new DateTimeImmutable( $value, $timezone );
            if ( $end_of_day ) {
                $datetime = $datetime->setTime( 23, 59, 59 );
            }

            return $datetime;
        } catch ( Exception $exception ) {
            return null;
        }
    }

    /**
     * Normalise a transaction type key without relying on WordPress helpers.
     *
     * @param string $key Raw key.
     *
     * @return string
     */
    protected static function normalise_key( $key ) {
        if ( function_exists( 'sanitize_key' ) ) {
            return sanitize_key( $key );
        }

        $key = strtolower( (string) $key );
        return preg_replace( '/[^a-z0-9_]/', '', $key );
    }

    /**
     * Parse arguments without relying on WordPress being loaded.
     *
     * @param mixed $args     Raw arguments.
     * @param array $defaults Default values.
     *
     * @return array
     */
    protected static function parse_args( $args, $defaults ) {
        if ( function_exists( 'wp_parse_args' ) ) {
            return wp_parse_args( $args, $defaults );
        }

        if ( is_object( $args ) ) {
            $args = get_object_vars( $args );
        } elseif ( ! is_array( $args ) ) {
            parse_str( (string) $args, $args );
        }

        if ( ! is_array( $args ) ) {
            $args = array();
        }

        return array_merge( $defaults, $args );
    }

    /**
     * Retrieve the site's timezone.
     *
     * @return DateTimeZone
     */
    protected static function get_timezone() {
        if ( function_exists( 'wp_timezone' ) ) {
            $timezone = wp_timezone();
            if ( $timezone instanceof DateTimeZone ) {
                return $timezone;
            }
        }

        $timezone_string = function_exists( 'get_option' ) ? get_option( 'timezone_string' ) : '';

        if ( $timezone_string ) {
            try {
                return new DateTimeZone( $timezone_string );
            } catch ( Exception $exception ) {
                // Fall through to offset handling.
            }
        }

        $offset = function_exists( 'get_option' ) ? get_option( 'gmt_offset', 0 ) : 0;
        if ( is_numeric( $offset ) && 0 !== (float) $offset ) {
            $hours   = (float) $offset;
            $seconds = (int) round( $hours * HOUR_IN_SECONDS );
            $name    = timezone_name_from_abbr( '', $seconds, 0 );
            if ( $name ) {
                try {
                    return new DateTimeZone( $name );
                } catch ( Exception $exception ) {
                    // Continue to default UTC fallback.
                }
            }
        }

        return new DateTimeZone( 'UTC' );
    }

    /**
     * Round monetary values to two decimal places.
     *
     * @param float $value Raw value.
     *
     * @return float
     */
    protected static function round_money( $value ) {
        return round( (float) $value, 2 );
    }

    /**
     * Determine the default currency to report.
     *
     * @return string
     */
    protected static function get_default_currency() {
        $currency = 'USD';

        if ( function_exists( 'bwk_get_option' ) ) {
            $stored = bwk_get_option( 'default_currency', 'USD' );
            if ( is_string( $stored ) && '' !== $stored ) {
                $currency = $stored;
            }
        }

        $currency = strtoupper( preg_replace( '/[^A-Z]/', '', (string) $currency ) );

        return $currency ? $currency : 'USD';
    }
}
