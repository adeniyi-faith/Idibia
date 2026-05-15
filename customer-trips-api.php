<?php
/** Idibia — Customer Trips API */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';
idibia_clean_json_buffer();

if ( $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

$auth_type = 'customer';
require_once __DIR__ . '/auth-helper.php';

global $wpdb;
$customer_id = (int) $GLOBALS['auth_customer_id'];

$trips = $wpdb->get_results( $wpdb->prepare(
    "SELECT id, trip_ref, pickup_address, dropoff_address, category, service_category, vehicle_type,
            status, dispatch_status, fare, fare_estimate, created_at, accepted_at, completed_at
     FROM `{$wpdb->prefix}sd_trips`
     WHERE customer_id = %d
     ORDER BY created_at DESC
     LIMIT 50",
    $customer_id
), ARRAY_A );

// Normalize outputs
foreach ( $trips as &$trip ) {
    $trip['fare'] = $trip['fare'] ?: $trip['fare_estimate'];
    $trip['pickup'] = $trip['pickup_address'] ?: $trip['pickup'];
    $trip['dropoff'] = $trip['dropoff_address'] ?: $trip['dropoff'];
}
unset( $trip );

wp_send_json_success( [
    'trips' => $trips ?: []
] );
