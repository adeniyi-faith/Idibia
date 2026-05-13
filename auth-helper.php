<?php
/**
 * Idibia authentication helper.
 *
 * Caller must load WordPress first and set $auth_type to 'customer' or 'driver'.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

$auth_type = $auth_type ?? '';
if ( ! in_array( $auth_type, [ 'customer', 'driver' ], true ) ) {
    http_response_code( 401 );
    wp_send_json_error( [ 'message' => 'Unauthenticated.' ] );
    exit;
}

$header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if ( ! $header && function_exists( 'getallheaders' ) ) {
    $headers = getallheaders();
    foreach ( $headers as $key => $value ) {
        if ( strtolower( $key ) === 'authorization' ) {
            $header = $value;
            break;
        }
    }
}

$token = '';
if ( preg_match( '/Bearer\s+(.+)/i', $header, $matches ) ) {
    $token = sanitize_text_field( trim( $matches[1] ) );
} elseif ( ! empty( $_POST['_token'] ) ) {
    $token = sanitize_text_field( wp_unslash( $_POST['_token'] ) );
}

if ( ! $token ) {
    http_response_code( 401 );
    wp_send_json_error( [ 'message' => 'Unauthenticated.' ] );
    exit;
}

if ( $auth_type === 'driver' ) {
    $session_table = $wpdb->prefix . 'sd_driver_sessions';
    $id_column     = 'driver_id';
} else {
    $session_table = $wpdb->prefix . 'sd_sessions';
    $id_column     = 'customer_id';
}

$session = $wpdb->get_row( $wpdb->prepare(
    "SELECT `$id_column` AS entity_id FROM `$session_table` WHERE token = %s AND expires_at > %s LIMIT 1",
    $token,
    gmdate( 'Y-m-d H:i:s' )
) );

if ( ! $session ) {
    http_response_code( 401 );
    wp_send_json_error( [ 'message' => 'Unauthenticated.' ] );
    exit;
}

if ( $auth_type === 'driver' ) {
    $GLOBALS['auth_driver_id'] = (int) $session->entity_id;
} else {
    $GLOBALS['auth_customer_id'] = (int) $session->entity_id;
}
