<?php
/** Idibia — Driver Online Toggle Handler */

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

global $wpdb;
$driver_id = (int) $GLOBALS['auth_driver_id'];
$driver = $wpdb->get_row( $wpdb->prepare( "SELECT kyc_status, status FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d LIMIT 1", $driver_id ) );
if ( ! $driver || $driver->kyc_status !== 'approved' || $driver->status !== 'active' ) {
    wp_send_json_error( [ 'message' => 'Your driver account must be approved before going online.' ] );
}
$is_online = ! empty( $_POST['online'] ) ? 1 : 0;

if ( $is_online === 1 ) {
    $active_zones = $wpdb->get_results(
        "SELECT center_lat, center_lng, radius_km FROM `{$wpdb->prefix}sd_operational_zones` WHERE is_active = 1",
        ARRAY_A
    );

    if ( ! empty( $active_zones ) ) {
        $location = $wpdb->get_row( $wpdb->prepare(
            "SELECT lat, lng FROM `{$wpdb->prefix}sd_driver_locations` WHERE driver_id = %d LIMIT 1",
            $driver_id
        ) );

        if ( ! $location ) {
            wp_send_json_error( [ 'message' => 'Your location is unknown. Please enable GPS and try again.' ] );
        }

        $in_zone = false;
        foreach ( $active_zones as $zone ) {
            $dist = idibia_dispatch_haversine_km(
                (float) $location->lat,
                (float) $location->lng,
                (float) $zone['center_lat'],
                (float) $zone['center_lng']
            );
            if ( $dist <= (float) $zone['radius_km'] ) {
                $in_zone = true;
                break;
            }
        }

        if ( ! $in_zone ) {
            wp_send_json_error( [ 'message' => 'You are outside the service area and cannot go online.' ] );
        }
    }
}

$updated = $wpdb->update( $wpdb->prefix . 'sd_drivers', [ 'is_online' => $is_online ], [ 'id' => $driver_id ], [ '%d' ], [ '%d' ] );
if ( false === $updated ) wp_send_json_error( [ 'message' => 'Could not update online status.' ] );

wp_send_json_success( [ 'is_online' => $is_online ] );
