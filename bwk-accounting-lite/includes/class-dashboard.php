<?php
/**
 * Dashboard metrics and rendering.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BWK_Dashboard {
    public static function init() {
        // Placeholder for future dashboard specific hooks.
    }

    /**
     * Compile dashboard metrics.
     *
     * @param array $args Optional arguments passed to the profit helper.
     * @return array
     */
    public static function get_metrics( $args = array() ) {
        $profit_window = bwk_calculate_profit_window( $args );
        $currency      = strtoupper( bwk_get_option( 'default_currency', 'USD' ) );
        $zakat_enabled = (bool) get_option( 'bwk_accounting_enable_zakat' );
        $zakat_rate    = floatval( bwk_get_option( 'zakat_rate', 2.5 ) );
        $zakat_to_pay  = $zakat_enabled ? bwk_calculate_zakat_due( $args ) : 0.0;

        $metrics = array(
            'currency'      => $currency,
            'income'        => isset( $profit_window['income'] ) ? floatval( $profit_window['income'] ) : 0.0,
            'deductions'    => isset( $profit_window['deductions'] ) ? floatval( $profit_window['deductions'] ) : 0.0,
            'profit'        => isset( $profit_window['profit'] ) ? floatval( $profit_window['profit'] ) : 0.0,
            'zakat_enabled' => $zakat_enabled,
            'zakat_rate'    => $zakat_rate,
            'zakat_to_pay'  => $zakat_enabled ? max( 0.0, $zakat_to_pay ) : 0.0,
        );

        return apply_filters( 'bwk_dashboard_metrics', $metrics, $args );
    }

    /**
     * Render the dashboard page.
     */
    public static function render_page() {
        if ( ! bwk_current_user_can() ) {
            return;
        }

        $metrics = self::get_metrics();

        include BWK_AL_PATH . 'admin/views-dashboard.php';
    }
}
