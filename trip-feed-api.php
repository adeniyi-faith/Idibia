<?php
/** Idibia — Trip Status Feed API */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-dispatch-helpers.php';
idibia_clean_json_buffer();

if ( $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

$trip_id = (int) ( $_GET['trip_id'] ?? 0 );
if ( $trip_id <= 0 ) {
    http_response_code( 400 );
    wp_send_json_error( [ 'message' => 'Trip ID is required.' ] );
}

if ( ! is_user_logged_in() ) {
    http_response_code( 401 );
    wp_send_json_error( [ 'message' => 'Unauthenticated.' ] );
    exit;
}

$user_id = get_current_user_id();
$account_type = get_user_meta( $user_id, 'idibia_account_type', true );

global $wpdb;

// Verify access
$trip = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sd_trips` WHERE id = %d LIMIT 1", $trip_id ), ARRAY_A );

if ( ! $trip ) {
    http_response_code( 404 );
    wp_send_json_error( [ 'message' => 'Trip not found.' ] );
}

if ( $account_type === 'customer' ) {
    $customer_id = idibia_find_or_create_profile_row( $user_id, 'customer' );
    if ( (int) $trip['customer_id'] !== $customer_id ) {
        http_response_code( 403 );
        wp_send_json_error( [ 'message' => 'Forbidden.' ] );
    }
} elseif ( $account_type === 'driver' ) {
    $driver_id = idibia_find_or_create_profile_row( $user_id, 'driver' );
    if ( (int) $trip['driver_id'] !== $driver_id ) {
        http_response_code( 403 );
        wp_send_json_error( [ 'message' => 'Forbidden.' ] );
    }
} elseif ( ! current_user_can( 'manage_options' ) ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Forbidden.' ] );
}

// Fetch driver details if assigned
$driver_info = null;
if ( ! empty( $trip['driver_id'] ) ) {
    $driver = $wpdb->get_row( $wpdb->prepare( "SELECT first_name, vehicle_type, vehicle_plate, rating FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d LIMIT 1", $trip['driver_id'] ), ARRAY_A );
    $loc = $wpdb->get_row( $wpdb->prepare( "SELECT lat, lng, heading, updated_at FROM `{$wpdb->prefix}sd_driver_locations` WHERE driver_id = %d LIMIT 1", $trip['driver_id'] ), ARRAY_A );

    if ( $driver ) {
        $driver_info = [
            'first_name' => $driver['first_name'],
            'vehicle'    => $driver['vehicle_type'],
            'plate'      => $driver['vehicle_plate'],
            'rating'     => $driver['rating'],
            'location'   => $loc ? [
                'lat'        => (float) $loc['lat'],
                'lng'        => (float) $loc['lng'],
                'heading'    => (float) $loc['heading'],
                'updated_at' => $loc['updated_at']
            ] : null
        ];
    }
}

wp_send_json_success( [
    'trip_id'         => (int) $trip['id'],
    'trip_ref'        => $trip['trip_ref'],
    'status'          => $trip['status'],
    'dispatch_status' => $trip['dispatch_status'],
    'pickup'          => $trip['pickup_address'] ?: $trip['pickup'],
    'dropoff'         => $trip['dropoff_address'] ?: $trip['dropoff'],
    'fare'            => (float) ( $trip['fare'] ?: $trip['fare_estimate'] ),
    'created_at'      => $trip['created_at'],
    'accepted_at'     => $trip['accepted_at'],
    'driver'          => $driver_info
] );
