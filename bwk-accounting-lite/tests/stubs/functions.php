<?php
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/../../' );
}

if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}

global $_bwk_test_options;
if ( ! isset( $_bwk_test_options ) ) {
    $_bwk_test_options = array();
}

global $_bwk_timezone_string;
if ( ! isset( $_bwk_timezone_string ) ) {
    $_bwk_timezone_string = 'UTC';
}

function get_option( $name, $default = false ) {
    global $_bwk_test_options;
    return array_key_exists( $name, $_bwk_test_options ) ? $_bwk_test_options[ $name ] : $default;
}

function update_option( $name, $value ) {
    global $_bwk_test_options;
    $_bwk_test_options[ $name ] = $value;
    return true;
}

function wp_parse_args( $args, $defaults = array() ) {
    if ( is_object( $args ) ) {
        $args = get_object_vars( $args );
    } elseif ( ! is_array( $args ) ) {
        parse_str( (string) $args, $args );
    }

    if ( ! is_array( $args ) ) {
        $args = array();
    }

    return array_merge( $defaults, $args );
}

function sanitize_key( $key ) {
    $key = strtolower( (string) $key );
    return preg_replace( '/[^a-z0-9_]/', '', $key );
}

function absint( $maybeint ) {
    return abs( intval( $maybeint ) );
}

function wp_timezone() {
    global $_bwk_timezone_string;
    return new DateTimeZone( $_bwk_timezone_string ? $_bwk_timezone_string : 'UTC' );
}

function current_time( $type ) {
    $timestamp = time();

    if ( 'timestamp' === $type ) {
        return $timestamp;
    }

    if ( 'mysql' === $type ) {
        return gmdate( 'Y-m-d H:i:s', $timestamp );
    }

    return $timestamp;
}

function wp_date( $format, $timestamp, $timezone = null ) {
    if ( ! $timezone instanceof DateTimeZone ) {
        $timezone = wp_timezone();
    }

    $datetime = new DateTimeImmutable( '@' . (int) $timestamp );
    $datetime = $datetime->setTimezone( $timezone );

    return $datetime->format( $format );
}

function get_gmt_from_date( $date_string, $format = 'Y-m-d H:i:s' ) {
    try {
        $timezone = wp_timezone();
        $datetime = new DateTimeImmutable( $date_string, $timezone );
        return $datetime->setTimezone( new DateTimeZone( 'UTC' ) )->format( $format );
    } catch ( Exception $exception ) {
        return $date_string;
    }
}
