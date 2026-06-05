<?php ob_start();
/** Idibia — Admin API Router */

require_once __DIR__ . '/../wp-auth-config.php';
require_once __DIR__ . '/../wp/wp-content/mu-plugins/idibia-helpers.php';
idibia_clean_json_buffer();

// Check auth first
$admin_id = 0;
if ( isset($_COOKIE['idibia_admin_auth']) ) {
    $decoded = base64_decode($_COOKIE['idibia_admin_auth']);
    $parts = explode('|', $decoded);
    if ( count($parts) === 2 ) {
        $hash = hash_hmac('sha256', $parts[0], wp_salt('auth'));
        if ( hash_equals($hash, $parts[1]) ) {
            $payload = json_decode($parts[0], true);
            if ( $payload && isset($payload['id']) ) {
                $admin_id = (int) $payload['id'];
            }
        }
    }
}

// They must either have an admin_id from the secure cookie, or be a legacy WP admin
if ( ! $admin_id && ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Unauthorized access.' ] );
}

global $wpdb;
$action = sanitize_key( $_POST['action'] ?? $_GET['action'] ?? '' );

try {
    switch ( $action ) {
        case 'list_admin_users':
            idibia_require_method( 'GET' );
            idibia_admin_list_users();
            break;
        case 'get_my_permissions':
            idibia_require_method( 'GET' );
            idibia_admin_get_my_permissions();
            break;

        case 'create_admin_user':
            idibia_require_method( 'POST' );
            idibia_admin_create_user();
            break;

        case 'update_admin_user':
            idibia_require_method( 'POST' );
            idibia_admin_update_user();
            break;

        case 'suspend_admin_user':
            idibia_require_method( 'POST' );
            idibia_admin_suspend_user();
            break;

        case 'get_roles':
            idibia_require_method( 'GET' );
            idibia_admin_get_roles();
            break;

        case 'get_dashboard_stats':
            idibia_require_method( 'GET' );
            if(!idibia_admin_has_permission('view_live_map')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_dashboard_stats();
            break;

        case 'get_drivers':
            idibia_require_method( 'GET' );
            if(!idibia_admin_has_permission('view_drivers')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_paginated_drivers();
            break;

        case 'get_driver':
            idibia_require_method( 'GET' );
            if(!idibia_admin_has_permission('view_drivers')){ wp_send_json_error(['message'=>'Denied.'],403); }
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
            if(!idibia_admin_has_permission('view_customers')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_paginated_customers();
            break;

        case 'get_trips':
            idibia_require_method( 'GET' );
            if(!idibia_admin_has_permission('view_trips')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_paginated_trips();
            break;

        case 'get_live_ops':
            idibia_require_method( 'GET' );
            idibia_admin_live_ops();
            break;

        case 'get_disputes':
            idibia_require_method( 'GET' );
            if(!idibia_admin_has_permission('view_disputes')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_paginated_disputes();
            break;

        case 'get_payouts':
            idibia_require_method( 'GET' );
            if(!idibia_admin_has_permission('execute_payouts') && !idibia_admin_has_permission('view_export_revenue')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_paginated_payouts();
            break;

        case 'process_payout':
            idibia_require_method( 'POST' );
            if(!idibia_admin_has_permission('execute_payouts')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_process_payout();
            break;

        case 'resolve_dispute':
            idibia_require_method( 'POST' );
            if(!idibia_admin_has_permission('assign_resolve_disputes')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_resolve_dispute();
            break;

        case 'get_settings':
            idibia_require_method( 'GET' );
            if(!idibia_admin_has_permission('view_settings')){ wp_send_json_error(['message'=>'Denied.'],403); }
            $rows = $wpdb->get_results( "SELECT setting_key, setting_value FROM `{$wpdb->prefix}sd_settings`", ARRAY_A );
            $settings = [];
            foreach ( $rows as $row ) {
                if ( in_array( $row['setting_key'], ['paystack_secret_key', 'flutterwave_secret_key', 'pusher_secret'] ) ) {
                    $settings[ $row['setting_key'] ] = !empty($row['setting_value']) ? '********' : '';
                } else {
                    $settings[ $row['setting_key'] ] = $row['setting_value'];
                }
            }
            wp_send_json_success( [ 'settings' => $settings, 'payment' => idibia_payment_settings() ] );
            break;

        case 'export_tax_summary':
            idibia_require_method( 'GET' );
            idibia_admin_export_tax_summary();
            break;

        case 'export_driver_wht':
            idibia_require_method( 'GET' );
            idibia_admin_export_driver_wht();
            break;

        case 'export_vat_schedule':
            idibia_require_method( 'GET' );
            idibia_admin_export_vat_schedule();
            break;

        case 'get_manual_payments':
            idibia_require_method( 'GET' );
            if(!idibia_admin_has_permission('view_payments')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_manual_payments();
            break;

        case 'get_reconciliation_data':
            idibia_require_method( 'GET' );
            idibia_admin_reconciliation_data();
            break;

        case 'get_payment':
            idibia_require_method( 'GET' );
            $payment_id = absint( $_GET['payment_id'] ?? 0 );
            $payment = $wpdb->get_row( $wpdb->prepare( "SELECT p.*, t.trip_ref, t.status AS trip_status, c.full_name AS customer_name FROM `{$wpdb->prefix}sd_payments` p LEFT JOIN `{$wpdb->prefix}sd_trips` t ON t.id = p.trip_id LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = p.customer_id WHERE p.id = %d LIMIT 1", $payment_id ), ARRAY_A );
            if ( $payment ) {
                if ( in_array( $payment['status'], [ 'approved', 'captured' ], true ) ) {
                    $token = hash_hmac( 'sha256', $payment['trip_id'], wp_salt( 'auth' ) );
                    $payment['receipt_url'] = '/receipt-handler.php?trip_id=' . $payment['trip_id'] . '&token=' . $token;
                } else {
                    $payment['receipt_url'] = null;
                }
                wp_send_json_success( [ 'payment' => $payment ] );
            } else {
                wp_send_json_error( [ 'message' => 'Payment not found.' ] );
            }
            break;

        case 'review_manual_payment':
            idibia_require_method( 'POST' );
            if(!idibia_admin_has_permission('approve_reject_payment')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_review_manual_payment();
            break;

        case 'admin_reassign_trip':
            idibia_require_method( 'POST' );
            idibia_admin_reassign_trip();
            break;

        case 'admin_force_cancel_trip':
            idibia_require_method( 'POST' );
            idibia_admin_force_cancel_trip();
            break;


        case 'save_settings':
            idibia_require_method( 'POST' );
            if(!idibia_admin_has_permission('edit_pricing_commission')){ wp_send_json_error(['message'=>'Denied.'],403); }
            idibia_admin_save_settings();
            break;

        default:
            wp_send_json_error( [ 'message' => 'Unknown action.' ] );
    }
} catch ( Exception $e ) {
    http_response_code( 500 );
    wp_send_json_error( [ 'message' => 'Server error.' ] );
}


function idibia_admin_audit_log( string $action, string $entity_type, int $entity_id, array $metadata = [] ): void {
    global $wpdb;
    $table = $wpdb->prefix . 'sd_admin_audit_logs';
    $wpdb->query( "CREATE TABLE IF NOT EXISTS `$table` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `admin_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
        `action` VARCHAR(80) NOT NULL,
        `entity_type` VARCHAR(80) NOT NULL,
        `entity_id` BIGINT UNSIGNED NULL,
        `metadata` LONGTEXT NULL,
        `ip` VARCHAR(45) NULL,
        `user_agent` VARCHAR(255) NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `action` (`action`),
        KEY `entity` (`entity_type`, `entity_id`),
        KEY `created_at` (`created_at`)
    ) " . $wpdb->get_charset_collate() );
    $wpdb->insert( $table, [
        'admin_id'    => get_current_user_id(),
        'action'      => $action,
        'entity_type' => $entity_type,
        'entity_id'   => $entity_id,
        'metadata'    => wp_json_encode( $metadata ),
        'ip'          => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
        'user_agent'  => substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 255 ),
        'created_at'  => gmdate( 'Y-m-d H:i:s' ),
    ], [ '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ] );
}

function idibia_admin_dashboard_stats(): void {
    global $wpdb;
    $today = gmdate( 'Y-m-d' );
    $yesterday = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
    $completed_expr = "COALESCE(NULLIF(final_fare, 0), NULLIF(fare_estimate, 0), fare, 0)";
    $trips_today = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_trips` WHERE DATE(created_at) = %s", $today ) );
    $trips_yesterday = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_trips` WHERE DATE(created_at) = %s", $yesterday ) );
    $revenue_today = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM($completed_expr * platform_pct / 100), 0) FROM `{$wpdb->prefix}sd_trips` WHERE status = 'completed' AND DATE(COALESCE(completed_at, created_at)) = %s", $today ) );
    $completed_24h = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_trips` WHERE status = 'completed' AND COALESCE(completed_at, created_at) >= UTC_TIMESTAMP() - INTERVAL 1 DAY" );
    $finished_24h = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_trips` WHERE status IN ('completed','cancelled') AND COALESCE(completed_at, created_at) >= UTC_TIMESTAMP() - INTERVAL 1 DAY" );
    $avg_pickup = (float) $wpdb->get_var( "SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, created_at, accepted_at)), 0) FROM `{$wpdb->prefix}sd_trips` WHERE accepted_at IS NOT NULL AND created_at >= UTC_TIMESTAMP() - INTERVAL 1 DAY" );
    $recent = $wpdb->get_results( "SELECT t.id, t.trip_ref, t.category, t.service_category, t.status, t.dispatch_status, t.pickup, t.dropoff, t.pickup_address, t.dropoff_address, $completed_expr AS fare_amount, t.created_at, t.completed_at, c.full_name AS customer_name, d.full_name AS driver_name FROM `{$wpdb->prefix}sd_trips` t LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = t.customer_id LEFT JOIN `{$wpdb->prefix}sd_drivers` d ON d.id = t.driver_id ORDER BY t.created_at DESC LIMIT 5", ARRAY_A ) ?: [];
    $trend_rows = $wpdb->get_results( "SELECT DATE(created_at) AS day, COUNT(*) AS trips FROM `{$wpdb->prefix}sd_trips` WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY day ASC", ARRAY_A ) ?: [];
    $trend_map = [];
    foreach ( $trend_rows as $row ) { $trend_map[ $row['day'] ] = (int) $row['trips']; }
    $activity_trend = [];
    for ( $i = 6; $i >= 0; $i-- ) {
        $d = gmdate( 'Y-m-d', strtotime( "-{$i} day" ) );
        $activity_trend[] = [ 'date' => $d, 'label' => gmdate( 'D', strtotime( $d ) ), 'trips' => $trend_map[ $d ] ?? 0 ];
    }
    wp_send_json_success( [
        'total_customers' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_customers`" ),
        'active_drivers'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_drivers` WHERE status = 'active'" ),
        'online_drivers'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_drivers` WHERE is_online = 1 AND status = 'active'" ),
        'kyc_pending'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_drivers` WHERE kyc_status = 'under_review'" ),
        'trips_today'     => $trips_today,
        'trips_yesterday' => $trips_yesterday,
        'revenue_today'   => $revenue_today,
        'completion_rate' => $finished_24h > 0 ? round( ( $completed_24h / $finished_24h ) * 100, 1 ) : 0,
        'avg_pickup_time' => round( $avg_pickup, 1 ),
        'open_disputes'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_disputes` WHERE status IN ('open','escalated')" ),
        'escalated_disputes' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_disputes` WHERE status = 'escalated'" ),
        'suspended_drivers' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_drivers` WHERE status = 'suspended'" ),
        'recent_trips'    => $recent,
        'activity_trend'  => $activity_trend,
    ] );
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
    $status     = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );
    $search     = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) );
    if ( $kyc_status ) { $where[] = 'kyc_status = %s'; $args[] = $kyc_status; }
    if ( $status && in_array( $status, [ 'pending', 'active', 'suspended' ], true ) ) { $where[] = 'status = %s'; $args[] = $status; }
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
    idibia_admin_audit_log( $decision === 'approved' ? 'approve_kyc' : 'reject_kyc', 'driver', $driver_id, [ 'decision' => $decision, 'notes' => $notes ] );
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
    idibia_admin_audit_log( 'suspend_driver', 'driver', $driver_id, [ 'reason' => $reason ] );
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

function idibia_admin_live_ops(): void {
    global $wpdb;

    // Active dispatch statuses — excludes terminal states
    $active_dispatch = "'searching','offered','accepted','arriving','arrived_pickup','picked_up','arrived_dropoff'";

    // All active trips: in_progress status OR any non-terminal dispatch status
    $trips = $wpdb->get_results(
        "SELECT t.id AS trip_id, t.trip_ref, t.status AS trip_status, t.dispatch_status,
                t.pickup_address, t.dropoff_address, t.distance_km, t.duration_mins,
                t.fare, t.final_fare, t.created_at, t.searching_at,
                c.full_name AS customer_name, c.phone AS customer_phone,
                d.id AS driver_id, d.full_name AS driver_name, d.vehicle_type,
                dl.lat AS driver_lat, dl.lng AS driver_lng,
                dl.heading AS driver_heading, dl.updated_at AS location_updated_at
         FROM `{$wpdb->prefix}sd_trips` t
         LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = t.customer_id
         LEFT JOIN `{$wpdb->prefix}sd_drivers` d ON d.id = t.driver_id
         LEFT JOIN `{$wpdb->prefix}sd_driver_locations` dl ON dl.driver_id = t.driver_id
         WHERE (t.status = 'in_progress' OR t.dispatch_status IN ($active_dispatch))
           AND t.dispatch_status NOT IN ('completed','cancelled','no_driver')
         ORDER BY FIELD(t.dispatch_status,'searching','offered','accepted','arriving','arrived_pickup','picked_up','arrived_dropoff'), t.created_at DESC
         LIMIT 100",
        ARRAY_A
    ) ?: [];

    // Driver markers: all online approved drivers with last known location
    $drivers = $wpdb->get_results(
        "SELECT d.id AS driver_id, d.full_name, d.first_name, d.vehicle_type, d.is_online,
                dl.lat, dl.lng, dl.heading, dl.updated_at,
                t.id AS trip_id, t.trip_ref, t.dispatch_status, t.status AS trip_status,
                t.pickup_address, t.dropoff_address, t.distance_km, t.duration_mins,
                c.full_name AS customer_name
         FROM `{$wpdb->prefix}sd_drivers` d
         LEFT JOIN `{$wpdb->prefix}sd_driver_locations` dl ON dl.driver_id = d.id
         LEFT JOIN `{$wpdb->prefix}sd_trips` t ON t.driver_id = d.id AND t.dispatch_status IN ($active_dispatch) AND t.dispatch_status NOT IN ('completed','cancelled','no_driver')
         LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = t.customer_id
         WHERE d.status = 'active' AND d.kyc_status = 'approved' AND (d.is_online = 1 OR t.id IS NOT NULL)
         ORDER BY t.id IS NULL ASC, dl.updated_at DESC, d.full_name ASC
         LIMIT 100",
        ARRAY_A
    ) ?: [];

    $online_drivers = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_drivers` WHERE is_online = 1 AND status = 'active' AND kyc_status = 'approved'" );

    $active_trips = 0;
    $searching_trips = 0;
    foreach ( $trips as &$trip ) {
        $trip['trip_id']          = (int) $trip['trip_id'];
        $trip['driver_id']        = $trip['driver_id'] !== null ? (int) $trip['driver_id'] : null;
        $trip['driver_lat']       = $trip['driver_lat'] !== null ? (float) $trip['driver_lat'] : null;
        $trip['driver_lng']       = $trip['driver_lng'] !== null ? (float) $trip['driver_lng'] : null;
        $trip['driver_heading']   = $trip['driver_heading'] !== null ? (float) $trip['driver_heading'] : null;
        $trip['distance_km']      = $trip['distance_km'] !== null ? (float) $trip['distance_km'] : null;
        $trip['duration_mins']    = $trip['duration_mins'] !== null ? (int) $trip['duration_mins'] : null;
        $trip['fare']             = $trip['fare'] !== null ? (float) $trip['fare'] : null;
        $trip['final_fare']       = $trip['final_fare'] !== null ? (float) $trip['final_fare'] : null;
        if ( $trip['dispatch_status'] === 'searching' || $trip['dispatch_status'] === 'offered' ) {
            $searching_trips++;
        } else {
            $active_trips++;
        }
    }
    unset( $trip );

    foreach ( $drivers as &$driver ) {
        $driver['driver_id']    = (int) $driver['driver_id'];
        $driver['is_online']    = (int) $driver['is_online'];
        $driver['trip_id']      = $driver['trip_id'] !== null ? (int) $driver['trip_id'] : null;
        $driver['lat']          = $driver['lat'] !== null ? (float) $driver['lat'] : null;
        $driver['lng']          = $driver['lng'] !== null ? (float) $driver['lng'] : null;
        $driver['heading']      = $driver['heading'] !== null ? (float) $driver['heading'] : null;
        $driver['distance_km']  = $driver['distance_km'] !== null ? (float) $driver['distance_km'] : null;
        $driver['duration_mins']= $driver['duration_mins'] !== null ? (int) $driver['duration_mins'] : null;
    }
    unset( $driver );

    wp_send_json_success( [
        'trips'   => $trips,
        'drivers' => $drivers,
        'metrics' => [
            'online_drivers'  => $online_drivers,
            'active_trips'    => $active_trips,
            'searching_trips' => $searching_trips,
            'last_refreshed'  => gmdate( 'Y-m-d H:i:s' ),
        ],
    ] );
}

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
    wp_send_json_success( [ 'disputes' => $rows ?: [], 'page' => $page, 'per_page' => $per_page, 'total' => $total ] );
}

function idibia_admin_sync_pending_payouts(): void {
    global $wpdb;
    $drivers = $wpdb->get_results(
        "SELECT d.id, d.wallet_balance
         FROM `{$wpdb->prefix}sd_drivers` d
         LEFT JOIN `{$wpdb->prefix}sd_payouts` p ON p.driver_id = d.id AND p.status IN ('pending','processing')
         WHERE d.wallet_balance > 0 AND p.id IS NULL",
        ARRAY_A
    ) ?: [];

    foreach ( $drivers as $driver ) {
        $wpdb->insert(
            $wpdb->prefix . 'sd_payouts',
            [
                'driver_id'    => (int) $driver['id'],
                'amount'       => (float) $driver['wallet_balance'],
                'status'       => 'pending',
                'provider_ref' => 'wallet-' . (int) $driver['id'] . '-' . gmdate( 'YmdHis' ),
                'created_at'   => gmdate( 'Y-m-d H:i:s' ),
                'updated_at'   => gmdate( 'Y-m-d H:i:s' ),
            ],
            [ '%d', '%f', '%s', '%s', '%s', '%s' ]
        );
    }
}

function idibia_admin_paginated_payouts(): void {
    global $wpdb;
    idibia_admin_sync_pending_payouts();
    [ $page, $per_page, $offset ] = idibia_page_args();
    $status = sanitize_text_field( wp_unslash( $_GET['status'] ?? 'pending' ) );
    $search = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) );
    $where = [ '1=1' ]; $args = [];
    if ( $status && $status !== 'all' ) { $where[] = 'p.status = %s'; $args[] = $status; }
    if ( $search ) { $like = '%' . $wpdb->esc_like( $search ) . '%'; $where[] = '(d.full_name LIKE %s OR d.email LIKE %s OR d.bank_name LIKE %s OR p.provider_ref LIKE %s)'; array_push( $args, $like, $like, $like, $like ); }
    $sql_where = implode( ' AND ', $where );
    $metrics = [
        'pending_amount' => (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount), 0) FROM `{$wpdb->prefix}sd_payouts` WHERE status IN ('pending','processing')" ),
        'pending_count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_payouts` WHERE status IN ('pending','processing')" ),
        'processed_today_amount' => (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount), 0) FROM `{$wpdb->prefix}sd_payouts` WHERE status = 'paid' AND DATE(updated_at) = UTC_DATE()" ),
        'processed_today_count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_payouts` WHERE status = 'paid' AND DATE(updated_at) = UTC_DATE()" ),
        'failed_count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_payouts` WHERE status = 'failed'" ),
        'avg_payout' => (float) $wpdb->get_var( "SELECT COALESCE(AVG(amount), 0) FROM `{$wpdb->prefix}sd_payouts` WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 7 DAY" ),
        'wallet_balance' => (float) $wpdb->get_var( "SELECT COALESCE(SUM(wallet_balance), 0) FROM `{$wpdb->prefix}sd_drivers`" ),
    ];
    $total = (int) $wpdb->get_var( idibia_sql( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_payouts` p LEFT JOIN `{$wpdb->prefix}sd_drivers` d ON d.id = p.driver_id WHERE $sql_where", $args ) );
    $rows = $wpdb->get_results( idibia_sql( "SELECT p.*, d.full_name AS driver_name, d.bank_name, d.account_number, d.wallet_balance, d.total_trips FROM `{$wpdb->prefix}sd_payouts` p LEFT JOIN `{$wpdb->prefix}sd_drivers` d ON d.id = p.driver_id WHERE $sql_where ORDER BY p.updated_at DESC, p.created_at DESC LIMIT %d OFFSET %d", array_merge( $args, [ $per_page, $offset ] ) ), ARRAY_A ) ?: [];
    wp_send_json_success( [ 'payouts' => $rows, 'metrics' => $metrics, 'page' => $page, 'per_page' => $per_page, 'total' => $total ] );
}

function idibia_admin_process_payout(): void {
    global $wpdb;
    $payout_id = absint( $_POST['payout_id'] ?? 0 );
    $status = sanitize_key( $_POST['status'] ?? 'paid' );
    if ( ! in_array( $status, [ 'processing', 'paid', 'failed' ], true ) ) wp_send_json_error( [ 'message' => 'Invalid payout status.' ] );
    $payout = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sd_payouts` WHERE id = %d LIMIT 1", $payout_id ), ARRAY_A );
    if ( ! $payout ) wp_send_json_error( [ 'message' => 'Payout not found.' ] );
    if ( $payout['status'] === 'paid' && $status === 'paid' ) {
        wp_send_json_error( [ 'message' => 'This payout has already been released.' ] );
    }

    idibia_transaction_start();
    $updated = $wpdb->update( $wpdb->prefix . 'sd_payouts', [ 'status' => $status, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ], [ 'id' => $payout_id ], [ '%s', '%s' ], [ '%d' ] );
    if ( false === $updated ) {
        idibia_transaction_rollback();
        wp_send_json_error( [ 'message' => 'Could not update payout.' ] );
    }
    if ( $status === 'paid' ) {
        $ledger_exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_wallet_ledger` WHERE entry_type = 'payout' AND reference_id = %d", $payout_id ) );
        if ( $ledger_exists === 0 ) {
            $wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->prefix}sd_drivers` SET wallet_balance = GREATEST(wallet_balance - %f, 0) WHERE id = %d", (float) $payout['amount'], (int) $payout['driver_id'] ) );
            $wpdb->insert( $wpdb->prefix . 'sd_wallet_ledger', [ 'driver_id' => (int) $payout['driver_id'], 'amount' => -abs( (float) $payout['amount'] ), 'entry_type' => 'payout', 'reference_id' => $payout_id, 'description' => 'Admin payout released', 'created_at' => gmdate( 'Y-m-d H:i:s' ) ], [ '%d', '%f', '%s', '%d', '%s', '%s' ] );
        }
    }
    idibia_transaction_commit();
    idibia_admin_audit_log( 'payout', 'payout', $payout_id, [ 'status' => $status, 'driver_id' => (int) $payout['driver_id'], 'amount' => (float) $payout['amount'] ] );
    wp_send_json_success( [ 'message' => 'Payout updated.' ] );
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

function idibia_admin_export_tax_summary(): void {
    global $wpdb;
    $rows = $wpdb->get_results( "SELECT d.full_name, d.email, COALESCE(SUM(p.amount), 0) as total_payouts FROM `{$wpdb->prefix}sd_drivers` d LEFT JOIN `{$wpdb->prefix}sd_payouts` p ON p.driver_id = d.id AND p.status = 'paid' GROUP BY d.id", ARRAY_A );

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="tax_summary.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Driver Name', 'Email', 'Total Payouts']);
    foreach ($rows as $row) {
        fputcsv($output, [$row['full_name'], $row['email'], $row['total_payouts']]);
    }
    fclose($output);
    exit;
}

function idibia_admin_export_driver_wht(): void {
    global $wpdb;
    $rows = $wpdb->get_results( "SELECT d.full_name, d.email, d.bank_name, d.account_number, COALESCE(SUM(p.amount), 0) as total_payouts FROM `{$wpdb->prefix}sd_drivers` d LEFT JOIN `{$wpdb->prefix}sd_payouts` p ON p.driver_id = d.id AND p.status = 'paid' GROUP BY d.id", ARRAY_A );

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="driver_wht.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Driver Name', 'Email', 'Bank Name', 'Account Number', 'Total Payouts', 'WHT Withheld (e.g. 5%)']);
    foreach ($rows as $row) {
        $wht = $row['total_payouts'] * 0.05;
        fputcsv($output, [$row['full_name'], $row['email'], $row['bank_name'], $row['account_number'], $row['total_payouts'], $wht]);
    }
    fclose($output);
    exit;
}

function idibia_admin_export_vat_schedule(): void {
    global $wpdb;
    $rows = $wpdb->get_results( "SELECT id, trip_ref, fare, platform_pct FROM `{$wpdb->prefix}sd_trips` WHERE status = 'completed'", ARRAY_A );

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="vat_schedule.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Trip Ref', 'Total Fare', 'Platform Commission', 'VAT (e.g. 7.5% of Commission)']);
    foreach ($rows as $row) {
        $commission = $row['fare'] * ($row['platform_pct'] / 100);
        $vat = $commission * 0.075;
        fputcsv($output, [$row['trip_ref'], $row['fare'], $commission, $vat]);
    }
    fclose($output);
    exit;
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
            $sanitized_key = sanitize_key( $key );
            $sanitized_value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : wp_json_encode( $value );

            // Field-level validation
            if ( in_array( $sanitized_key, ['platform_commission_pct', 'surge_multiplier_cap', 'min_fare', 'max_delivery_radius_km'] ) ) {
                if ( ! is_numeric( $sanitized_value ) || (float) $sanitized_value < 0 ) {
                    wp_send_json_error( [ 'message' => "Invalid value for {$sanitized_key}. Must be a positive number." ] );
                }
            }

            // Do not overwrite secrets if they are blank or masked
            if ( in_array( $sanitized_key, ['paystack_secret_key', 'flutterwave_secret_key', 'pusher_secret'] ) ) {
                if ( $sanitized_value === '' || $sanitized_value === '********' ) {
                    continue; // Skip updating this secret
                }
            }

            $placeholders[] = '(%s, %s)';
            $values[]       = $sanitized_key;
            $values[]       = $sanitized_value;
        }

        if ( ! empty( $placeholders ) ) {
            $query = "REPLACE INTO `{$wpdb->prefix}sd_settings` (`setting_key`, `setting_value`) VALUES " . implode( ', ', $placeholders );
            $wpdb->query( $wpdb->prepare( $query, $values ) );

            // Log the action
            idibia_admin_audit_log( 'save_settings', 'settings', 0, array_keys($settings) );
        }
    }

    wp_send_json_success( [ 'message' => 'Settings saved.' ] );
}

function idibia_admin_manual_payments(): void {
    global $wpdb;
    idibia_ensure_manual_payment_columns();
    [ $page, $per_page, $offset ] = idibia_page_args();
    $status = sanitize_text_field( wp_unslash( $_GET['status'] ?? 'proof_submitted' ) );
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

function idibia_admin_reconciliation_data(): void {
    global $wpdb;
    [ $page, $per_page, $offset ] = idibia_page_args();
    $status = sanitize_text_field( wp_unslash( $_GET['status'] ?? 'all' ) );
    $search = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) );

    $where = [ '1=1' ];
    $args = [];

    if ( $status && $status !== 'all' ) {
        $where[] = 'p.status = %s';
        $args[]  = $status;
    }

    if ( $search ) {
        $like = '%' . $wpdb->esc_like( $search ) . '%';
        $where[] = '(t.trip_ref LIKE %s OR c.full_name LIKE %s)';
        $args[] = $like;
        $args[] = $like;
    }

    $start_date = sanitize_text_field( wp_unslash( $_GET['start_date'] ?? '' ) );
    $end_date = sanitize_text_field( wp_unslash( $_GET['end_date'] ?? '' ) );

    if ( $start_date ) {
        $where[] = 'DATE(p.created_at) >= %s';
        $args[] = $start_date;
    }
    if ( $end_date ) {
        $where[] = 'DATE(p.created_at) <= %s';
        $args[] = $end_date;
    }

    $sql_where = implode( ' AND ', $where );
    $total = (int) $wpdb->get_var( idibia_sql( "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_payments` p LEFT JOIN `{$wpdb->prefix}sd_trips` t ON t.id = p.trip_id LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = p.customer_id WHERE $sql_where", $args ) );

    $sql = "SELECT p.*, t.trip_ref, t.status AS trip_status, c.full_name AS customer_name
            FROM `{$wpdb->prefix}sd_payments` p
            LEFT JOIN `{$wpdb->prefix}sd_trips` t ON t.id = p.trip_id
            LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = p.customer_id
            WHERE $sql_where
            ORDER BY p.created_at DESC
            LIMIT %d OFFSET %d";

    $rows = $wpdb->get_results( idibia_sql( $sql, array_merge( $args, [ $per_page, $offset ] ) ), ARRAY_A ) ?: [];

    foreach ( $rows as &$row ) {
        if ( in_array( $row['status'], [ 'approved', 'captured' ], true ) ) {
            $token = hash_hmac( 'sha256', $row['trip_id'], wp_salt( 'auth' ) );
            $row['receipt_url'] = '/receipt-handler.php?trip_id=' . $row['trip_id'] . '&token=' . $token;
        } else {
            $row['receipt_url'] = null;
        }
    }
    unset($row);

    wp_send_json_success( [ 'reconciliation' => $rows, 'page' => $page, 'per_page' => $per_page, 'total' => $total ] );
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

    $new_status = $decision === 'approve' ? 'approved' : 'rejected';
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
    idibia_admin_audit_log( $decision === 'approve' ? 'approve_manual_payment' : 'reject_manual_payment', 'payment', $payment_id, [ 'decision' => $decision, 'trip_id' => (int) $payment['trip_id'], 'notes' => $notes ] );

    wp_send_json_success( [ 'message' => $decision === 'approve' ? 'Payment approved.' : 'Payment rejected.', 'payment' => idibia_payment_public_payload( (int) $payment['trip_id'] ) ] );
}


// --- ADMIN USERS (RBAC) ---

function idibia_admin_get_roles() {
    if ( ! idibia_admin_has_permission('view_admin_users') ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
    }
    global $wpdb;

    // Fetch roles
    $roles = $wpdb->get_results( "SELECT id, name, description, is_system FROM `{$wpdb->prefix}sd_roles` ORDER BY id ASC", ARRAY_A );

    // Fetch permissions per role
    $role_perms = $wpdb->get_results( "SELECT role_id, permission FROM `{$wpdb->prefix}sd_role_permissions`", ARRAY_A );

    $perms_map = [];
    foreach($role_perms as $rp) {
        $perms_map[$rp['role_id']][] = $rp['permission'];
    }

    foreach($roles as &$role) {
        $role['permissions'] = $perms_map[$role['id']] ?? [];
    }

    wp_send_json_success( $roles );
}

function idibia_admin_get_my_permissions() {
    global $wpdb, $admin_id;
    $perms = [];

    // Legacy WP admin bypass
    if ( ! $admin_id && current_user_can('manage_options') ) {
        wp_send_json_success( ['is_super' => true, 'permissions' => []] );
    }

    if ( ! $admin_id ) {
        wp_send_json_error( ['message' => 'Not authenticated as admin'] );
    }

    $admin = $wpdb->get_row( $wpdb->prepare( "SELECT u.role_id, r.name as role_name FROM `{$wpdb->prefix}sd_admin_users` u JOIN `{$wpdb->prefix}sd_roles` r ON u.role_id = r.id WHERE u.id = %d", $admin_id ) );
    if ( ! $admin ) wp_send_json_error();

    if ( $admin->role_name === 'Super Admin' ) {
        wp_send_json_success( ['is_super' => true, 'permissions' => []] );
    }

    $role_perms = $wpdb->get_col( $wpdb->prepare( "SELECT permission FROM `{$wpdb->prefix}sd_role_permissions` WHERE role_id = %d", $admin->role_id ) );

    $overrides = $wpdb->get_results( $wpdb->prepare( "SELECT permission, is_granted FROM `{$wpdb->prefix}sd_user_permission_overrides` WHERE admin_id = %d", $admin_id ) );
    $override_map = [];
    foreach ($overrides as $o) {
        $override_map[$o->permission] = (bool)$o->is_granted;
    }

    // We can just construct the final boolean map or return raw arrays
    wp_send_json_success( [
        'is_super' => false,
        'role_perms' => $role_perms,
        'overrides' => $override_map
    ] );
}

function idibia_admin_list_users() {
    if ( ! idibia_admin_has_permission('view_admin_users') ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
    }
    global $wpdb;

    $users = $wpdb->get_results( "
        SELECT u.id, u.full_name, u.email, u.avatar_path, u.status, u.last_login, u.created_at,
               r.id as role_id, r.name as role_name
        FROM `{$wpdb->prefix}sd_admin_users` u
        JOIN `{$wpdb->prefix}sd_roles` r ON u.role_id = r.id
        ORDER BY u.created_at DESC
    ", ARRAY_A );

    // Attach overrides for each user
    if ( ! empty($users) ) {
        $user_ids = array_column($users, 'id');
        $ids_sql = implode(',', array_map('intval', $user_ids));
        $overrides = $wpdb->get_results( "
            SELECT admin_id, permission, is_granted
            FROM `{$wpdb->prefix}sd_user_permission_overrides`
            WHERE admin_id IN ($ids_sql)
        ", ARRAY_A );

        $override_map = [];
        foreach($overrides as $o) {
            $override_map[$o['admin_id']][$o['permission']] = (bool)$o['is_granted'];
        }

        foreach($users as &$u) {
            $u['overrides'] = $override_map[$u['id']] ?? new stdClass();
        }
    }

    wp_send_json_success( $users );
}

function idibia_admin_create_user() {
    if ( ! idibia_admin_has_permission('create_admin_users') ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
    }
    global $wpdb;

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    $full_name = sanitize_text_field( $data['full_name'] ?? '' );
    $email = sanitize_email( $data['email'] ?? '' );
    $password = $data['password'] ?? '';
    $role_id = (int) ($data['role_id'] ?? 0);
    $overrides = $data['overrides'] ?? []; // Associative array map of 'permission' => boolean

    if ( empty($full_name) || empty($email) || empty($password) || empty($role_id) ) {
        wp_send_json_error( [ 'message' => 'Missing required fields.' ] );
    }

    if ( ! is_email($email) ) {
        wp_send_json_error( [ 'message' => 'Invalid email address.' ] );
    }

    // Check if role is Super Admin and prevent non-super admins from assigning it
    $role = $wpdb->get_row( $wpdb->prepare("SELECT name FROM `{$wpdb->prefix}sd_roles` WHERE id = %d", $role_id) );
    if ( $role && $role->name === 'Super Admin' && ! idibia_admin_has_permission('all') ) { // Assume only Super Admins effectively have 'all' permissions through the helper logic override
        // A rough check if they are Super Admin to assign Super Admin
        global $admin_id;
        $current_admin_id = $admin_id;

        $current_admin = $wpdb->get_row( $wpdb->prepare( "
            SELECT r.name
            FROM `{$wpdb->prefix}sd_admin_users` u
            JOIN `{$wpdb->prefix}sd_roles` r ON u.role_id = r.id
            WHERE u.id = %d
        ", $current_admin_id ) );

        if ( !$current_admin || $current_admin->name !== 'Super Admin' ) {
            wp_send_json_error( [ 'message' => 'Only Super Admins can create Super Admin accounts.' ], 403 );
        }
    }

    // Check email unique
    $exists = $wpdb->get_var( $wpdb->prepare("SELECT id FROM `{$wpdb->prefix}sd_admin_users` WHERE email = %s", $email) );
    if ( $exists ) {
        wp_send_json_error( [ 'message' => 'Email already exists.' ] );
    }

    $password_hash = wp_hash_password($password);

    $wpdb->insert(
        "{$wpdb->prefix}sd_admin_users",
        [
            'full_name' => $full_name,
            'email' => $email,
            'password_hash' => $password_hash,
            'role_id' => $role_id,
            'force_password_change' => 1,
            'status' => 'active',
            'created_at' => gmdate('Y-m-d H:i:s')
        ],
        [ '%s', '%s', '%s', '%d', '%d', '%s', '%s' ]
    );

    $new_id = $wpdb->insert_id;

    if ( ! empty($overrides) ) {
        // Enforce that creator cannot grant permissions they do not possess
        $creator_has_all = idibia_admin_has_permission('all');
        foreach ( $overrides as $perm => $granted ) {
            $perm = sanitize_key($perm);
            if ( $granted && !$creator_has_all && !idibia_admin_has_permission($perm) ) {
                continue; // Skip granting permissions the creator lacks
            }
            $wpdb->insert(
                "{$wpdb->prefix}sd_user_permission_overrides",
                [
                    'admin_id' => $new_id,
                    'permission' => sanitize_key($perm),
                    'is_granted' => $granted ? 1 : 0
                ],
                [ '%d', '%s', '%d' ]
            );
        }
    }

    idibia_admin_audit_log('create', 'admin_user', $new_id, ['email' => $email, 'role_id' => $role_id]);

    wp_send_json_success( [ 'message' => 'Admin user created.', 'id' => $new_id ] );
}

function idibia_admin_update_user() {
    if ( ! idibia_admin_has_permission('edit_admin_users') ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
    }
    global $wpdb;

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    $id = (int) ($data['id'] ?? 0);
    if ( ! $id ) wp_send_json_error( [ 'message' => 'Invalid ID.' ] );

    $full_name = sanitize_text_field( $data['full_name'] ?? '' );
    $email = sanitize_email( $data['email'] ?? '' );
    $role_id = (int) ($data['role_id'] ?? 0);
    $overrides = $data['overrides'] ?? [];
    $status = sanitize_key( $data['status'] ?? '' );

    // Validate target user
    $target = $wpdb->get_row( $wpdb->prepare("
        SELECT u.*, r.name as role_name
        FROM `{$wpdb->prefix}sd_admin_users` u
        JOIN `{$wpdb->prefix}sd_roles` r ON u.role_id = r.id
        WHERE u.id = %d
    ", $id) );

    if ( ! $target ) {
        wp_send_json_error( [ 'message' => 'User not found.' ] );
    }

    // Super admin protection
    global $admin_id;
    $current_admin_id = $admin_id;

    $current_admin = $wpdb->get_row( $wpdb->prepare( "
        SELECT r.name
        FROM `{$wpdb->prefix}sd_admin_users` u
        JOIN `{$wpdb->prefix}sd_roles` r ON u.role_id = r.id
        WHERE u.id = %d
    ", $current_admin_id ) );

    $is_current_super = ($current_admin && $current_admin->name === 'Super Admin') || current_user_can('manage_options');

    if ( $target->role_name === 'Super Admin' && ! $is_current_super ) {
        wp_send_json_error( [ 'message' => 'Cannot modify a Super Admin account.' ], 403 );
    }

    if ( ! empty($email) && $email !== $target->email ) {
        $exists = $wpdb->get_var( $wpdb->prepare("SELECT id FROM `{$wpdb->prefix}sd_admin_users` WHERE email = %s AND id != %d", $email, $id) );
        if ( $exists ) wp_send_json_error( [ 'message' => 'Email already in use.' ] );
    }

    $update_data = [];
    $update_format = [];

    if ( ! empty($full_name) ) { $update_data['full_name'] = $full_name; $update_format[] = '%s'; }
    if ( ! empty($email) ) { $update_data['email'] = $email; $update_format[] = '%s'; }
    if ( ! empty($status) && in_array($status, ['active', 'inactive', 'suspended']) ) {
        $update_data['status'] = $status; $update_format[] = '%s';
    }
    if ( $role_id && $role_id !== (int)$target->role_id ) {
        // Prevent assigning Super Admin if not super admin
        if ( ! $is_current_super ) {
            $new_role = $wpdb->get_row( $wpdb->prepare("SELECT name FROM `{$wpdb->prefix}sd_roles` WHERE id = %d", $role_id) );
            if ( $new_role && $new_role->name === 'Super Admin' ) {
                wp_send_json_error( [ 'message' => 'Cannot assign Super Admin role.' ], 403 );
            }
        }
        $update_data['role_id'] = $role_id; $update_format[] = '%d';
    }

    if ( ! empty($update_data) ) {
        $update_data['updated_at'] = gmdate('Y-m-d H:i:s');
        $update_format[] = '%s';
        $wpdb->update( "{$wpdb->prefix}sd_admin_users", $update_data, ['id' => $id], $update_format, ['%d'] );
    }

    // Handle overrides update
    if ( isset($data['overrides']) ) {
        // Clear existing overrides
        $wpdb->delete( "{$wpdb->prefix}sd_user_permission_overrides", ['admin_id' => $id], ['%d'] );
        // Insert new ones
        $editor_has_all = idibia_admin_has_permission('all');
        foreach ( $overrides as $perm => $granted ) {
            $perm = sanitize_key($perm);
            if ( $granted && !$editor_has_all && !idibia_admin_has_permission($perm) ) {
                continue; // Skip granting permissions the editor lacks
            }
            $wpdb->insert(
                "{$wpdb->prefix}sd_user_permission_overrides",
                [
                    'admin_id' => $id,
                    'permission' => sanitize_key($perm),
                    'is_granted' => $granted ? 1 : 0
                ],
                [ '%d', '%s', '%d' ]
            );
        }
    }

    idibia_admin_audit_log('update', 'admin_user', $id, $update_data);
    wp_send_json_success( [ 'message' => 'User updated successfully.' ] );
}

function idibia_admin_suspend_user() {
    if ( ! idibia_admin_has_permission('suspend_delete_admin_users') ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
    }
    global $wpdb;

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $id = (int) ($data['id'] ?? 0);
    $action_type = sanitize_key($data['action_type'] ?? 'suspend'); // 'suspend' or 'activate'

    if ( ! $id ) wp_send_json_error( [ 'message' => 'Invalid ID.' ] );

    $target = $wpdb->get_row( $wpdb->prepare("
        SELECT u.*, r.name as role_name
        FROM `{$wpdb->prefix}sd_admin_users` u
        JOIN `{$wpdb->prefix}sd_roles` r ON u.role_id = r.id
        WHERE u.id = %d
    ", $id) );

    if ( ! $target ) {
        wp_send_json_error( [ 'message' => 'User not found.' ] );
    }

    global $admin_id;
    $current_admin_id = $admin_id;
    if ( $id === $current_admin_id ) {
        wp_send_json_error( [ 'message' => 'You cannot suspend yourself.' ], 403 );
    }

    $current_admin = $wpdb->get_row( $wpdb->prepare( "
        SELECT r.name
        FROM `{$wpdb->prefix}sd_admin_users` u
        JOIN `{$wpdb->prefix}sd_roles` r ON u.role_id = r.id
        WHERE u.id = %d
    ", $current_admin_id ) );
    $is_current_super = ($current_admin && $current_admin->name === 'Super Admin') || current_user_can('manage_options');

    if ( $target->role_name === 'Super Admin' && ! $is_current_super ) {
        wp_send_json_error( [ 'message' => 'Cannot suspend a Super Admin.' ], 403 );
    }

    $new_status = $action_type === 'activate' ? 'active' : 'suspended';

    $wpdb->update(
        "{$wpdb->prefix}sd_admin_users",
        [ 'status' => $new_status, 'updated_at' => gmdate('Y-m-d H:i:s') ],
        [ 'id' => $id ],
        [ '%s', '%s' ],
        [ '%d' ]
    );

    idibia_admin_audit_log($action_type, 'admin_user', $id);
    wp_send_json_success( [ 'message' => "User $action_type successful." ] );
}
