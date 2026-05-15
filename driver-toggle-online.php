<?php
/** Idibia — Driver Online Toggle Handler */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
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
$updated = $wpdb->update( $wpdb->prefix . 'sd_drivers', [ 'is_online' => $is_online ], [ 'id' => $driver_id ], [ '%d' ], [ '%d' ] );
if ( false === $updated ) wp_send_json_error( [ 'message' => 'Could not update online status.' ] );

wp_send_json_success( [ 'is_online' => $is_online ] );
