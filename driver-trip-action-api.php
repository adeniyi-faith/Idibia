<?php
/** Idibia — Driver Trip Action API */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-dispatch-helpers.php';
idibia_clean_json_buffer();

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( $_POST['_nonce'] ) : '';
if ( ! wp_verify_nonce( $nonce, 'idibia_driver_action' ) ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Security check failed. Please refresh and try again.' ] );
}

$auth_type = 'driver';
require_once __DIR__ . '/auth-helper.php';

global $wpdb;
$driver_id = (int) $GLOBALS['auth_driver_id'];

$trip_id = (int) ( $_POST['trip_id'] ?? 0 );
$action  = sanitize_text_field( $_POST['action'] ?? '' );
$valid_actions = [ 'accept', 'decline', 'arrive_pickup', 'start_delivery', 'arrive_dropoff', 'complete', 'cancel' ];

if ( ! $trip_id || ! in_array( $action, $valid_actions, true ) ) {
    wp_send_json_error( [ 'message' => 'Invalid request parameters.' ] );
}

idibia_transaction_start();

// 1. Decline Action
if ( $action === 'decline' ) {
    $offer = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM `{$wpdb->prefix}sd_dispatch_offers` WHERE trip_id = %d AND driver_id = %d AND status = 'pending' FOR UPDATE", $trip_id, $driver_id ) );

    if ( $offer ) {
        $wpdb->update( $wpdb->prefix . 'sd_dispatch_offers', [ 'status' => 'declined' ], [ 'id' => $offer->id ], [ '%s' ], [ '%d' ] );
        idibia_log_event( $trip_id, 'dispatch_declined', [ 'driver_id' => $driver_id ] );
    }

    idibia_transaction_commit();

    // Find next candidate for the trip we just declined
    $trip = $wpdb->get_row( $wpdb->prepare( "SELECT id, vehicle_type, pickup_lat, pickup_lng FROM `{$wpdb->prefix}sd_trips` WHERE id = %d LIMIT 1", $trip_id ) );
    if ( $trip ) {
        $candidates = idibia_find_dispatch_candidates( (int) $trip->id, $trip->vehicle_type, (float) $trip->pickup_lat, (float) $trip->pickup_lng );

        if ( ! empty( $candidates ) ) {
            idibia_create_dispatch_offer( (int) $trip->id, (int) $candidates[0]['driver_id'] );
        } else {
            $wpdb->update( $wpdb->prefix . 'sd_trips', [ 'dispatch_status' => 'no_driver' ], [ 'id' => $trip->id ], [ '%s' ], [ '%d' ] );
            idibia_log_event( (int) $trip->id, 'dispatch_no_driver', [] );
        }
    }

    // Also clean up any other stale offers just in case
    idibia_expire_stale_offers();
    wp_send_json_success( [ 'message' => 'Offer declined.' ] );
}

// 2. Accept Action
if ( $action === 'accept' ) {
    $offer = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM `{$wpdb->prefix}sd_dispatch_offers` WHERE trip_id = %d AND driver_id = %d AND status = 'pending' AND expires_at > NOW() FOR UPDATE", $trip_id, $driver_id ) );

    if ( ! $offer ) {
        idibia_transaction_rollback();
        wp_send_json_error( [ 'message' => 'Offer expired or no longer available.' ] );
    }

    $trip = $wpdb->get_row( $wpdb->prepare( "SELECT status, dispatch_status FROM `{$wpdb->prefix}sd_trips` WHERE id = %d FOR UPDATE", $trip_id ) );

    if ( $trip->dispatch_status !== 'offered' || $trip->status !== 'pending' ) {
        idibia_transaction_rollback();
        wp_send_json_error( [ 'message' => 'Trip is no longer available.' ] );
    }

    // Accept it
    $wpdb->update( $wpdb->prefix . 'sd_dispatch_offers', [ 'status' => 'accepted' ], [ 'id' => $offer->id ], [ '%s' ], [ '%d' ] );
    $wpdb->update( $wpdb->prefix . 'sd_trips',
        [ 'driver_id' => $driver_id, 'status' => 'accepted', 'dispatch_status' => 'accepted', 'accepted_at' => current_time('mysql') ],
        [ 'id' => $trip_id ], [ '%d', '%s', '%s', '%s' ], [ '%d' ]
    );

    idibia_log_event( $trip_id, 'dispatch_accepted', [ 'driver_id' => $driver_id ] );
    idibia_transaction_commit();
    wp_send_json_success( [ 'message' => 'Trip accepted successfully.' ] );
}

// 3. Execution Actions
$trip = $wpdb->get_row( $wpdb->prepare( "SELECT status, dispatch_status FROM `{$wpdb->prefix}sd_trips` WHERE id = %d AND driver_id = %d FOR UPDATE", $trip_id, $driver_id ) );

if ( ! $trip ) {
    idibia_transaction_rollback();
    wp_send_json_error( [ 'message' => 'Trip not found or not assigned to you.' ] );
}

if ( $trip->status === 'cancelled' || $trip->status === 'completed' ) {
    idibia_transaction_rollback();
    wp_send_json_error( [ 'message' => 'Trip is already ' . $trip->status . '.' ] );
}

$updates = [];
$event_name = '';

switch ( $action ) {
    case 'arrive_pickup':
        if ( $trip->dispatch_status !== 'accepted' ) {
            idibia_transaction_rollback(); wp_send_json_error( [ 'message' => 'Invalid state transition.' ] );
        }
        $updates = [ 'dispatch_status' => 'arrived_pickup', 'arrived_at' => current_time('mysql') ];
        $event_name = 'driver_arrived_pickup';
        break;

    case 'start_delivery':
        if ( $trip->dispatch_status !== 'arrived_pickup' ) {
            idibia_transaction_rollback(); wp_send_json_error( [ 'message' => 'Invalid state transition.' ] );
        }
        $updates = [ 'status' => 'in_progress', 'dispatch_status' => 'picked_up' ];
        $event_name = 'trip_in_progress';
        break;

    case 'arrive_dropoff':
        if ( $trip->dispatch_status !== 'picked_up' ) {
            idibia_transaction_rollback(); wp_send_json_error( [ 'message' => 'Invalid state transition.' ] );
        }
        $updates = [ 'dispatch_status' => 'arrived_dropoff' ];
        $event_name = 'driver_arrived_dropoff';
        break;

    case 'complete':
        if ( $trip->dispatch_status !== 'arrived_dropoff' ) {
            idibia_transaction_rollback(); wp_send_json_error( [ 'message' => 'Invalid state transition.' ] );
        }
        $updates = [ 'status' => 'completed', 'dispatch_status' => 'completed', 'completed_at' => current_time('mysql') ];
        $event_name = 'trip_completed';
        break;

    case 'cancel':
        $updates = [ 'status' => 'cancelled', 'dispatch_status' => 'cancelled', 'cancellation_reason' => 'Driver cancelled' ];
        $event_name = 'trip_cancelled_driver';
        break;
}

if ( ! empty( $updates ) ) {
    $wpdb->update( $wpdb->prefix . 'sd_trips', $updates, [ 'id' => $trip_id ], array_fill(0, count($updates), '%s'), [ '%d' ] );
    idibia_log_event( $trip_id, $event_name, [ 'driver_id' => $driver_id ] );
}

idibia_transaction_commit();
wp_send_json_success( [ 'message' => 'Trip updated.', 'new_state' => $updates['dispatch_status'] ?? $trip->dispatch_status ] );
