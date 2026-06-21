<?php
/** Idibia — Tracking Token Generation API */

require_once __DIR__ . '/wp-auth-config.php';
idibia_clean_json_buffer();

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

if ( ! is_user_logged_in() ) {
    http_response_code( 401 );
    wp_send_json_error( [ 'message' => 'Unauthenticated.' ] );
}

$user_id = get_current_user_id();
$account_type = get_user_meta( $user_id, 'idibia_account_type', true );

if ( $account_type !== 'customer' ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Forbidden.' ] );
}

$customer_id = idibia_find_or_create_profile_row( $user_id, 'customer' );
$trip_id = (int) ( $_POST['trip_id'] ?? 0 );

if ( $trip_id <= 0 ) {
    http_response_code( 400 );
    wp_send_json_error( [ 'message' => 'Invalid trip ID.' ] );
}

global $wpdb;

// Verify trip belongs to customer
$trip = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM `{$wpdb->prefix}sd_trips` WHERE id = %d AND customer_id = %d LIMIT 1", $trip_id, $customer_id ) );

if ( ! $trip ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Forbidden.' ] );
}

// Generate secure token
$token = wp_generate_password( 32, false, false );
// Expire token 24 hours from now
$expires_at = gmdate( 'Y-m-d H:i:s', strtotime( '+24 hours' ) );

$inserted = $wpdb->insert(
    $wpdb->prefix . 'sd_tracking_tokens',
    [
        'token'      => $token,
        'trip_id'    => $trip_id,
        'expires_at' => $expires_at,
    ],
    [ '%s', '%d', '%s' ]
);

if ( ! $inserted ) {
    http_response_code( 500 );
    wp_send_json_error( [ 'message' => 'Failed to generate token.' ] );
}

$share_url = '/public-tracking.php?token=' . $token;
wp_send_json_success( [ 'token' => $token, 'share_url' => $share_url ] );
