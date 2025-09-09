<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$tabs = array(
    'general'   => __( 'General', 'bwk-accounting-lite' ),
    'numbering' => __( 'Numbering', 'bwk-accounting-lite' ),
    'advanced'  => __( 'Advanced', 'bwk-accounting-lite' ),
);
$active = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
?>
<div class="wrap">
    <h1><?php _e( 'BWK Accounting Settings', 'bwk-accounting-lite' ); ?></h1>
    <h2 class="nav-tab-wrapper">
        <?php foreach ( $tabs as $tab => $label ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=bwk-settings&tab=' . $tab ) ); ?>" class="nav-tab <?php echo $active === $tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
        <?php endforeach; ?>
    </h2>
    <form method="post" action="options.php">
        <?php
        if ( 'general' === $active ) {
            settings_fields( 'bwk_settings_general' );
            ?>
            <table class="form-table">
                <tr><th><label for="bwk_accounting_company_name"><?php _e( 'Company Name', 'bwk-accounting-lite' ); ?></label></th>
                <td><input name="bwk_accounting_company_name" id="bwk_accounting_company_name" type="text" value="<?php echo esc_attr( bwk_get_option( 'company_name' ) ); ?>" class="regular-text" /></td></tr>
                <tr><th><label for="bwk_accounting_company_email"><?php _e( 'Company Email', 'bwk-accounting-lite' ); ?></label></th>
                <td><input name="bwk_accounting_company_email" id="bwk_accounting_company_email" type="email" value="<?php echo esc_attr( bwk_get_option( 'company_email' ) ); ?>" class="regular-text" /></td></tr>
                <tr><th><label for="bwk_accounting_default_currency"><?php _e( 'Default Currency', 'bwk-accounting-lite' ); ?></label></th>
                <td><input name="bwk_accounting_default_currency" id="bwk_accounting_default_currency" type="text" value="<?php echo esc_attr( bwk_get_option( 'default_currency', 'USD' ) ); ?>" class="regular-text" /></td></tr>
                <tr><th scope="row"><?php _e( 'Enable Zakat', 'bwk-accounting-lite' ); ?></th>
                <td><input type="checkbox" name="bwk_accounting_enable_zakat" value="1" <?php checked( bwk_get_option( 'enable_zakat', 0 ), 1 ); ?> /></td></tr>
                <tr><th><label for="bwk_accounting_zakat_rate"><?php _e( 'Zakat Rate (%)', 'bwk-accounting-lite' ); ?></label></th>
                <td><input name="bwk_accounting_zakat_rate" id="bwk_accounting_zakat_rate" type="number" step="0.01" value="<?php echo esc_attr( bwk_get_option( 'zakat_rate', 2.5 ) ); ?>" /></td></tr>
            </table>
            <?php
        } elseif ( 'numbering' === $active ) {
            settings_fields( 'bwk_settings_numbering' );
            ?>
            <table class="form-table">
                <tr><th><label for="bwk_accounting_number_prefix"><?php _e( 'Invoice Prefix', 'bwk-accounting-lite' ); ?></label></th>
                <td><input name="bwk_accounting_number_prefix" id="bwk_accounting_number_prefix" type="text" value="<?php echo esc_attr( bwk_get_option( 'number_prefix', 'INV-' ) ); ?>" class="regular-text" /></td></tr>
                <tr><th><label for="bwk_accounting_number_padding"><?php _e( 'Sequence Padding', 'bwk-accounting-lite' ); ?></label></th>
                <td><input name="bwk_accounting_number_padding" id="bwk_accounting_number_padding" type="number" value="<?php echo esc_attr( bwk_get_option( 'number_padding', 4 ) ); ?>" /></td></tr>
            </table>
            <?php
        } else {
            settings_fields( 'bwk_settings_advanced' );
            ?>
            <table class="form-table">
                <tr><th scope="row"><?php _e( 'Remove data on uninstall', 'bwk-accounting-lite' ); ?></th>
                <td><input type="checkbox" name="bwk_accounting_remove_data" value="1" <?php checked( get_option( 'bwk_accounting_remove_data' ), 1 ); ?> /></td></tr>
            </table>
            <?php
        }
        submit_button();
        ?>
    </form>
</div>
