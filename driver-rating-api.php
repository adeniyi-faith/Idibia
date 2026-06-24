<?php
/** Idibia — Driver-to-Customer Rating API */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';
idibia_clean_json_buffer();

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

$auth_type = 'driver';
require_once __DIR__ . '/auth-helper.php';

$driver_id = (int) $GLOBALS['auth_driver_id'];
$trip_id   = absint( $_POST['trip_id'] ?? 0 );
$rating    = absint( $_POST['rating'] ?? 0 );
$comment   = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) );

if ( $trip_id <= 0 || $rating < 1 || $rating > 5 ) {
    wp_send_json_error( [ 'message' => 'Select a valid trip and a star rating from 1 to 5.' ] );
}

global $wpdb;

$trip = $wpdb->get_row( $wpdb->prepare(
    "SELECT id, driver_id, customer_id, status, dispatch_status FROM `{$wpdb->prefix}sd_trips` WHERE id = %d LIMIT 1",
    $trip_id
), ARRAY_A );

if ( ! $trip || (int) $trip['driver_id'] !== $driver_id ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'You were not the driver on this trip.' ] );
}

if ( $trip['status'] !== 'completed' && $trip['dispatch_status'] !== 'completed' ) {
    wp_send_json_error( [ 'message' => 'You can only rate a customer after the trip is completed.' ] );
}

$customer_id = (int) $trip['customer_id'];
if ( $customer_id <= 0 ) {
    wp_send_json_error( [ 'message' => 'No customer is assigned to this trip.' ] );
}

$existing_id = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT id FROM `{$wpdb->prefix}sd_ratings` WHERE trip_id = %d AND reviewer_id = %d AND reviewer_type = 'driver' LIMIT 1",
    $trip_id,
    $driver_id
) );

$data    = [
    'trip_id'       => $trip_id,
    'reviewer_id'   => $driver_id,
    'reviewer_type' => 'driver',
    'subject_id'    => $customer_id,
    'rating'        => $rating,
    'comment'       => $comment ?: null,
];
$formats = [ '%d', '%d', '%s', '%d', '%d', '%s' ];

if ( $existing_id > 0 ) {
    $saved = $wpdb->update( $wpdb->prefix . 'sd_ratings', $data, [ 'id' => $existing_id ], $formats, [ '%d' ] );
} else {
    $saved = $wpdb->insert( $wpdb->prefix . 'sd_ratings', $data, $formats );
}

if ( false === $saved ) {
    wp_send_json_error( [ 'message' => 'Could not save rating. Please try again.' ] );
}

$avg = (float) $wpdb->get_var( $wpdb->prepare(
    "SELECT AVG(rating) FROM `{$wpdb->prefix}sd_ratings` WHERE reviewer_type = 'driver' AND subject_id = %d",
    $customer_id
) );
$wpdb->update(
    $wpdb->prefix . 'sd_customers',
    [ 'rating' => round( $avg, 2 ) ],
    [ 'id' => $customer_id ],
    [ '%f' ],
    [ '%d' ]
);

idibia_log_event( $trip_id, 'rating_submitted', [ 'reviewer_type' => 'driver', 'rating' => $rating ] );

wp_send_json_success( [ 'message' => 'Rating saved. Thank you!' ] );
