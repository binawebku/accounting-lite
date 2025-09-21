<?php
require __DIR__ . '/stubs/functions.php';
require __DIR__ . '/stubs/class-test-wpdb.php';

global $wpdb;
$wpdb = new BWK_Test_WPDB();

require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/class-ledger.php';
