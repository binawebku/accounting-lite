<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$primary_currency      = isset( $primary_currency ) && $primary_currency ? $primary_currency : 'USD';
$currency_prefix       = $primary_currency ? $primary_currency . ' ' : '';
$invoice_summary       = isset( $invoice_summary ) && is_array( $invoice_summary ) ? $invoice_summary : array();
$profit_summary        = isset( $profit_summary ) && is_array( $profit_summary ) ? $profit_summary : array();
$zakat_summary         = isset( $zakat_summary ) && is_array( $zakat_summary ) ? $zakat_summary : array();
$invoice_status_totals = isset( $invoice_status_totals ) && is_array( $invoice_status_totals ) ? $invoice_status_totals : array();
$quote_status_totals   = isset( $quote_status_totals ) && is_array( $quote_status_totals ) ? $quote_status_totals : array();
$recent_activity       = isset( $recent_activity ) && is_array( $recent_activity ) ? $recent_activity : array();

$invoice_total = isset( $invoice_summary['amount'] ) ? (float) $invoice_summary['amount'] : 0.0;
$invoice_count = isset( $invoice_summary['count'] ) ? (int) $invoice_summary['count'] : 0;
$invoice_paid  = isset( $invoice_summary['paid'] ) ? (float) $invoice_summary['paid'] : 0.0;
$invoice_due   = isset( $invoice_summary['outstanding'] ) ? (float) $invoice_summary['outstanding'] : 0.0;

$profit_revenue = isset( $profit_summary['revenue'] ) ? (float) $profit_summary['revenue'] : 0.0;
$profit_expense = isset( $profit_summary['expenses'] ) ? (float) $profit_summary['expenses'] : 0.0;
$profit_zakat   = isset( $profit_summary['zakat'] ) ? (float) $profit_summary['zakat'] : 0.0;
$profit_net     = isset( $profit_summary['profit'] ) ? (float) $profit_summary['profit'] : 0.0;
$profit_margin  = isset( $profit_summary['margin'] ) ? (float) $profit_summary['margin'] : 0.0;

$profit_margin_display = number_format_i18n( $profit_margin, 1 );
$profit_margin_message = $profit_margin >= 0
    ? sprintf( __( '%s%% margin', 'bwk-accounting-lite' ), $profit_margin_display )
    : sprintf( __( '%s%% loss', 'bwk-accounting-lite' ), number_format_i18n( abs( $profit_margin ), 1 ) );
$profit_margin_progress = $profit_margin > 0 ? min( 100, $profit_margin ) : 0;
$profit_class           = $profit_net >= 0 ? ' is-positive' : ' is-negative';

$zakat_expected = isset( $zakat_summary['expected'] ) ? (float) $zakat_summary['expected'] : 0.0;
$zakat_recorded = isset( $zakat_summary['recorded'] ) ? (float) $zakat_summary['recorded'] : 0.0;
$zakat_pending  = isset( $zakat_summary['pending'] ) ? (float) $zakat_summary['pending'] : 0.0;
$zakat_rate     = isset( $zakat_summary['rate'] ) ? (float) $zakat_summary['rate'] : 0.0;
$zakat_progress = isset( $zakat_summary['progress'] ) ? (float) $zakat_summary['progress'] : 0.0;
$zakat_progress = max( 0, min( 100, $zakat_progress ) );
$last_zakat     = isset( $zakat_summary['last_contribution'] ) ? $zakat_summary['last_contribution'] : null;

$paid_ratio = $invoice_total > 0 ? round( ( $invoice_paid / $invoice_total ) * 100 ) : 0;
$due_ratio  = $invoice_total > 0 ? round( ( $invoice_due / $invoice_total ) * 100 ) : 0;

