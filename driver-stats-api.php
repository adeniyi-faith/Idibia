<?php
/** Idibia — Driver Stats API (dashboard stats + trip history refresh) */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-dispatch-helpers.php';
idibia_clean_json_buffer();

if ( $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

$auth_type = 'driver';
require_once __DIR__ . '/auth-helper.php';

global $wpdb;
$driver_id = (int) $GLOBALS['auth_driver_id'];

$today_start = gmdate( 'Y-m-d 00:00:00' );
$today_end   = gmdate( 'Y-m-d 23:59:59' );

$today_stats = $wpdb->get_row( $wpdb->prepare(
    "SELECT SUM(fare) as today_earnings, COUNT(id) as today_trips
     FROM `{$wpdb->prefix}sd_trips`
     WHERE driver_id = %d AND status = 'completed'
       AND completed_at >= %s AND completed_at <= %s",
    $driver_id, $today_start, $today_end
), ARRAY_A );

$today_earnings = (float) ( $today_stats['today_earnings'] ?? 0 );
$today_trips    = (int)   ( $today_stats['today_trips']    ?? 0 );

$week_start = gmdate( 'Y-m-d 00:00:00', strtotime( 'monday this week' ) );
$week_end   = gmdate( 'Y-m-d 23:59:59', strtotime( 'sunday this week' ) );

$weekly_stats = $wpdb->get_row( $wpdb->prepare(
    "SELECT SUM(fare) as week_earnings, COUNT(id) as week_trips
     FROM `{$wpdb->prefix}sd_trips`
     WHERE driver_id = %d AND status = 'completed'
       AND completed_at >= %s AND completed_at <= %s",
    $driver_id, $week_start, $week_end
), ARRAY_A );

$week_earnings = (float) ( $weekly_stats['week_earnings'] ?? 0 );
$week_trips    = (int)   ( $weekly_stats['week_trips']    ?? 0 );

$daily_breakdown_raw = $wpdb->get_results( $wpdb->prepare(
    "SELECT DATE(completed_at) as date, SUM(fare) as daily_earnings
     FROM `{$wpdb->prefix}sd_trips`
     WHERE driver_id = %d AND status = 'completed'
       AND completed_at >= %s AND completed_at <= %s
     GROUP BY DATE(completed_at)",
    $driver_id, $week_start, $week_end
), ARRAY_A );

$daily_breakdown = [];
foreach ( $daily_breakdown_raw as $row ) {
    $daily_breakdown[ $row['date'] ] = (float) $row['daily_earnings'];
}

$avg_rating = (float) $wpdb->get_var( $wpdb->prepare(
    "SELECT AVG(rating) FROM `{$wpdb->prefix}sd_ratings`
     WHERE subject_id = %d AND reviewer_type = 'customer'",
    $driver_id
) );
$avg_rating = $avg_rating > 0 ? round( $avg_rating, 1 ) : 0.0;

$trips_history_raw = $wpdb->get_results( $wpdb->prepare(
    "SELECT t.id, t.trip_ref, t.pickup, t.dropoff, t.fare, t.final_fare, t.fare_estimate, t.status, t.created_at, t.completed_at, p.status AS payment_status
     FROM `{$wpdb->prefix}sd_trips` t
     LEFT JOIN `{$wpdb->prefix}sd_payments` p ON p.trip_id = t.id
     WHERE t.driver_id = %d
     ORDER BY t.created_at DESC
     LIMIT 100",
    $driver_id
), ARRAY_A ) ?: [];

$trips_history = [];
foreach ( $trips_history_raw as $trip ) {
    $receipt_url = null;
    if ( in_array( $trip['payment_status'], [ 'approved', 'captured' ], true ) ) {
        $token       = hash_hmac( 'sha256', (int) $trip['id'], wp_salt( 'auth' ) );
        $receipt_url = '/receipt-handler.php?trip_id=' . $trip['id'] . '&token=' . $token;
    }
    $trips_history[] = [
        'id'           => (int) $trip['id'],
        'trip_ref'     => $trip['trip_ref'],
        'pickup'       => $trip['pickup'],
        'dropoff'      => $trip['dropoff'],
        'fare'         => (float) ( $trip['final_fare'] ?: $trip['fare_estimate'] ?: $trip['fare'] ),
        'status'       => $trip['status'],
        'created_at'   => $trip['created_at'],
        'completed_at' => $trip['completed_at'],
        'receipt_url'  => $receipt_url,
    ];
}

$now                = gmdate( 'Y-m-d H:i:s' );
$active_campaigns_raw = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM `{$wpdb->prefix}sd_campaigns`
     WHERE status = 'active' AND start_time <= %s AND end_time >= %s
     ORDER BY end_time ASC",
    $now, $now
), ARRAY_A );

$active_campaigns = [];
if ( ! empty( $active_campaigns_raw ) ) {
    $min_time = $active_campaigns_raw[0]['start_time'];
    $max_time = $active_campaigns_raw[0]['end_time'];
    foreach ( $active_campaigns_raw as $c ) {
        if ( $c['start_time'] < $min_time ) $min_time = $c['start_time'];
        if ( $c['end_time']   > $max_time ) $max_time = $c['end_time'];
    }
    $trip_dates = $wpdb->get_col( $wpdb->prepare(
        "SELECT completed_at FROM `{$wpdb->prefix}sd_trips`
         WHERE driver_id = %d AND status = 'completed'
           AND completed_at >= %s AND completed_at <= %s",
        $driver_id, $min_time, $max_time
    ) );
    foreach ( $active_campaigns_raw as $campaign ) {
        $completed = 0;
        foreach ( $trip_dates as $date ) {
            if ( $date >= $campaign['start_time'] && $date <= $campaign['end_time'] ) {
                $completed++;
            }
        }
        $campaign['progress'] = $completed;
        $active_campaigns[]   = $campaign;
    }
}

wp_send_json_success( [
    'dashboard_stats' => [
        'today_earnings'  => $today_earnings,
        'today_trips'     => $today_trips,
        'week_earnings'   => $week_earnings,
        'week_trips'      => $week_trips,
        'daily_breakdown' => $daily_breakdown,
        'avg_rating'      => $avg_rating,
    ],
    'trips_history'    => $trips_history,
    'active_campaigns' => $active_campaigns,
] );
