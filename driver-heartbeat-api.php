<?php
/** Idibia — Driver Heartbeat API */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-dispatch-helpers.php';
idibia_clean_json_buffer();

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

$auth_type = 'driver';
require_once __DIR__ . '/auth-helper.php';

$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ) : '';
if ( ! wp_verify_nonce( $nonce, 'idibia_driver_action' ) ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Security check failed. Please refresh and try again.' ] );
}

global $wpdb;
$driver_id = (int) $GLOBALS['auth_driver_id'];
$driver = $wpdb->get_row( $wpdb->prepare( "SELECT kyc_status, status FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d LIMIT 1", $driver_id ) );
if ( ! $driver || $driver->kyc_status !== 'approved' || $driver->status !== 'active' ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Driver account is not approved.' ] );
}

$lat = isset( $_POST['lat'] ) ? (float) $_POST['lat'] : null;
$lng = isset( $_POST['lng'] ) ? (float) $_POST['lng'] : null;
$heading = isset( $_POST['heading'] ) && $_POST['heading'] !== '' ? (float) $_POST['heading'] : null;

if ( $lat !== null && $lng !== null ) {
    if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
        wp_send_json_error( [ 'message' => 'Invalid location coordinates.' ] );
    }

    $wpdb->query( $wpdb->prepare(
        "INSERT INTO `{$wpdb->prefix}sd_driver_locations` (driver_id, lat, lng, heading, updated_at)
         VALUES (%d, %f, %f, %f, %s)
         ON DUPLICATE KEY UPDATE lat = VALUES(lat), lng = VALUES(lng), heading = VALUES(heading), updated_at = VALUES(updated_at)",
        $driver_id,
        $lat,
        $lng,
        $heading,
        idibia_dispatch_now()
    ) );
}

if ( isset( $_POST['online'] ) ) {
    $wpdb->update( $wpdb->prefix . 'sd_drivers', [ 'is_online' => ! empty( $_POST['online'] ) ? 1 : 0 ], [ 'id' => $driver_id ], [ '%d' ], [ '%d' ] );
}

wp_send_json_success( [
    'offers'      => idibia_get_driver_offers( $driver_id ),
    'active_trip' => idibia_get_driver_active_trip( $driver_id ),
] );
