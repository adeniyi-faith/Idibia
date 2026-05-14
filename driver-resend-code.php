<?php
/** Idibia — Driver Resend Verification Code Handler */

require_once __DIR__ . '/wp-auth-config.php';
if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

if ( ! session_id() ) session_start();
$driver_id = isset( $_SESSION['sd_pending_driver_id'] ) ? (int) $_SESSION['sd_pending_driver_id'] : 0;
if ( ! $driver_id ) wp_send_json_error( [ 'message' => 'Session expired. Please register again.' ] );

global $wpdb;
$table = $wpdb->prefix . 'sd_drivers';
$driver = $wpdb->get_row( $wpdb->prepare( "SELECT id, full_name, email, email_verified, verify_expires FROM `$table` WHERE id = %d LIMIT 1", $driver_id ) );
if ( ! $driver ) wp_send_json_error( [ 'message' => 'Account not found. Please register again.' ] );
if ( (int) $driver->email_verified ) wp_send_json_error( [ 'message' => 'This account is already verified. Please log in.' ] );

if ( $driver->verify_expires ) {
    $issued_at = strtotime( $driver->verify_expires ) - ( 30 * MINUTE_IN_SECONDS );
    if ( time() < $issued_at + 60 ) {
        wp_send_json_error( [ 'message' => 'Please wait ' . ( ( $issued_at + 60 ) - time() ) . ' second(s) before requesting another code.' ] );
    }
}

$new_code    = (string) wp_rand( 10000, 99999 );
$new_expires = gmdate( 'Y-m-d H:i:s', time() + ( 30 * MINUTE_IN_SECONDS ) );
$updated = $wpdb->update( $table, [ 'verify_code' => $new_code, 'verify_expires' => $new_expires ], [ 'id' => $driver_id ], [ '%s', '%s' ], [ '%d' ] );
if ( false === $updated ) wp_send_json_error( [ 'message' => 'Could not generate a new code. Please try again.' ] );

$first_name = idibia_split_full_name( $driver->full_name )[0];
$site_name  = get_bloginfo( 'name' ) ?: 'Idibia';
$site_url   = home_url();
$subject    = "[$site_name] Your new driver verification code is $new_code";
$body       = "Hi $first_name,\n\nEnter this new verification code in the driver app:\n\n    $new_code\n\nThis code expires in 30 minutes.\n\n— The Idibia Team\n$site_url";
$headers    = [ 'Content-Type: text/plain; charset=UTF-8', 'From: ' . $site_name . ' <no-reply@' . parse_url( $site_url, PHP_URL_HOST ) . '>' ];

if ( ! wp_mail( $driver->email, $subject, $body, $headers ) ) {
    wp_send_json_error( [ 'message' => 'Could not send the email. Check your address and try again.' ] );
}

wp_send_json_success( [ 'message' => 'New code sent! Check your inbox.' ] );
