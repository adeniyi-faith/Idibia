<?php
/** Idibia — Admin API: Broadcasts & Notifications */

function idibia_admin_send_broadcast(): void {
    global $wpdb, $admin_id;

    $raw  = file_get_contents( 'php://input' );
    $body = json_decode( $raw, true ) ?: [];

    $title        = wp_strip_all_tags( $body['title']        ?? $_POST['title']        ?? '' );
    $msg          = wp_strip_all_tags( $body['body']         ?? $_POST['body']         ?? '' );
    $target_type  = sanitize_key(      $body['target_type']  ?? $_POST['target_type']  ?? '' );
    $target_value = sanitize_text_field( $body['target_value'] ?? $_POST['target_value'] ?? '' );
    $send_email   = ! empty( $body['send_email'] ?? $_POST['send_email'] ?? false );
    $scheduled_at = sanitize_text_field( $body['scheduled_at'] ?? $_POST['scheduled_at'] ?? '' );

    $valid_targets = [ 'all_both', 'all_drivers', 'all_customers', 'online_drivers', 'vehicle_type', 'specific_ids' ];
    if ( ! $title || ! $msg || ! in_array( $target_type, $valid_targets, true ) ) {
        wp_send_json_error( [ 'message' => 'title, body and target_type are required.' ] );
    }

    // Resolve recipients
    $recipients = [];

    switch ( $target_type ) {
        case 'all_both':
            $crows = $wpdb->get_results( "SELECT id FROM `{$wpdb->prefix}sd_customers` WHERE status = 'active'", ARRAY_A );
            foreach ( $crows as $r ) $recipients[] = [ 'id' => (int) $r['id'], 'type' => 'customer' ];
            $drows = $wpdb->get_results( "SELECT id FROM `{$wpdb->prefix}sd_drivers` WHERE status = 'active'", ARRAY_A );
            foreach ( $drows as $r ) $recipients[] = [ 'id' => (int) $r['id'], 'type' => 'driver' ];
            break;

        case 'all_customers':
            $rows = $wpdb->get_results( "SELECT id FROM `{$wpdb->prefix}sd_customers` WHERE status = 'active'", ARRAY_A );
            foreach ( $rows as $r ) $recipients[] = [ 'id' => (int) $r['id'], 'type' => 'customer' ];
            break;

        case 'all_drivers':
            $rows = $wpdb->get_results( "SELECT id FROM `{$wpdb->prefix}sd_drivers` WHERE status = 'active'", ARRAY_A );
            foreach ( $rows as $r ) $recipients[] = [ 'id' => (int) $r['id'], 'type' => 'driver' ];
            break;

        case 'online_drivers':
            $rows = $wpdb->get_results( "SELECT id FROM `{$wpdb->prefix}sd_drivers` WHERE status = 'active' AND is_online = 1", ARRAY_A );
            foreach ( $rows as $r ) $recipients[] = [ 'id' => (int) $r['id'], 'type' => 'driver' ];
            break;

        case 'vehicle_type':
            $vtype = sanitize_key( $target_value );
            if ( ! in_array( $vtype, [ 'bike', 'car', 'van', 'keke' ], true ) ) {
                wp_send_json_error( [ 'message' => 'Invalid vehicle_type.' ] );
            }
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT id FROM `{$wpdb->prefix}sd_drivers` WHERE status = 'active' AND vehicle_type = %s",
                $vtype
            ), ARRAY_A );
            foreach ( $rows as $r ) $recipients[] = [ 'id' => (int) $r['id'], 'type' => 'driver' ];
            break;

        case 'specific_ids':
            $parts = array_filter( array_map( 'intval', explode( ',', $target_value ) ) );
            foreach ( $parts as $uid ) {
                $recipients[] = [ 'id' => $uid, 'type' => 'customer' ];
            }
            break;
    }

    if ( empty( $recipients ) ) {
        wp_send_json_error( [ 'message' => 'No recipients matched the selected audience.' ] );
    }

    $sent_at    = $scheduled_at ? null : gmdate( 'Y-m-d H:i:s' );
    $sched_val  = $scheduled_at ? gmdate( 'Y-m-d H:i:s', strtotime( $scheduled_at ) ) : null;

    $wpdb->insert(
        $wpdb->prefix . 'sd_broadcasts',
        [
            'title'           => $title,
            'body'            => $msg,
            'target_type'     => $target_type,
            'target_value'    => $target_value ?: null,
            'send_email'      => $send_email ? 1 : 0,
            'recipient_count' => count( $recipients ),
            'scheduled_at'    => $sched_val,
            'sent_at'         => $sent_at,
            'created_by'      => $admin_id,
            'created_at'      => gmdate( 'Y-m-d H:i:s' ),
        ],
        [ '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s' ]
    );
    $broadcast_id = (int) $wpdb->insert_id;
    idibia_admin_audit_log( 'send_broadcast', 'broadcast', $broadcast_id, [], null, [
        'title'           => $title,
        'target_type'     => $target_type,
        'target_value'    => $target_value ?: null,
        'recipient_count' => count( $recipients ),
        'send_email'      => $send_email,
        'scheduled'       => ! empty( $sched_val ),
    ] );

    if ( $sent_at ) {
        foreach ( $recipients as $r ) {
            idibia_notify_user( $r['id'], $r['type'], $title, $msg );

            if ( $send_email ) {
                $table  = $r['type'] === 'customer' ? $wpdb->prefix . 'sd_customers' : $wpdb->prefix . 'sd_drivers';
                $person = $wpdb->get_row( $wpdb->prepare( "SELECT email, full_name FROM `$table` WHERE id = %d", $r['id'] ) );
                if ( $person && $person->email ) {
                    idibia_send_mail(
                        $person->email,
                        $title,
                        "Hi {$person->full_name},\n\n{$msg}\n\nThe Idibia Team",
                        [ 'Content-Type: text/plain; charset=UTF-8' ]
                    );
                }
            }
        }
    }

    wp_send_json_success( [
        'message'         => $sent_at ? 'Broadcast sent.' : 'Broadcast scheduled.',
        'recipient_count' => count( $recipients ),
    ] );
}

