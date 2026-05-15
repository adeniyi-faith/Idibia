<?php
/**
 * Plugin Name: Idibia Dispatch Helpers
 * Description: Abstractions for dispatch candidate matching and offers.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Finds online, KYC-approved drivers near the pickup location who are not currently active on a trip.
 */
function idibia_find_dispatch_candidates( int $trip_id, string $vehicle_type, float $pickup_lat, float $pickup_lng, int $radius_km = 10 ): array {
    global $wpdb;

    // Use Haversine formula to sort by distance
    $query = $wpdb->prepare( "
        SELECT d.id as driver_id, l.lat, l.lng,
               ( 6371 * acos( cos( radians(%f) ) * cos( radians( l.lat ) )
               * cos( radians( l.lng ) - radians(%f) ) + sin( radians(%f) )
               * sin( radians( l.lat ) ) ) ) AS distance
        FROM `{$wpdb->prefix}sd_drivers` d
        JOIN `{$wpdb->prefix}sd_driver_locations` l ON d.id = l.driver_id
        WHERE d.is_online = 1
          AND d.kyc_status = 'approved'
          AND d.status = 'active'
          AND d.vehicle_type = %s
          AND l.updated_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
          AND NOT EXISTS (
              SELECT 1 FROM `{$wpdb->prefix}sd_trips` t
              WHERE t.driver_id = d.id AND t.status IN ('accepted', 'in_progress')
          )
          AND NOT EXISTS (
              SELECT 1 FROM `{$wpdb->prefix}sd_dispatch_offers` o
              WHERE o.trip_id = %d AND o.driver_id = d.id AND o.status IN ('declined', 'pending', 'expired')
          )
        HAVING distance <= %d
        ORDER BY distance ASC
        LIMIT 5
    ", $pickup_lat, $pickup_lng, $pickup_lat, $vehicle_type, $trip_id, $radius_km );

    $candidates = $wpdb->get_results( $query, ARRAY_A );
    return $candidates ?: [];
}

/**
 * Creates a pending offer for a driver.
 */
function idibia_create_dispatch_offer( int $trip_id, int $driver_id ): bool {
    global $wpdb;

    idibia_transaction_start();

    // Create the offer
    $inserted = $wpdb->insert(
        $wpdb->prefix . 'sd_dispatch_offers',
        [
            'trip_id'    => $trip_id,
            'driver_id'  => $driver_id,
            'status'     => 'pending',
            'expires_at' => gmdate( 'Y-m-d H:i:s', time() + 60 ), // 60-second expiry
        ],
        [ '%d', '%d', '%s', '%s' ]
    );

    if ( ! $inserted ) {
        idibia_transaction_rollback();
        return false;
    }

    // Update trip status
    $wpdb->update(
        $wpdb->prefix . 'sd_trips',
        [ 'dispatch_status' => 'offered' ],
        [ 'id' => $trip_id ],
        [ '%s' ],
        [ '%d' ]
    );

    idibia_log_event( $trip_id, 'dispatch_offered', [ 'driver_id' => $driver_id ] );

    idibia_transaction_commit();
    return true;
}

/**
 * Marks expired offers as 'expired' and triggers finding the next candidate if needed.
 */
function idibia_expire_stale_offers(): void {
    global $wpdb;

    // Find all expired pending offers
    $expired_offers = $wpdb->get_results( "
        SELECT id, trip_id, driver_id
        FROM `{$wpdb->prefix}sd_dispatch_offers`
        WHERE status = 'pending' AND expires_at <= NOW()
    " );

    if ( empty( $expired_offers ) ) {
        return;
    }

    foreach ( $expired_offers as $offer ) {
        idibia_transaction_start();

        $wpdb->update(
            $wpdb->prefix . 'sd_dispatch_offers',
            [ 'status' => 'expired' ],
            [ 'id' => $offer->id ],
            [ '%s' ],
            [ '%d' ]
        );

        idibia_log_event( (int) $offer->trip_id, 'dispatch_expired', [ 'driver_id' => $offer->driver_id ] );

        // Find trip details for the next candidate
        $trip = $wpdb->get_row( $wpdb->prepare( "SELECT id, vehicle_type, pickup_lat, pickup_lng FROM `{$wpdb->prefix}sd_trips` WHERE id = %d LIMIT 1", $offer->trip_id ) );

        if ( $trip ) {
            $candidates = idibia_find_dispatch_candidates( (int) $trip->id, $trip->vehicle_type, (float) $trip->pickup_lat, (float) $trip->pickup_lng );

            if ( ! empty( $candidates ) ) {
                idibia_create_dispatch_offer( (int) $trip->id, (int) $candidates[0]['driver_id'] );
            } else {
                $wpdb->update(
                    $wpdb->prefix . 'sd_trips',
                    [ 'dispatch_status' => 'no_driver' ],
                    [ 'id' => $trip->id ],
                    [ '%s' ],
                    [ '%d' ]
                );
                idibia_log_event( (int) $trip->id, 'dispatch_no_driver', [] );
            }
        }

        idibia_transaction_commit();
    }
}
