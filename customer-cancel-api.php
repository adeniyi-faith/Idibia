<?php
/** Idibia — Customer Cancel API */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';
idibia_clean_json_buffer();

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
if ( ! idibia_check_rate_limit( 'cancellation', $ip, 5, 60 ) ) {
    http_response_code( 429 );
    wp_send_json_error( [ 'message' => 'Too many requests. Please try again later.' ] );
}

$auth_type = 'customer';
require_once __DIR__ . '/auth-helper.php';

global $wpdb;
$customer_id = (int) $GLOBALS['auth_customer_id'];
$trip_id     = (int) ( $_POST['trip_id'] ?? 0 );
$reason      = sanitize_text_field( wp_unslash( $_POST['reason'] ?? 'Cancelled by customer' ) );

if ( $trip_id <= 0 ) {
    wp_send_json_error( [ 'message' => 'Invalid trip ID.' ] );
}

// Ensure the trip exists and belongs to the customer
$trip = $wpdb->get_row( $wpdb->prepare(
    "SELECT id, status, dispatch_status, payment_status FROM `{$wpdb->prefix}sd_trips` WHERE id = %d AND customer_id = %d LIMIT 1",
    $trip_id,
    $customer_id
), ARRAY_A );

if ( ! $trip ) {
    wp_send_json_error( [ 'message' => 'Trip not found or permission denied.' ] );
}

if ( in_array( $trip['status'], [ 'completed', 'cancelled' ], true ) || in_array( $trip['dispatch_status'], [ 'completed', 'cancelled' ], true ) ) {
    wp_send_json_error( [ 'message' => 'This trip cannot be cancelled.' ] );
}

if ( in_array( $trip['dispatch_status'], [ 'arrived_pickup', 'picked_up', 'arrived_dropoff' ], true ) ) {
    wp_send_json_error( [ 'message' => 'Driver has already arrived or picked up. Please contact support to cancel.' ] );
}

idibia_transaction_start();

// Update trip status
$updated = $wpdb->update(
    $wpdb->prefix . 'sd_trips',
    [
        'status'              => 'cancelled',
        'dispatch_status'     => 'cancelled',
        'cancellation_reason' => $reason
    ],
    [ 'id' => $trip_id ],
    [ '%s', '%s', '%s' ],
    [ '%d' ]
);

if ( false === $updated ) {
    idibia_transaction_rollback();
    wp_send_json_error( [ 'message' => 'Failed to cancel the trip.' ] );
}

// Expire pending offers
$wpdb->query( $wpdb->prepare(
    "UPDATE `{$wpdb->prefix}sd_dispatch_offers` SET status = 'cancelled' WHERE trip_id = %d AND status IN ('pending', 'accepted')",
    $trip_id
) );

// Refund payment if necessary (naive implementation: mark as failed/refunded)
if ( $trip['payment_status'] === 'pending' || $trip['payment_status'] === 'authorized' ) {
    $wpdb->update(
        $wpdb->prefix . 'sd_payments',
        [ 'status' => 'failed' ], // Or refunded, depending on flow
        [ 'trip_id' => $trip_id ],
        [ '%s' ],
        [ '%d' ]
    );

    // Also update trips payment status
    $wpdb->update(
        $wpdb->prefix . 'sd_trips',
        [ 'payment_status' => 'refunded' ],
        [ 'id' => $trip_id ],
        [ '%s' ],
        [ '%d' ]
    );
}

idibia_transaction_commit();

idibia_log_event( $trip_id, 'trip_cancelled_by_customer', [ 'reason' => $reason ] );
idibia_notify_trip_participants( $trip_id, 'trip_cancelled_by_customer' );

wp_send_json_success( [ 'message' => 'Trip cancelled successfully.' ] );
