<?php
/** Idibia — Trip Status Feed API */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-dispatch-helpers.php';
idibia_clean_json_buffer();

if ( $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

$trip_id = (int) ( $_GET['trip_id'] ?? 0 );
if ( $trip_id <= 0 ) {
    http_response_code( 400 );
    wp_send_json_error( [ 'message' => 'Trip ID is required.' ] );
}

if ( ! is_user_logged_in() ) {
    http_response_code( 401 );
    wp_send_json_error( [ 'message' => 'Unauthenticated.' ] );
    exit;
}

function idibia_mask_phone( ?string $phone ): string {
    $digits = preg_replace( '/\D+/', '', (string) $phone );
    return $digits ? '•••• ' . substr( $digits, -4 ) : 'Masked contact';
}

function idibia_trip_feed_eta( array $trip, ?array $location ): array {
    $base_minutes = max( 3, (int) ( $trip['duration_mins'] ?: 12 ) );
    $remaining_km = $trip['distance_km'] !== null ? (float) $trip['distance_km'] : null;

    if ( $location && in_array( $trip['dispatch_status'], [ 'accepted', 'arriving', 'arrived_pickup' ], true ) && $trip['pickup_lat'] !== null && $trip['pickup_lng'] !== null ) {
        $remaining_km = idibia_dispatch_haversine_km( (float) $location['lat'], (float) $location['lng'], (float) $trip['pickup_lat'], (float) $trip['pickup_lng'] );
        $base_minutes = max( 2, (int) ceil( $remaining_km * 4 ) );
    } elseif ( $location && in_array( $trip['dispatch_status'], [ 'picked_up', 'arrived_dropoff' ], true ) && $trip['dropoff_lat'] !== null && $trip['dropoff_lng'] !== null ) {
        $remaining_km = idibia_dispatch_haversine_km( (float) $location['lat'], (float) $location['lng'], (float) $trip['dropoff_lat'], (float) $trip['dropoff_lng'] );
        $base_minutes = max( 1, (int) ceil( $remaining_km * 4 ) );
    }

    if ( in_array( $trip['dispatch_status'], [ 'searching', 'offered', 'no_driver' ], true ) ) {
        return [ 'label' => 'Finding driver', 'minutes' => null, 'distance_km' => null ];
    }

    if ( in_array( $trip['dispatch_status'], [ 'arrived_pickup', 'arrived_dropoff' ], true ) ) {
        $base_minutes = 0;
        $remaining_km = 0;
    }

    return [
        'label'       => $base_minutes > 0 ? $base_minutes . ' min ETA' : 'Arrived',
        'minutes'     => $base_minutes,
        'distance_km' => $remaining_km !== null ? round( (float) $remaining_km, 1 ) : null,
    ];
}

function idibia_trip_timeline( array $events ): array {
    $labels = [
        'trip_created'            => 'Trip created',
        'dispatch_offers_created' => 'Searching nearby drivers',
        'offer_accepted'          => 'Driver assigned',
        'driver_en_route'         => 'Driver arriving',
        'driver_arrived_pickup'   => 'Driver at pickup',
        'package_picked_up'       => 'Picked up',
        'driver_arrived_dropoff'  => 'Near drop-off',
        'trip_completed'          => 'Delivered',
        'trip_cancelled'          => 'Cancelled',
        'dispatch_no_driver'      => 'No driver currently available',
    ];

    return array_map( static function ( $event ) use ( $labels ) {
        $type = $event['event_type'];
        return [
            'event_type' => $type,
            'label'      => $labels[ $type ] ?? ucwords( str_replace( '_', ' ', $type ) ),
            'created_at' => $event['created_at'],
            'data'       => $event['event_data'] ? json_decode( $event['event_data'], true ) : null,
        ];
    }, $events );
}

function idibia_build_trip_feed_payload( int $trip_id ): array {
    global $wpdb;

    $trip = $wpdb->get_row( $wpdb->prepare(
        "SELECT t.*, c.full_name AS customer_name, c.phone AS customer_phone
         FROM `{$wpdb->prefix}sd_trips` t
         LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = t.customer_id
         WHERE t.id = %d LIMIT 1",
        $trip_id
    ), ARRAY_A );

    if ( ! $trip ) {
        return [];
    }

    $driver_info = null;
    $driver_location = null;
    if ( ! empty( $trip['driver_id'] ) ) {
        $driver = $wpdb->get_row( $wpdb->prepare( "SELECT full_name, first_name, phone, vehicle_type, vehicle_plate, rating, total_trips FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d LIMIT 1", $trip['driver_id'] ), ARRAY_A );
        $loc = $wpdb->get_row( $wpdb->prepare( "SELECT lat, lng, heading, updated_at FROM `{$wpdb->prefix}sd_driver_locations` WHERE driver_id = %d LIMIT 1", $trip['driver_id'] ), ARRAY_A );
        $driver_location = $loc ?: null;

        if ( $driver ) {
            $driver_info = [
                'first_name'   => $driver['first_name'] ?: strtok( $driver['full_name'], ' ' ),
                'full_name'    => $driver['full_name'],
                'vehicle'      => $driver['vehicle_type'],
                'plate'        => $driver['vehicle_plate'],
                'rating'       => $driver['rating'] !== null ? (float) $driver['rating'] : null,
                'total_trips'  => (int) $driver['total_trips'],
                'masked_phone' => idibia_mask_phone( $driver['phone'] ?? '' ),
                'contact'      => 'masked_relay',
                'location'     => $loc ? [
                    'lat'        => (float) $loc['lat'],
                    'lng'        => (float) $loc['lng'],
                    'heading'    => $loc['heading'] !== null ? (float) $loc['heading'] : null,
                    'updated_at' => $loc['updated_at'],
                ] : null,
            ];
        }
    }

    $events = $wpdb->get_results( $wpdb->prepare( "SELECT event_type, event_data, created_at FROM `{$wpdb->prefix}sd_trip_events` WHERE trip_id = %d ORDER BY created_at ASC, id ASC", $trip_id ), ARRAY_A ) ?: [];
    $eta = idibia_trip_feed_eta( $trip, $driver_location );

    return [
        'trip_id'         => (int) $trip['id'],
        'trip_ref'        => $trip['trip_ref'],
        'status'          => $trip['status'],
        'dispatch_status' => $trip['dispatch_status'],
        'payment_status'  => $trip['payment_status'],
        'payment'         => idibia_payment_public_payload( $trip_id ),
        'pickup'          => $trip['pickup_address'] ?: $trip['pickup'],
        'dropoff'         => $trip['dropoff_address'] ?: $trip['dropoff'],
        'pickup_location' => [ 'lat' => $trip['pickup_lat'] !== null ? (float) $trip['pickup_lat'] : null, 'lng' => $trip['pickup_lng'] !== null ? (float) $trip['pickup_lng'] : null ],
        'dropoff_location'=> [ 'lat' => $trip['dropoff_lat'] !== null ? (float) $trip['dropoff_lat'] : null, 'lng' => $trip['dropoff_lng'] !== null ? (float) $trip['dropoff_lng'] : null ],
        'fare'            => (float) ( $trip['final_fare'] ?: $trip['fare_estimate'] ?: $trip['fare'] ),
        'distance_km'     => $trip['distance_km'] !== null ? (float) $trip['distance_km'] : null,
        'duration_mins'   => $trip['duration_mins'] !== null ? (int) $trip['duration_mins'] : null,
        'eta'             => $eta,
        'delivery_pin'    => $trip['delivery_pin'],
        'created_at'      => $trip['created_at'],
        'accepted_at'     => $trip['accepted_at'],
        'completed_at'    => $trip['completed_at'],
        'driver'          => $driver_info,
        'customer'        => [
            'name'         => $trip['customer_name'] ?: 'Customer',
            'masked_phone' => idibia_mask_phone( $trip['customer_phone'] ?? '' ),
            'contact'      => 'masked_relay',
        ],
        'timeline'        => idibia_trip_timeline( $events ),
        'actions'         => [
            'cancel'  => in_array( $trip['dispatch_status'], [ 'searching', 'offered', 'accepted' ], true ),
            'support' => true,
            'share'   => true,
        ],
    ];
}

$user_id = get_current_user_id();
$account_type = get_user_meta( $user_id, 'idibia_account_type', true );

global $wpdb;
$trip = $wpdb->get_row( $wpdb->prepare( "SELECT id, customer_id, driver_id FROM `{$wpdb->prefix}sd_trips` WHERE id = %d LIMIT 1", $trip_id ), ARRAY_A );

if ( ! $trip ) {
    http_response_code( 404 );
    wp_send_json_error( [ 'message' => 'Trip not found.' ] );
}

if ( $account_type === 'customer' ) {
    $customer_id = idibia_find_or_create_profile_row( $user_id, 'customer' );
    if ( (int) $trip['customer_id'] !== $customer_id ) {
        http_response_code( 403 );
        wp_send_json_error( [ 'message' => 'Forbidden.' ] );
    }
} elseif ( $account_type === 'driver' ) {
    $driver_id = idibia_find_or_create_profile_row( $user_id, 'driver' );
    if ( (int) $trip['driver_id'] !== $driver_id ) {
        http_response_code( 403 );
        wp_send_json_error( [ 'message' => 'Forbidden.' ] );
    }
} elseif ( ! current_user_can( 'manage_options' ) ) {
    http_response_code( 403 );
    wp_send_json_error( [ 'message' => 'Forbidden.' ] );
}

$payload = idibia_build_trip_feed_payload( $trip_id );
if ( isset( $_GET['stream'] ) && $_GET['stream'] === '1' ) {
    while ( ob_get_level() > 0 ) ob_end_clean();
    header( 'Content-Type: text/event-stream; charset=utf-8' );
    header( 'Cache-Control: no-cache' );
    header( 'X-Accel-Buffering: no' );
    echo "event: trip\n";
    echo 'data: ' . wp_json_encode( $payload ) . "\n\n";
    flush();
    exit;
}

wp_send_json_success( $payload );
