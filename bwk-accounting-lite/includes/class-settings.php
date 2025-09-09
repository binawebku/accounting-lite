<?php
/**
 * Settings management.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BWK_Settings {
    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'register' ) );
    }

    public static function register() {
        register_setting( 'bwk_settings_general', 'bwk_accounting_company_name' );
        register_setting( 'bwk_settings_general', 'bwk_accounting_company_email' );
        register_setting( 'bwk_settings_general', 'bwk_accounting_default_currency' );
        register_setting( 'bwk_settings_general', 'bwk_accounting_enable_zakat' );
        register_setting( 'bwk_settings_general', 'bwk_accounting_zakat_rate' );
        register_setting( 'bwk_settings_numbering', 'bwk_accounting_number_prefix' );
        register_setting( 'bwk_settings_numbering', 'bwk_accounting_number_padding' );
        register_setting( 'bwk_settings_advanced', 'bwk_accounting_remove_data' );
    }

    public static function render_settings_page() {
        if ( ! bwk_current_user_can() ) {
            return;
        }
        include BWK_AL_PATH . 'admin/views-settings.php';
    }
}
