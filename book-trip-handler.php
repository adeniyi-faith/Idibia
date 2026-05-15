<?php
/** Idibia — Book Trip Handler */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';
idibia_clean_json_buffer();


if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
if ( ! idibia_check_rate_limit( 'booking', $ip, 10, 60 ) ) {
    http_response_code( 429 );
    wp_send_json_error( [ 'message' => 'Too many requests. Please try again later.' ] );
}

$auth_type = 'customer';
require_once __DIR__ . '/auth-helper.php';

global $wpdb;
$customer_id = (int) $GLOBALS['auth_customer_id'];
$pickup      = sanitize_text_field( wp_unslash( $_POST['pickup'] ?? '' ) );
$dropoff     = sanitize_text_field( wp_unslash( $_POST['dropoff'] ?? '' ) );
$category    = sanitize_text_field( wp_unslash( $_POST['category'] ?? 'bike' ) );
$allowed     = [ 'bike', 'car', 'van', 'keke' ];

if ( ! $pickup || ! $dropoff ) wp_send_json_error( [ 'message' => 'Pickup and drop-off are required.' ] );
if ( ! in_array( $category, $allowed, true ) ) $category = 'bike';

$table = $wpdb->prefix . 'sd_trips';
do {
    $trip_ref = 'TRP-' . strtoupper( substr( uniqid( '', true ), -6 ) );
    $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `$table` WHERE trip_ref = %s LIMIT 1", $trip_ref ) );
} while ( $exists );

$inserted = $wpdb->insert( $table, [ 'trip_ref' => $trip_ref, 'customer_id' => $customer_id, 'pickup' => $pickup, 'dropoff' => $dropoff, 'category' => $category, 'status' => 'pending' ], [ '%s', '%d', '%s', '%s', '%s', '%s' ] );
if ( ! $inserted ) wp_send_json_error( [ 'message' => 'Could not book trip. Please try again.' ] );

wp_send_json_success( [ 'trip_id' => (int) $wpdb->insert_id, 'trip_ref' => $trip_ref, 'status' => 'pending' ] );
