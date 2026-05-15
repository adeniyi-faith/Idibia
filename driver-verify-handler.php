<?php
/** Idibia — Driver Email Verification Handler */

require_once __DIR__ . '/wp-auth-config.php';
if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

if ( ! session_id() ) session_start();
$driver_id = isset( $_SESSION['sd_pending_driver_id'] ) ? (int) $_SESSION['sd_pending_driver_id'] : 0;
if ( ! $driver_id ) wp_send_json_error( [ 'message' => 'Session expired. Please register again.' ] );

$submitted_code = sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) );
if ( ! preg_match( '/^\d{5}$/', $submitted_code ) ) {
    wp_send_json_error( [ 'message' => 'Enter the 5-digit code from your email.' ] );
}

global $wpdb;
$table = $wpdb->prefix . 'sd_drivers';
$driver = $wpdb->get_row( $wpdb->prepare(
    "SELECT id, full_name, email, verify_code, verify_expires, email_verified, status FROM `$table` WHERE id = %d LIMIT 1",
    $driver_id
) );

if ( ! $driver ) wp_send_json_error( [ 'message' => 'Account not found. Please register again.' ] );

if ( (int) $driver->email_verified ) {
    unset( $_SESSION['sd_pending_driver_id'], $_SESSION['sd_pending_driver_email'] );
    wp_send_json_success( [
        'message'    => 'Already verified.',
        'first_name' => idibia_split_full_name( $driver->full_name )[0],
        'token'      => idibia_create_driver_session( (int) $driver->id ),
    ] );
}

if ( ! wp_check_password( $submitted_code, $driver->verify_code ) ) wp_send_json_error( [ 'message' => 'Incorrect code. Double-check and try again.' ] );
if ( ! strtotime( $driver->verify_expires ) || time() > strtotime( $driver->verify_expires ) ) {
    wp_send_json_error( [ 'message' => 'That code has expired. Use the Resend Email button to get a fresh one.' ] );
}

$updated = $wpdb->update(
    $table,
    [ 'email_verified' => 1, 'verify_code' => null, 'verify_expires' => null ],
    [ 'id' => $driver_id ],
    [ '%d', '%s', '%s' ],
    [ '%d' ]
);
if ( false === $updated ) wp_send_json_error( [ 'message' => 'Something went wrong. Please try again.' ] );

$user = get_user_by( 'email', $driver->email );
if ( $user ) {
    idibia_finish_wordpress_login( $user );
}

$token = idibia_create_driver_session( $driver_id );
unset( $_SESSION['sd_pending_driver_id'], $_SESSION['sd_pending_driver_email'] );

wp_send_json_success( [
    'message'    => 'Email verified! Continue your driver application.',
    'first_name' => idibia_split_full_name( $driver->full_name )[0],
    'token'      => $token,
] );

function idibia_create_driver_session( int $driver_id ): string {
    global $wpdb;
    $token      = bin2hex( random_bytes( 32 ) );
    $expires_at = gmdate( 'Y-m-d H:i:s', time() + ( 30 * DAY_IN_SECONDS ) );
    $ip         = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
    $user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
    if ( strlen( $user_agent ) > 300 ) $user_agent = substr( $user_agent, 0, 300 );
    $wpdb->insert( $wpdb->prefix . 'sd_driver_sessions', [ 'driver_id' => $driver_id, 'token' => $token, 'expires_at' => $expires_at, 'ip' => $ip, 'user_agent' => $user_agent ], [ '%d', '%s', '%s', '%s', '%s' ] );
    return $token;
}

