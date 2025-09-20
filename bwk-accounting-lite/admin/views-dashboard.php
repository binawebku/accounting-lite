<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$currency      = isset( $metrics['currency'] ) ? $metrics['currency'] : '';
$income        = isset( $metrics['income'] ) ? $metrics['income'] : 0;
$deductions    = isset( $metrics['deductions'] ) ? $metrics['deductions'] : 0;
$profit        = isset( $metrics['profit'] ) ? $metrics['profit'] : 0;
$zakat_enabled = ! empty( $metrics['zakat_enabled'] );
$zakat_due     = isset( $metrics['zakat_to_pay'] ) ? $metrics['zakat_to_pay'] : 0;
$zakat_rate    = isset( $metrics['zakat_rate'] ) ? $metrics['zakat_rate'] : 0;
?>
<div class="wrap bwk-dashboard">
    <h1><?php esc_html_e( 'BWK Accounting Dashboard', 'bwk-accounting-lite' ); ?></h1>
    <div class="bwk-dashboard-grid">
        <div class="bwk-dashboard-card">
            <h2><?php esc_html_e( 'Revenue', 'bwk-accounting-lite' ); ?></h2>
            <p class="bwk-dashboard-amount">
                <span class="bwk-dashboard-value"><?php echo esc_html( number_format_i18n( $income, 2 ) ); ?></span>
                <?php if ( $currency ) : ?>
                    <span class="bwk-dashboard-currency"><?php echo esc_html( $currency ); ?></span>
                <?php endif; ?>
            </p>
            <p class="description"><?php esc_html_e( 'Total recognised sales for the selected window.', 'bwk-accounting-lite' ); ?></p>
        </div>
        <div class="bwk-dashboard-card">
            <h2><?php esc_html_e( 'Refunds & Expenses', 'bwk-accounting-lite' ); ?></h2>
            <p class="bwk-dashboard-amount">
                <span class="bwk-dashboard-value"><?php echo esc_html( number_format_i18n( $deductions, 2 ) ); ?></span>
                <?php if ( $currency ) : ?>
                    <span class="bwk-dashboard-currency"><?php echo esc_html( $currency ); ?></span>
                <?php endif; ?>
            </p>
            <p class="description"><?php esc_html_e( 'Money out from refunds or tracked expenses.', 'bwk-accounting-lite' ); ?></p>
        </div>
        <div class="bwk-dashboard-card">
            <h2><?php esc_html_e( 'Net Profit', 'bwk-accounting-lite' ); ?></h2>
            <p class="bwk-dashboard-amount">
                <span class="bwk-dashboard-value"><?php echo esc_html( number_format_i18n( $profit, 2 ) ); ?></span>
                <?php if ( $currency ) : ?>
                    <span class="bwk-dashboard-currency"><?php echo esc_html( $currency ); ?></span>
                <?php endif; ?>
            </p>
            <p class="description"><?php esc_html_e( 'Revenue minus refunds and expenses.', 'bwk-accounting-lite' ); ?></p>
        </div>
        <?php if ( $zakat_enabled ) : ?>
            <div class="bwk-dashboard-card bwk-dashboard-highlight">
                <h2><?php esc_html_e( 'Zakat to Pay', 'bwk-accounting-lite' ); ?></h2>
                <p class="bwk-dashboard-amount">
                    <span class="bwk-dashboard-value"><?php echo esc_html( number_format_i18n( $zakat_due, 2 ) ); ?></span>
                    <?php if ( $currency ) : ?>
                        <span class="bwk-dashboard-currency"><?php echo esc_html( $currency ); ?></span>
                    <?php endif; ?>
                </p>
                <p class="description">
                    <?php
                    printf(
                        esc_html__( 'Calculated at %s%% of profit.', 'bwk-accounting-lite' ),
                        esc_html( number_format_i18n( $zakat_rate, 2 ) )
                    );
                    ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>
