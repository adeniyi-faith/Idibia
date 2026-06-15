<?php
/** Idibia — Quote API */

if ( ! ob_get_level() ) ob_start();
require_once __DIR__ . '/wp-auth-config.php';
require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-helpers.php';
idibia_clean_json_buffer();

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
}

$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
if ( ! idibia_check_rate_limit( 'quote', $ip, 20, 60 ) ) {
    http_response_code( 429 );
    wp_send_json_error( [ 'message' => 'Too many requests. Please try again later.' ] );
}

$auth_type = 'customer';
require_once __DIR__ . '/auth-helper.php';

$category    = sanitize_text_field( wp_unslash( $_POST['category'] ?? 'package' ) );
$vehicle     = sanitize_text_field( wp_unslash( $_POST['vehicle_type'] ?? 'bike' ) );
$scheduled_time = sanitize_text_field( wp_unslash( $_POST['scheduled_time'] ?? '' ) );
$allowed_vehicles = [ 'bike', 'car', 'van', 'keke' ];

if ( ! in_array( $vehicle, $allowed_vehicles, true ) ) {
    $vehicle = 'bike';
}

// MODE A — Direct coordinates mode (from Photon autocomplete selection)
if ( isset( $_POST['pickup_lat'], $_POST['pickup_lng'], $_POST['dropoff_lat'], $_POST['dropoff_lng'] ) ) {
    $pickup_lat  = filter_var( wp_unslash( $_POST['pickup_lat'] ),  FILTER_VALIDATE_FLOAT );
    $pickup_lng  = filter_var( wp_unslash( $_POST['pickup_lng'] ),  FILTER_VALIDATE_FLOAT );
    $dropoff_lat = filter_var( wp_unslash( $_POST['dropoff_lat'] ), FILTER_VALIDATE_FLOAT );
    $dropoff_lng = filter_var( wp_unslash( $_POST['dropoff_lng'] ), FILTER_VALIDATE_FLOAT );

    if ( $pickup_lat === false || $pickup_lng === false ) {
        wp_send_json_error( [ 'message' => 'Invalid pickup coordinates provided.' ] );
    }

    if ( $dropoff_lat === false || $dropoff_lng === false ) {
        wp_send_json_error( [ 'message' => 'Invalid drop-off coordinates provided.' ] );
    }

    if ( ! idibia_is_valid_nigeria_coords( $pickup_lat, $pickup_lng ) ) {
        wp_send_json_error( [ 'message' => 'Pickup location must be within Nigeria.' ] );
    }

    if ( ! idibia_is_valid_nigeria_coords( $dropoff_lat, $dropoff_lng ) ) {
        wp_send_json_error( [ 'message' => 'Drop-off location must be within Nigeria.' ] );
    }

    $pickup_coords   = [ 'lat' => $pickup_lat,  'lng' => $pickup_lng  ];
    $dropoff_coords  = [ 'lat' => $dropoff_lat, 'lng' => $dropoff_lng ];
    $pickup_address  = sanitize_text_field( wp_unslash( $_POST['pickup']  ?? '' ) );
    $dropoff_address = sanitize_text_field( wp_unslash( $_POST['dropoff'] ?? '' ) );

} else {
    // MODE B — Text address mode (original behaviour, unchanged)
    $pickup  = sanitize_text_field( wp_unslash( $_POST['pickup'] ?? '' ) );
    $dropoff = sanitize_text_field( wp_unslash( $_POST['dropoff'] ?? '' ) );

    if ( ! $pickup || ! $dropoff ) {
        wp_send_json_error( [ 'message' => 'Pickup and drop-off addresses are required.' ] );
    }

    // 1. Geocode the addresses
    $pickup_coords = idibia_geocode_address( $pickup );
    if ( is_wp_error( $pickup_coords ) ) {
        wp_send_json_error( [ 'message' => 'Could not find the pickup address.' ] );
    }

    $dropoff_coords = idibia_geocode_address( $dropoff );
    if ( is_wp_error( $dropoff_coords ) ) {
        wp_send_json_error( [ 'message' => 'Could not find the drop-off address.' ] );
    }

    $pickup_address  = $pickup;
    $dropoff_address = $dropoff;
}

// 2. Calculate route distance and duration
$route = idibia_calculate_route( $pickup_coords['lat'], $pickup_coords['lng'], $dropoff_coords['lat'], $dropoff_coords['lng'] );
if ( is_wp_error( $route ) ) {
    wp_send_json_error( [ 'message' => 'Could not calculate the route between those locations.' ] );
}

$distance_km = $route['distance_km'];
$duration_mins = $route['duration_mins'];

// 3. Calculate fare
// Get platform settings or use defaults
$min_fare = (float) idibia_get_setting( 'min_fare', 800 );
$max_radius = (float) idibia_get_setting( 'max_delivery_radius_km', 50 );

if ( $distance_km > $max_radius ) {
    wp_send_json_error( [ 'message' => "The destination is too far. Our maximum delivery radius is {$max_radius}km." ] );
}

$base_fare = (float) idibia_get_setting( 'base_fare', 500 );
$rate_per_km = (float) idibia_get_setting( 'rate_per_km_' . $vehicle, [
    'bike' => 100,
    'keke' => 150,
    'car'  => 200,
    'van'  => 400
][$vehicle] ?? 100 );

$calculated_fare = $base_fare + ( $distance_km * $rate_per_km );

// Get the commission, we might pass it as a parameter, or maybe we don't need to add it to the quote if the quote represents total fare that includes commission anyway.
$commission_pct = (int) idibia_get_setting( 'platform_commission_pct', 20 );
$final_fare = max( $min_fare, $calculated_fare );

// 4. Generate quote token and store data
$quote_id = wp_generate_uuid4();
$quote_data = [
    'pickup_address'  => $pickup_address,
    'pickup_lat'      => $pickup_coords['lat'],
    'pickup_lng'      => $pickup_coords['lng'],
    'dropoff_address' => $dropoff_address,
    'dropoff_lat'     => $dropoff_coords['lat'],
    'dropoff_lng'     => $dropoff_coords['lng'],
    'service_category'=> $category,
    'vehicle_type'    => $vehicle,
    'distance_km'     => $distance_km,
    'duration_mins'   => $duration_mins,
    'fare_estimate'   => $final_fare,
    'platform_pct'    => $commission_pct,
    'scheduled_time'  => $scheduled_time ?: null,
    'created_at'      => time()
];

// Store in transient for 5 minutes
set_transient( 'idibia_quote_' . $quote_id, $quote_data, 5 * MINUTE_IN_SECONDS );

wp_send_json_success( [
    'quote_id'      => $quote_id,
    'distance_km'   => $distance_km,
    'duration_mins' => $duration_mins,
    'fare'          => $final_fare,
    'expires_in'    => 300 // seconds
] );
