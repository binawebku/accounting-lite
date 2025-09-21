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
        $cost_data = self::calculate_order_cost( $order );
        $meta      = array(
            'status'     => $order->get_status(),
            'cost_total' => isset( $cost_data['total'] ) ? (float) $cost_data['total'] : 0.0,
            'items'      => isset( $cost_data['items'] ) ? $cost_data['items'] : array(),
            'net_total'  => round( (float) $order->get_total() - ( isset( $cost_data['total'] ) ? (float) $cost_data['total'] : 0.0 ), 2 ),
        );

        /**
         * Filter the metadata stored against a WooCommerce sale ledger entry.
         *
         * @since 1.0.0
         *
         * @param array     $meta  Metadata array.
         * @param WC_Order  $order WooCommerce order object.
         * @param array     $cost_data Cost breakdown.
         */
        $meta = apply_filters( 'bwk_wc_sale_ledger_meta', $meta, $order, $cost_data );

        BWK_Ledger::insert_entry( array(
            'source'    => 'wc',
            'source_id' => $order_id,
            'txn_type'  => 'sale',
            'amount'    => $order->get_total(),
            'currency'  => $order->get_currency(),
            'txn_date'  => self::format_datetime( $order->get_date_created() ),
            'meta_json' => wp_json_encode( $meta ),
        ) );
    }

    public static function sync_refund( $refund_id, $order_id ) {
        $refund = wc_get_order( $refund_id );
        if ( ! $refund ) {
            return;
        }
        $order     = wc_get_order( $order_id );
        $cost_data = self::calculate_refund_cost( $refund, $order );
        $meta      = array(
            'parent'        => $order_id,
            'is_refund'     => true,
            'cost_total'    => isset( $cost_data['total'] ) ? (float) $cost_data['total'] : 0.0,
            'items'         => isset( $cost_data['items'] ) ? $cost_data['items'] : array(),
            'status'        => method_exists( $refund, 'get_status' ) ? $refund->get_status() : '',
        );

        if ( $order ) {
            $meta['parent_status'] = $order->get_status();
        }

        /**
         * Filter the metadata stored against a WooCommerce refund ledger entry.
         *
         * @since 1.0.0
         *
         * @param array           $meta      Metadata array.
         * @param WC_Order_Refund $refund    Refund object.
         * @param WC_Order|null   $order     Parent order object.
         * @param array           $cost_data Cost breakdown for the refund.
         */
        $meta = apply_filters( 'bwk_wc_refund_ledger_meta', $meta, $refund, $order, $cost_data );

        BWK_Ledger::insert_entry( array(
            'source'    => 'wc',
            'source_id' => $refund_id,
            'txn_type'  => 'refund',
            'amount'    => $refund->get_total(),
            'currency'  => $refund->get_currency(),
            'txn_date'  => self::format_datetime( $refund->get_date_created() ),
            'meta_json' => wp_json_encode( $meta ),
        ) );
    }

    /**
     * Prepare cost breakdown for an order.
     *
     * @param WC_Order $order WooCommerce order object.
     *
     * @return array
     */
    protected static function calculate_order_cost( $order ) {
        $total_cost = 0.0;
        $items      = array();

        if ( ! $order ) {
            return array(
                'total' => 0.0,
                'items' => array(),
            );
        }

        foreach ( $order->get_items() as $item_id => $item ) {
            if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
                continue;
            }

            $qty       = (float) $item->get_quantity();
            $qty       = $qty > 0 ? $qty : 0.0;
            $unit_cost = self::determine_unit_cost( $item );
            $line_cost = round( $unit_cost * $qty, 2 );

            $items[] = array(
                'item_id'      => (int) $item_id,
                'product_id'   => (int) $item->get_product_id(),
                'variation_id' => (int) $item->get_variation_id(),
                'qty'          => $qty,
                'unit_cost'    => round( $unit_cost, 4 ),
                'line_cost'    => $line_cost,
            );

            $total_cost += $line_cost;

            if ( function_exists( 'wc_update_order_item_meta' ) && $item_id ) {
                wc_update_order_item_meta( $item_id, '_bwk_cost_basis', $unit_cost );
            }
        }

        return array(
            'total' => round( $total_cost, 2 ),
            'items' => $items,
        );
    }

    /**
     * Prepare cost data for a refund.
     *
     * @param WC_Order_Refund $refund Refund instance.
     * @param WC_Order|null   $order  Parent order.
     *
     * @return array
     */
    protected static function calculate_refund_cost( $refund, $order ) {
        $total_cost = 0.0;
        $items      = array();

        if ( ! $refund ) {
            return array(
                'total' => 0.0,
                'items' => array(),
            );
        }

        $cost_basis = array();

        if ( $order ) {
            foreach ( $order->get_items() as $item_id => $order_item ) {
                if ( ! is_a( $order_item, 'WC_Order_Item_Product' ) ) {
                    continue;
                }

                $basis = $order_item->get_meta( '_bwk_cost_basis', true );

                if ( '' === $basis || null === $basis ) {
                    $basis = self::determine_unit_cost( $order_item );
                }

                $cost_basis[ $item_id ] = (float) $basis;
            }
        }

        foreach ( $refund->get_items() as $item_id => $item ) {
            if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
                continue;
            }

            $qty = abs( (float) $item->get_quantity() );
            $qty = $qty > 0 ? $qty : 0.0;

            $refunded_item_id = $item->get_meta( '_refunded_item_id', true );
            if ( ! $refunded_item_id ) {
                $refunded_item_id = $item->get_meta( 'refunded_item_id', true );
            }

            $unit_cost = null;

            if ( $refunded_item_id && isset( $cost_basis[ $refunded_item_id ] ) ) {
                $unit_cost = $cost_basis[ $refunded_item_id ];
            }

            if ( null === $unit_cost ) {
                $unit_cost = self::determine_unit_cost( $item );
            }

            $unit_cost = (float) $unit_cost;
            $line_cost = round( $unit_cost * $qty, 2 );

            $items[] = array(
                'refunded_item_id' => $refunded_item_id ? (int) $refunded_item_id : null,
                'product_id'       => (int) $item->get_product_id(),
                'variation_id'     => (int) $item->get_variation_id(),
                'qty'              => $qty,
                'unit_cost'        => round( $unit_cost, 4 ),
                'line_cost'        => $line_cost,
            );

            $total_cost += $line_cost;
        }

        return array(
            'total' => round( $total_cost, 2 ),
            'items' => $items,
        );
    }

    /**
     * Determine the unit cost for a WooCommerce order item.
     *
     * @param WC_Order_Item_Product $item Order item.
     *
     * @return float
     */
    protected static function determine_unit_cost( $item ) {
        $unit_cost = null;
        $meta_keys = self::get_cost_meta_keys();
        $product   = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;

        if ( $product ) {
            foreach ( $meta_keys as $key ) {
                $value = $product->get_meta( $key, true );

                if ( '' !== $value && null !== $value ) {
                    $unit_cost = (float) $value;
                    break;
                }
            }
        }

        if ( null === $unit_cost ) {
            foreach ( $meta_keys as $key ) {
                $value = $item->get_meta( $key, true );

                if ( '' !== $value && null !== $value ) {
                    $unit_cost = (float) $value;
                    break;
                }
            }
        }

        if ( null === $unit_cost ) {
            $value = $item->get_meta( '_bwk_cost_basis', true );

            if ( '' !== $value && null !== $value ) {
                $unit_cost = (float) $value;
            }
        }

        /**
         * Filter the detected unit cost for an order item.
         *
         * @since 1.0.0
         *
         * @param float|null             $unit_cost Unit cost value or null if unknown.
         * @param WC_Order_Item_Product  $item      Order item.
         * @param WC_Product|false|null  $product   Related product instance.
         */
        $unit_cost = apply_filters( 'bwk_wc_order_item_unit_cost', $unit_cost, $item, $product );

        if ( null === $unit_cost || ! is_numeric( $unit_cost ) ) {
            $unit_cost = 0.0;
        }

        return (float) $unit_cost;
    }

    /**
     * Retrieve the list of product meta keys checked for unit costs.
     *
     * @return array
     */
    protected static function get_cost_meta_keys() {
        $keys = array(
            '_bwk_cost',
            '_cost',
            '_wc_cost',
            '_purchase_price',
            '_wc_cog_cost',
            '_wc_cog_cost_price',
            '_wc_cogs_cost',
            'cost_of_goods',
        );

        return apply_filters( 'bwk_wc_cost_meta_keys', $keys );
    }

    /**
     * Format a WooCommerce datetime into the ledger timestamp.
     *
     * @param DateTimeInterface|null $datetime Datetime instance.
     *
     * @return string
     */
    protected static function format_datetime( $datetime ) {
        if ( $datetime instanceof DateTimeInterface ) {
            return gmdate( 'Y-m-d H:i:s', $datetime->getTimestamp() );
        }

        return gmdate( 'Y-m-d H:i:s' );
    }
}
