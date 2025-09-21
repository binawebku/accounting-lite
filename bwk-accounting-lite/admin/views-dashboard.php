<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$primary_currency = isset( $primary_currency ) && $primary_currency ? $primary_currency : 'USD';
$currency_prefix  = $primary_currency ? $primary_currency . ' ' : '';
$invoice_summary  = isset( $invoice_summary ) && is_array( $invoice_summary ) ? $invoice_summary : array();
$invoice_total    = isset( $invoice_summary['amount'] ) ? (float) $invoice_summary['amount'] : 0.0;
$invoice_count    = isset( $invoice_summary['count'] ) ? (int) $invoice_summary['count'] : 0;
$invoice_paid     = isset( $invoice_summary['paid'] ) ? (float) $invoice_summary['paid'] : 0.0;
$invoice_due      = isset( $invoice_summary['outstanding'] ) ? (float) $invoice_summary['outstanding'] : 0.0;
$profit_summary   = isset( $profit_summary ) && is_array( $profit_summary ) ? $profit_summary : array();
$profit_currency  = isset( $profit_summary['currency'] ) && $profit_summary['currency'] ? $profit_summary['currency'] : $primary_currency;
$profit_prefix    = $profit_currency ? $profit_currency . ' ' : '';
$profit_net       = isset( $profit_summary['net'] ) ? (float) $profit_summary['net'] : 0.0;
$profit_sales     = isset( $profit_summary['sales'] ) ? (float) $profit_summary['sales'] : 0.0;
$profit_refunds   = isset( $profit_summary['refunds'] ) ? (float) $profit_summary['refunds'] : 0.0;
$profit_expenses  = isset( $profit_summary['expenses'] ) ? (float) $profit_summary['expenses'] : 0.0;
$profit_costs     = isset( $profit_summary['costs'] ) ? (float) $profit_summary['costs'] : 0.0;
$profit_window_label = isset( $profit_window_label ) ? (string) $profit_window_label : '';
$invoice_status_totals = isset( $invoice_status_totals ) && is_array( $invoice_status_totals ) ? $invoice_status_totals : array();
$quote_status_totals   = isset( $quote_status_totals ) && is_array( $quote_status_totals ) ? $quote_status_totals : array();
$recent_activity       = isset( $recent_activity ) && is_array( $recent_activity ) ? $recent_activity : array();
?>
<div class="wrap bwk-dashboard-wrap">
    <h1><?php esc_html_e( 'Accounting Dashboard', 'bwk-accounting-lite' ); ?></h1>

    <div class="bwk-dashboard-kpis">
        <div class="bwk-dashboard-kpi">
            <span class="bwk-dashboard-kpi-label"><?php esc_html_e( 'Net Profit', 'bwk-accounting-lite' ); ?></span>
            <span class="bwk-dashboard-kpi-value"><?php echo esc_html( $profit_prefix . number_format_i18n( $profit_net, 2 ) ); ?></span>
            <?php if ( $profit_window_label ) : ?>
                <span class="bwk-dashboard-kpi-meta"><?php echo esc_html( $profit_window_label ); ?></span>
            <?php endif; ?>
            <?php
            $profit_breakdown = sprintf(
                /* translators: 1: sales total, 2: refunds total, 3: expenses total, 4: cost of goods total. */
                __( 'Sales %1$s, refunds %2$s, expenses %3$s, costs %4$s', 'bwk-accounting-lite' ),
                $profit_prefix . number_format_i18n( $profit_sales, 2 ),
                $profit_prefix . number_format_i18n( $profit_refunds, 2 ),
                $profit_prefix . number_format_i18n( $profit_expenses, 2 ),
                $profit_prefix . number_format_i18n( $profit_costs, 2 )
            );
            ?>
            <span class="bwk-dashboard-kpi-meta"><?php echo esc_html( $profit_breakdown ); ?></span>
        </div>
        <div class="bwk-dashboard-kpi">
            <span class="bwk-dashboard-kpi-label"><?php esc_html_e( 'Total Invoiced', 'bwk-accounting-lite' ); ?></span>
            <span class="bwk-dashboard-kpi-value"><?php echo esc_html( $currency_prefix . number_format_i18n( $invoice_total, 2 ) ); ?></span>
            <span class="bwk-dashboard-kpi-meta"><?php echo esc_html( sprintf( _n( '%s invoice recorded', '%s invoices recorded', $invoice_count, 'bwk-accounting-lite' ), number_format_i18n( $invoice_count ) ) ); ?></span>
        </div>
        <div class="bwk-dashboard-kpi">
            <span class="bwk-dashboard-kpi-label"><?php esc_html_e( 'Paid to Date', 'bwk-accounting-lite' ); ?></span>
            <span class="bwk-dashboard-kpi-value"><?php echo esc_html( $currency_prefix . number_format_i18n( $invoice_paid, 2 ) ); ?></span>
            <?php
            $paid_ratio = $invoice_total > 0 ? round( ( $invoice_paid / $invoice_total ) * 100 ) : 0;
            ?>
            <span class="bwk-dashboard-kpi-meta"><?php echo esc_html( sprintf( __( '%s%% of invoiced total', 'bwk-accounting-lite' ), number_format_i18n( $paid_ratio ) ) ); ?></span>
        </div>
        <div class="bwk-dashboard-kpi">
            <span class="bwk-dashboard-kpi-label"><?php esc_html_e( 'Outstanding Balance', 'bwk-accounting-lite' ); ?></span>
            <span class="bwk-dashboard-kpi-value"><?php echo esc_html( $currency_prefix . number_format_i18n( $invoice_due, 2 ) ); ?></span>
            <?php
            $due_ratio = $invoice_total > 0 ? round( ( $invoice_due / $invoice_total ) * 100 ) : 0;
            ?>
            <span class="bwk-dashboard-kpi-meta"><?php echo esc_html( sprintf( __( '%s%% awaiting payment', 'bwk-accounting-lite' ), number_format_i18n( $due_ratio ) ) ); ?></span>
        </div>
    </div>

    <div class="bwk-dashboard-grid">
        <section class="bwk-dashboard-panel">
            <header class="bwk-dashboard-panel-header">
                <h2><?php esc_html_e( 'Invoice Status Overview', 'bwk-accounting-lite' ); ?></h2>
            </header>
            <div class="bwk-dashboard-status-grid">
                <?php foreach ( $invoice_status_totals as $status => $row ) :
                    $fallback_label = ucwords( str_replace( array( '-', '_' ), ' ', (string) $status ) );
                    $label = isset( $row['label'] ) ? $row['label'] : $fallback_label;
                    $count = isset( $row['count'] ) ? (int) $row['count'] : 0;
                    $total = isset( $row['total'] ) ? (float) $row['total'] : 0.0;
                    $status_class = $status ? ' status-' . sanitize_html_class( $status ) : '';
                    ?>
                    <div class="bwk-dashboard-status-card<?php echo esc_attr( $status_class ); ?>">
                        <span class="bwk-dashboard-status-label"><?php echo esc_html( $label ); ?></span>
                        <span class="bwk-dashboard-status-amount"><?php echo esc_html( $currency_prefix . number_format_i18n( $total, 2 ) ); ?></span>
                        <span class="bwk-dashboard-status-count"><?php echo esc_html( sprintf( _n( '%s invoice', '%s invoices', $count, 'bwk-accounting-lite' ), number_format_i18n( $count ) ) ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="bwk-dashboard-panel">
            <header class="bwk-dashboard-panel-header">
                <h2><?php esc_html_e( 'Quote Pipeline', 'bwk-accounting-lite' ); ?></h2>
            </header>
            <ul class="bwk-dashboard-list">
                <?php
                $has_quote_activity = false;
                foreach ( $quote_status_totals as $status => $row ) :
                    $fallback_label = ucwords( str_replace( array( '-', '_' ), ' ', (string) $status ) );
                    $label = isset( $row['label'] ) ? $row['label'] : $fallback_label;
                    $count = isset( $row['count'] ) ? (int) $row['count'] : 0;
                    $total = isset( $row['total'] ) ? (float) $row['total'] : 0.0;
                    if ( $count > 0 || $total > 0 ) {
                        $has_quote_activity = true;
                    }
                    ?>
                    <li class="bwk-dashboard-list-item">
                        <span class="bwk-dashboard-list-label"><?php echo esc_html( $label ); ?></span>
                        <span class="bwk-dashboard-list-count"><?php echo esc_html( sprintf( _n( '%s quote', '%s quotes', $count, 'bwk-accounting-lite' ), number_format_i18n( $count ) ) ); ?></span>
                        <span class="bwk-dashboard-list-amount"><?php echo esc_html( $currency_prefix . number_format_i18n( $total, 2 ) ); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ( ! $has_quote_activity ) : ?>
                <p class="bwk-dashboard-empty-message"><?php esc_html_e( 'No quotes recorded yet. Create one to start tracking your pipeline.', 'bwk-accounting-lite' ); ?></p>
            <?php endif; ?>
        </section>
    </div>

    <div class="bwk-dashboard-grid">
        <section class="bwk-dashboard-panel bwk-dashboard-panel-chart">
            <header class="bwk-dashboard-panel-header">
                <h2><?php esc_html_e( 'Net Profit Trend', 'bwk-accounting-lite' ); ?></h2>
            </header>
            <div class="bwk-dashboard-chart">
                <canvas id="bwk-dashboard-chart"></canvas>
                <p id="bwk-dashboard-chart-state" class="bwk-dashboard-chart-state"><?php esc_html_e( 'Loading…', 'bwk-accounting-lite' ); ?></p>
            </div>
            <div class="bwk-dashboard-legend-wrap is-empty">
                <ul id="bwk-dashboard-chart-legend" class="bwk-dashboard-legend"></ul>
                <p class="bwk-dashboard-empty-message"><?php esc_html_e( 'Chart data will appear once invoices are marked as paid.', 'bwk-accounting-lite' ); ?></p>
            </div>
        </section>
        <section class="bwk-dashboard-panel">
            <header class="bwk-dashboard-panel-header">
                <h2><?php esc_html_e( 'Recent Activity', 'bwk-accounting-lite' ); ?></h2>
            </header>
            <?php if ( $recent_activity ) : ?>
                <ul class="bwk-dashboard-activity">
                    <?php foreach ( $recent_activity as $activity ) :
                        $type_label   = isset( $activity['type_label'] ) ? $activity['type_label'] : '';
                        $title        = isset( $activity['title'] ) ? $activity['title'] : '';
                        $url          = isset( $activity['url'] ) ? $activity['url'] : '';
                        $status       = isset( $activity['status'] ) ? $activity['status'] : '';
                        $status_label = isset( $activity['status_label'] ) ? $activity['status_label'] : '';
                        $status_class = $status ? ' status-' . sanitize_html_class( $status ) : '';
                        $amount_value = isset( $activity['total'] ) ? (float) $activity['total'] : null;
                        $activity_currency = isset( $activity['currency'] ) && $activity['currency'] ? $activity['currency'] : $primary_currency;
                        $amount_display = null === $amount_value ? '' : $activity_currency . ' ' . number_format_i18n( $amount_value, 2 );
                        $timestamp  = isset( $activity['timestamp'] ) ? (int) $activity['timestamp'] : 0;
                        $time_phrase = '';
                        $datetime_attr = '';
                        if ( $timestamp ) {
                            $time_phrase  = human_time_diff( $timestamp, current_time( 'timestamp' ) );
                            $datetime_attr = wp_date( 'c', $timestamp );
                        }
                        ?>
                        <li class="bwk-dashboard-activity-item">
                            <div class="bwk-dashboard-activity-header">
                                <?php if ( $type_label ) : ?>
                                    <span class="bwk-dashboard-activity-type"><?php echo esc_html( $type_label ); ?></span>
                                <?php endif; ?>
                                <?php if ( $url ) : ?>
                                    <a class="bwk-dashboard-activity-link" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a>
                                <?php else : ?>
                                    <span class="bwk-dashboard-activity-link"><?php echo esc_html( $title ); ?></span>
                                <?php endif; ?>
                                <?php if ( $status_label ) : ?>
                                    <span class="bwk-dashboard-activity-status<?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="bwk-dashboard-activity-meta">
                                <?php if ( '' !== $amount_display ) : ?>
                                    <span class="bwk-dashboard-activity-amount"><?php echo esc_html( $amount_display ); ?></span>
                                <?php endif; ?>
                                <?php if ( $time_phrase ) : ?>
                                    <time class="bwk-dashboard-activity-time" datetime="<?php echo esc_attr( $datetime_attr ); ?>"><?php echo esc_html( sprintf( __( '%s ago', 'bwk-accounting-lite' ), $time_phrase ) ); ?></time>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="bwk-dashboard-empty-message"><?php esc_html_e( 'Your latest invoices, quotes, and ledger updates will appear here.', 'bwk-accounting-lite' ); ?></p>
            <?php endif; ?>
        </section>
    </div>
</div>
