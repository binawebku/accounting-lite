<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title><?php echo esc_html( $invoice->number ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( BWK_AL_URL . 'public/css/public.css' ); ?>" />
</head>
<body class="bwk-invoice">
<div class="invoice-wrapper">
    <header>
        <h1><?php echo esc_html( bwk_get_option( 'company_name', get_bloginfo( 'name' ) ) ); ?></h1>
        <p><?php echo esc_html( bwk_get_option( 'company_email' ) ); ?></p>
    </header>
    <h2><?php _e( 'Invoice', 'bwk-accounting-lite' ); ?> <?php echo esc_html( $invoice->number ); ?></h2>
    <p><strong><?php _e( 'Bill To:', 'bwk-accounting-lite' ); ?></strong> <?php echo esc_html( $invoice->customer_name ); ?><br /><?php echo nl2br( esc_html( $invoice->billing_address ) ); ?></p>
    <table class="items">
        <thead><tr><th><?php _e( 'Item', 'bwk-accounting-lite' ); ?></th><th><?php _e( 'Qty', 'bwk-accounting-lite' ); ?></th><th><?php _e( 'Price', 'bwk-accounting-lite' ); ?></th><th><?php _e( 'Total', 'bwk-accounting-lite' ); ?></th></tr></thead>
        <tbody>
            <?php foreach ( $items as $it ) : ?>
            <tr>
                <td><?php echo esc_html( $it->item_name ); ?></td>
                <td><?php echo esc_html( $it->qty ); ?></td>
                <td><?php echo esc_html( $it->unit_price ); ?></td>
                <td><?php echo esc_html( $it->line_total ); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <table class="totals">
        <tr><th><?php _e( 'Subtotal', 'bwk-accounting-lite' ); ?></th><td><?php echo esc_html( $invoice->subtotal ); ?></td></tr>
        <tr><th><?php _e( 'Discount', 'bwk-accounting-lite' ); ?></th><td><?php echo esc_html( $invoice->discount_total ); ?></td></tr>
        <tr><th><?php _e( 'Tax', 'bwk-accounting-lite' ); ?></th><td><?php echo esc_html( $invoice->tax_total ); ?></td></tr>
        <tr><th><?php _e( 'Shipping', 'bwk-accounting-lite' ); ?></th><td><?php echo esc_html( $invoice->shipping_total ); ?></td></tr>
        <tr><th><?php _e( 'Grand Total', 'bwk-accounting-lite' ); ?></th><td><?php echo esc_html( $invoice->grand_total ); ?></td></tr>
    </table>
    <footer>
        <p><?php echo nl2br( esc_html( $invoice->notes ) ); ?></p>
    </footer>
</div>
<script src="<?php echo esc_url( BWK_AL_URL . 'public/js/public.js' ); ?>"></script>
</body>
</html>
