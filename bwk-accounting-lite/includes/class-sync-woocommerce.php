<?php
/**
 * WooCommerce synchronization.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BWK_Sync_WooCommerce {
    public static function init() {
        if ( class_exists( 'WooCommerce' ) ) {
            add_action( 'woocommerce_new_order', array( __CLASS__, 'sync_order' ) );
            add_action( 'woocommerce_update_order', array( __CLASS__, 'sync_order' ) );
            add_action( 'woocommerce_order_refunded', array( __CLASS__, 'sync_refund' ), 10, 2 );
        }
    }

    public static function sync_order( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }
        BWK_Ledger::insert_entry( array(
            'source'    => 'wc',
            'source_id' => $order_id,
            'txn_type'  => 'sale',
            'amount'    => $order->get_total(),
            'currency'  => $order->get_currency(),
            'txn_date'  => gmdate( 'Y-m-d H:i:s', $order->get_date_created()->getTimestamp() ),
            'meta_json' => wp_json_encode( array( 'status' => $order->get_status() ) ),
        ) );
    }

    public static function sync_refund( $refund_id, $order_id ) {
        $refund = wc_get_order( $refund_id );
        if ( ! $refund ) {
            return;
        }
        BWK_Ledger::insert_entry( array(
            'source'    => 'wc',
            'source_id' => $refund_id,
            'txn_type'  => 'refund',
            'amount'    => $refund->get_total(),
            'currency'  => $refund->get_currency(),
            'txn_date'  => gmdate( 'Y-m-d H:i:s', $refund->get_date_created()->getTimestamp() ),
            'meta_json' => wp_json_encode( array( 'parent' => $order_id ) ),
        ) );
    }
}
