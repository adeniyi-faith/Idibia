<?php
/** Idibia — Driver Online Toggle Handler */

define( 'WP_USE_THEMES', false );
require_once __DIR__ . '/wp/wp-load.php';
if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

$auth_type = 'driver';
require_once __DIR__ . '/auth-helper.php';

global $wpdb;
$driver_id = (int) $GLOBALS['auth_driver_id'];
$is_online = ! empty( $_POST['online'] ) ? 1 : 0;
$updated = $wpdb->update( $wpdb->prefix . 'sd_drivers', [ 'is_online' => $is_online ], [ 'id' => $driver_id ], [ '%d' ], [ '%d' ] );
if ( false === $updated ) wp_send_json_error( [ 'message' => 'Could not update online status.' ] );

wp_send_json_success( [ 'is_online' => $is_online ] );
