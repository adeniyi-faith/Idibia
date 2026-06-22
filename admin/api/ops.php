<?php
/** Idibia — Admin API: Live Operations, Alerts & Heatmap */

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

/**
 * Returns an array of active operational alerts: stuck trips, SOS, stale payment proofs, failed payouts, escalated disputes.
 */
function idibia_admin_get_live_alerts(): void {
    global $wpdb;

    $alerts  = [];
    $now     = gmdate( 'Y-m-d H:i:s' );
    $timeout = max( 1, (int) idibia_get_setting( 'trip_timeout_minutes', 10 ) );

    // 1. Trips stuck in searching for longer than the configured timeout
    $stuck_searching = $wpdb->get_results( $wpdb->prepare(
        "SELECT t.id AS trip_id, t.trip_ref, t.created_at, t.searching_at
         FROM `{$wpdb->prefix}sd_trips` t
         WHERE t.dispatch_status = 'searching'
           AND t.driver_id IS NULL
           AND (
               (t.searching_at IS NOT NULL AND t.searching_at <= DATE_SUB(%s, INTERVAL %d MINUTE))
               OR (t.searching_at IS NULL AND t.created_at <= DATE_SUB(%s, INTERVAL %d MINUTE))
           )
         ORDER BY t.created_at ASC LIMIT 20",
        $now, $timeout, $now, $timeout
    ), ARRAY_A ) ?: [];

    foreach ( $stuck_searching as $row ) {
        $alerts[] = [
            'alert_type'  => 'trip_stuck_searching',
            'severity'    => 'high',
            'trip_id'     => (int) $row['trip_id'],
            'user_id'     => null,
            'description' => "Trip {$row['trip_ref']} has been searching for a driver for over {$timeout} minutes.",
            'created_at'  => $row['searching_at'] ?? $row['created_at'],
        ];
    }

    // 2. In-progress trips with no driver GPS update for > 15 minutes
    $stuck_active = $wpdb->get_results(
        "SELECT t.id AS trip_id, t.trip_ref, dl.updated_at AS gps_at, d.full_name AS driver_name
         FROM `{$wpdb->prefix}sd_trips` t
         LEFT JOIN `{$wpdb->prefix}sd_drivers` d ON d.id = t.driver_id
         LEFT JOIN `{$wpdb->prefix}sd_driver_locations` dl ON dl.driver_id = t.driver_id
         WHERE t.dispatch_status IN ('accepted','arriving','arrived_pickup','picked_up','arrived_dropoff')
           AND t.driver_id IS NOT NULL
           AND (dl.updated_at IS NULL OR dl.updated_at <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE))
         ORDER BY t.created_at ASC LIMIT 20",
        ARRAY_A
    ) ?: [];

    foreach ( $stuck_active as $row ) {
        $alerts[] = [
            'alert_type'  => 'trip_stuck_in_progress',
            'severity'    => 'medium',
            'trip_id'     => (int) $row['trip_id'],
            'user_id'     => null,
            'description' => "Trip {$row['trip_ref']} is active but no GPS from driver {$row['driver_name']} for 15+ minutes.",
            'created_at'  => $row['gps_at'] ?? $now,
        ];
    }

    // 3. SOS support tickets filed in the last hour
    $tickets_table = $wpdb->prefix . 'sd_support_tickets';
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $tickets_table ) ) ) {
        $sos = $wpdb->get_results(
            "SELECT st.id AS ticket_id, st.creator_id, st.creator_type, st.created_at
             FROM `{$tickets_table}` st
             WHERE st.category LIKE '%sos%'
               AND st.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)
               AND st.status IN ('open','in_progress')
             ORDER BY st.created_at DESC LIMIT 10",
            ARRAY_A
        ) ?: [];

        foreach ( $sos as $row ) {
            $alerts[] = [
                'alert_type'  => 'sos_filed',
                'severity'    => 'critical',
                'trip_id'     => null,
                'user_id'     => (int) $row['creator_id'],
                'description' => "SOS ticket #{$row['ticket_id']} filed by {$row['creator_type']} in the last hour — immediate attention required.",
                'created_at'  => $row['created_at'],
                'ticket_id'   => (int) $row['ticket_id'],
            ];
        }
    }

    // 4. Payment proofs submitted but not reviewed for > 2 hours
    $pending_proofs = $wpdb->get_results(
        "SELECT p.id AS payment_id, p.trip_id, p.amount, p.updated_at, t.trip_ref
         FROM `{$wpdb->prefix}sd_payments` p
         LEFT JOIN `{$wpdb->prefix}sd_trips` t ON t.id = p.trip_id
         WHERE p.status = 'proof_submitted'
           AND p.reviewed_at IS NULL
           AND p.updated_at <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR)
         ORDER BY p.updated_at ASC LIMIT 10",
        ARRAY_A
    ) ?: [];

    foreach ( $pending_proofs as $row ) {
        $alerts[] = [
            'alert_type'  => 'payment_proof_pending_long',
            'severity'    => 'medium',
            'trip_id'     => (int) $row['trip_id'],
            'user_id'     => null,
            'description' => "Payment proof for trip {$row['trip_ref']} (₦" . number_format( (float) $row['amount'], 2 ) . ") has been waiting review for over 2 hours.",
            'created_at'  => $row['updated_at'],
            'payment_id'  => (int) $row['payment_id'],
        ];
    }

    // 5. Failed payouts in the last 24 hours
    $failed_payouts = $wpdb->get_results(
        "SELECT po.id AS payout_id, po.driver_id, po.amount, po.created_at, d.full_name AS driver_name
         FROM `{$wpdb->prefix}sd_payouts` po
         LEFT JOIN `{$wpdb->prefix}sd_drivers` d ON d.id = po.driver_id
         WHERE po.status = 'failed'
           AND po.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR)
         ORDER BY po.created_at DESC LIMIT 10",
        ARRAY_A
    ) ?: [];

    foreach ( $failed_payouts as $row ) {
        $alerts[] = [
            'alert_type'  => 'payout_failed',
            'severity'    => 'high',
            'trip_id'     => null,
            'user_id'     => (int) $row['driver_id'],
            'description' => "Payout of ₦" . number_format( (float) $row['amount'], 2 ) . " to driver {$row['driver_name']} failed.",
            'created_at'  => $row['created_at'],
            'payout_id'   => (int) $row['payout_id'],
        ];
    }

    // 6. Disputes escalated in the last 24 hours
    $escalated = $wpdb->get_results(
        "SELECT di.id AS dispute_id, di.trip_id, di.description, di.created_at, t.trip_ref
         FROM `{$wpdb->prefix}sd_disputes` di
         LEFT JOIN `{$wpdb->prefix}sd_trips` t ON t.id = di.trip_id
         WHERE di.status = 'escalated'
           AND di.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR)
         ORDER BY di.created_at DESC LIMIT 10",
        ARRAY_A
    ) ?: [];

    foreach ( $escalated as $row ) {
        $alerts[] = [
            'alert_type'  => 'dispute_escalated',
            'severity'    => 'high',
            'trip_id'     => (int) $row['trip_id'],
            'user_id'     => null,
            'description' => "Dispute on trip {$row['trip_ref']} was escalated: " . mb_substr( $row['description'] ?? '', 0, 100 ),
            'created_at'  => $row['created_at'],
            'dispute_id'  => (int) $row['dispute_id'],
        ];
    }

    // Sort: critical first, then high, medium, low
    $order = [ 'critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3 ];
    usort( $alerts, static fn( $a, $b ) => ( $order[ $a['severity'] ] ?? 9 ) <=> ( $order[ $b['severity'] ] ?? 9 ) );

    wp_send_json_success( [ 'alerts' => $alerts, 'count' => count( $alerts ) ] );
}

