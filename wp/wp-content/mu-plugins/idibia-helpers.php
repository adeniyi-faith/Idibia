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
