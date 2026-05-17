<?php ob_start();
/** Idibia — Admin API Router */

require_once __DIR__ . '/../wp-auth-config.php';
require_once __DIR__ . '/../wp/wp-content/mu-plugins/idibia-helpers.php';
idibia_clean_json_buffer();

if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
    http_response_code( 401 );
    wp_send_json_error( [ 'message' => 'Unauthenticated.' ] );
}

global $wpdb;
$action = sanitize_key( $_POST['action'] ?? $_GET['action'] ?? '' );

try {
    switch ( $action ) {
        case 'get_dashboard_stats':
            idibia_require_method( 'GET' );
            $today = gmdate( 'Y-m-d' );
            $stats = [
                'total_customers' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_customers`" ),
                'active_drivers'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_drivers` WHERE status = 'active'" ),
                'trips_today'     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_trips` WHERE DATE(created_at) = %s", $today ) ),
                'revenue_today'   => (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(fare * platform_pct / 100), 0) FROM `{$wpdb->prefix}sd_trips` WHERE status = 'completed' AND DATE(completed_at) = %s", $today ) ),
            ];
            wp_send_json_success( $stats );

        case 'get_drivers':
            idibia_require_method( 'GET' );
            idibia_admin_paginated_drivers();
            break;

        case 'get_driver':
            idibia_require_method( 'GET' );
            $driver_id = absint( $_GET['driver_id'] ?? 0 );
            $driver = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d LIMIT 1", $driver_id ), ARRAY_A );
            $driver ? wp_send_json_success( [ 'driver' => idibia_admin_prepare_driver( $driver ) ] ) : wp_send_json_error( [ 'message' => 'Driver not found.' ] );
            break;

        case 'kyc_action':
            idibia_require_method( 'POST' );
            idibia_admin_kyc_action();
            break;

        case 'suspend_driver':
            idibia_require_method( 'POST' );
            idibia_admin_suspend_driver();
            break;

        case 'get_customers':
            idibia_require_method( 'GET' );
            idibia_admin_paginated_customers();
            break;

        case 'get_trips':
            idibia_require_method( 'GET' );
            idibia_admin_paginated_trips();
            break;

        case 'get_live_ops':
            idibia_require_method( 'GET' );
            idibia_admin_live_ops();
            break;

        case 'get_disputes':
            idibia_require_method( 'GET' );
            idibia_admin_paginated_disputes();
            break;

        case 'resolve_dispute':
            idibia_require_method( 'POST' );
            idibia_admin_resolve_dispute();
            break;

        case 'get_settings':
            idibia_require_method( 'GET' );
            $rows = $wpdb->get_results( "SELECT setting_key, setting_value FROM `{$wpdb->prefix}sd_settings`", ARRAY_A );
            $settings = [];
            foreach ( $rows as $row ) $settings[ $row['setting_key'] ] = $row['setting_value'];
            wp_send_json_success( [ 'settings' => $settings, 'payment' => idibia_payment_settings() ] );

        case 'get_manual_payments':
            idibia_require_method( 'GET' );
            idibia_admin_manual_payments();
            break;

        case 'review_manual_payment':
            idibia_require_method( 'POST' );
            idibia_admin_review_manual_payment();
            break;

        case 'save_settings':
            idibia_require_method( 'POST' );
            idibia_admin_save_settings();
            break;

        default:
            wp_send_json_error( [ 'message' => 'Unknown action.' ] );
    }
} catch ( Exception $e ) {
    http_response_code( 500 );
    wp_send_json_error( [ 'message' => 'Server error.' ] );
}

function idibia_require_method( string $method ): void {
    if ( $_SERVER['REQUEST_METHOD'] !== $method ) {
        http_response_code( 405 );
        wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
    }
}


function idibia_sql( string $sql, array $args = [] ): string {
    global $wpdb;
    return $args ? $wpdb->prepare( $sql, $args ) : $sql;
}

function idibia_page_args(): array {
    $page     = max( 1, absint( $_GET['page'] ?? 1 ) );
    $per_page = min( 100, max( 1, absint( $_GET['per_page'] ?? 20 ) ) );
    return [ $page, $per_page, ( $page - 1 ) * $per_page ];
}

function idibia_admin_paginated_drivers(): void {
    global $wpdb;
    [ $page, $per_page, $offset ] = idibia_page_args();
    $where = [ '1=1' ];
    $args = [];
    $kyc_status = sanitize_text_field( wp_unslash( $_GET['kyc_status'] ?? '' ) );
    $search = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) );
    if ( $kyc_status ) { $where[] = 'kyc_status = %s'; $args[] = $kyc_status; }
    if ( $search ) { $like = '%' . $wpdb->esc_like( $search ) . '%'; $where[] = '(full_name LIKE %s OR email LIKE %s OR phone LIKE %s)'; array_push( $args, $like, $like, $like ); }
    $sql_where = implode( ' AND ', $where );
    $total = (int) $wpdb->get_var( idibia_sql( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_drivers` WHERE $sql_where", $args ) );
    $rows = $wpdb->get_results( idibia_sql( "SELECT * FROM `{$wpdb->prefix}sd_drivers` WHERE $sql_where ORDER BY created_at DESC LIMIT %d OFFSET %d", array_merge( $args, [ $per_page, $offset ] ) ), ARRAY_A );
    $rows = array_map( 'idibia_admin_prepare_driver', $rows ?: [] );
    wp_send_json_success( [
        'drivers'         => $rows,
        'page'            => $page,
        'per_page'        => $per_page,
        'total'           => $total,
        'upload_base_url' => trailingslashit( wp_upload_dir()['baseurl'] ?? '' ),
    ] );
}

function idibia_admin_prepare_driver( array $driver ): array {
    $user = ! empty( $driver['email'] ) ? get_user_by( 'email', $driver['email'] ) : false;
    if ( $user instanceof WP_User ) {
        $driver['user_id']         = $user->ID;
        $driver['first_name']      = (string) get_user_meta( $user->ID, 'first_name', true );
        $driver['last_name']       = (string) get_user_meta( $user->ID, 'last_name', true );
        $driver['language']        = (string) get_user_meta( $user->ID, 'idibia_driver_language', true );
        $driver['middle_name']     = (string) get_user_meta( $user->ID, 'idibia_driver_middle_name', true );
        $driver['date_of_birth']   = (string) get_user_meta( $user->ID, 'idibia_driver_date_of_birth', true );
        $driver['gender']          = (string) get_user_meta( $user->ID, 'idibia_driver_gender', true );
        $driver['state_of_origin'] = (string) get_user_meta( $user->ID, 'idibia_driver_state_of_origin', true );
    }

    return $driver;
}

function idibia_admin_kyc_action(): void {
    global $wpdb;
    $driver_id = absint( $_POST['driver_id'] ?? 0 );
    $decision  = sanitize_text_field( wp_unslash( $_POST['decision'] ?? '' ) );
    $notes     = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );
    if ( ! in_array( $decision, [ 'approved', 'rejected' ], true ) ) wp_send_json_error( [ 'message' => 'Invalid decision.' ] );
    $current_status = $wpdb->get_var( $wpdb->prepare( "SELECT kyc_status FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d LIMIT 1", $driver_id ) );
    if ( $current_status !== 'under_review' ) {
        wp_send_json_error( [ 'message' => 'This KYC record has already been resolved.' ] );
    }
    $status = $decision === 'approved' ? 'active' : 'pending';
    $updated = $wpdb->update( $wpdb->prefix . 'sd_drivers', [ 'kyc_status' => $decision, 'status' => $status, 'kyc_notes' => $notes ], [ 'id' => $driver_id, 'kyc_status' => 'under_review' ], [ '%s', '%s', '%s' ], [ '%d', '%s' ] );
    if ( false === $updated ) wp_send_json_error( [ 'message' => 'Could not update driver.' ] );
    $driver = $wpdb->get_row( $wpdb->prepare( "SELECT email, full_name FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d", $driver_id ) );
    if ( $driver ) {
        $user = get_user_by( 'email', $driver->email );
        if ( $user instanceof WP_User ) {
            update_user_meta( $user->ID, 'idibia_kyc_status', $decision );
            update_user_meta( $user->ID, 'idibia_account_status', $status );
        }
        wp_mail( $driver->email, '[Idibia] Driver application update', "Hi {$driver->full_name},\n\nYour KYC application was $decision.\n\n$notes", [ 'Content-Type: text/plain; charset=UTF-8' ] );
    }
    wp_send_json_success( [ 'message' => 'KYC updated.' ] );
}

function idibia_admin_suspend_driver(): void {
    global $wpdb;
    $driver_id = absint( $_POST['driver_id'] ?? 0 );
    $reason = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
    $updated = $wpdb->update( $wpdb->prefix . 'sd_drivers', [ 'status' => 'suspended', 'is_online' => 0, 'kyc_notes' => $reason ], [ 'id' => $driver_id ], [ '%s', '%d', '%s' ], [ '%d' ] );
    if ( false === $updated ) wp_send_json_error( [ 'message' => 'Could not suspend driver.' ] );
    $driver = $wpdb->get_row( $wpdb->prepare( "SELECT email, full_name FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d", $driver_id ) );
    if ( $driver ) {
        $user = get_user_by( 'email', $driver->email );
        if ( $user instanceof WP_User ) {
            update_user_meta( $user->ID, 'idibia_account_status', 'suspended' );
        }
        wp_mail( $driver->email, '[Idibia] Driver account suspended', "Hi {$driver->full_name},\n\nYour driver account has been suspended.\n\nReason: $reason", [ 'Content-Type: text/plain; charset=UTF-8' ] );
    }
    wp_send_json_success( [ 'message' => 'Driver suspended.' ] );
}

function idibia_admin_paginated_customers(): void {
    global $wpdb;
    [ $page, $per_page, $offset ] = idibia_page_args();
    $where = [ '1=1' ]; $args = [];
    $status = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );
    $search = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) );
    if ( $status ) { $where[] = 'status = %s'; $args[] = $status; }
    if ( $search ) { $like = '%' . $wpdb->esc_like( $search ) . '%'; $where[] = '(full_name LIKE %s OR email LIKE %s OR phone LIKE %s)'; array_push( $args, $like, $like, $like ); }
    $sql_where = implode( ' AND ', $where );
    $total = (int) $wpdb->get_var( idibia_sql( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_customers` WHERE $sql_where", $args ) );
    $rows = $wpdb->get_results( idibia_sql( "SELECT id, full_name, email, phone, email_verified, status, created_at FROM `{$wpdb->prefix}sd_customers` WHERE $sql_where ORDER BY created_at DESC LIMIT %d OFFSET %d", array_merge( $args, [ $per_page, $offset ] ) ), ARRAY_A );
    wp_send_json_success( [ 'customers' => $rows, 'page' => $page, 'per_page' => $per_page, 'total' => $total ] );
}

function idibia_admin_paginated_trips(): void {
    global $wpdb;
    [ $page, $per_page, $offset ] = idibia_page_args();
    $where = [ '1=1' ]; $args = [];
    $status = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );
    $category = sanitize_text_field( wp_unslash( $_GET['category'] ?? '' ) );
    if ( $status ) { $where[] = 't.status = %s'; $args[] = $status; }
    if ( $category ) { $where[] = 't.category = %s'; $args[] = $category; }
    $sql_where = implode( ' AND ', $where );
    $total = (int) $wpdb->get_var( idibia_sql( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_trips` t WHERE $sql_where", $args ) );
    $sql = "SELECT t.*, c.full_name AS customer_name, d.full_name AS driver_name FROM `{$wpdb->prefix}sd_trips` t LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = t.customer_id LEFT JOIN `{$wpdb->prefix}sd_drivers` d ON d.id = t.driver_id WHERE $sql_where ORDER BY t.created_at DESC LIMIT %d OFFSET %d";
    $rows = $wpdb->get_results( idibia_sql( $sql, array_merge( $args, [ $per_page, $offset ] ) ), ARRAY_A );
    wp_send_json_success( [ 'trips' => $rows, 'page' => $page, 'per_page' => $per_page, 'total' => $total ] );
}


function idibia_admin_live_ops(): void {
    global $wpdb;

    $active_statuses = "'accepted','arriving','arrived_pickup','picked_up','arrived_dropoff'";
    $drivers = $wpdb->get_results(
        "SELECT d.id AS driver_id, d.full_name, d.first_name, d.vehicle_type, d.is_online,
                dl.lat, dl.lng, dl.heading, dl.updated_at,
                t.id AS trip_id, t.trip_ref, t.dispatch_status, t.status AS trip_status,
                t.pickup_address, t.dropoff_address, t.distance_km, t.duration_mins,
                c.full_name AS customer_name
         FROM `{$wpdb->prefix}sd_drivers` d
         LEFT JOIN `{$wpdb->prefix}sd_driver_locations` dl ON dl.driver_id = d.id
         LEFT JOIN `{$wpdb->prefix}sd_trips` t ON t.driver_id = d.id AND t.dispatch_status IN ($active_statuses)
         LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = t.customer_id
         WHERE d.status = 'active' AND d.kyc_status = 'approved' AND (d.is_online = 1 OR t.id IS NOT NULL)
         ORDER BY t.id IS NULL ASC, dl.updated_at DESC, d.full_name ASC
         LIMIT 100",
        ARRAY_A
    ) ?: [];

    $online_drivers = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_drivers` WHERE is_online = 1 AND status = 'active' AND kyc_status = 'approved'" );

    $active_trips = 0;
    foreach ( $drivers as &$driver ) {
        $driver['driver_id'] = (int) $driver['driver_id'];
        $driver['is_online'] = (int) $driver['is_online'];
        $driver['trip_id'] = $driver['trip_id'] !== null ? (int) $driver['trip_id'] : null;
        $driver['lat'] = $driver['lat'] !== null ? (float) $driver['lat'] : null;
        $driver['lng'] = $driver['lng'] !== null ? (float) $driver['lng'] : null;
        $driver['heading'] = $driver['heading'] !== null ? (float) $driver['heading'] : null;
        $driver['distance_km'] = $driver['distance_km'] !== null ? (float) $driver['distance_km'] : null;
        $driver['duration_mins'] = $driver['duration_mins'] !== null ? (int) $driver['duration_mins'] : null;
        if ( $driver['trip_id'] ) $active_trips++;
    }
    unset( $driver );

    wp_send_json_success( [
        'drivers' => $drivers,
        'metrics' => [
            'online_drivers' => $online_drivers,
            'active_trips'   => $active_trips,
            'last_refreshed' => gmdate( 'Y-m-d H:i:s' ),
        ],
    ] );
}

function idibia_admin_paginated_disputes(): void {
    global $wpdb;
    [ $page, $per_page, $offset ] = idibia_page_args();
    $status = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );
    $where = $status ? 'WHERE status = %s' : '';
    $args = $status ? [ $status ] : [];
    $total = (int) $wpdb->get_var( idibia_sql( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_disputes` $where", $args ) );
    $rows = $wpdb->get_results( idibia_sql( "SELECT * FROM `{$wpdb->prefix}sd_disputes` $where ORDER BY created_at DESC LIMIT %d OFFSET %d", array_merge( $args, [ $per_page, $offset ] ) ), ARRAY_A );
    wp_send_json_success( [ 'disputes' => $rows, 'page' => $page, 'per_page' => $per_page, 'total' => $total ] );
}

function idibia_admin_resolve_dispute(): void {
    global $wpdb;
    $dispute_id = absint( $_POST['dispute_id'] ?? 0 );
    $resolution = sanitize_text_field( wp_unslash( $_POST['resolution_action'] ?? '' ) );
    $refund = (float) ( $_POST['refund_amount'] ?? 0 );
    $notes = sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ?? '' ) );
    $updated = $wpdb->update( $wpdb->prefix . 'sd_disputes', [ 'status' => 'resolved', 'resolution' => $resolution, 'refund_amount' => $refund, 'admin_notes' => $notes, 'resolved_at' => gmdate( 'Y-m-d H:i:s' ) ], [ 'id' => $dispute_id ], [ '%s', '%s', '%f', '%s', '%s' ], [ '%d' ] );
    if ( false === $updated ) wp_send_json_error( [ 'message' => 'Could not resolve dispute.' ] );
    wp_send_json_success( [ 'message' => 'Dispute resolved.' ] );
}

