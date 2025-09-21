<?php
use PHPUnit\Framework\TestCase;

class LedgerProfitTest extends TestCase {
    protected function setUp(): void {
        global $wpdb, $_bwk_timezone_string, $_bwk_test_options;

        $wpdb->reset();

        $_bwk_timezone_string = 'Asia/Kuala_Lumpur';
        $_bwk_test_options    = array();

        update_option( 'timezone_string', 'Asia/Kuala_Lumpur' );
        update_option( 'bwk_accounting_default_currency', 'MYR' );
    }

    public function test_profit_summary_includes_utc_orders_with_local_range() {
        $this->seed_ledger_entries();

        $summary = BWK_Ledger::get_profit_summary(
            array(
                'range' => 'custom',
                'start' => '2024-01-01',
                'end'   => '2024-01-31',
            )
        );

        $this->assertArrayHasKey( 'range', $summary );
        $this->assertSame( '2024-01-01 00:00:00', $summary['range']['start'] );
        $this->assertSame( '2024-01-31 23:59:59', $summary['range']['end'] );
        $this->assertSame( 'Asia/Kuala_Lumpur', $summary['range']['timezone'] );

        $this->assertEquals( 250.0, $summary['revenue'] );
        $this->assertEquals( 30.0, $summary['expenses'] );
        $this->assertEquals( 5.0, $summary['zakat'] );
        $this->assertEquals( 215.0, $summary['profit'] );
    }

    public function test_profit_series_buckets_use_local_timezone() {
        $this->seed_ledger_entries();

        $series = BWK_Ledger::get_profit_series(
            array(
                'range' => 'custom',
                'start' => '2024-01-01',
                'end'   => '2024-01-31',
            )
        );

        $this->assertArrayHasKey( 'labels', $series );
        $labels = $series['labels'];
        $this->assertContains( '2024-01-01', $labels );
        $this->assertContains( '2024-01-15', $labels );
        $this->assertContains( '2024-01-21', $labels );
        $this->assertContains( '2024-01-31', $labels );

        $map = array();
        foreach ( $labels as $index => $label ) {
            $map[ $label ] = array(
                'revenue'  => $series['revenue'][ $index ],
                'expenses' => $series['expenses'][ $index ],
                'zakat'    => $series['zakat'][ $index ],
                'profit'   => $series['profit'][ $index ],
            );
        }

        $this->assertEquals( 100.0, $map['2024-01-01']['revenue'] );
        $this->assertEquals( 100.0, $map['2024-01-01']['profit'] );

        $this->assertEquals( 30.0, $map['2024-01-15']['expenses'] );
        $this->assertEquals( -30.0, $map['2024-01-15']['profit'] );

        $this->assertEquals( 5.0, $map['2024-01-21']['zakat'] );
        $this->assertEquals( -5.0, $map['2024-01-21']['profit'] );

        $this->assertEquals( 150.0, $map['2024-01-31']['revenue'] );
        $this->assertEquals( 150.0, $map['2024-01-31']['profit'] );
    }

    protected function seed_ledger_entries() {
        $this->add_entry( 'sale', 100.00, '2023-12-31 16:15:00' );
        $this->add_entry( 'sale', 150.00, '2024-01-31 15:45:00' );
        $this->add_entry( 'expense', -30.00, '2024-01-15 04:00:00' );
        $this->add_entry( 'zakat', 5.00, '2024-01-20 16:00:00' );
        $this->add_entry( 'sale', 75.00, '2023-12-31 15:45:00' );
    }

    protected function add_entry( $type, $amount, $utc_datetime, $currency = 'MYR' ) {
        global $wpdb;

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
    }
}
