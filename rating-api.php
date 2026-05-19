<?php
/** Idibia — Trip Rating API */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';
idibia_clean_json_buffer();

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

if ( ! is_user_logged_in() ) {
    http_response_code( 401 );
    wp_send_json_error( [ 'message' => 'Unauthenticated.' ] );
}

$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ) : '';
if ( ! wp_verify_nonce( $nonce, 'idibia_support_action' ) ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Security check failed. Please refresh and try again.' ] );
}

$user_id = get_current_user_id();
$reviewer_type = get_user_meta( $user_id, 'idibia_account_type', true );
if ( ! in_array( $reviewer_type, [ 'customer', 'driver' ], true ) ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Only customers and drivers can rate trips.' ] );
}

$reviewer_id = idibia_find_or_create_profile_row( $user_id, $reviewer_type );
$trip_id = absint( $_POST['trip_id'] ?? 0 );
$rating = absint( $_POST['rating'] ?? 0 );
$comment = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) );
if ( $trip_id <= 0 || $rating < 1 || $rating > 5 ) {
    wp_send_json_error( [ 'message' => 'Select a trip and a rating from 1 to 5.' ] );
}

$trip = idibia_get_trip_for_actor( $trip_id, $reviewer_type, $reviewer_id );
if ( ! $trip ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'You cannot rate this trip.' ] );
}
if ( $trip['status'] !== 'completed' && $trip['dispatch_status'] !== 'completed' ) {
    wp_send_json_error( [ 'message' => 'You can rate only after the trip is completed.' ] );
}

$subject_id = $reviewer_type === 'customer' ? (int) $trip['driver_id'] : (int) $trip['customer_id'];
if ( $subject_id <= 0 ) {
    wp_send_json_error( [ 'message' => 'The other party is not assigned yet.' ] );
}

global $wpdb;
$existing_id = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT id FROM `{$wpdb->prefix}sd_ratings` WHERE trip_id = %d AND reviewer_id = %d AND reviewer_type = %s LIMIT 1",
    $trip_id,
    $reviewer_id,
    $reviewer_type
) );

$data = [
    'trip_id'       => $trip_id,
    'reviewer_id'   => $reviewer_id,
    'reviewer_type' => $reviewer_type,
    'subject_id'    => $subject_id,
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
    wp_send_json_error( [ 'message' => 'Could not save rating.' ] );
}

if ( $reviewer_type === 'customer' ) {
    $avg = (float) $wpdb->get_var( $wpdb->prepare( "SELECT AVG(rating) FROM `{$wpdb->prefix}sd_ratings` WHERE reviewer_type = 'customer' AND subject_id = %d", $subject_id ) );
    $wpdb->update( $wpdb->prefix . 'sd_drivers', [ 'rating' => round( $avg, 2 ) ], [ 'id' => $subject_id ], [ '%f' ], [ '%d' ] );
} else if ( $reviewer_type === 'driver' ) {
    $avg = (float) $wpdb->get_var( $wpdb->prepare( "SELECT AVG(rating) FROM `{$wpdb->prefix}sd_ratings` WHERE reviewer_type = 'driver' AND subject_id = %d", $subject_id ) );
    $wpdb->update( $wpdb->prefix . 'sd_customers', [ 'rating' => round( $avg, 2 ) ], [ 'id' => $subject_id ], [ '%f' ], [ '%d' ] );
}

idibia_log_event( $trip_id, 'rating_submitted', [ 'reviewer_type' => $reviewer_type, 'rating' => $rating ] );
idibia_notify_trip_participants( $trip_id, 'rating_submitted' );

wp_send_json_success( [ 'message' => 'Rating saved. Thank you!' ] );
