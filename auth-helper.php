<?php
/**
 * Idibia authentication helper.
 *
 * Caller must load WordPress first and set $auth_type to 'customer' or 'driver'.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$auth_type = $auth_type ?? '';
if ( ! in_array( $auth_type, [ 'customer', 'driver' ], true ) ) {
    http_response_code( 401 );
    wp_send_json_error( [ 'message' => 'Unauthenticated.' ] );
    exit;
}

if ( ! is_user_logged_in() ) {
    http_response_code( 401 );
    wp_send_json_error( [ 'message' => 'Unauthenticated.' ] );
    exit;
}

$user_id      = get_current_user_id();
$account_type = get_user_meta( $user_id, 'idibia_account_type', true );

if ( $account_type !== $auth_type ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Forbidden.' ] );
    exit;
}

if ( get_user_meta( $user_id, 'idibia_account_status', true ) === 'suspended' ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Your account is suspended.' ] );
    exit;
}

if ( ! function_exists( 'idibia_find_or_create_profile_row' ) ) {
    require_once __DIR__ . '/wp-auth-config.php';
}

if ( $auth_type === 'driver' ) {
    $GLOBALS['auth_driver_id'] = idibia_find_or_create_profile_row( $user_id, 'driver' );
} else {
    $GLOBALS['auth_customer_id'] = idibia_find_or_create_profile_row( $user_id, 'customer' );
}
