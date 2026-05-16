<?php
/** Idibia — Driver Offers API */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-dispatch-helpers.php';
idibia_clean_json_buffer();

if ( $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

$auth_type = 'driver';
require_once __DIR__ . '/auth-helper.php';

$driver_id = (int) $GLOBALS['auth_driver_id'];
wp_send_json_success( [
    'offers'      => idibia_get_driver_offers( $driver_id ),
    'active_trip' => idibia_get_driver_active_trip( $driver_id ),
] );
