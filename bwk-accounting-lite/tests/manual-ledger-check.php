<?php
require __DIR__ . '/bootstrap.php';

// Configure timezone and options as in tests.
$_bwk_timezone_string = 'Asia/Kuala_Lumpur';
update_option( 'timezone_string', 'Asia/Kuala_Lumpur' );
update_option( 'bwk_accounting_default_currency', 'MYR' );

// Reset ledger data.
$wpdb->reset();

$insert = function ( $type, $amount, $utc_datetime, $currency = 'MYR' ) use ( $wpdb ) {
    $wpdb->insert(
        bwk_table_ledger(),
        array(
            'source'    => 'wc',
            'source_id' => rand( 1, 1000 ),
            'txn_type'  => $type,
            'amount'    => $amount,
            'currency'  => $currency,
            'txn_date'  => $utc_datetime,
        )
    );
};

$insert( 'sale', 100.00, '2023-12-31 16:15:00' );
$insert( 'sale', 150.00, '2024-01-31 15:45:00' );
$insert( 'expense', -30.00, '2024-01-15 04:00:00' );
$insert( 'zakat', 5.00, '2024-01-20 16:00:00' );
$insert( 'sale', 75.00, '2023-12-31 15:45:00' );

$summary = BWK_Ledger::get_profit_summary(
    array(
        'range' => 'custom',
        'start' => '2024-01-01',
        'end'   => '2024-01-31',
    )
);

$series = BWK_Ledger::get_profit_series(
    array(
        'range' => 'custom',
        'start' => '2024-01-01',
        'end'   => '2024-01-31',
    )
);

$assertions = array();
$assertions[] = abs( $summary['revenue'] - 250.0 ) < 0.001;
$assertions[] = abs( $summary['expenses'] - 30.0 ) < 0.001;
$assertions[] = abs( $summary['zakat'] - 5.0 ) < 0.001;
$assertions[] = abs( $summary['profit'] - 215.0 ) < 0.001;

$map = array();
foreach ( $series['labels'] as $index => $label ) {
    $map[ $label ] = array(
        'revenue'  => $series['revenue'][ $index ],
        'expenses' => $series['expenses'][ $index ],
        'zakat'    => $series['zakat'][ $index ],
        'profit'   => $series['profit'][ $index ],
    );
}

$assertions[] = isset( $map['2024-01-01'] ) && abs( $map['2024-01-01']['revenue'] - 100.0 ) < 0.001;
$assertions[] = isset( $map['2024-01-01'] ) && abs( $map['2024-01-01']['profit'] - 100.0 ) < 0.001;
$assertions[] = isset( $map['2024-01-15'] ) && abs( $map['2024-01-15']['expenses'] - 30.0 ) < 0.001;
$assertions[] = isset( $map['2024-01-15'] ) && abs( $map['2024-01-15']['profit'] + 30.0 ) < 0.001;
$assertions[] = isset( $map['2024-01-21'] ) && abs( $map['2024-01-21']['zakat'] - 5.0 ) < 0.001;
$assertions[] = isset( $map['2024-01-21'] ) && abs( $map['2024-01-21']['profit'] + 5.0 ) < 0.001;
$assertions[] = isset( $map['2024-01-31'] ) && abs( $map['2024-01-31']['revenue'] - 150.0 ) < 0.001;
$assertions[] = isset( $map['2024-01-31'] ) && abs( $map['2024-01-31']['profit'] - 150.0 ) < 0.001;

$passed = array_reduce( $assertions, function ( $carry, $value ) {
    return $carry && $value;
}, true );

echo $passed ? "All manual ledger checks passed\n" : "Manual ledger checks failed\n";
