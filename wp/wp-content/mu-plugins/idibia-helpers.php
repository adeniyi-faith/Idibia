<?php
/**
 * Plugin Name: Idibia Core Helpers
 * Description: Shared helper functions for Idibia backend. Place in /wp-content/mu-plugins/
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Sends a clean JSON response, ensuring output buffers are cleared.
 */
function idibia_json_response( array $data, int $status_code = 200 ) {
    while ( ob_get_level() > 0 ) {
        ob_end_clean();
    }
    http_response_code( $status_code );
    header( 'Content-Type: application/json; charset=utf-8' );
    echo wp_json_encode( $data );
    exit;
}

/**
 * Returns the current authenticated actor (customer or driver) ID, or 0 if not logged in.
 */
function idibia_get_current_actor( string $type ): int {
    if ( ! is_user_logged_in() ) {
        return 0;
    }

    $user_id = get_current_user_id();
    $account_type = get_user_meta( $user_id, 'idibia_account_type', true );

    if ( $account_type !== $type ) {
        return 0;
    }

    $meta_key = $type === 'driver' ? 'idibia_driver_id' : 'idibia_customer_id';
    return (int) get_user_meta( $user_id, $meta_key, true );
}

/**
 * Logs a trip event to the sd_trip_events table.
 */
function idibia_log_event( int $trip_id, string $event_type, array $event_data = [] ): bool {
    global $wpdb;

    $inserted = $wpdb->insert(
        $wpdb->prefix . 'sd_trip_events',
        [
            'trip_id'    => $trip_id,
            'event_type' => $event_type,
            'event_data' => ! empty( $event_data ) ? wp_json_encode( $event_data ) : null,
        ],
        [ '%d', '%s', '%s' ]
    );

    return $inserted !== false;
}

/**
 * Transaction helpers.
 */
function idibia_transaction_start() {
    global $wpdb;
    $wpdb->query( 'START TRANSACTION' );
}

function idibia_transaction_commit() {
    global $wpdb;
    $wpdb->query( 'COMMIT' );
}

function idibia_transaction_rollback() {
    global $wpdb;
    $wpdb->query( 'ROLLBACK' );
}

/**
 * Rate limiting helper using WordPress Transients API.
 * Returns true if allowed, false if rate limited.
 */
function idibia_check_rate_limit( string $action, string $ip, int $max_attempts = 5, int $timeout = 300 ): bool {
    $transient_name = 'rate_limit_' . md5( $action . '_' . $ip );
    $attempts = (int) get_transient( $transient_name );

    if ( $attempts >= $max_attempts ) {
        return false;
    }

    set_transient( $transient_name, $attempts + 1, $timeout );
    return true;
}
/**
 * Creates an in-app notification for a customer, driver, or admin actor.
 */
function idibia_notify_user( int $user_id, string $user_type, string $title, string $body ): bool {
    global $wpdb;

    if ( $user_id <= 0 || ! in_array( $user_type, [ 'customer', 'driver', 'admin' ], true ) ) {
        return false;
    }

    $inserted = $wpdb->insert(
        $wpdb->prefix . 'sd_notifications',
        [
            'user_id'   => $user_id,
            'user_type' => $user_type,
            'title'     => wp_strip_all_tags( $title ),
            'body'      => wp_strip_all_tags( $body ),
            'is_read'   => 0,
        ],
        [ '%d', '%s', '%s', '%s', '%d' ]
    );

    return $inserted !== false;
}

/**
 * Sends lifecycle notifications to the trip customer, assigned driver, and admins.
 */
function idibia_notify_trip_participants( int $trip_id, string $event_type, array $context = [] ): void {
    global $wpdb;

    $trip = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, trip_ref, customer_id, driver_id, payment_status, dispatch_status FROM `{$wpdb->prefix}sd_trips` WHERE id = %d LIMIT 1",
        $trip_id
    ), ARRAY_A );

    if ( ! $trip ) {
        return;
    }

    $labels = [
        'trip_created'            => [ 'Trip created', 'We are searching for a verified driver for trip #' . $trip['trip_ref'] . '.' ],
        'dispatch_offers_created' => [ 'Finding your driver', 'Your request has been sent to nearby verified drivers.' ],
        'dispatch_no_driver'      => [ 'No driver available yet', 'We are still watching for nearby drivers and will update this trip.' ],
        'offer_accepted'          => [ 'Driver assigned', 'A driver has accepted trip #' . $trip['trip_ref'] . '.' ],
        'driver_en_route'         => [ 'Driver arriving', 'Your driver is on the way to the pickup location.' ],
        'driver_arrived_pickup'   => [ 'Driver at pickup', 'Your driver has arrived at the pickup location.' ],
        'package_picked_up'       => [ 'Package picked up', 'Your package is now on the way.' ],
        'driver_arrived_dropoff'  => [ 'Near drop-off', 'Your driver has arrived at the drop-off location.' ],
        'trip_completed'          => [ 'Delivered', 'Trip #' . $trip['trip_ref'] . ' has been completed.' ],
        'trip_cancelled'          => [ 'Trip cancelled', 'Trip #' . $trip['trip_ref'] . ' was cancelled.' ],
        'payment_captured'        => [ 'Payment captured', 'Payment for trip #' . $trip['trip_ref'] . ' has been captured.' ],
        'payment_refunded'        => [ 'Refund updated', 'A refund update is available for trip #' . $trip['trip_ref'] . '.' ],
        'support_reply'           => [ 'Support replied', 'Support has replied about trip #' . $trip['trip_ref'] . '.' ],
    ];

    if ( ! isset( $labels[ $event_type ] ) ) {
        return;
    }

    [ $title, $body ] = $labels[ $event_type ];
    if ( ! empty( $context['body'] ) ) {
        $body = (string) $context['body'];
    }

    idibia_notify_user( (int) $trip['customer_id'], 'customer', $title, $body );
    if ( ! empty( $trip['driver_id'] ) ) {
        idibia_notify_user( (int) $trip['driver_id'], 'driver', $title, $body );
    }

    $admins = get_users( [ 'role__in' => [ 'administrator' ], 'fields' => [ 'ID' ], 'number' => 20 ] );
    foreach ( $admins as $admin ) {
        idibia_notify_user( (int) $admin->ID, 'admin', $title, $body );
    }
}
