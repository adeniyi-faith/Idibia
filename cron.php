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

// 4. Take stale drivers offline (location not updated in 15 minutes)
global $wpdb;
$stale_threshold = gmdate('Y-m-d H:i:s', time() - 900);
$wpdb->query( $wpdb->prepare(
    "UPDATE `{$wpdb->prefix}sd_drivers` d
     INNER JOIN `{$wpdb->prefix}sd_driver_locations` dl ON dl.driver_id = d.id
     SET d.is_online = 0
     WHERE d.is_online = 1 AND dl.updated_at < %s",
    $stale_threshold
) );

if ( php_sapi_name() !== 'cli' ) {
    wp_send_json_success(['message' => 'Cron ran successfully']);
} else {
    echo "Cron ran successfully\n";
}
