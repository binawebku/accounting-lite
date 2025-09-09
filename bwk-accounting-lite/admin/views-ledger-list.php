<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php _e( 'Ledger', 'bwk-accounting-lite' ); ?></h1>
    <table class="widefat fixed">
        <thead><tr><th><?php _e( 'Date', 'bwk-accounting-lite' ); ?></th><th><?php _e( 'Type', 'bwk-accounting-lite' ); ?></th><th><?php _e( 'Amount', 'bwk-accounting-lite' ); ?></th><th><?php _e( 'Currency', 'bwk-accounting-lite' ); ?></th><th><?php _e( 'Source', 'bwk-accounting-lite' ); ?></th></tr></thead>
        <tbody>
            <?php if ( $entries ) : foreach ( $entries as $e ) : ?>
                <tr>
                    <td><?php echo esc_html( mysql2date( 'Y-m-d', $e->txn_date ) ); ?></td>
                    <td><?php echo esc_html( $e->txn_type ); ?></td>
                    <td><?php echo esc_html( $e->amount ); ?></td>
                    <td><?php echo esc_html( $e->currency ); ?></td>
                    <td><?php echo esc_html( $e->source ); ?></td>
                </tr>
            <?php endforeach; else : ?>
                <tr><td colspan="5"><?php _e( 'No entries.', 'bwk-accounting-lite' ); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
