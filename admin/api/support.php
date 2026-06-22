<?php
/** Idibia — Admin API: Support Tickets */

// ─── SUPPORT TICKET FUNCTIONS ────────────────────────────────────────────────

function idibia_admin_get_support_tickets(): void {
    global $wpdb, $admin_id;
    idibia_admin_ensure_ticket_columns();
    [ $page, $per_page, $offset ] = idibia_page_args();
    $where = [ '1=1' ]; $args = [];
    $status = sanitize_key( $_GET['status'] ?? '' );
    $filter = sanitize_key( $_GET['filter'] ?? 'all' );
    $search = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) );
    if ( $status && $status !== 'all' ) { $where[] = 't.status = %s'; $args[] = $status; }
    if ( $filter === 'unassigned' ) { $where[] = 't.assigned_to IS NULL'; }
    elseif ( $filter === 'mine' ) { $where[] = 't.assigned_to = %d'; $args[] = $admin_id; }
    elseif ( $filter === 'escalated' ) { $where[] = "t.status = 'escalated'"; }
    elseif ( $filter === 'resolved' ) { $where[] = "t.status IN ('resolved','closed')"; }
    if ( $search ) { $like = '%' . $wpdb->esc_like( $search ) . '%'; $where[] = '(t.category LIKE %s OR c.full_name LIKE %s OR d.full_name LIKE %s)'; array_push( $args, $like, $like, $like ); }
    $sql_where = implode( ' AND ', $where );
    $total = (int) $wpdb->get_var( idibia_sql( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_support_tickets` t LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = t.creator_id AND t.creator_type = 'customer' LEFT JOIN `{$wpdb->prefix}sd_drivers` d ON d.id = t.creator_id AND t.creator_type = 'driver' WHERE $sql_where", $args ) );
    $rows = $wpdb->get_results( idibia_sql( "SELECT t.*, COALESCE(c.full_name, d.full_name) AS creator_name, a.full_name AS assignee_name, (SELECT message FROM `{$wpdb->prefix}sd_support_messages` sm WHERE sm.ticket_id = t.id ORDER BY sm.created_at DESC LIMIT 1) AS last_message FROM `{$wpdb->prefix}sd_support_tickets` t LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = t.creator_id AND t.creator_type = 'customer' LEFT JOIN `{$wpdb->prefix}sd_drivers` d ON d.id = t.creator_id AND t.creator_type = 'driver' LEFT JOIN `{$wpdb->prefix}sd_admin_users` a ON a.id = t.assigned_to WHERE $sql_where ORDER BY t.updated_at DESC, t.created_at DESC LIMIT %d OFFSET %d", array_merge( $args, [ $per_page, $offset ] ) ), ARRAY_A );
    $open_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_support_tickets` WHERE status IN ('open','in_progress','escalated')" );
    wp_send_json_success( [ 'tickets' => $rows ?: [], 'page' => $page, 'per_page' => $per_page, 'total' => $total, 'open_count' => $open_count ] );
}

function idibia_admin_ensure_ticket_columns(): void {
    global $wpdb;
    $table = $wpdb->prefix . 'sd_support_tickets';
    $cols = $wpdb->get_col( "SHOW COLUMNS FROM `$table`" );
    if ( ! in_array( 'assigned_to', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE `$table` ADD COLUMN `assigned_to` BIGINT UNSIGNED NULL DEFAULT NULL" );
    }
    if ( ! in_array( 'priority', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE `$table` ADD COLUMN `priority` VARCHAR(20) NOT NULL DEFAULT 'medium'" );
    }
}

function idibia_admin_get_ticket_messages(): void {
    global $wpdb;
    idibia_admin_ensure_ticket_columns();
    $ticket_id = absint( $_GET['ticket_id'] ?? 0 );
    if ( ! $ticket_id ) {
        wp_send_json_error( [ 'message' => 'ticket_id required.' ], 400 );
    }
    $ticket = $wpdb->get_row(
        $wpdb->prepare( "SELECT t.*, c.full_name AS customer_name, d.full_name AS driver_name, a.full_name AS assignee_name FROM `{$wpdb->prefix}sd_support_tickets` t LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = t.creator_id AND t.creator_type = 'customer' LEFT JOIN `{$wpdb->prefix}sd_drivers` d ON d.id = t.creator_id AND t.creator_type = 'driver' LEFT JOIN `{$wpdb->prefix}sd_admin_users` a ON a.id = t.assigned_to WHERE t.id = %d LIMIT 1", $ticket_id ),
        ARRAY_A
    );
    if ( ! $ticket ) {
        wp_send_json_error( [ 'message' => 'Ticket not found.' ], 404 );
    }
    $messages = $wpdb->get_results(
        $wpdb->prepare( "SELECT m.*, CASE WHEN m.sender_type='admin' THEN a.full_name WHEN m.sender_type='customer' THEN c.full_name WHEN m.sender_type='driver' THEN d.full_name ELSE 'Unknown' END AS sender_name FROM `{$wpdb->prefix}sd_support_messages` m LEFT JOIN `{$wpdb->prefix}sd_admin_users` a ON a.id = m.sender_id AND m.sender_type = 'admin' LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = m.sender_id AND m.sender_type = 'customer' LEFT JOIN `{$wpdb->prefix}sd_drivers` d ON d.id = m.sender_id AND m.sender_type = 'driver' WHERE m.ticket_id = %d ORDER BY m.created_at ASC", $ticket_id ),
        ARRAY_A
    );
    $admins = $wpdb->get_results( "SELECT id, full_name FROM `{$wpdb->prefix}sd_admin_users` WHERE status = 'active' ORDER BY full_name", ARRAY_A );
    wp_send_json_success( [ 'ticket' => $ticket, 'messages' => $messages ?: [], 'admins' => $admins ?: [] ] );
}

function idibia_admin_reply_ticket(): void {
    global $wpdb, $admin_id;
    idibia_admin_ensure_ticket_columns();
    $ticket_id = absint( $_POST['ticket_id'] ?? 0 );
    $message   = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
    if ( ! $ticket_id || ! $message ) {
        wp_send_json_error( [ 'message' => 'ticket_id and message are required.' ], 400 );
    }
    $ticket = $wpdb->get_row( $wpdb->prepare( "SELECT id, creator_id, creator_type FROM `{$wpdb->prefix}sd_support_tickets` WHERE id = %d LIMIT 1", $ticket_id ), ARRAY_A );
    if ( ! $ticket ) {
        wp_send_json_error( [ 'message' => 'Ticket not found.' ], 404 );
    }
    $inserted = $wpdb->insert(
        $wpdb->prefix . 'sd_support_messages',
        [ 'ticket_id' => $ticket_id, 'sender_id' => $admin_id, 'sender_type' => 'admin', 'message' => $message, 'created_at' => gmdate( 'Y-m-d H:i:s' ) ],
        [ '%d', '%d', '%s', '%s', '%s' ]
    );
    if ( ! $inserted ) {
        wp_send_json_error( [ 'message' => 'Failed to save reply.' ], 500 );
    }
    $wpdb->update( $wpdb->prefix . 'sd_support_tickets', [ 'updated_at' => gmdate( 'Y-m-d H:i:s' ), 'status' => 'in_progress' ], [ 'id' => $ticket_id ], [ '%s', '%s' ], [ '%d' ] );
    // Notify the ticket creator
    $admin_row = $wpdb->get_row( $wpdb->prepare( "SELECT full_name FROM `{$wpdb->prefix}sd_admin_users` WHERE id = %d LIMIT 1", $admin_id ), ARRAY_A );
    $admin_name = $admin_row['full_name'] ?? 'Support';
    if ( function_exists( 'idibia_notify_user' ) ) {
        idibia_notify_user( (int) $ticket['creator_id'], $ticket['creator_type'], 'Support Reply', "Admin {$admin_name} replied to your support ticket #" . str_pad( $ticket_id, 4, '0', STR_PAD_LEFT ) . '.' );
    }
    idibia_admin_audit_log( 'admin_reply_ticket', 'support_ticket', $ticket_id, [ 'message_length' => strlen( $message ) ] );
    wp_send_json_success( [ 'message' => 'Reply sent.' ] );
}

function idibia_admin_assign_ticket(): void {
    global $wpdb;
    idibia_admin_ensure_ticket_columns();
    $ticket_id   = absint( $_POST['ticket_id'] ?? 0 );
    $assigned_to = absint( $_POST['assigned_to'] ?? 0 );
    if ( ! $ticket_id ) {
        wp_send_json_error( [ 'message' => 'ticket_id required.' ], 400 );
    }
    if ( $assigned_to ) {
        $wpdb->update( $wpdb->prefix . 'sd_support_tickets', [ 'assigned_to' => $assigned_to, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ], [ 'id' => $ticket_id ], [ '%d', '%s' ], [ '%d' ] );
    } else {
        $wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->prefix}sd_support_tickets` SET assigned_to = NULL, updated_at = %s WHERE id = %d", gmdate( 'Y-m-d H:i:s' ), $ticket_id ) );
    }
    idibia_admin_audit_log( 'assign_ticket', 'support_ticket', $ticket_id, [ 'assigned_to' => $assigned_to ] );
    wp_send_json_success( [ 'message' => 'Ticket assigned.' ] );
}

function idibia_admin_update_ticket_status(): void {
    global $wpdb;
    $ticket_id = absint( $_POST['ticket_id'] ?? 0 );
    $status    = sanitize_key( $_POST['status'] ?? '' );
    $allowed   = [ 'open', 'in_progress', 'resolved', 'closed' ];
    if ( ! $ticket_id || ! in_array( $status, $allowed, true ) ) {
        wp_send_json_error( [ 'message' => 'Invalid ticket_id or status.' ], 400 );
    }
    $wpdb->update( $wpdb->prefix . 'sd_support_tickets', [ 'status' => $status, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ], [ 'id' => $ticket_id ], [ '%s', '%s' ], [ '%d' ] );
    idibia_admin_audit_log( 'update_ticket_status', 'support_ticket', $ticket_id, [ 'status' => $status ] );
    wp_send_json_success( [ 'message' => 'Status updated.' ] );
}

function idibia_admin_set_ticket_priority(): void {
    global $wpdb;
    idibia_admin_ensure_ticket_columns();
    $ticket_id = absint( $_POST['ticket_id'] ?? 0 );
    $priority  = sanitize_key( $_POST['priority'] ?? '' );
    $allowed   = [ 'low', 'medium', 'high', 'urgent' ];
    if ( ! $ticket_id || ! in_array( $priority, $allowed, true ) ) {
        wp_send_json_error( [ 'message' => 'Invalid ticket_id or priority.' ], 400 );
    }
    $wpdb->update( $wpdb->prefix . 'sd_support_tickets', [ 'priority' => $priority, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ], [ 'id' => $ticket_id ], [ '%s', '%s' ], [ '%d' ] );
    idibia_admin_audit_log( 'set_ticket_priority', 'support_ticket', $ticket_id, [ 'priority' => $priority ] );
    wp_send_json_success( [ 'message' => 'Priority updated.' ] );
}
