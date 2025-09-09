<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/class-uninstaller.php';
BWK_Uninstaller::uninstall();
