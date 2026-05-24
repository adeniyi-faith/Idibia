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

global $wpdb;

// 1. Expire dispatch offers
idibia_expire_dispatch_offers();

// 2. Automatically redispatch `no_driver` trips or trips with expired offers
// ⚡ Bolt: Fixed N+1 query by filtering out trips with pending offers in the main SQL query using NOT EXISTS
$trips_to_redispatch = $wpdb->get_results(
    "SELECT t.id
     FROM `{$wpdb->prefix}sd_trips` t
     WHERE t.dispatch_status IN ('no_driver', 'searching', 'offered')
       AND t.status = 'pending'
       AND NOT EXISTS (
           SELECT 1
           FROM `{$wpdb->prefix}sd_dispatch_offers` o
           WHERE o.trip_id = t.id AND o.status = 'pending'
       )", ARRAY_A
);

foreach ($trips_to_redispatch as $trip) {
    // Try to dispatch again
    idibia_dispatch_trip((int)$trip['id']);
}

// 3. Stale driver locations (Drivers who haven't updated location in 15 mins)
$stale_threshold = gmdate('Y-m-d H:i:s', time() - 900); // 15 minutes ago
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
