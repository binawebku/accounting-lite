<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$wp_admin_bar->add_node( array(
    'id'    => 'bwk-accounting',
    'title' => 'BWK Accounting',
    'href'  => admin_url( 'admin.php?page=bwk-dashboard' ),
) );
$wp_admin_bar->add_node( array(
    'id'     => 'bwk-accounting-invoices',
    'parent' => 'bwk-accounting',
    'title'  => __( 'Invoices', 'bwk-accounting-lite' ),
    'href'   => admin_url( 'admin.php?page=bwk-accounting' ),
) );
$wp_admin_bar->add_node( array(
    'id'     => 'bwk-accounting-ledger',
    'parent' => 'bwk-accounting',
    'title'  => __( 'Ledger', 'bwk-accounting-lite' ),
    'href'   => admin_url( 'admin.php?page=bwk-ledger' ),
) );
$wp_admin_bar->add_node( array(
    'id'     => 'bwk-accounting-settings',
    'parent' => 'bwk-accounting',
    'title'  => __( 'Settings', 'bwk-accounting-lite' ),
    'href'   => admin_url( 'admin.php?page=bwk-settings' ),
) );
