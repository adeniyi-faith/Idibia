<?php
/** Idibia — Pusher Private Channel Auth API */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';
idibia_clean_json_buffer();

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

if ( ! is_user_logged_in() ) {
    http_response_code( 401 );
    wp_send_json_error( [ 'message' => 'Unauthenticated.' ] );
}

if ( ! idibia_pusher_is_configured() ) {
    http_response_code( 503 );
    wp_send_json_error( [ 'message' => 'Realtime is not configured yet.' ] );
}

$socket_id    = sanitize_text_field( wp_unslash( $_POST['socket_id'] ?? '' ) );
$channel_name = sanitize_text_field( wp_unslash( $_POST['channel_name'] ?? '' ) );

if ( ! preg_match( '/^\d+\.\d+$/', $socket_id ) || ! preg_match( '/^private-(trip|driver|customer)-\d+$/', $channel_name ) ) {
    http_response_code( 400 );
    wp_send_json_error( [ 'message' => 'Invalid realtime channel.' ] );
}

$user_id      = get_current_user_id();
$account_type = get_user_meta( $user_id, 'idibia_account_type', true );
$allowed      = false;

global $wpdb;

if ( preg_match( '/^private-customer-(\d+)$/', $channel_name, $matches ) ) {
    $customer_id = (int) $matches[1];
    $allowed = $account_type === 'customer' && idibia_find_or_create_profile_row( $user_id, 'customer' ) === $customer_id;
} elseif ( preg_match( '/^private-driver-(\d+)$/', $channel_name, $matches ) ) {
    $driver_id = (int) $matches[1];
    $allowed = $account_type === 'driver' && idibia_find_or_create_profile_row( $user_id, 'driver' ) === $driver_id;
} elseif ( preg_match( '/^private-trip-(\d+)$/', $channel_name, $matches ) ) {
    $trip_id = (int) $matches[1];
    $trip = $wpdb->get_row( $wpdb->prepare( "SELECT customer_id, driver_id FROM `{$wpdb->prefix}sd_trips` WHERE id = %d LIMIT 1", $trip_id ), ARRAY_A );

    if ( $trip ) {
        if ( $account_type === 'customer' ) {
            $allowed = (int) $trip['customer_id'] === idibia_find_or_create_profile_row( $user_id, 'customer' );
        } elseif ( $account_type === 'driver' ) {
            $allowed = ! empty( $trip['driver_id'] ) && (int) $trip['driver_id'] === idibia_find_or_create_profile_row( $user_id, 'driver' );
        } elseif ( current_user_can( 'manage_options' ) ) {
            $allowed = true;
        }
    }
}

if ( ! $allowed ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Forbidden realtime channel.' ] );
}

$signature = hash_hmac( 'sha256', $socket_id . ':' . $channel_name, IDIBIA_PUSHER_SECRET );
wp_send_json( [ 'auth' => IDIBIA_PUSHER_KEY . ':' . $signature ] );
