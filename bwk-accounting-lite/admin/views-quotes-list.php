<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php _e( 'Quotes', 'bwk-accounting-lite' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=bwk-quote-add' ) ); ?>" class="page-title-action"><?php _e( 'Add New', 'bwk-accounting-lite' ); ?></a></h1>
    <table class="widefat fixed">
        <thead><tr><th><?php _e( 'Number', 'bwk-accounting-lite' ); ?></th><th><?php _e( 'Customer', 'bwk-accounting-lite' ); ?></th><th><?php _e( 'Status', 'bwk-accounting-lite' ); ?></th><th><?php _e( 'Total', 'bwk-accounting-lite' ); ?></th><th><?php _e( 'Date', 'bwk-accounting-lite' ); ?></th><th>&nbsp;</th></tr></thead>
        <tbody>
            <?php if ( $quotes ) : foreach ( $quotes as $qt ) : ?>
                <tr>
                    <td><?php echo esc_html( $qt->number ); ?></td>
                    <td><?php echo esc_html( $qt->customer_name ); ?></td>
                    <td><?php echo esc_html( ucfirst( $qt->status ) ); ?></td>
                    <td><?php echo esc_html( $qt->grand_total ); ?></td>
                    <td><?php echo esc_html( mysql2date( 'Y-m-d', $qt->created_at ) ); ?></td>
                    <td><a href="<?php echo esc_url( admin_url( 'admin.php?page=bwk-quote-add&id=' . $qt->id ) ); ?>"><?php _e( 'Edit', 'bwk-accounting-lite' ); ?></a> |
                        <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=bwk_convert_quote&id=' . $qt->id ) ); ?>"><?php _e( 'Convert', 'bwk-accounting-lite' ); ?></a> |
                        <a href="<?php echo esc_url( add_query_arg( array( 'bwk_quote' => $qt->id, '_nonce' => wp_create_nonce( 'bwk_print_quote_' . $qt->id ) ), home_url() ) ); ?>" target="_blank"><?php _e( 'Print', 'bwk-accounting-lite' ); ?></a></td>
                </tr>
            <?php endforeach; else : ?>
                <tr><td colspan="6"><?php _e( 'No quotes found.', 'bwk-accounting-lite' ); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
