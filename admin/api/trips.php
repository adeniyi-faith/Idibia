<?php
/** Idibia — Admin API: Trips */

function idibia_admin_paginated_trips(): void {
    global $wpdb;
    [ $page, $per_page, $offset ] = idibia_page_args();
    $where = [ '1=1' ]; $args = [];
    $status = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );
    $category = sanitize_text_field( wp_unslash( $_GET['category'] ?? '' ) );
    $search = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) );
    if ( $status ) {
        if ( $status === 'in-transit' ) { $where[] = "(t.status IN ('accepted','in_progress') OR t.dispatch_status IN ('accepted','arriving','arrived_pickup','picked_up','arrived_dropoff'))"; }
        elseif ( $status === 'delivered' ) { $where[] = "t.status = 'completed'"; }
        elseif ( $status === 'cancelled' ) { $where[] = "t.status = 'cancelled'"; }
        elseif ( $status === 'delayed' ) { $where[] = "t.status NOT IN ('completed','cancelled') AND t.created_at < UTC_TIMESTAMP() - INTERVAL 2 HOUR"; }
        else { $where[] = 't.status = %s'; $args[] = $status; }
    }
    if ( $category ) { $like = '%' . $wpdb->esc_like( $category ) . '%'; $where[] = '(t.category = %s OR t.service_category LIKE %s)'; array_push( $args, $category, $like ); }
    if ( $search ) { $like = '%' . $wpdb->esc_like( $search ) . '%'; $where[] = '(t.trip_ref LIKE %s OR t.pickup LIKE %s OR t.dropoff LIKE %s OR t.pickup_address LIKE %s OR t.dropoff_address LIKE %s OR c.full_name LIKE %s OR d.full_name LIKE %s)'; array_push( $args, $like, $like, $like, $like, $like, $like, $like ); }
    $sql_where = implode( ' AND ', $where );
    $total = (int) $wpdb->get_var( idibia_sql( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_trips` t LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = t.customer_id LEFT JOIN `{$wpdb->prefix}sd_drivers` d ON d.id = t.driver_id WHERE $sql_where", $args ) );
    $sql = "SELECT t.*, COALESCE(NULLIF(t.final_fare, 0), NULLIF(t.fare_estimate, 0), t.fare, 0) AS fare_amount, c.full_name AS customer_name, d.full_name AS driver_name FROM `{$wpdb->prefix}sd_trips` t LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = t.customer_id LEFT JOIN `{$wpdb->prefix}sd_drivers` d ON d.id = t.driver_id WHERE $sql_where ORDER BY t.created_at DESC LIMIT %d OFFSET %d";
    $rows = $wpdb->get_results( idibia_sql( $sql, array_merge( $args, [ $per_page, $offset ] ) ), ARRAY_A );
    wp_send_json_success( [ 'trips' => $rows ?: [], 'page' => $page, 'per_page' => $per_page, 'total' => $total ] );
}



function idibia_admin_reassign_trip(): void {
    global $wpdb;
    $trip_id = (int) ( $_POST['trip_id'] ?? 0 );
    $driver_id = (int) ( $_POST['driver_id'] ?? 0 );

    if ( $trip_id <= 0 || $driver_id <= 0 ) {
        wp_send_json_error( [ 'message' => 'Invalid parameters.' ] );
    }

    $trip = $wpdb->get_row( $wpdb->prepare( "SELECT id, status, dispatch_status FROM `{$wpdb->prefix}sd_trips` WHERE id = %d LIMIT 1", $trip_id ), ARRAY_A );
    if ( ! $trip ) wp_send_json_error( [ 'message' => 'Trip not found.' ] );
    if ( in_array( $trip['status'], [ 'completed', 'cancelled' ] ) || in_array( $trip['dispatch_status'], [ 'completed', 'cancelled' ] ) ) {
        wp_send_json_error( [ 'message' => 'Cannot reassign a completed or cancelled trip.' ] );
    }

    idibia_transaction_start();

    // Update the trip
    $updated = $wpdb->update(
        $wpdb->prefix . 'sd_trips',
        [ 'driver_id' => $driver_id, 'dispatch_status' => 'accepted', 'status' => 'accepted' ],
        [ 'id' => $trip_id ],
        [ '%d', '%s', '%s' ],
        [ '%d' ]
    );

    if ( false === $updated ) {
        idibia_transaction_rollback();
        wp_send_json_error( [ 'message' => 'Failed to reassign trip.' ] );
    }

    // Expire pending offers
    $wpdb->query( $wpdb->prepare(
        "UPDATE `{$wpdb->prefix}sd_dispatch_offers` SET status = 'expired' WHERE trip_id = %d AND status IN ('pending', 'accepted')",
        $trip_id
    ) );

    idibia_log_event( $trip_id, 'trip_reassigned_by_admin', [ 'new_driver_id' => $driver_id ] );

    // Broadcast trip change
    if ( function_exists('idibia_pusher_broadcast_trip') ) {
        idibia_pusher_broadcast_trip( $trip_id, 'trip_reassigned' );
    }

    // Add Audit Log
    idibia_admin_audit_log( 'reassign_trip', 'trip', $trip_id, [ 'new_driver_id' => $driver_id ] );

    idibia_transaction_commit();
    wp_send_json_success( [ 'message' => 'Trip successfully reassigned.' ] );
}

function idibia_admin_force_cancel_trip(): void {
    global $wpdb;
    $trip_id = (int) ( $_POST['trip_id'] ?? 0 );
    $reason = sanitize_text_field( wp_unslash( $_POST['reason'] ?? 'Force cancelled by admin' ) );

    if ( $trip_id <= 0 ) {
        wp_send_json_error( [ 'message' => 'Invalid parameters.' ] );
    }

    $trip = $wpdb->get_row( $wpdb->prepare( "SELECT id, status, dispatch_status FROM `{$wpdb->prefix}sd_trips` WHERE id = %d LIMIT 1", $trip_id ), ARRAY_A );
    if ( ! $trip ) wp_send_json_error( [ 'message' => 'Trip not found.' ] );
    if ( in_array( $trip['status'], [ 'completed', 'cancelled' ] ) || in_array( $trip['dispatch_status'], [ 'completed', 'cancelled' ] ) ) {
        wp_send_json_error( [ 'message' => 'Trip is already completed or cancelled.' ] );
    }

    idibia_transaction_start();

    // Update the trip
    $updated = $wpdb->update(
        $wpdb->prefix . 'sd_trips',
        [ 'status' => 'cancelled', 'dispatch_status' => 'cancelled', 'cancellation_reason' => $reason ],
        [ 'id' => $trip_id ],
        [ '%s', '%s', '%s' ],
        [ '%d' ]
    );

    if ( false === $updated ) {
        idibia_transaction_rollback();
        wp_send_json_error( [ 'message' => 'Failed to cancel trip.' ] );
    }

    // Expire pending offers
    $wpdb->query( $wpdb->prepare(
        "UPDATE `{$wpdb->prefix}sd_dispatch_offers` SET status = 'expired' WHERE trip_id = %d AND status IN ('pending', 'accepted')",
        $trip_id
    ) );

    // Update payments
    $wpdb->query( $wpdb->prepare(
        "UPDATE `{$wpdb->prefix}sd_payments` SET status = 'failed' WHERE trip_id = %d AND status IN ('pending', 'authorized')",
        $trip_id
    ) );
    $wpdb->update(
        $wpdb->prefix . 'sd_trips',
        [ 'payment_status' => 'failed' ],
        [ 'id' => $trip_id ],
        [ '%s' ],
        [ '%d' ]
    );


    idibia_log_event( $trip_id, 'trip_force_cancelled_by_admin', [ 'reason' => $reason ] );

    // Broadcast trip change
    if ( function_exists('idibia_pusher_broadcast_trip') ) {
        idibia_pusher_broadcast_trip( $trip_id, 'trip_cancelled' );
    }

    // Add Audit Log
    idibia_admin_audit_log( 'force_cancel_trip', 'trip', $trip_id, [ 'reason' => $reason ] );

    idibia_transaction_commit();
    wp_send_json_success( [ 'message' => 'Trip successfully cancelled.' ] );
}

function idibia_admin_get_trip_pod(): void {
    global $wpdb;
    $trip_id = absint( $_GET['trip_id'] ?? 0 );
    if ( ! $trip_id ) {
        wp_send_json_error( [ 'message' => 'trip_id required.' ], 400 );
    }
    $pod = $wpdb->get_var( $wpdb->prepare( "SELECT proof_of_delivery_path FROM `{$wpdb->prefix}sd_trips` WHERE id = %d LIMIT 1", $trip_id ) );
    if ( ! $pod ) {
        wp_send_json_error( [ 'message' => 'No proof of delivery available for this trip.' ], 404 );
    }
    wp_send_json_success( [ 'proof_url' => $pod ] );
}

/**
 * Manually corrects a trip's status with a mandatory reason, logs the event, and notifies both parties.
 */
function idibia_admin_correct_trip_status(): void {
    global $wpdb, $admin_id;

    $trip_id      = absint( $_POST['trip_id'] ?? 0 );
    $new_status   = sanitize_key( $_POST['new_status'] ?? '' );
    $new_dispatch = sanitize_key( $_POST['new_dispatch_status'] ?? '' );
    $reason       = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );

    if ( ! $trip_id || ! trim( $reason ) ) {
        wp_send_json_error( [ 'message' => 'trip_id and reason are required.' ] );
    }

    $valid_statuses = [ 'pending', 'searching', 'in_progress', 'completed', 'cancelled' ];
    $valid_dispatch = [ 'searching', 'offered', 'accepted', 'arriving', 'arrived_pickup', 'picked_up', 'arrived_dropoff', 'completed', 'cancelled', 'no_driver' ];

    if ( $new_status && ! in_array( $new_status, $valid_statuses, true ) ) {
        wp_send_json_error( [ 'message' => 'Invalid new_status value.' ] );
    }
    if ( $new_dispatch && ! in_array( $new_dispatch, $valid_dispatch, true ) ) {
        wp_send_json_error( [ 'message' => 'Invalid new_dispatch_status value.' ] );
    }
    if ( ! $new_status && ! $new_dispatch ) {
        wp_send_json_error( [ 'message' => 'Provide new_status or new_dispatch_status (or both).' ] );
    }

    $trip = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, status, dispatch_status FROM `{$wpdb->prefix}sd_trips` WHERE id = %d LIMIT 1",
        $trip_id
    ), ARRAY_A );

    if ( ! $trip ) {
        wp_send_json_error( [ 'message' => 'Trip not found.' ] );
    }

    // Whitelist: which trip statuses can be changed to which targets
    $allowed_transitions = [
        'pending'     => [ 'searching', 'in_progress', 'cancelled' ],
        'searching'   => [ 'pending', 'in_progress', 'cancelled' ],
        'in_progress' => [ 'completed', 'cancelled', 'searching' ],
        'completed'   => [], // Terminal — admins may not change a legitimately completed trip
        'cancelled'   => [ 'pending', 'searching' ], // Allow re-opening if cancelled by mistake
    ];

    if ( $new_status && $new_status !== $trip['status'] ) {
        $from = $trip['status'];
        $permitted = $allowed_transitions[ $from ] ?? [];
        if ( ! in_array( $new_status, $permitted, true ) ) {
            wp_send_json_error( [ 'message' => "Cannot move trip from '{$from}' to '{$new_status}'. This transition is not allowed." ] );
        }
    }

    $old_status   = $trip['status'];
    $old_dispatch = $trip['dispatch_status'];

    idibia_transaction_start();

    $update_data   = [];
    $update_format = [];
    if ( $new_status ) {
        $update_data['status'] = $new_status;
        $update_format[]       = '%s';
    }
    if ( $new_dispatch ) {
        $update_data['dispatch_status'] = $new_dispatch;
        $update_format[]                = '%s';
    }

    $updated = $wpdb->update(
        $wpdb->prefix . 'sd_trips',
        $update_data,
        [ 'id' => $trip_id ],
        $update_format,
        [ '%d' ]
    );

    if ( false === $updated ) {
        idibia_transaction_rollback();
        wp_send_json_error( [ 'message' => 'Database update failed.' ] );
    }

    idibia_log_event( $trip_id, 'admin_status_correction', [
        'old_status'          => $old_status,
        'new_status'          => $new_status ?: $old_status,
        'old_dispatch_status' => $old_dispatch,
        'new_dispatch_status' => $new_dispatch ?: $old_dispatch,
        'admin_id'            => (int) $admin_id,
        'reason'              => $reason,
    ] );

    idibia_admin_audit_log( 'correct_trip_status', 'trip', $trip_id, [
        'old_status'          => $old_status,
        'new_status'          => $new_status ?: $old_status,
        'old_dispatch_status' => $old_dispatch,
        'new_dispatch_status' => $new_dispatch ?: $old_dispatch,
        'reason'              => $reason,
    ] );

    if ( function_exists( 'idibia_pusher_broadcast_trip' ) ) {
        idibia_pusher_broadcast_trip( $trip_id, 'admin_status_correction', [ 'reason' => $reason ] );
    }

    idibia_transaction_commit();
    wp_send_json_success( [ 'message' => 'Trip status corrected successfully.' ] );
}
