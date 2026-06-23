<?php
/** Idibia — Admin API: Driver Campaigns */

function idibia_admin_get_campaigns(): void {
    global $wpdb;

    $status   = sanitize_key( $_GET['status'] ?? 'all' );
    $page     = max( 1, (int) ( $_GET['page'] ?? 1 ) );
    $per_page = max( 1, min( 100, (int) ( $_GET['per_page'] ?? 20 ) ) );
    $offset   = ( $page - 1 ) * $per_page;

    $where = '';
    if ( $status !== 'all' && in_array( $status, [ 'active', 'inactive', 'completed' ], true ) ) {
        $where = $wpdb->prepare( 'WHERE c.status = %s', $status );
    }

    $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_campaigns` c $where" );

    $rows = $wpdb->get_results( "
        SELECT c.*,
               COUNT(DISTINCT t.driver_id) AS enrolled_drivers,
               COUNT(DISTINCT p.driver_id) AS completed_drivers,
               COALESCE(SUM(p.bonus_paid), 0) AS total_bonus_paid
        FROM `{$wpdb->prefix}sd_campaigns` c
        LEFT JOIN `{$wpdb->prefix}sd_trips` t
            ON t.driver_id IS NOT NULL
            AND t.status = 'completed'
            AND t.completed_at BETWEEN c.start_time AND c.end_time
        LEFT JOIN `{$wpdb->prefix}sd_campaign_payouts` p ON p.campaign_id = c.id
        $where
        GROUP BY c.id
        ORDER BY c.created_at DESC
        LIMIT %d OFFSET %d
    ", $per_page, $offset ) ?? [];

    wp_send_json_success( [ 'campaigns' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $per_page ] );
}

function idibia_admin_get_campaign(): void {
    global $wpdb;

    $id = absint( $_GET['campaign_id'] ?? 0 );
    if ( ! $id ) wp_send_json_error( [ 'message' => 'Invalid campaign ID.' ] );

    $campaign = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM `{$wpdb->prefix}sd_campaigns` WHERE id = %d", $id
    ), ARRAY_A );

    if ( ! $campaign ) wp_send_json_error( [ 'message' => 'Campaign not found.' ] );

    wp_send_json_success( [ 'campaign' => $campaign ] );
}

function idibia_admin_create_campaign(): void {
    global $wpdb;

    $raw  = file_get_contents( 'php://input' );
    $data = json_decode( $raw, true ) ?: [];

    $title         = sanitize_text_field( $data['title'] ?? '' );
    $description   = sanitize_textarea_field( $data['description'] ?? '' );
    $target_trips  = absint( $data['target_trips'] ?? 0 );
    $bonus_amount  = (float) ( $data['bonus_amount'] ?? 0 );
    $start_time    = sanitize_text_field( $data['start_time'] ?? '' );
    $end_time      = sanitize_text_field( $data['end_time'] ?? '' );
    $vehicle_types = $data['eligible_vehicle_types'] ?? null;

    if ( ! $title || ! $target_trips || ! $bonus_amount || ! $start_time || ! $end_time ) {
        wp_send_json_error( [ 'message' => 'Title, target trips, bonus amount, start time, and end time are required.' ] );
    }

    if ( strtotime( $end_time ) <= strtotime( $start_time ) ) {
        wp_send_json_error( [ 'message' => 'End time must be after start time.' ] );
    }

    $eligible_vehicle_types = null;
    if ( $vehicle_types && $vehicle_types !== 'all' ) {
        $valid_types = array_intersect( (array) $vehicle_types, [ 'bike', 'car', 'van', 'keke' ] );
        if ( ! empty( $valid_types ) ) {
            $eligible_vehicle_types = json_encode( array_values( $valid_types ) );
        }
    }

    $wpdb->insert(
        "{$wpdb->prefix}sd_campaigns",
        [
            'title'                  => $title,
            'description'            => $description,
            'target_trips'           => $target_trips,
            'bonus_amount'           => $bonus_amount,
            'start_time'             => gmdate( 'Y-m-d H:i:s', strtotime( $start_time ) ),
            'end_time'               => gmdate( 'Y-m-d H:i:s', strtotime( $end_time ) ),
            'status'                 => 'active',
            'eligible_vehicle_types' => $eligible_vehicle_types,
            'created_at'             => gmdate( 'Y-m-d H:i:s' ),
        ],
        [ '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s', '%s' ]
    );

    $new_id = (int) $wpdb->insert_id;

    idibia_admin_audit_log( 'create', 'campaign', $new_id, [ 'title' => $title ] );
    wp_send_json_success( [ 'message' => 'Campaign created.', 'id' => $new_id ] );
}

function idibia_admin_update_campaign(): void {
    global $wpdb;

    $raw  = file_get_contents( 'php://input' );
    $data = json_decode( $raw, true ) ?: [];

    $id = absint( $data['id'] ?? 0 );
    if ( ! $id ) wp_send_json_error( [ 'message' => 'Invalid campaign ID.' ] );

    $campaign = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM `{$wpdb->prefix}sd_campaigns` WHERE id = %d", $id
    ) );
    if ( ! $campaign ) wp_send_json_error( [ 'message' => 'Campaign not found.' ] );
    if ( $campaign->status === 'completed' ) wp_send_json_error( [ 'message' => 'Cannot edit a completed campaign.' ] );

    $update = [];
    $fmt    = [];

    if ( isset( $data['title'] ) )        { $update['title']        = sanitize_text_field( $data['title'] );        $fmt[] = '%s'; }
    if ( isset( $data['description'] ) )  { $update['description']  = sanitize_textarea_field( $data['description'] ); $fmt[] = '%s'; }
    if ( isset( $data['target_trips'] ) ) { $update['target_trips'] = absint( $data['target_trips'] );              $fmt[] = '%d'; }
    if ( isset( $data['bonus_amount'] ) ) { $update['bonus_amount'] = (float) $data['bonus_amount'];                $fmt[] = '%f'; }
    if ( isset( $data['start_time'] ) )   { $update['start_time']   = gmdate( 'Y-m-d H:i:s', strtotime( $data['start_time'] ) ); $fmt[] = '%s'; }
    if ( isset( $data['end_time'] ) )     { $update['end_time']     = gmdate( 'Y-m-d H:i:s', strtotime( $data['end_time'] ) );   $fmt[] = '%s'; }

    if ( isset( $data['eligible_vehicle_types'] ) ) {
        $vt = $data['eligible_vehicle_types'];
        if ( $vt === 'all' || empty( $vt ) ) {
            $update['eligible_vehicle_types'] = null;
            $fmt[] = '%s';
        } else {
            $valid = array_intersect( (array) $vt, [ 'bike', 'car', 'van', 'keke' ] );
            $update['eligible_vehicle_types'] = ! empty( $valid ) ? json_encode( array_values( $valid ) ) : null;
            $fmt[] = '%s';
        }
    }

    if ( empty( $update ) ) wp_send_json_error( [ 'message' => 'No fields to update.' ] );

    $wpdb->update( "{$wpdb->prefix}sd_campaigns", $update, [ 'id' => $id ], $fmt, [ '%d' ] );

    idibia_admin_audit_log( 'update', 'campaign', $id, $update );
    wp_send_json_success( [ 'message' => 'Campaign updated.' ] );
}

function idibia_admin_deactivate_campaign(): void {
    global $wpdb;

    $raw  = file_get_contents( 'php://input' );
    $data = json_decode( $raw, true ) ?: [];

    $id = absint( $data['campaign_id'] ?? $data['id'] ?? 0 );
    if ( ! $id ) wp_send_json_error( [ 'message' => 'Invalid campaign ID.' ] );

    $campaign = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM `{$wpdb->prefix}sd_campaigns` WHERE id = %d", $id
    ) );
    if ( ! $campaign ) wp_send_json_error( [ 'message' => 'Campaign not found.' ] );
    if ( $campaign->status !== 'active' ) wp_send_json_error( [ 'message' => 'Campaign is not active.' ] );

    $wpdb->update(
        "{$wpdb->prefix}sd_campaigns",
        [ 'status' => 'inactive' ],
        [ 'id' => $id ],
        [ '%s' ],
        [ '%d' ]
    );

    idibia_admin_audit_log( 'deactivate', 'campaign', $id );
    wp_send_json_success( [ 'message' => 'Campaign deactivated.' ] );
}

function idibia_admin_get_campaign_leaderboard(): void {
    global $wpdb;

    $id = absint( $_GET['campaign_id'] ?? 0 );
    if ( ! $id ) wp_send_json_error( [ 'message' => 'Invalid campaign ID.' ] );

    $campaign = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM `{$wpdb->prefix}sd_campaigns` WHERE id = %d", $id
    ), ARRAY_A );
    if ( ! $campaign ) wp_send_json_error( [ 'message' => 'Campaign not found.' ] );

    $target = (int) $campaign['target_trips'];

    // Eligible vehicle types filter
    $vehicle_filter = '';
    if ( ! empty( $campaign['eligible_vehicle_types'] ) ) {
        $types = json_decode( $campaign['eligible_vehicle_types'], true );
        if ( ! empty( $types ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
            $vehicle_filter = $wpdb->prepare( "AND d.vehicle_type IN ($placeholders)", ...$types );
        }
    }

    $leaderboard = $wpdb->get_results( $wpdb->prepare( "
        SELECT d.id AS driver_id,
               d.full_name,
               d.vehicle_type,
               COUNT(t.id) AS trips_completed,
               %d AS target_trips,
               CASE WHEN p.driver_id IS NOT NULL THEN 1 ELSE 0 END AS bonus_earned
        FROM `{$wpdb->prefix}sd_drivers` d
        JOIN `{$wpdb->prefix}sd_trips` t
            ON t.driver_id = d.id
            AND t.status = 'completed'
            AND t.completed_at BETWEEN %s AND %s
        LEFT JOIN `{$wpdb->prefix}sd_campaign_payouts` p
            ON p.campaign_id = %d AND p.driver_id = d.id
        WHERE 1=1 $vehicle_filter
        GROUP BY d.id
        ORDER BY trips_completed DESC
        LIMIT 100
    ", $target, $campaign['start_time'], $campaign['end_time'], $id ), ARRAY_A ) ?? [];

    foreach ( $leaderboard as &$row ) {
        $row['trips_completed'] = (int) $row['trips_completed'];
        $row['target_trips']    = $target;
        $row['bonus_earned']    = (bool) $row['bonus_earned'];
        $row['progress_pct']    = $target > 0 ? min( 100, round( ( $row['trips_completed'] / $target ) * 100 ) ) : 0;
    }
    unset( $row );

    wp_send_json_success( [ 'campaign' => $campaign, 'leaderboard' => $leaderboard ] );
}