?>
<div class="wrap bwk-dashboard-wrap">
    <div class="bwk-dashboard-heading">
        <h1><?php esc_html_e( 'Accounting Dashboard', 'bwk-accounting-lite' ); ?></h1>
        <p class="bwk-dashboard-subtitle"><?php esc_html_e( 'Monitor cash flow, track Zakat obligations, and stay ahead of your invoicing pipeline.', 'bwk-accounting-lite' ); ?></p>
    </div>

    <div class="bwk-dashboard-kpis">
        <article class="bwk-dashboard-kpi">
            <span class="bwk-dashboard-kpi-label"><?php esc_html_e( 'Total Invoiced', 'bwk-accounting-lite' ); ?></span>
            <span class="bwk-dashboard-kpi-value"><?php echo esc_html( $currency_prefix . number_format_i18n( $invoice_total, 2 ) ); ?></span>
            <span class="bwk-dashboard-kpi-meta"><?php echo esc_html( sprintf( _n( '%s invoice recorded', '%s invoices recorded', $invoice_count, 'bwk-accounting-lite' ), number_format_i18n( $invoice_count ) ) ); ?></span>
        </article>
        <article class="bwk-dashboard-kpi">
            <span class="bwk-dashboard-kpi-label"><?php esc_html_e( 'Paid to Date', 'bwk-accounting-lite' ); ?></span>
            <span class="bwk-dashboard-kpi-value"><?php echo esc_html( $currency_prefix . number_format_i18n( $invoice_paid, 2 ) ); ?></span>
            <span class="bwk-dashboard-kpi-meta"><?php echo esc_html( sprintf( __( '%s%% of invoiced total', 'bwk-accounting-lite' ), number_format_i18n( $paid_ratio ) ) ); ?></span>
        </article>
        <article class="bwk-dashboard-kpi">
            <span class="bwk-dashboard-kpi-label"><?php esc_html_e( 'Outstanding Balance', 'bwk-accounting-lite' ); ?></span>
            <span class="bwk-dashboard-kpi-value"><?php echo esc_html( $currency_prefix . number_format_i18n( $invoice_due, 2 ) ); ?></span>
            <span class="bwk-dashboard-kpi-meta"><?php echo esc_html( sprintf( __( '%s%% awaiting payment', 'bwk-accounting-lite' ), number_format_i18n( $due_ratio ) ) ); ?></span>
        </article>
        <article class="bwk-dashboard-kpi bwk-dashboard-kpi--profit<?php echo esc_attr( $profit_class ); ?>">
            <span class="bwk-dashboard-kpi-label"><?php esc_html_e( 'Net Profit', 'bwk-accounting-lite' ); ?></span>
            <span class="bwk-dashboard-kpi-value"><?php echo esc_html( $currency_prefix . number_format_i18n( $profit_net, 2 ) ); ?></span>
            <span class="bwk-dashboard-kpi-meta"><?php echo esc_html( $profit_margin_message ); ?></span>
        </article>
        <article class="bwk-dashboard-kpi bwk-dashboard-kpi--accent">
            <span class="bwk-dashboard-kpi-label"><?php esc_html_e( 'Zakat Reserved', 'bwk-accounting-lite' ); ?></span>
            <span class="bwk-dashboard-kpi-value"><?php echo esc_html( $currency_prefix . number_format_i18n( $zakat_recorded, 2 ) ); ?></span>
            <span class="bwk-dashboard-kpi-meta"><?php echo esc_html( sprintf( __( '%s pending at %s%%', 'bwk-accounting-lite' ), $currency_prefix . number_format_i18n( $zakat_pending, 2 ), number_format_i18n( $zakat_rate, 1 ) ) ); ?></span>
        </article>
    </div>

    <div class="bwk-dashboard-grid bwk-dashboard-grid--feature">
        <section class="bwk-dashboard-panel bwk-dashboard-panel-chart">
            <header class="bwk-dashboard-panel-header">
                <h2><?php esc_html_e( 'Revenue Trend', 'bwk-accounting-lite' ); ?></h2>
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
                <h2><?php esc_html_e( 'Profit Breakdown', 'bwk-accounting-lite' ); ?></h2>
            </header>
            <ul class="bwk-dashboard-breakdown">
                <li>
                    <span class="bwk-dashboard-breakdown-label"><?php esc_html_e( 'Revenue', 'bwk-accounting-lite' ); ?></span>
                    <span class="bwk-dashboard-breakdown-value"><?php echo esc_html( $currency_prefix . number_format_i18n( $profit_revenue, 2 ) ); ?></span>
                </li>
                <li>
                    <span class="bwk-dashboard-breakdown-label"><?php esc_html_e( 'Expenses', 'bwk-accounting-lite' ); ?></span>
                    <span class="bwk-dashboard-breakdown-value is-negative"><?php echo esc_html( $currency_prefix . number_format_i18n( $profit_expense, 2 ) ); ?></span>
                </li>
                <li>
                    <span class="bwk-dashboard-breakdown-label"><?php esc_html_e( 'Zakat Allocation', 'bwk-accounting-lite' ); ?></span>
                    <span class="bwk-dashboard-breakdown-value"><?php echo esc_html( $currency_prefix . number_format_i18n( $profit_zakat, 2 ) ); ?></span>
                </li>
                <li class="bwk-dashboard-breakdown-total">
                    <span class="bwk-dashboard-breakdown-label"><?php esc_html_e( 'Net Profit', 'bwk-accounting-lite' ); ?></span>
                    <span class="bwk-dashboard-breakdown-value"><?php echo esc_html( $currency_prefix . number_format_i18n( $profit_net, 2 ) ); ?></span>
                </li>
            </ul>
            <div class="bwk-dashboard-progress">
                <div class="bwk-dashboard-progress-track"></div>
                <div class="bwk-dashboard-progress-bar" style="width: <?php echo esc_attr( $profit_margin_progress ); ?>%;"></div>
            </div>
            <p class="bwk-dashboard-progress-label"><?php echo esc_html( $profit_margin_message ); ?></p>
        </section>
    </div>

    <div class="bwk-dashboard-grid">
        <section class="bwk-dashboard-panel">
            <header class="bwk-dashboard-panel-header">
                <h2><?php esc_html_e( 'Invoice Status Overview', 'bwk-accounting-lite' ); ?></h2>
            </header>
            <div class="bwk-dashboard-status-grid">
                <?php foreach ( $invoice_status_totals as $status => $row ) :
                    $fallback_label = ucwords( str_replace( array( '-', '_' ), ' ', (string) $status ) );
                    $label          = isset( $row['label'] ) ? $row['label'] : $fallback_label;
                    $count          = isset( $row['count'] ) ? (int) $row['count'] : 0;
                    $total          = isset( $row['total'] ) ? (float) $row['total'] : 0.0;
                    $status_class   = $status ? ' status-' . sanitize_html_class( $status ) : '';
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
                <h2><?php esc_html_e( 'Zakat Tracker', 'bwk-accounting-lite' ); ?></h2>
            </header>
            <div class="bwk-dashboard-meta-grid">
                <div>
                    <span class="bwk-dashboard-meta-label"><?php esc_html_e( 'Expected', 'bwk-accounting-lite' ); ?></span>
                    <span class="bwk-dashboard-meta-value"><?php echo esc_html( $currency_prefix . number_format_i18n( $zakat_expected, 2 ) ); ?></span>
                </div>
                <div>
                    <span class="bwk-dashboard-meta-label"><?php esc_html_e( 'Recorded', 'bwk-accounting-lite' ); ?></span>
                    <span class="bwk-dashboard-meta-value"><?php echo esc_html( $currency_prefix . number_format_i18n( $zakat_recorded, 2 ) ); ?></span>
                </div>
                <div>
                    <span class="bwk-dashboard-meta-label"><?php esc_html_e( 'Pending', 'bwk-accounting-lite' ); ?></span>
                    <span class="bwk-dashboard-meta-value"><?php echo esc_html( $currency_prefix . number_format_i18n( $zakat_pending, 2 ) ); ?></span>
                </div>
                <div>
                    <span class="bwk-dashboard-meta-label"><?php esc_html_e( 'Zakat Rate', 'bwk-accounting-lite' ); ?></span>
                    <span class="bwk-dashboard-meta-value"><?php echo esc_html( number_format_i18n( $zakat_rate, 1 ) . '%' ); ?></span>
                </div>
            </div>
            <div class="bwk-dashboard-progress bwk-dashboard-progress--accent">
                <div class="bwk-dashboard-progress-track"></div>
                <div class="bwk-dashboard-progress-bar" style="width: <?php echo esc_attr( $zakat_progress ); ?>%;"></div>
            </div>
            <?php if ( $last_zakat && ! empty( $last_zakat['timestamp'] ) ) :
                $timestamp     = (int) $last_zakat['timestamp'];
                $last_currency = isset( $last_zakat['currency'] ) && $last_zakat['currency'] ? $last_zakat['currency'] : $primary_currency;
                $last_amount   = isset( $last_zakat['amount'] ) ? abs( (float) $last_zakat['amount'] ) : 0.0;
                $time_diff     = human_time_diff( $timestamp, current_time( 'timestamp' ) );
                $datetime_attr = wp_date( 'c', $timestamp );

                $amount_text = esc_html( $last_currency . ' ' . number_format_i18n( $last_amount, 2 ) );
                $time_text   = '<time datetime="' . esc_attr( $datetime_attr ) . '">' . esc_html( $time_diff ) . '</time>';
                $message     = sprintf(
                    /* translators: 1: amount, 2: relative time */
                    __( 'Last contribution of %1$s was recorded %2$s ago.', 'bwk-accounting-lite' ),
                    $amount_text,
                    $time_text
                );
                ?>
                <p class="bwk-dashboard-meta-note"><?php echo wp_kses( $message, array( 'time' => array( 'datetime' => true ) ) ); ?></p>
            <?php else : ?>
                <p class="bwk-dashboard-meta-note"><?php esc_html_e( 'No Zakat contributions have been logged yet.', 'bwk-accounting-lite' ); ?></p>
            <?php endif; ?>
        </section>
    </div>

    <div class="bwk-dashboard-grid">
        <section class="bwk-dashboard-panel">
            <header class="bwk-dashboard-panel-header">
                <h2><?php esc_html_e( 'Quote Pipeline', 'bwk-accounting-lite' ); ?></h2>
            </header>
            <ul class="bwk-dashboard-list">
                <?php
                $has_quote_activity = false;
                foreach ( $quote_status_totals as $status => $row ) :
                    $fallback_label = ucwords( str_replace( array( '-', '_' ), ' ', (string) $status ) );
                    $label          = isset( $row['label'] ) ? $row['label'] : $fallback_label;
                    $count          = isset( $row['count'] ) ? (int) $row['count'] : 0;
                    $total          = isset( $row['total'] ) ? (float) $row['total'] : 0.0;
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
        <section class="bwk-dashboard-panel">
            <header class="bwk-dashboard-panel-header">
                <h2><?php esc_html_e( 'Recent Activity', 'bwk-accounting-lite' ); ?></h2>
            </header>
            <?php if ( $recent_activity ) : ?>
                <ul class="bwk-dashboard-activity">
                    <?php foreach ( $recent_activity as $activity ) :
                        $type_label    = isset( $activity['type_label'] ) ? $activity['type_label'] : '';
                        $title         = isset( $activity['title'] ) ? $activity['title'] : '';
                        $url           = isset( $activity['url'] ) ? $activity['url'] : '';
                        $status        = isset( $activity['status'] ) ? $activity['status'] : '';
                        $status_label  = isset( $activity['status_label'] ) ? $activity['status_label'] : '';
                        $status_class  = $status ? ' status-' . sanitize_html_class( $status ) : '';
                        $amount_value  = isset( $activity['total'] ) ? (float) $activity['total'] : null;
                        $activity_currency = isset( $activity['currency'] ) && $activity['currency'] ? $activity['currency'] : $primary_currency;
                        $amount_display = null === $amount_value ? '' : $activity_currency . ' ' . number_format_i18n( abs( $amount_value ), 2 );
                        $timestamp     = isset( $activity['timestamp'] ) ? (int) $activity['timestamp'] : 0;
                        $time_phrase   = '';
                        $datetime_attr = '';
                        if ( $timestamp ) {
                            $time_phrase   = human_time_diff( $timestamp, current_time( 'timestamp' ) );
                            $datetime_attr = wp_date( 'c', $timestamp );
                        }
                        $amount_class = '';
                        if ( null !== $amount_value ) {
                            $amount_class = $amount_value < 0 ? ' is-negative' : ' is-positive';
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
                                    <span class="bwk-dashboard-activity-amount<?php echo esc_attr( $amount_class ); ?>"><?php echo esc_html( $amount_display ); ?></span>
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
