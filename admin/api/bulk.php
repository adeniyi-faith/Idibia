<?php
/** Idibia — Admin API: Bulk Actions */

/**
 * Applies a single action (suspend, reinstate, send_notification, export) to multiple drivers or customers at once.
 */
function idibia_admin_bulk_action(): void {
    global $wpdb;

    $entity_type = sanitize_key( $_POST['entity_type'] ?? '' );
    $action      = sanitize_key( $_POST['action'] ?? '' );
    $reason      = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
    $message     = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );

    // entity_ids and action_params arrive as JSON strings from the JS FormData
    $entity_ids_raw  = sanitize_text_field( wp_unslash( $_POST['entity_ids'] ?? '[]' ) );
    $entity_ids      = json_decode( $entity_ids_raw, true );
    if ( ! is_array( $entity_ids ) ) { $entity_ids = []; }

    $params_raw = sanitize_text_field( wp_unslash( $_POST['action_params'] ?? '{}' ) );
    $params     = json_decode( $params_raw, true );
    if ( is_array( $params ) ) {
        if ( isset( $params['reason'] ) )  { $reason  = sanitize_textarea_field( $params['reason'] ); }
        if ( isset( $params['message'] ) ) { $message = sanitize_textarea_field( $params['message'] ); }
    }

    if ( ! in_array( $entity_type, [ 'driver', 'customer' ], true ) ) {
        wp_send_json_error( [ 'message' => 'entity_type must be "driver" or "customer".' ] );
    }
    if ( ! in_array( $action, [ 'suspend', 'reinstate', 'send_notification', 'export' ], true ) ) {
        wp_send_json_error( [ 'message' => 'Invalid action. Allowed: suspend, reinstate, send_notification, export.' ] );
    }

    $entity_ids = array_values( array_filter( array_map( 'absint', $entity_ids ) ) );
    if ( empty( $entity_ids ) ) {
        wp_send_json_error( [ 'message' => 'entity_ids must be a non-empty array of IDs.' ] );
    }
    if ( count( $entity_ids ) > 200 ) {
        wp_send_json_error( [ 'message' => 'Maximum 200 entities per bulk operation.' ] );
    }

    // Permission gating mirrors the individual action permissions
    if ( in_array( $action, [ 'suspend', 'reinstate' ], true ) ) {
        $perm = $entity_type === 'driver' ? 'suspend_reinstate_driver' : 'view_customers';
        if ( ! idibia_admin_has_permission( $perm ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
    } else {
        $perm = $entity_type === 'driver' ? 'view_drivers' : 'view_customers';
        if ( ! idibia_admin_has_permission( $perm ) ) { wp_send_json_error( [ 'message' => 'Denied.' ], 403 ); }
    }

    $bulk_id   = uniqid( 'bulk_', true );
    $db_table  = $wpdb->prefix . ( $entity_type === 'driver' ? 'sd_drivers' : 'sd_customers' );
    $placeholders = implode( ',', array_fill( 0, count( $entity_ids ), '%d' ) );

    // Export: just return the rows as data — no DB mutation
    if ( $action === 'export' ) {
        if ( $entity_type === 'driver' ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, full_name, email, phone, status, kyc_status, vehicle_type, total_trips, created_at FROM `$db_table` WHERE id IN ($placeholders) ORDER BY id",
                ...$entity_ids
            ), ARRAY_A ) ?: [];
        } else {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, full_name, email, phone, status, email_verified, created_at FROM `$db_table` WHERE id IN ($placeholders) ORDER BY id",
                ...$entity_ids
            ), ARRAY_A ) ?: [];
        }
        idibia_admin_audit_log( "bulk_export_{$entity_type}", $entity_type, 0, [ 'bulk_operation_id' => $bulk_id, 'entity_ids' => $entity_ids ] );
        wp_send_json_success( [ 'bulk_operation_id' => $bulk_id, 'action' => 'export', 'entity_type' => $entity_type, 'export_data' => $rows, 'count' => count( $rows ) ] );
    }

    $success_ids = [];
    $failed_ids  = [];

    foreach ( $entity_ids as $eid ) {
        $ok = false;
        if ( $action === 'suspend' ) {
            $data = $entity_type === 'driver' ? [ 'status' => 'suspended', 'is_online' => 0 ] : [ 'status' => 'suspended' ];
            $fmt  = $entity_type === 'driver' ? [ '%s', '%d' ] : [ '%s' ];
            $ok   = false !== $wpdb->update( $db_table, $data, [ 'id' => $eid ], $fmt, [ '%d' ] );
        } elseif ( $action === 'reinstate' ) {
            $ok = false !== $wpdb->update( $db_table, [ 'status' => 'active' ], [ 'id' => $eid ], [ '%s' ], [ '%d' ] );
        } elseif ( $action === 'send_notification' ) {
            if ( $message ) {
                idibia_notify_user( $eid, $entity_type, 'Admin Notification', $message );
                $ok = true;
            }
        }

        if ( $ok ) {
            idibia_admin_audit_log( "bulk_{$action}_{$entity_type}", $entity_type, $eid, [
                'bulk_operation_id' => $bulk_id,
                'reason'            => $reason,
                'message'           => $message,
            ] );
            $success_ids[] = $eid;
        } else {
            $failed_ids[] = $eid;
        }
    }

    wp_send_json_success( [
        'bulk_operation_id' => $bulk_id,
        'action'            => $action,
        'entity_type'       => $entity_type,
        'success_count'     => count( $success_ids ),
        'failed_count'      => count( $failed_ids ),
        'failed_ids'        => $failed_ids,
        'message'           => count( $success_ids ) . ' of ' . count( $entity_ids ) . " {$entity_type}(s) processed successfully.",
    ] );
}
