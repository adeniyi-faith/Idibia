<?php
/** Idibia — Driver Trip Action API */

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
$action = sanitize_key( wp_unslash( $_POST['action'] ?? '' ) );
$offer_id = (int) ( $_POST['offer_id'] ?? 0 );
$trip_id = (int) ( $_POST['trip_id'] ?? 0 );

function idibia_driver_action_fail( string $message, int $status = 400 ): void {
    idibia_transaction_rollback();
    http_response_code( $status );
    wp_send_json_error( [ 'message' => $message ] );
}

idibia_transaction_start();

if ( $action === 'decline_offer' ) {
    if ( $offer_id <= 0 ) idibia_driver_action_fail( 'Offer ID is required.' );
    $offer = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sd_dispatch_offers` WHERE id = %d AND driver_id = %d LIMIT 1", $offer_id, $driver_id ), ARRAY_A );
    if ( ! $offer || $offer['status'] !== 'pending' ) idibia_driver_action_fail( 'Offer is no longer available.', 409 );
    $wpdb->update( $wpdb->prefix . 'sd_dispatch_offers', [ 'status' => 'declined' ], [ 'id' => $offer_id ], [ '%s' ], [ '%d' ] );
    idibia_log_event( (int) $offer['trip_id'], 'offer_declined', [ 'driver_id' => $driver_id ] );
    idibia_pusher_broadcast_driver_offers( [ $driver_id ], 'offer_declined', [ 'trip_id' => (int) $offer['trip_id'] ] );
    idibia_transaction_commit();
    wp_send_json_success( [ 'message' => 'Offer declined.', 'offers' => idibia_get_driver_offers( $driver_id ) ] );
}

if ( $action === 'accept_offer' ) {
    if ( $offer_id <= 0 ) idibia_driver_action_fail( 'Offer ID is required.' );

    // Check if the driver already has an active trip
    $active_trip = idibia_get_driver_active_trip( $driver_id );
    if ( $active_trip !== null ) idibia_driver_action_fail( 'You already have an active trip.', 409 );

    $offer = $wpdb->get_row( $wpdb->prepare(
        "SELECT o.*, t.driver_id AS assigned_driver_id, t.dispatch_status FROM `{$wpdb->prefix}sd_dispatch_offers` o INNER JOIN `{$wpdb->prefix}sd_trips` t ON t.id = o.trip_id WHERE o.id = %d AND o.driver_id = %d LIMIT 1",
        $offer_id,
        $driver_id
    ), ARRAY_A );
    if ( ! $offer || $offer['status'] !== 'pending' || strtotime( $offer['expires_at'] . ' UTC' ) < time() ) idibia_driver_action_fail( 'Offer has expired.', 409 );
    if ( ! empty( $offer['assigned_driver_id'] ) || ! in_array( $offer['dispatch_status'], [ 'offered', 'searching', 'no_driver' ], true ) ) idibia_driver_action_fail( 'Trip has already been assigned.', 409 );

    $updated = $wpdb->query( $wpdb->prepare(
        "UPDATE `{$wpdb->prefix}sd_trips`
         SET driver_id = %d, status = 'accepted', dispatch_status = 'accepted', accepted_at = %s
         WHERE id = %d AND driver_id IS NULL",
        $driver_id,
        idibia_dispatch_now(),
        (int) $offer['trip_id']
    ) );
    if ( $updated !== 1 ) idibia_driver_action_fail( 'Trip has already been assigned.', 409 );

    $pending_driver_ids = $wpdb->get_col( $wpdb->prepare( "SELECT driver_id FROM `{$wpdb->prefix}sd_dispatch_offers` WHERE trip_id = %d AND id <> %d AND status = 'pending'", (int) $offer['trip_id'], $offer_id ) );
    $wpdb->update( $wpdb->prefix . 'sd_dispatch_offers', [ 'status' => 'accepted' ], [ 'id' => $offer_id ], [ '%s' ], [ '%d' ] );
    $wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->prefix}sd_dispatch_offers` SET status = 'expired' WHERE trip_id = %d AND id <> %d AND status = 'pending'", (int) $offer['trip_id'], $offer_id ) );
    idibia_pusher_broadcast_driver_offers( array_merge( [ $driver_id ], array_map( 'intval', $pending_driver_ids ?: [] ) ), 'offer_accepted', [ 'trip_id' => (int) $offer['trip_id'] ] );
    idibia_log_event( (int) $offer['trip_id'], 'offer_accepted', [ 'driver_id' => $driver_id ] );
    idibia_notify_trip_participants( (int) $offer['trip_id'], 'offer_accepted' );
    idibia_transaction_commit();
    wp_send_json_success( [ 'message' => 'Trip accepted.', 'active_trip' => idibia_get_driver_active_trip( $driver_id ) ] );
}

