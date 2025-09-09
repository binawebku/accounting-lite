<?php
/**
 * Invoice management using custom tables.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BWK_Invoices {
    public static function init() {
        add_action( 'admin_post_bwk_save_invoice', array( __CLASS__, 'save_invoice' ) );
        add_action( 'admin_init', array( __CLASS__, 'maybe_render_print_invoice' ) );
        add_shortcode( 'bwk_invoice', array( __CLASS__, 'shortcode_invoice' ) );
    }

    public static function render_list_page() {
        if ( ! bwk_current_user_can() ) {
            return;
        }
        global $wpdb;
        $table = bwk_table_invoices();
        $invoices = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC" );
        include BWK_AL_PATH . 'admin/views-invoices-list.php';
    }

    public static function render_edit_page() {
        if ( ! bwk_current_user_can() ) {
            return;
        }
        global $wpdb;
        $id      = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
        $invoice = null;
        $items   = array();
        if ( $id ) {
            $invoice = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . bwk_table_invoices() . ' WHERE id=%d', $id ) );
            $items   = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . bwk_table_invoice_items() . ' WHERE invoice_id=%d ORDER BY line_no ASC', $id ) );
        }
        include BWK_AL_PATH . 'admin/views-invoice-edit.php';
    }

    public static function save_invoice() {
        if ( ! bwk_current_user_can() ) {
            wp_die( 'Access denied' );
        }
        check_admin_referer( 'bwk_save_invoice' );
        global $wpdb;
        $table   = bwk_table_invoices();
        $item_tb = bwk_table_invoice_items();

        $id      = isset( $_POST['invoice_id'] ) ? intval( $_POST['invoice_id'] ) : 0;
        $prev_status = $id ? $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table WHERE id=%d", $id ) ) : '';
        $number  = sanitize_text_field( $_POST['number'] );
        if ( empty( $number ) ) {
            $number = bwk_next_invoice_number();
        }
        $grand_total = floatval( $_POST['grand_total'] );
        $zakat_total = 0;
        if ( get_option( 'bwk_accounting_enable_zakat' ) ) {
            $rate        = floatval( bwk_get_option( 'zakat_rate', 2.5 ) );
            $zakat_total = $grand_total * ( $rate / 100 );
        }
        $data = array(
            'number'         => $number,
            'status'         => sanitize_text_field( $_POST['status'] ),
            'customer_name'  => sanitize_text_field( $_POST['customer_name'] ),
            'customer_email' => sanitize_email( $_POST['customer_email'] ),
            'billing_address'=> sanitize_textarea_field( $_POST['billing_address'] ),
            'currency'       => sanitize_text_field( $_POST['currency'] ),
            'subtotal'       => floatval( $_POST['subtotal'] ),
            'discount_total' => floatval( $_POST['discount_total'] ),
            'tax_total'      => floatval( $_POST['tax_total'] ),
            'shipping_total' => floatval( $_POST['shipping_total'] ),
            'zakat_total'    => $zakat_total,
            'grand_total'    => $grand_total,
            'notes'          => sanitize_textarea_field( $_POST['notes'] ),
            'updated_at'     => current_time( 'mysql' ),
        );
        if ( $id ) {
            $wpdb->update( $table, $data, array( 'id' => $id ) );
        } else {
            $data['created_at'] = current_time( 'mysql' );
            $wpdb->insert( $table, $data );
            $id = $wpdb->insert_id;
        }

        // Items.
        $wpdb->delete( $item_tb, array( 'invoice_id' => $id ) );
        if ( isset( $_POST['item_name'] ) && is_array( $_POST['item_name'] ) ) {
            $line_no = 0;
            foreach ( $_POST['item_name'] as $idx => $name ) {
                $name = sanitize_text_field( $name );
                if ( '' === $name ) {
                    continue;
                }
                $qty   = floatval( $_POST['qty'][ $idx ] );
                $price = floatval( $_POST['unit_price'][ $idx ] );
                $total = $qty * $price;
                $wpdb->insert( $item_tb, array(
                    'invoice_id'  => $id,
                    'line_no'     => $line_no,
                    'item_name'   => $name,
                    'qty'         => $qty,
                    'unit_price'  => $price,
                    'line_total'  => $total,
                ) );
                $line_no++;
            }
        }

        if ( $data['status'] === 'paid' && $prev_status !== 'paid' && $zakat_total > 0 ) {
            BWK_Ledger::insert_zakat( $id, $zakat_total, $data['currency'] );
        }

        wp_redirect( admin_url( 'admin.php?page=bwk-accounting' ) );
        exit;
    }

    public static function maybe_render_print_invoice() {
        if ( isset( $_GET['bwk_invoice'] ) ) {
            $id    = intval( $_GET['bwk_invoice'] );
            $nonce = isset( $_GET['_nonce'] ) ? sanitize_text_field( $_GET['_nonce'] ) : '';
            if ( ! wp_verify_nonce( $nonce, 'bwk_print_invoice_' . $id ) ) {
                wp_die( 'Invalid nonce' );
            }
            global $wpdb;
            $invoice = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . bwk_table_invoices() . ' WHERE id=%d', $id ) );
            if ( ! $invoice ) {
                wp_die( 'Invoice not found' );
            }
            $items = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . bwk_table_invoice_items() . ' WHERE invoice_id=%d ORDER BY line_no ASC', $id ) );
            header( 'X-Robots-Tag: noindex, nofollow', true );
            include BWK_AL_PATH . 'public/templates/invoice-a4.php';
            exit;
        }
    }

    public static function shortcode_invoice( $atts ) {
        $atts = shortcode_atts( array( 'id' => 0 ), $atts );
        $id   = intval( $atts['id'] );
        if ( ! $id ) {
            return '';
        }
        global $wpdb;
        $invoice = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . bwk_table_invoices() . ' WHERE id=%d', $id ) );
        if ( ! $invoice ) {
            return '';
        }
        $items = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . bwk_table_invoice_items() . ' WHERE invoice_id=%d ORDER BY line_no ASC', $id ) );
        ob_start();
        include BWK_AL_PATH . 'public/templates/invoice-a4.php';
        return ob_get_clean();
    }
}
