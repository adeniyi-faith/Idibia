<?php
/**
 * Plugin Name: Idibia Dispatch Helpers
 * Description: Driver offer dispatch and trip transition helpers.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'idibia_log_event' ) ) {
    require_once __DIR__ . '/idibia-helpers.php';
}

function idibia_dispatch_now(): string {
    return current_time( 'mysql', true );
}

function idibia_dispatch_expiry( int $seconds = 60 ): string {
    return gmdate( 'Y-m-d H:i:s', time() + $seconds );
}

function idibia_dispatch_haversine_km( float $lat1, float $lng1, float $lat2, float $lng2 ): float {
    $earth_radius = 6371;
    $d_lat = deg2rad( $lat2 - $lat1 );
    $d_lng = deg2rad( $lng2 - $lng1 );
    $a = sin( $d_lat / 2 ) ** 2 + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $d_lng / 2 ) ** 2;
    return round( $earth_radius * ( 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) ) ), 2 );
}

function idibia_expire_dispatch_offers(): void {
    global $wpdb;
    $wpdb->query( $wpdb->prepare(
        "UPDATE `{$wpdb->prefix}sd_dispatch_offers` SET status = 'expired' WHERE status = 'pending' AND expires_at < %s",
        idibia_dispatch_now()
    ) );
}

function idibia_format_driver_offer( array $row ): array {
    $expires_at = strtotime( $row['expires_at'] . ' UTC' );
    $pickup_distance = isset( $row['pickup_distance_km'] ) ? (float) $row['pickup_distance_km'] : null;

    return [
        'offer_id'           => (int) $row['offer_id'],
        'trip_id'            => (int) $row['trip_id'],
        'trip_ref'           => $row['trip_ref'],
        'pickup_address'     => $row['pickup_address'] ?: $row['pickup'],
        'dropoff_address'    => $row['dropoff_address'] ?: $row['dropoff'],
        'service_category'   => $row['service_category'] ?: 'package',
        'vehicle_type'       => $row['vehicle_type'] ?: $row['category'],
        'distance_km'        => isset( $row['distance_km'] ) ? (float) $row['distance_km'] : 0,
        'duration_mins'      => isset( $row['duration_mins'] ) ? (int) $row['duration_mins'] : 0,
        'fare'               => (float) ( $row['fare_estimate'] ?: $row['fare'] ),
        'pickup_distance_km' => $pickup_distance,
        'expires_at'         => $row['expires_at'],
        'expires_in'         => max( 0, $expires_at - time() ),
    ];
}

function idibia_get_driver_offers( int $driver_id ): array {
    global $wpdb;
    idibia_expire_dispatch_offers();

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT o.id AS offer_id, o.expires_at, t.id AS trip_id, t.trip_ref, t.pickup, t.dropoff,
                t.pickup_address, t.dropoff_address, t.category, t.service_category, t.vehicle_type,
                t.distance_km, t.duration_mins, t.fare, t.fare_estimate, dl.lat AS driver_lat, dl.lng AS driver_lng,
                CASE WHEN dl.lat IS NULL OR t.pickup_lat IS NULL THEN NULL ELSE
                    (6371 * 2 * ASIN(SQRT(POWER(SIN(RADIANS(t.pickup_lat - dl.lat) / 2), 2) + COS(RADIANS(dl.lat)) * COS(RADIANS(t.pickup_lat)) * POWER(SIN(RADIANS(t.pickup_lng - dl.lng) / 2), 2))))
                END AS pickup_distance_km
         FROM `{$wpdb->prefix}sd_dispatch_offers` o
         INNER JOIN `{$wpdb->prefix}sd_trips` t ON t.id = o.trip_id
         LEFT JOIN `{$wpdb->prefix}sd_driver_locations` dl ON dl.driver_id = o.driver_id
         WHERE o.driver_id = %d AND o.status = 'pending' AND o.expires_at >= %s
         ORDER BY o.created_at ASC
         LIMIT 10",
        $driver_id,
        idibia_dispatch_now()
    ), ARRAY_A );

    return array_map( 'idibia_format_driver_offer', $rows ?: [] );
}

function idibia_get_driver_active_trip( int $driver_id ): ?array {
    global $wpdb;
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, trip_ref, pickup, dropoff, pickup_address, dropoff_address, status, dispatch_status,
                service_category, vehicle_type, distance_km, duration_mins, fare, fare_estimate, accepted_at
         FROM `{$wpdb->prefix}sd_trips`
         WHERE driver_id = %d AND dispatch_status IN ('accepted','arriving','arrived_pickup','picked_up','arrived_dropoff')
         ORDER BY accepted_at DESC LIMIT 1",
        $driver_id
    ), ARRAY_A );

    if ( ! $row ) {
        return null;
    }

    return [
        'trip_id'          => (int) $row['id'],
        'trip_ref'         => $row['trip_ref'],
        'pickup_address'   => $row['pickup_address'] ?: $row['pickup'],
        'dropoff_address'  => $row['dropoff_address'] ?: $row['dropoff'],
        'status'           => $row['status'],
        'dispatch_status'  => $row['dispatch_status'],
        'service_category' => $row['service_category'] ?: 'package',
        'vehicle_type'     => $row['vehicle_type'],
        'distance_km'      => (float) $row['distance_km'],
        'duration_mins'    => (int) $row['duration_mins'],
        'fare'             => (float) ( $row['fare_estimate'] ?: $row['fare'] ),
    ];
}

function idibia_dispatch_trip( int $trip_id, int $limit = 5 ): array {
    global $wpdb;
    idibia_expire_dispatch_offers();

    $trip = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM `{$wpdb->prefix}sd_trips` WHERE id = %d LIMIT 1",
        $trip_id
    ), ARRAY_A );

    if ( ! $trip || ! in_array( $trip['dispatch_status'], [ 'searching', 'no_driver' ], true ) || ! empty( $trip['driver_id'] ) ) {
        return [ 'created' => 0, 'message' => 'Trip is not dispatchable.' ];
    }

    $vehicle = $trip['vehicle_type'] ?: $trip['category'];
    $now = idibia_dispatch_now();
    $drivers = $wpdb->get_results( $wpdb->prepare(
        "SELECT d.id, dl.lat, dl.lng,
                CASE WHEN dl.lat IS NULL OR %f = 0 THEN NULL ELSE
                    (6371 * 2 * ASIN(SQRT(POWER(SIN(RADIANS(%f - dl.lat) / 2), 2) + COS(RADIANS(dl.lat)) * COS(RADIANS(%f)) * POWER(SIN(RADIANS(%f - dl.lng) / 2), 2))))
                END AS pickup_distance_km
         FROM `{$wpdb->prefix}sd_drivers` d
         LEFT JOIN `{$wpdb->prefix}sd_driver_locations` dl ON dl.driver_id = d.id
         WHERE d.is_online = 1
           AND d.status = 'active'
           AND d.kyc_status = 'approved'
           AND (d.vehicle_type = %s OR d.vehicle_type IS NULL)
           AND NOT EXISTS (
               SELECT 1 FROM `{$wpdb->prefix}sd_trips` active
               WHERE active.driver_id = d.id
                 AND active.dispatch_status IN ('accepted','arriving','arrived_pickup','picked_up','arrived_dropoff')
           )
           AND NOT EXISTS (
               SELECT 1 FROM `{$wpdb->prefix}sd_dispatch_offers` existing
               WHERE existing.driver_id = d.id AND existing.trip_id = %d AND existing.status IN ('pending','accepted')
           )
         ORDER BY pickup_distance_km IS NULL ASC, pickup_distance_km ASC, d.rating DESC
         LIMIT %d",
        (float) $trip['pickup_lat'],
        (float) $trip['pickup_lat'],
        (float) $trip['pickup_lat'],
        (float) $trip['pickup_lng'],
        $vehicle,
        $trip_id,
        $limit
    ), ARRAY_A );

    $created = 0;
    foreach ( $drivers ?: [] as $driver ) {
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'sd_dispatch_offers',
            [
                'trip_id'    => $trip_id,
                'driver_id'  => (int) $driver['id'],
                'status'     => 'pending',
                'expires_at' => idibia_dispatch_expiry(),
            ],
            [ '%d', '%d', '%s', '%s' ]
        );
        if ( false !== $inserted ) {
            $created++;
        }
    }

    if ( $created > 0 ) {
        $wpdb->update(
            $wpdb->prefix . 'sd_trips',
            [ 'dispatch_status' => 'offered' ],
            [ 'id' => $trip_id ],
            [ '%s' ],
            [ '%d' ]
        );
        idibia_log_event( $trip_id, 'dispatch_offers_created', [ 'count' => $created ] );
    } else {
        $wpdb->update(
            $wpdb->prefix . 'sd_trips',
            [ 'dispatch_status' => 'no_driver' ],
            [ 'id' => $trip_id ],
            [ '%s' ],
            [ '%d' ]
        );
        idibia_log_event( $trip_id, 'dispatch_no_driver' );
    }

    return [ 'created' => $created ];
}