$allowed = [
    'start_to_pickup' => [ 'from' => [ 'accepted' ], 'to' => 'arriving', 'status' => 'accepted', 'event' => 'driver_en_route' ],
    'arrived_pickup'  => [ 'from' => [ 'accepted', 'arriving' ], 'to' => 'arrived_pickup', 'status' => 'accepted', 'event' => 'driver_arrived_pickup' ],
    'picked_up'       => [ 'from' => [ 'arrived_pickup' ], 'to' => 'picked_up', 'status' => 'in_progress', 'event' => 'package_picked_up' ],
    'arrived_dropoff' => [ 'from' => [ 'picked_up' ], 'to' => 'arrived_dropoff', 'status' => 'in_progress', 'event' => 'driver_arrived_dropoff' ],
    'complete'        => [ 'from' => [ 'arrived_dropoff', 'picked_up' ], 'to' => 'completed', 'status' => 'completed', 'event' => 'trip_completed' ],
];

if ( ! isset( $allowed[ $action ] ) ) idibia_driver_action_fail( 'Unknown action.' );
if ( $trip_id <= 0 ) idibia_driver_action_fail( 'Trip ID is required.' );

$trip = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sd_trips` WHERE id = %d AND driver_id = %d LIMIT 1", $trip_id, $driver_id ), ARRAY_A );
if ( ! $trip ) idibia_driver_action_fail( 'Trip not found.', 404 );
$transition = $allowed[ $action ];
if ( ! in_array( $trip['dispatch_status'], $transition['from'], true ) ) idibia_driver_action_fail( 'Trip is not in the right state for this action.', 409 );

$data = [ 'dispatch_status' => $transition['to'], 'status' => $transition['status'] ];
$formats = [ '%s', '%s' ];
if ( $action === 'complete' ) {
    $submitted_pin = sanitize_text_field( wp_unslash( $_POST['delivery_pin'] ?? '' ) );
    if ( ! empty( $trip['delivery_pin'] ) && ! hash_equals( (string) $trip['delivery_pin'], $submitted_pin ) ) {
        idibia_driver_action_fail( 'Delivery PIN is required to complete this trip.', 409 );
    }

    $fare = (float) ( $trip['final_fare'] ?: $trip['fare_estimate'] ?: $trip['fare'] );
    $data['final_fare'] = $fare;
    $data['completed_at'] = idibia_dispatch_now();
    $formats = array_merge( $formats, [ '%f', '%s' ] );
}

$wpdb->update( $wpdb->prefix . 'sd_trips', $data, [ 'id' => $trip_id ], $formats, [ '%d' ] );
idibia_log_event( $trip_id, $transition['event'], [ 'driver_id' => $driver_id ] );
idibia_notify_trip_participants( $trip_id, $transition['event'] );
if ( $action === 'complete' && $trip['payment_status'] === 'captured' ) {
    idibia_credit_driver_for_trip( $trip_id );
}
idibia_transaction_commit();
wp_send_json_success( [ 'message' => 'Trip updated.', 'active_trip' => idibia_get_driver_active_trip( $driver_id ) ] );