function idibia_admin_get_broadcasts(): void {
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT b.*, a.full_name AS sent_by_name
         FROM `{$wpdb->prefix}sd_broadcasts` b
         LEFT JOIN `{$wpdb->prefix}sd_admin_users` a ON a.id = b.created_by
         ORDER BY b.created_at DESC
         LIMIT 100",
        ARRAY_A
    ) ?: [];

    foreach ( $rows as &$r ) {
        $r['id']              = (int) $r['id'];
        $r['send_email']      = (bool) $r['send_email'];
        $r['recipient_count'] = (int) $r['recipient_count'];
    }
    unset( $r );

    wp_send_json_success( [ 'broadcasts' => $rows ] );
}

function idibia_admin_get_notifications(): void {
    global $wpdb, $admin_id;
    if ( ! $admin_id ) { wp_send_json_error( [ 'message' => 'Unauthorized.' ], 401 ); }

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, title, body, is_read, created_at
         FROM `{$wpdb->prefix}sd_notifications`
         WHERE user_id = %d AND user_type = 'admin'
         ORDER BY created_at DESC LIMIT 30",
        $admin_id
    ), ARRAY_A ) ?: [];

    $unread_count = 0;
    foreach ( $rows as &$row ) {
        $row['id']      = (int) $row['id'];
        $row['is_read'] = (bool) $row['is_read'];
        if ( ! $row['is_read'] ) $unread_count++;
    }
    unset( $row );

    wp_send_json_success( [ 'notifications' => $rows, 'unread_count' => $unread_count ] );
}

function idibia_admin_mark_notifications_read(): void {
    global $wpdb, $admin_id;
    if ( ! $admin_id ) { wp_send_json_error( [ 'message' => 'Unauthorized.' ], 401 ); }

    $wpdb->update(
        $wpdb->prefix . 'sd_notifications',
        [ 'is_read' => 1 ],
        [ 'user_id' => $admin_id, 'user_type' => 'admin', 'is_read' => 0 ],
        [ '%d' ],
        [ '%d', '%s', '%d' ]
    );

    wp_send_json_success( [ 'message' => 'All notifications marked as read.' ] );
}

function idibia_admin_test_smtp_email(): void {
    $to = sanitize_email( $_POST['to'] ?? '' );
    if ( ! $to ) {
        wp_send_json_error( [ 'message' => 'Provide a valid recipient email address.' ] );
    }
    $sent = idibia_send_mail(
        $to,
        '[Idibia] SMTP Test Email',
        "Hello,\n\nThis is a test email from your Idibia platform.\nIf you received this, your SMTP settings are working correctly.\n\nThe Idibia Team",
        [ 'Content-Type: text/plain; charset=UTF-8' ]
    );
    if ( $sent ) {
        wp_send_json_success( [ 'message' => "Test email sent to $to." ] );
    } else {
        wp_send_json_error( [ 'message' => "Failed to send. Check your SMTP settings." ] );
    }
}
