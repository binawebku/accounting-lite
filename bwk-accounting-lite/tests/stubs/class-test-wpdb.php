<?php
class BWK_Test_WPDB {
    public $prefix = 'wp_';

    /**
     * @var array
     */
    private $ledger = array();

    /**
     * @var int
     */
    private $auto_increment = 1;

    /**
     * Reset stored ledger rows.
     */
    public function reset() {
        $this->ledger         = array();
        $this->auto_increment = 1;
    }

    /**
     * Mimic $wpdb->insert().
     *
     * @param string $table Target table name.
     * @param array  $data  Row data.
     *
     * @return bool
     */
    public function insert( $table, $data ) {
        if ( $table !== $this->prefix . 'bwk_ledger' ) {
            return false;
        }

        $row = array_merge(
            array(
                'id'        => $this->auto_increment++,
                'source'    => '',
                'source_id' => 0,
                'txn_type'  => '',
                'amount'    => 0.0,
                'currency'  => 'USD',
                'txn_date'  => gmdate( 'Y-m-d H:i:s' ),
                'meta_json' => '',
            ),
            $data
        );

        $this->ledger[] = $row;

        return true;
    }

    /**
     * Mimic $wpdb->prepare().
     *
     * @param string $query Query with placeholders.
     * @param mixed  ...$args Parameters to bind.
     *
     * @return array
     */
    public function prepare( $query, ...$args ) {
        if ( 1 === count( $args ) && is_array( $args[0] ) ) {
            $args = $args[0];
        }

        return array(
            'query'  => $query,
            'params' => $args,
        );
    }

    /**
     * Mimic $wpdb->get_results().
     *
     * @param mixed  $prepared Prepared statement representation.
     * @param string $output   Not used.
     *
     * @return array
     */
    public function get_results( $prepared, $output = ARRAY_A ) {
        if ( is_array( $prepared ) ) {
            $query  = isset( $prepared['query'] ) ? $prepared['query'] : '';
            $params = isset( $prepared['params'] ) ? $prepared['params'] : array();
        } else {
            $query  = (string) $prepared;
            $params = array();
        }

        if ( false !== strpos( $query, 'GROUP BY txn_type' ) ) {
            return $this->group_by_type( $params );
        }

        if ( false !== strpos( $query, 'ORDER BY txn_date' ) ) {
            return $this->select_rows( $params );
        }

        return array();
    }

    /**
     * Mimic $wpdb->get_var().
     *
     * @param mixed $prepared Prepared statement representation.
     *
     * @return string|null
     */
    public function get_var( $prepared ) {
        if ( is_array( $prepared ) ) {
            $query  = isset( $prepared['query'] ) ? $prepared['query'] : '';
            $params = isset( $prepared['params'] ) ? $prepared['params'] : array();
        } else {
            $query  = (string) $prepared;
            $params = array();
        }

        if ( false !== strpos( $query, 'SELECT currency' ) ) {
            $rows = $this->select_rows( $params );
            if ( empty( $rows ) ) {
                return null;
            }
            $row = end( $rows );
            return isset( $row['currency'] ) ? $row['currency'] : null;
        }

        return null;
    }

    /**
     * Group rows by type.
     *
     * @param array $params Range parameters.
     *
     * @return array
     */
    private function group_by_type( $params ) {
        list( $start, $end ) = $this->parse_range_params( $params );
        $rows = $this->filter_rows( $start, $end );

        $grouped = array();
        foreach ( $rows as $row ) {
            $type = strtolower( isset( $row['txn_type'] ) ? $row['txn_type'] : '' );
            if ( ! isset( $grouped[ $type ] ) ) {
                $grouped[ $type ] = array(
                    'txn_type' => $type,
                    'total'    => 0.0,
                    'count'    => 0,
                );
            }

            $grouped[ $type ]['total'] += (float) $row['amount'];
            $grouped[ $type ]['count'] ++;
        }

        return array_values( $grouped );
    }

    /**
     * Select raw rows ordered by date.
     *
     * @param array $params Range parameters.
     *
     * @return array
     */
    private function select_rows( $params ) {
        list( $start, $end ) = $this->parse_range_params( $params );
        $rows = $this->filter_rows( $start, $end );

        usort(
            $rows,
            function ( $a, $b ) {
                return strcmp( $a['txn_date'], $b['txn_date'] );
            }
        );

        return $rows;
    }

    /**
     * Filter ledger rows using start/end parameters.
     *
     * @param string|null $start Start bound.
     * @param string|null $end   End bound.
     *
     * @return array
     */
    private function filter_rows( $start, $end ) {
        return array_values(
            array_filter(
                $this->ledger,
                function ( $row ) use ( $start, $end ) {
                    $date = isset( $row['txn_date'] ) ? $row['txn_date'] : '';
                    if ( $start && $date < $start ) {
                        return false;
                    }
                    if ( $end && $date > $end ) {
                        return false;
                    }
                    return true;
                }
            )
        );
    }

    /**
     * Extract start and end parameters from a prepared statement.
     *
     * @param array $params Prepared parameters.
     *
     * @return array
     */
    private function parse_range_params( $params ) {
        $start = isset( $params[0] ) ? $params[0] : null;
        $end   = isset( $params[1] ) ? $params[1] : null;

        return array( $start, $end );
    }
}
