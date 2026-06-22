<?php
/** Idibia Cron Jobs Worker */
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-dispatch-helpers.php';
idibia_clean_json_buffer();

if ( php_sapi_name() !== 'cli' ) {
    // Basic protection for HTTP requests
    $cron_key = $_GET['key'] ?? '';
    if ( $cron_key !== idibia_get_setting('cron_secret_key') ) {
        http_response_code( 403 );
        die('Unauthorized');
    }
}

// 1. Expire stale offers and re-dispatch eligible trips (with retry limit)
idibia_cron_offer_expiry();

// 2. Dispatch scheduled trips whose time is approaching
idibia_cron_scheduled_dispatch();

// 3. Auto-cancel trips that have been searching too long with no driver
idibia_cron_trip_timeout();

// 4. Take stale drivers offline (location not updated in 10 minutes)
// We find them first so we can log each one, then update all at once.
global $wpdb;
$stale_threshold = gmdate('Y-m-d H:i:s', time() - 600);

$stale_driver_ids = $wpdb->get_col( $wpdb->prepare(
    "SELECT d.id
     FROM `{$wpdb->prefix}sd_drivers` d
     INNER JOIN `{$wpdb->prefix}sd_driver_locations` dl ON dl.driver_id = d.id
     WHERE d.is_online = 1 AND dl.updated_at < %s",
    $stale_threshold
) );

if ( ! empty( $stale_driver_ids ) ) {
    $placeholders = implode( ',', array_fill( 0, count( $stale_driver_ids ), '%d' ) );
    $wpdb->query( $wpdb->prepare(
        "UPDATE `{$wpdb->prefix}sd_drivers` SET is_online = 0 WHERE id IN ($placeholders)",
        ...$stale_driver_ids
    ) );

    // Insert a system log event for any active trip belonging to each driver so dispatchers can trace it.
    foreach ( $stale_driver_ids as $driver_id ) {
        $active_trip = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM `{$wpdb->prefix}sd_trips`
             WHERE driver_id = %d AND status NOT IN ('completed','cancelled')
             ORDER BY created_at DESC LIMIT 1",
            (int) $driver_id
        ) );

        if ( $active_trip ) {
            idibia_log_event( (int) $active_trip, 'driver_auto_offline', [
                'driver_id' => (int) $driver_id,
                'reason'    => 'No GPS update for 10+ minutes (cron auto-offline)',
            ] );
        }
    }
}

// 5. Record when the cron last ran so the System Health panel can display it.
$wpdb->query( $wpdb->prepare(
    "REPLACE INTO `{$wpdb->prefix}sd_settings` (setting_key, setting_value) VALUES ('last_cron_run', %s)",
    gmdate( 'Y-m-d H:i:s' )
) );

if ( php_sapi_name() !== 'cli' ) {
    wp_send_json_success(['message' => 'Cron ran successfully']);
} else {
    echo "Cron ran successfully\n";
}
