<?php
/** Idibia — Admin API: System Health */

/**
 * Returns a snapshot of all key system health indicators.
 * Admins can call this to see the state of the platform without needing server access.
 */
function idibia_admin_get_system_health(): void {
    global $wpdb;

    $health = [];

    // --- Stale driver sessions ---
    // A driver is "stale" if they appear online but their GPS location
    // hasn't been updated for more than 5 minutes (app probably crashed).
    $five_min_ago = gmdate( 'Y-m-d H:i:s', time() - 300 );
    $health['stale_driver_sessions'] = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(d.id)
         FROM `{$wpdb->prefix}sd_drivers` d
         INNER JOIN `{$wpdb->prefix}sd_driver_locations` dl ON dl.driver_id = d.id
         WHERE d.is_online = 1 AND dl.updated_at < %s",
        $five_min_ago
    ) );

    // --- Trips grouped by dispatch status (only active trips) ---
    // This shows where trips are "stuck" in the pipeline at a glance.
    $dispatch_rows = $wpdb->get_results(
        "SELECT dispatch_status, COUNT(*) AS cnt
         FROM `{$wpdb->prefix}sd_trips`
         WHERE status NOT IN ('completed', 'cancelled')
         GROUP BY dispatch_status",
        ARRAY_A
    );
    $health['trips_by_dispatch_status'] = [];
    foreach ( $dispatch_rows ?: [] as $row ) {
        $health['trips_by_dispatch_status'][ $row['dispatch_status'] ] = (int) $row['cnt'];
    }

    // --- Trips stuck in "searching" beyond the configured timeout ---
    $timeout_min = (int) idibia_get_setting( 'trip_timeout_minutes', 10 );
    $timeout_ago = gmdate( 'Y-m-d H:i:s', time() - ( $timeout_min * 60 ) );
    $health['trips_stuck_searching'] = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_trips`
         WHERE status = 'searching' AND created_at < %s",
        $timeout_ago
    ) );

    // --- Payment gateway statuses ---
    // We make a lightweight "ping" to each gateway to see if it is responding.
    $health['paystack_status']    = idibia_health_ping_paystack();
    $health['flutterwave_status'] = idibia_health_ping_flutterwave();

    // --- Pusher (real-time notifications) ---
    // We just check if the keys are saved — we can't easily test the connection server-side.
    $pusher_configured = (
        idibia_get_setting( 'pusher_app_id' ) &&
        idibia_get_setting( 'pusher_key' ) &&
        idibia_get_setting( 'pusher_secret' ) &&
        idibia_get_setting( 'pusher_cluster' )
    );
    $health['pusher_status'] = $pusher_configured ? 'configured' : 'not_configured';

    // --- SMTP (email delivery) ---
    $smtp_configured = (
        idibia_get_setting( 'smtp_host' ) &&
        idibia_get_setting( 'smtp_username' )
    );
    $health['smtp_status'] = $smtp_configured ? 'configured' : 'not_configured';

    // --- Pending KYC applications ---
    $health['pending_kyc_count'] = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_drivers` WHERE kyc_status = 'pending'"
    );

    // --- Open disputes ---
    $health['open_disputes_count'] = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_disputes` WHERE status = 'open'"
    );

    // --- Failed payouts in the last 24 hours ---
    $day_ago = gmdate( 'Y-m-d H:i:s', time() - 86400 );
    $health['failed_payouts_24h'] = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM `{$wpdb->prefix}sd_payouts` WHERE status = 'failed' AND created_at > %s",
        $day_ago
    ) );

    // --- Database size ---
    // Uses information_schema to estimate the total size of all tables.
    $health['db_size_mb'] = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT ROUND( SUM(data_length + index_length) / 1024 / 1024, 2 )
         FROM information_schema.tables
         WHERE table_schema = %s",
        DB_NAME
    ) );

    // --- Last cron run timestamp ---
    $health['last_cron_run'] = idibia_get_setting( 'last_cron_run' ) ?: null;

    wp_send_json_success( [ 'health' => $health ] );
}

/**
 * Forces all stale drivers (no GPS update in 10+ minutes) offline immediately.
 * Used by the "Force Offline All" button in the System Health panel.
 */
function idibia_admin_force_drivers_offline(): void {
    global $wpdb;

    $ten_min_ago = gmdate( 'Y-m-d H:i:s', time() - 600 );

    $stale_ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT d.id
         FROM `{$wpdb->prefix}sd_drivers` d
         INNER JOIN `{$wpdb->prefix}sd_driver_locations` dl ON dl.driver_id = d.id
         WHERE d.is_online = 1 AND dl.updated_at < %s",
        $ten_min_ago
    ) );

    if ( empty( $stale_ids ) ) {
        wp_send_json_success( [ 'message' => 'No stale drivers found.', 'count' => 0 ] );
    }

    $placeholders = implode( ',', array_fill( 0, count( $stale_ids ), '%d' ) );
    $wpdb->query( $wpdb->prepare(
        "UPDATE `{$wpdb->prefix}sd_drivers` SET is_online = 0 WHERE id IN ($placeholders)",
        ...$stale_ids
    ) );

    wp_send_json_success( [
        'message' => count( $stale_ids ) . ' driver(s) forced offline.',
        'count'   => count( $stale_ids ),
    ] );
}

/**
 * Pings the Paystack API to check if it is reachable.
 * Returns: 'ok', 'degraded', 'down', or 'not_configured'.
 */
function idibia_health_ping_paystack(): string {
    $secret = idibia_get_setting( 'paystack_secret_key' );
    if ( ! $secret ) return 'not_configured';

    $response = wp_remote_get( 'https://api.paystack.co/bank?perPage=1', [
        'timeout' => 6,
        'headers' => [ 'Authorization' => 'Bearer ' . $secret ],
    ] );

    if ( is_wp_error( $response ) ) return 'down';
    $code = (int) wp_remote_retrieve_response_code( $response );
    if ( $code === 200 ) return 'ok';
    if ( $code >= 500 ) return 'down';
    return 'degraded';
}

/**
 * Pings the Flutterwave API to check if it is reachable.
 * Returns: 'ok', 'degraded', 'down', or 'not_configured'.
 */
function idibia_health_ping_flutterwave(): string {
    $secret = idibia_get_setting( 'flutterwave_secret_key' );
    if ( ! $secret ) return 'not_configured';

    $response = wp_remote_get( 'https://api.flutterwave.com/v3/banks/NG', [
        'timeout' => 6,
        'headers' => [ 'Authorization' => 'Bearer ' . $secret ],
    ] );

    if ( is_wp_error( $response ) ) return 'down';
    $code = (int) wp_remote_retrieve_response_code( $response );
    if ( $code === 200 ) return 'ok';
    if ( $code >= 500 ) return 'down';
    return 'degraded';
}
