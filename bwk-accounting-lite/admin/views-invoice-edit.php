<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$invoice_id = $invoice ? intval( $invoice->id ) : 0;
?>
<div class="wrap">
    <h1><?php echo $invoice_id ? esc_html__( 'Edit Invoice', 'bwk-accounting-lite' ) : esc_html__( 'Add Invoice', 'bwk-accounting-lite' ); ?></h1>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'bwk_save_invoice' ); ?>
        <input type="hidden" name="action" value="bwk_save_invoice" />
        <input type="hidden" name="invoice_id" value="<?php echo esc_attr( $invoice_id ); ?>" />
        <table class="form-table">
            <tr><th><label for="number"><?php _e( 'Number', 'bwk-accounting-lite' ); ?></label></th>
            <td><input name="number" id="number" type="text" value="<?php echo esc_attr( $invoice ? $invoice->number : '' ); ?>" class="regular-text" /></td></tr>
            <tr><th><label for="status"><?php _e( 'Status', 'bwk-accounting-lite' ); ?></label></th>
            <td><select name="status" id="status">
                <?php foreach ( array( 'draft','sent','paid','partial','void' ) as $st ) : ?>
                    <option value="<?php echo esc_attr( $st ); ?>" <?php selected( $invoice && $invoice->status === $st ); ?>><?php echo esc_html( ucfirst( $st ) ); ?></option>
                <?php endforeach; ?>
            </select></td></tr>
            <tr><th><label for="customer_name"><?php _e( 'Customer Name', 'bwk-accounting-lite' ); ?></label></th>
            <td><input name="customer_name" id="customer_name" type="text" value="<?php echo esc_attr( $invoice ? $invoice->customer_name : '' ); ?>" class="regular-text" /></td></tr>
            <tr><th><label for="customer_email"><?php _e( 'Customer Email', 'bwk-accounting-lite' ); ?></label></th>
            <td><input name="customer_email" id="customer_email" type="email" value="<?php echo esc_attr( $invoice ? $invoice->customer_email : '' ); ?>" class="regular-text" /></td></tr>
            <tr><th><label for="billing_address"><?php _e( 'Billing Address', 'bwk-accounting-lite' ); ?></label></th>
            <td><textarea name="billing_address" id="billing_address" rows="4" class="large-text"><?php echo esc_textarea( $invoice ? $invoice->billing_address : '' ); ?></textarea></td></tr>
        </table>
        <h2><?php _e( 'Items', 'bwk-accounting-lite' ); ?></h2>
        <table class="widefat" id="bwk-items-table">
            <thead><tr><th><?php _e( 'Item', 'bwk-accounting-lite' ); ?></th><th><?php _e( 'Qty', 'bwk-accounting-lite' ); ?></th><th><?php _e( 'Unit Price', 'bwk-accounting-lite' ); ?></th><th><?php _e( 'Line Total', 'bwk-accounting-lite' ); ?></th><th>&nbsp;</th></tr></thead>
            <tbody>
                <?php
                if ( $items ) {
                    foreach ( $items as $it ) {
                        $product_id       = $it->product_id ? absint( $it->product_id ) : '';
                        $product_sku      = $it->product_sku ? $it->product_sku : '';
                        $picker_classes   = 'bwk-product-picker';
                        $option_label     = $it->item_name;
                        $product_selected = ! empty( $product_id );

                        if ( $product_selected ) {
                            $picker_classes .= ' is-active';
                        }

                        if ( $product_sku ) {
                            $option_label .= ' (' . $product_sku . ')';
                        }
                        ?>
                        <tr class="bwk-item-row">
                            <td>
                                <input type="text" name="item_name[]" value="<?php echo esc_attr( $it->item_name ); ?>" />
                                <input type="hidden" class="bwk-product-id" name="product_id[]" value="<?php echo esc_attr( $product_id ); ?>" />
                                <input type="hidden" class="bwk-product-sku" name="product_sku[]" value="<?php echo esc_attr( $product_sku ); ?>" />
                                <div class="bwk-product-toggle">
                                    <label>
                                        <input type="checkbox" class="bwk-use-product" <?php checked( $product_selected ); ?> />
                                        <?php esc_html_e( 'Use existing product', 'bwk-accounting-lite' ); ?>
                                    </label>
                                    <div class="<?php echo esc_attr( $picker_classes ); ?>">
                                        <input type="search" class="bwk-product-search" placeholder="<?php esc_attr_e( 'Search for a product…', 'bwk-accounting-lite' ); ?>" />
                                        <select class="bwk-product-select">
                                            <option value=""><?php esc_html_e( 'Select a product', 'bwk-accounting-lite' ); ?></option>
                                            <?php if ( $product_selected ) : ?>
                                                <option value="<?php echo esc_attr( $product_id ); ?>" data-name="<?php echo esc_attr( $it->item_name ); ?>" data-price="<?php echo esc_attr( $it->unit_price ); ?>" data-sku="<?php echo esc_attr( $product_sku ); ?>" selected><?php echo esc_html( $option_label ); ?></option>
                                            <?php endif; ?>
                                        </select>
                                        <p class="description"><?php esc_html_e( 'Start typing to search WooCommerce products.', 'bwk-accounting-lite' ); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td><input type="number" step="0.01" name="qty[]" value="<?php echo esc_attr( $it->qty ); ?>" class="bwk-qty" /></td>
                            <td><input type="number" step="0.01" name="unit_price[]" value="<?php echo esc_attr( $it->unit_price ); ?>" class="bwk-price" /></td>
                            <td class="bwk-line-total"><?php echo esc_html( $it->line_total ); ?></td>
                            <td><button type="button" class="button bwk-remove">&times;</button></td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>
        <p><button type="button" class="button" id="bwk-add-row"><?php _e( 'Add Item', 'bwk-accounting-lite' ); ?></button></p>
        <h2><?php _e( 'Totals', 'bwk-accounting-lite' ); ?></h2>
        <table class="form-table">
            <tr><th><label for="subtotal"><?php _e( 'Subtotal', 'bwk-accounting-lite' ); ?></label></th>
            <td><input name="subtotal" id="subtotal" type="number" step="0.01" value="<?php echo esc_attr( $invoice ? $invoice->subtotal : 0 ); ?>" /></td></tr>
            <tr><th><label for="discount_total"><?php _e( 'Discount', 'bwk-accounting-lite' ); ?></label></th>
            <td><input name="discount_total" id="discount_total" type="number" step="0.01" value="<?php echo esc_attr( $invoice ? $invoice->discount_total : 0 ); ?>" /></td></tr>
            <tr><th><label for="tax_total"><?php _e( 'Tax', 'bwk-accounting-lite' ); ?></label></th>
            <td><input name="tax_total" id="tax_total" type="number" step="0.01" value="<?php echo esc_attr( $invoice ? $invoice->tax_total : 0 ); ?>" /></td></tr>
            <tr><th><label for="shipping_total"><?php _e( 'Shipping', 'bwk-accounting-lite' ); ?></label></th>
            <td><input name="shipping_total" id="shipping_total" type="number" step="0.01" value="<?php echo esc_attr( $invoice ? $invoice->shipping_total : 0 ); ?>" /></td></tr>
            <tr><th><label for="zakat_total"><?php _e( 'Zakat', 'bwk-accounting-lite' ); ?></label></th>
            <td><input name="zakat_total" id="zakat_total" type="number" step="0.01" value="<?php echo esc_attr( $invoice ? $invoice->zakat_total : 0 ); ?>" readonly /></td></tr>
            <tr><th><label for="grand_total"><?php _e( 'Grand Total', 'bwk-accounting-lite' ); ?></label></th>
            <td><input name="grand_total" id="grand_total" type="number" step="0.01" value="<?php echo esc_attr( $invoice ? $invoice->grand_total : 0 ); ?>" /></td></tr>
            <tr><th><label for="currency"><?php _e( 'Currency', 'bwk-accounting-lite' ); ?></label></th>
            <td><input name="currency" id="currency" type="text" value="<?php echo esc_attr( $invoice ? $invoice->currency : bwk_get_option( 'default_currency', 'USD' ) ); ?>" class="regular-text" /></td></tr>
            <tr><th><label for="notes"><?php _e( 'Notes', 'bwk-accounting-lite' ); ?></label></th>
            <td><textarea name="notes" id="notes" rows="4" class="large-text"><?php echo esc_textarea( $invoice ? $invoice->notes : '' ); ?></textarea></td></tr>
        </table>
        <?php submit_button( __( 'Save Invoice', 'bwk-accounting-lite' ) ); ?>
    </form>
</div>
