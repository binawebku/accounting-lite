<?php
/**
 * Quote table management.
 *
 * @author Wan Mohd Aiman Binawebpro.com
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BWK_Quotes_Table {
    public static function init() {
        add_action( 'admin_post_bwk_save_quote', array( __CLASS__, 'save_quote' ) );
        add_action( 'admin_post_bwk_convert_quote', array( __CLASS__, 'convert_to_invoice' ) );
        add_action( 'template_redirect', array( __CLASS__, 'maybe_render_print_quote' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
    }

    public static function register_rest_routes() {
        register_rest_route( 'bwk-accounting/v1', '/quote/(?P<id>\\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'rest_get_quote' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public static function rest_get_quote( $request ) {
        $quote = self::get_quote( intval( $request['id'] ) );
        if ( ! $quote ) {
            return new WP_Error( 'not_found', 'Quote not found', array( 'status' => 404 ) );
        }
        return rest_ensure_response( $quote );
    }

    public static function get_quote( $id ) {
        global $wpdb;
        $quote = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . bwk_table_quotes() . ' WHERE id=%d', $id ) );
        if ( ! $quote ) {
            return null;
        }
        $items = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . bwk_table_quote_items() . ' WHERE quote_id=%d ORDER BY line_no ASC', $id ) );
        foreach ( $items as $item ) {
            $product_id       = property_exists( $item, 'product_id' ) ? absint( $item->product_id ) : 0;
            $item->product_id = $product_id > 0 ? $product_id : null;

            if ( property_exists( $item, 'product_sku' ) ) {
                $item->product_sku = '' !== $item->product_sku ? $item->product_sku : null;
            } else {
                $item->product_sku = null;
            }
        }
        $quote->items = $items;
        return $quote;
    }

    public static function render_list_page() {
        if ( ! bwk_current_user_can() ) {
            return;
        }
        global $wpdb;
        $quotes = $wpdb->get_results( 'SELECT * FROM ' . bwk_table_quotes() . ' ORDER BY created_at DESC' );
        include BWK_AL_PATH . 'admin/views-quotes-list.php';
    }

    public static function render_edit_page() {
        if ( ! bwk_current_user_can() ) {
            return;
        }
        global $wpdb;
        $id    = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
        $quote = null;
        $items = array();
        if ( $id ) {
            $quote = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . bwk_table_quotes() . ' WHERE id=%d', $id ) );
            $items = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . bwk_table_quote_items() . ' WHERE quote_id=%d ORDER BY line_no ASC', $id ) );
        }
        include BWK_AL_PATH . 'admin/views-quote-edit.php';
    }

    public static function save_quote() {
        if ( ! bwk_current_user_can() ) {
            wp_die( 'Access denied' );
        }
        check_admin_referer( 'bwk_save_quote' );
        global $wpdb;
        $table   = bwk_table_quotes();
        $item_tb = bwk_table_quote_items();

        $id     = isset( $_POST['quote_id'] ) ? intval( $_POST['quote_id'] ) : 0;
        $number = sanitize_text_field( $_POST['number'] );
        if ( empty( $number ) ) {
            $number = bwk_next_quote_number();
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
            'grand_total'    => floatval( $_POST['grand_total'] ),
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

        $wpdb->delete( $item_tb, array( 'quote_id' => $id ) );
        if ( isset( $_POST['item_name'] ) && is_array( $_POST['item_name'] ) ) {
            $line_no = 0;
            foreach ( $_POST['item_name'] as $idx => $name ) {
                $name = sanitize_text_field( $name );
                if ( '' === $name ) {
                    continue;
                }
                $qty   = isset( $_POST['qty'][ $idx ] ) ? floatval( $_POST['qty'][ $idx ] ) : 0;
                $price = isset( $_POST['unit_price'][ $idx ] ) ? floatval( $_POST['unit_price'][ $idx ] ) : 0;
                $total = $qty * $price;
                $product_id = isset( $_POST['product_id'][ $idx ] ) ? absint( $_POST['product_id'][ $idx ] ) : 0;
                $product_id = $product_id > 0 ? $product_id : null;

                $product_sku = null;
                if ( isset( $_POST['product_sku'][ $idx ] ) ) {
                    $product_sku = sanitize_text_field( $_POST['product_sku'][ $idx ] );
                    if ( '' === $product_sku ) {
                        $product_sku = null;
                    }
                }
                $wpdb->insert( $item_tb, array(
                    'quote_id'   => $id,
                    'line_no'    => $line_no,
                    'item_name'  => $name,
                    'product_id' => $product_id,
                    'product_sku'=> $product_sku,
                    'qty'        => $qty,
                    'unit_price' => $price,
                    'line_total' => $total,
                ) );
                $line_no++;
            }
        }

        wp_redirect( admin_url( 'admin.php?page=bwk-quotes' ) );
        exit;
    }

    public static function convert_to_invoice() {
        if ( ! bwk_current_user_can() ) {
            wp_die( 'Access denied' );
        }
        $id    = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
        $quote = self::get_quote( $id );
        if ( ! $quote ) {
            wp_die( 'Quote not found' );
        }
        global $wpdb;
        $inv_tb  = bwk_table_invoices();
        $item_tb = bwk_table_invoice_items();
        $inv_data = array(
            'number'         => bwk_next_invoice_number(),
            'status'         => 'draft',
            'customer_name'  => $quote->customer_name,
            'customer_email' => $quote->customer_email,
            'billing_address'=> $quote->billing_address,
            'currency'       => $quote->currency,
            'subtotal'       => $quote->subtotal,
            'discount_total' => $quote->discount_total,
            'tax_total'      => $quote->tax_total,
            'shipping_total' => $quote->shipping_total,
            'grand_total'    => $quote->grand_total,
            'notes'          => $quote->notes,
            'created_at'     => current_time( 'mysql' ),
            'updated_at'     => current_time( 'mysql' ),
        );
        $wpdb->insert( $inv_tb, $inv_data );
        $invoice_id = $wpdb->insert_id;
        foreach ( $quote->items as $i => $it ) {
            $product_id = isset( $it->product_id ) ? absint( $it->product_id ) : 0;
            $product_id = $product_id > 0 ? $product_id : null;
            $product_sku = null;
            if ( isset( $it->product_sku ) && '' !== $it->product_sku ) {
                $product_sku = $it->product_sku;
            }

            $wpdb->insert( $item_tb, array(
                'invoice_id'   => $invoice_id,
                'line_no'      => $i,
                'item_name'    => $it->item_name,
                'product_id'   => $product_id,
                'product_sku'  => $product_sku,
                'qty'          => $it->qty,
                'unit_price'   => $it->unit_price,
                'line_total'   => $it->line_total,
            ) );
        }
        wp_redirect( admin_url( 'admin.php?page=bwk-invoice-add&id=' . $invoice_id ) );
        exit;
    }

    public static function maybe_render_print_quote() {
        if ( is_admin() ) {
            return;
        }

        if ( isset( $_GET['bwk_quote'] ) ) {
            $id    = intval( $_GET['bwk_quote'] );
            $nonce = isset( $_GET['_nonce'] ) ? sanitize_text_field( $_GET['_nonce'] ) : '';
            if ( ! wp_verify_nonce( $nonce, 'bwk_print_quote_' . $id ) ) {
                wp_die( 'Invalid nonce' );
            }
            $quote = self::get_quote( $id );
            if ( ! $quote ) {
                wp_die( 'Quote not found' );
            }
            $items = $quote->items;
            header( 'X-Robots-Tag: noindex, nofollow', true );
            include BWK_AL_PATH . 'public/templates/quote-a4.php';
            exit;
        }
    }
}
