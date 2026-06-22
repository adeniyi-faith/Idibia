<?php
/** Idibia — Admin API: Disputes */

function idibia_admin_paginated_disputes(): void {
    global $wpdb;
    [ $page, $per_page, $offset ] = idibia_page_args();
    $where = [ '1=1' ]; $args = [];
    $status = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );
    $search = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) );
    if ( $status && $status !== 'all' ) { $where[] = 'di.status = %s'; $args[] = $status; }
    if ( $search ) { $like = '%' . $wpdb->esc_like( $search ) . '%'; $where[] = '(di.category LIKE %s OR di.description LIKE %s OR t.trip_ref LIKE %s OR c.full_name LIKE %s OR d.full_name LIKE %s)'; array_push( $args, $like, $like, $like, $like, $like ); }
    $sql_where = implode( ' AND ', $where );
    $total = (int) $wpdb->get_var( idibia_sql( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_disputes` di LEFT JOIN `{$wpdb->prefix}sd_trips` t ON t.id = di.trip_id LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = di.customer_id LEFT JOIN `{$wpdb->prefix}sd_drivers` d ON d.id = di.driver_id WHERE $sql_where", $args ) );
    $rows = $wpdb->get_results( idibia_sql( "SELECT di.*, t.trip_ref, c.full_name AS customer_name, d.full_name AS driver_name FROM `{$wpdb->prefix}sd_disputes` di LEFT JOIN `{$wpdb->prefix}sd_trips` t ON t.id = di.trip_id LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = di.customer_id LEFT JOIN `{$wpdb->prefix}sd_drivers` d ON d.id = di.driver_id WHERE $sql_where ORDER BY di.created_at DESC LIMIT %d OFFSET %d", array_merge( $args, [ $per_page, $offset ] ) ), ARRAY_A );
    $open_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_disputes` WHERE status IN ('open','escalated')" );
    $escalated_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_disputes` WHERE status = 'escalated'" );
    wp_send_json_success( [ 'disputes' => $rows ?: [], 'page' => $page, 'per_page' => $per_page, 'total' => $total, 'open_count' => $open_count, 'escalated_count' => $escalated_count ] );
}

function idibia_admin_resolve_dispute(): void {
    global $wpdb;
    $dispute_id = absint( $_POST['dispute_id'] ?? 0 );

    $current_status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM `{$wpdb->prefix}sd_disputes` WHERE id = %d LIMIT 1", $dispute_id ) );
    if ( $current_status === 'resolved' ) {
        wp_send_json_error( [ 'message' => 'This dispute has already been resolved.' ] );
    }

    $resolution = sanitize_text_field( wp_unslash( $_POST['resolution_action'] ?? '' ) );
    $refund = (float) ( $_POST['refund_amount'] ?? 0 );
    $notes = sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ?? '' ) );
    $updated = $wpdb->update( $wpdb->prefix . 'sd_disputes', [ 'status' => 'resolved', 'resolution' => $resolution, 'refund_amount' => $refund, 'admin_notes' => $notes, 'resolved_at' => gmdate( 'Y-m-d H:i:s' ) ], [ 'id' => $dispute_id ], [ '%s', '%s', '%f', '%s', '%s' ], [ '%d' ] );
    if ( false === $updated ) wp_send_json_error( [ 'message' => 'Could not resolve dispute.' ] );
    idibia_admin_audit_log( 'resolve_dispute', 'dispute', $dispute_id, [ 'resolution' => $resolution, 'refund_amount' => $refund, 'admin_notes' => $notes ] );
    if ( $refund > 0 ) idibia_admin_audit_log( 'refund', 'dispute', $dispute_id, [ 'refund_amount' => $refund, 'resolution' => $resolution ] );
    wp_send_json_success( [ 'message' => 'Dispute resolved.' ] );
}