function idibia_admin_save_settings(): void {
    global $wpdb;
    $raw = file_get_contents( 'php://input' );
    $settings = json_decode( $raw, true );
    if ( ! is_array( $settings ) ) {
        $settings = $_POST['settings'] ?? $_POST;
    }
    unset( $settings['action'] );

    if ( ! empty( $settings ) ) {
        $values       = [];
        $placeholders = [];
        foreach ( $settings as $key => $value ) {
            $placeholders[] = '(%s, %s)';
            $values[]       = sanitize_key( $key );
            $values[]       = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : wp_json_encode( $value );
        }
        $query = "REPLACE INTO `{$wpdb->prefix}sd_settings` (`setting_key`, `setting_value`) VALUES " . implode( ', ', $placeholders );
        $wpdb->query( $wpdb->prepare( $query, $values ) );
    }

    wp_send_json_success( [ 'message' => 'Settings saved.' ] );
}

function idibia_admin_manual_payments(): void {
    global $wpdb;
    idibia_ensure_manual_payment_columns();
    [ $page, $per_page, $offset ] = idibia_page_args();
    $status = sanitize_text_field( wp_unslash( $_GET['status'] ?? 'pending' ) );
    $where = [ "p.provider = 'manual_transfer'" ];
    $args = [];
    if ( $status && $status !== 'all' ) { $where[] = 'p.status = %s'; $args[] = $status; }
    $sql_where = implode( ' AND ', $where );
    $total = (int) $wpdb->get_var( idibia_sql( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_payments` p WHERE $sql_where", $args ) );
    $sql = "SELECT p.*, t.trip_ref, t.status AS trip_status, t.dispatch_status, c.full_name AS customer_name, c.phone AS customer_phone
            FROM `{$wpdb->prefix}sd_payments` p
            INNER JOIN `{$wpdb->prefix}sd_trips` t ON t.id = p.trip_id
            LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = p.customer_id
            WHERE $sql_where
            ORDER BY p.updated_at DESC, p.created_at DESC
            LIMIT %d OFFSET %d";
    $rows = $wpdb->get_results( idibia_sql( $sql, array_merge( $args, [ $per_page, $offset ] ) ), ARRAY_A ) ?: [];
    $upload = wp_upload_dir();
    $baseurl = empty( $upload['error'] ) ? trailingslashit( $upload['baseurl'] ) : '';
    foreach ( $rows as &$row ) {
        $row['id'] = (int) $row['id'];
        $row['trip_id'] = (int) $row['trip_id'];
        $row['customer_id'] = (int) $row['customer_id'];
        $row['amount'] = (float) $row['amount'];
        $row['proof_url'] = ! empty( $row['proof_path'] ) && $baseurl ? $baseurl . ltrim( $row['proof_path'], '/' ) : '';
    }
    unset( $row );
    wp_send_json_success( [ 'payments' => $rows, 'page' => $page, 'per_page' => $per_page, 'total' => $total ] );
}

function idibia_admin_review_manual_payment(): void {
    global $wpdb;
    idibia_ensure_manual_payment_columns();
    $payment_id = absint( $_POST['payment_id'] ?? 0 );
    $decision = sanitize_key( $_POST['decision'] ?? '' );
    $notes = sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ?? '' ) );
    if ( $payment_id <= 0 || ! in_array( $decision, [ 'approve', 'reject' ], true ) ) {
        wp_send_json_error( [ 'message' => 'Select a valid payment review action.' ] );
    }

    $payment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sd_payments` WHERE id = %d AND provider = 'manual_transfer' LIMIT 1", $payment_id ), ARRAY_A );
    if ( ! $payment ) {
        wp_send_json_error( [ 'message' => 'Payment record not found.' ] );
    }
    if ( $decision === 'approve' && empty( $payment['proof_path'] ) ) {
        wp_send_json_error( [ 'message' => 'A receipt/proof file is required before approval.' ] );
    }

    $new_status = $decision === 'approve' ? 'captured' : 'failed';
    idibia_transaction_start();
    $updated = $wpdb->update(
        $wpdb->prefix . 'sd_payments',
        [
            'status'      => $new_status,
            'admin_notes' => $notes,
            'reviewed_by' => get_current_user_id(),
            'reviewed_at' => gmdate( 'Y-m-d H:i:s' ),
        ],
        [ 'id' => $payment_id ],
        [ '%s', '%s', '%d', '%s' ],
        [ '%d' ]
    );
    if ( false === $updated ) {
        idibia_transaction_rollback();
        wp_send_json_error( [ 'message' => 'Could not review payment.' ] );
    }

    $wpdb->update( $wpdb->prefix . 'sd_trips', [ 'payment_status' => $new_status ], [ 'id' => (int) $payment['trip_id'] ], [ '%s' ], [ '%d' ] );
    idibia_log_event( (int) $payment['trip_id'], $decision === 'approve' ? 'payment_approved' : 'payment_rejected', [ 'payment_id' => $payment_id, 'admin_id' => get_current_user_id() ] );
    idibia_notify_trip_participants( (int) $payment['trip_id'], $decision === 'approve' ? 'payment_approved' : 'payment_rejected', [ 'body' => $notes ?: null ] );
    if ( $decision === 'approve' ) {
        idibia_notify_trip_participants( (int) $payment['trip_id'], 'payment_captured' );
        idibia_credit_driver_for_trip( (int) $payment['trip_id'] );
    }
    idibia_transaction_commit();

    wp_send_json_success( [ 'message' => $decision === 'approve' ? 'Payment approved.' : 'Payment rejected.', 'payment' => idibia_payment_public_payload( (int) $payment['trip_id'] ) ] );
}
