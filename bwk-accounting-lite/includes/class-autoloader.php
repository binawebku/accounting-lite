<?php
/**
 * Simple autoloader for BWK classes.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BWK_Autoloader {
    public static function register() {
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }

    public static function autoload( $class ) {
        if ( 0 !== strpos( $class, 'BWK_' ) ) {
            return;
        }
        $filename = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
        $path     = BWK_AL_PATH . 'includes/' . $filename;
        if ( ! file_exists( $path ) ) {
            $alt = BWK_AL_PATH . 'includes/' . str_replace( 'bwk-', '', $filename );
            if ( file_exists( $alt ) ) {
                $path = $alt;
            } else {
                $alt2 = BWK_AL_PATH . 'includes/' . str_replace( array( 'bwk-', '.php' ), array( '', '-tables.php' ), $filename );
                if ( file_exists( $alt2 ) ) {
                    $path = $alt2;
                }
            }
        }
        if ( file_exists( $path ) ) {
            require $path;
        }
    }
}
