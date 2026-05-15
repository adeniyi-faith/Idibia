<?php
/** Idibia — Driver Heartbeat API */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';
idibia_clean_json_buffer();

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
if ( ! idibia_check_rate_limit( 'heartbeat', $ip, 60, 60 ) ) {
    http_response_code( 429 );
    wp_send_json_error( [ 'message' => 'Too many requests.' ] );
}

$auth_type = 'driver';
require_once __DIR__ . '/auth-helper.php';

global $wpdb;
$driver_id = (int) $GLOBALS['auth_driver_id'];

$lat = (float) ( $_POST['lat'] ?? 0 );
$lng = (float) ( $_POST['lng'] ?? 0 );

if ( ! $lat || ! $lng ) {
    wp_send_json_error( [ 'message' => 'Coordinates are required.' ] );
}

// 1. Update sd_driver_locations
$wpdb->query( $wpdb->prepare(
    "INSERT INTO `{$wpdb->prefix}sd_driver_locations` (`driver_id`, `lat`, `lng`, `updated_at`)
     VALUES (%d, %f, %f, CURRENT_TIMESTAMP)
     ON DUPLICATE KEY UPDATE `lat` = VALUES(`lat`), `lng` = VALUES(`lng`), `updated_at` = CURRENT_TIMESTAMP",
    $driver_id, $lat, $lng
) );

// 2. Ensure driver is marked online
$wpdb->update(
    $wpdb->prefix . 'sd_drivers',
    [ 'is_online' => 1 ],
    [ 'id' => $driver_id ],
    [ '%d' ],
    [ '%d' ]
);

wp_send_json_success( [ 'message' => 'Heartbeat received.' ] );
