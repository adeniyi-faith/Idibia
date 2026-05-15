<?php
/** Idibia — Driver Offers Polling API */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-dispatch-helpers.php';
idibia_clean_json_buffer();

if ( $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

$auth_type = 'driver';
require_once __DIR__ . '/auth-helper.php';

global $wpdb;
$driver_id = (int) $GLOBALS['auth_driver_id'];

// 1. Clean up stale offers first
idibia_expire_stale_offers();

// 2. Look for an active pending offer for this driver
$offer = $wpdb->get_row( $wpdb->prepare( "
    SELECT o.id as offer_id, o.trip_id, o.expires_at,
           t.pickup_address, t.dropoff_address, t.pickup_lat, t.pickup_lng,
           t.dropoff_lat, t.dropoff_lng, t.distance_km, t.duration_mins, t.fare, t.fare_estimate, t.package_metadata, t.service_category
    FROM `{$wpdb->prefix}sd_dispatch_offers` o
    JOIN `{$wpdb->prefix}sd_trips` t ON o.trip_id = t.id
    WHERE o.driver_id = %d AND o.status = 'pending' AND o.expires_at > NOW()
    ORDER BY o.created_at ASC
    LIMIT 1
", $driver_id ), ARRAY_A );

if ( ! $offer ) {
    wp_send_json_success( [ 'offer' => null ] );
}

// 3. Format response
$offer['fare'] = $offer['fare'] ?: $offer['fare_estimate'];
$offer['seconds_remaining'] = max( 0, strtotime( $offer['expires_at'] ) - time() );

wp_send_json_success( [ 'offer' => $offer ] );