/**
 * Returns supply-vs-demand data for each active operational zone.
 * Counts online drivers and recent trip requests within each zone's radius.
 */
function idibia_admin_get_demand_supply_heatmap(): void {
    global $wpdb;

    $zones_raw = $wpdb->get_results(
        "SELECT id, name, center_lat, center_lng, radius_km FROM `{$wpdb->prefix}sd_operational_zones` WHERE is_active = 1 ORDER BY name ASC",
        ARRAY_A
    ) ?: [];

    if ( empty( $zones_raw ) ) {
        wp_send_json_success( [ 'zones' => [] ] );
    }

    $zones = [];
    foreach ( $zones_raw as $zone ) {
        $clat = (float) $zone['center_lat'];
        $clng = (float) $zone['center_lng'];
        $rkm  = (float) $zone['radius_km'];

        // Bounding box for a fast pre-filter before the precise Haversine check
        $deg_lat = $rkm / 111.0;
        $deg_lng = $rkm / max( 0.001, 111.0 * cos( deg2rad( $clat ) ) );
        $lat_min = $clat - $deg_lat;
        $lat_max = $clat + $deg_lat;
        $lng_min = $clng - $deg_lng;
        $lng_max = $clng + $deg_lng;

        // Count active drivers inside the zone
        $driver_locs = $wpdb->get_results( $wpdb->prepare(
            "SELECT dl.lat, dl.lng
             FROM `{$wpdb->prefix}sd_driver_locations` dl
             JOIN `{$wpdb->prefix}sd_drivers` d ON d.id = dl.driver_id
             WHERE d.is_online = 1 AND d.status = 'active' AND d.kyc_status = 'approved'
               AND dl.lat BETWEEN %f AND %f AND dl.lng BETWEEN %f AND %f",
            $lat_min, $lat_max, $lng_min, $lng_max
        ), ARRAY_A ) ?: [];

        $drivers_count = 0;
        foreach ( $driver_locs as $loc ) {
            $dist = function_exists( 'idibia_dispatch_haversine_km' )
                ? idibia_dispatch_haversine_km( $clat, $clng, (float) $loc['lat'], (float) $loc['lng'] )
                : idibia_heatmap_haversine( $clat, $clng, (float) $loc['lat'], (float) $loc['lng'] );
            if ( $dist <= $rkm ) { $drivers_count++; }
        }

        // Count trip requests from the last 30 minutes whose pickup falls in this zone
        $recent_trips = $wpdb->get_results( $wpdb->prepare(
            "SELECT pickup_lat, pickup_lng
             FROM `{$wpdb->prefix}sd_trips`
             WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 MINUTE)
               AND pickup_lat IS NOT NULL AND pickup_lng IS NOT NULL
               AND pickup_lat BETWEEN %f AND %f AND pickup_lng BETWEEN %f AND %f",
            $lat_min, $lat_max, $lng_min, $lng_max
        ), ARRAY_A ) ?: [];

        $quote_requests = 0;
        foreach ( $recent_trips as $t ) {
            $dist = function_exists( 'idibia_dispatch_haversine_km' )
                ? idibia_dispatch_haversine_km( $clat, $clng, (float) $t['pickup_lat'], (float) $t['pickup_lng'] )
                : idibia_heatmap_haversine( $clat, $clng, (float) $t['pickup_lat'], (float) $t['pickup_lng'] );
            if ( $dist <= $rkm ) { $quote_requests++; }
        }

        // Classify demand level by driver-to-request ratio
        if ( $quote_requests === 0 ) {
            $demand_level = $drivers_count > 0 ? 'low' : 'low';
        } elseif ( $drivers_count === 0 ) {
            $demand_level = 'high';
        } else {
            $ratio = $drivers_count / $quote_requests;
            $demand_level = $ratio >= 2 ? 'low' : ( $ratio >= 1 ? 'medium' : 'high' );
        }

        $zones[] = [
            'id'                        => (int) $zone['id'],
            'name'                      => $zone['name'],
            'center_lat'                => $clat,
            'center_lng'                => $clng,
            'radius_km'                 => $rkm,
            'active_drivers_count'      => $drivers_count,
            'quote_requests_last_30min' => $quote_requests,
            'demand_level'              => $demand_level,
        ];
    }

    wp_send_json_success( [ 'zones' => $zones ] );
}

function idibia_heatmap_haversine( float $lat1, float $lng1, float $lat2, float $lng2 ): float {
    $earth = 6371;
    $dlat  = deg2rad( $lat2 - $lat1 );
    $dlng  = deg2rad( $lng2 - $lng1 );
    $a     = sin( $dlat / 2 ) ** 2 + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $dlng / 2 ) ** 2;
    return $earth * 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
}
