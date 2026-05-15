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

$pickup      = sanitize_text_field( wp_unslash( $_POST['pickup'] ?? '' ) );
$dropoff     = sanitize_text_field( wp_unslash( $_POST['dropoff'] ?? '' ) );
$category    = sanitize_text_field( wp_unslash( $_POST['category'] ?? 'package' ) );
$vehicle     = sanitize_text_field( wp_unslash( $_POST['vehicle_type'] ?? 'bike' ) );
$allowed_vehicles = [ 'bike', 'car', 'van', 'keke' ];

if ( ! $pickup || ! $dropoff ) {
    wp_send_json_error( [ 'message' => 'Pickup and drop-off addresses are required.' ] );
}

if ( ! in_array( $vehicle, $allowed_vehicles, true ) ) {
    $vehicle = 'bike';
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

// 2. Calculate route distance and duration
$route = idibia_calculate_route( $pickup_coords['lat'], $pickup_coords['lng'], $dropoff_coords['lat'], $dropoff_coords['lng'] );
if ( is_wp_error( $route ) ) {
    wp_send_json_error( [ 'message' => 'Could not calculate the route between those locations.' ] );
}

$distance_km = $route['distance_km'];
$duration_mins = $route['duration_mins'];

// 3. Calculate fare
// Get platform settings or use defaults
$min_fare = (float) get_option( 'sd_min_fare', 800 );
$base_fare = 500;
$per_km_rates = [
    'bike' => 100,
    'keke' => 150,
    'car'  => 200,
    'van'  => 400
];

$rate_per_km = $per_km_rates[$vehicle] ?? 100;
$calculated_fare = $base_fare + ( $distance_km * $rate_per_km );
$final_fare = max( $min_fare, $calculated_fare );

// 4. Generate quote token and store data
$quote_id = wp_generate_uuid4();
$quote_data = [
    'pickup_address' => $pickup,
    'pickup_lat'     => $pickup_coords['lat'],
    'pickup_lng'     => $pickup_coords['lng'],
    'dropoff_address'=> $dropoff,
    'dropoff_lat'    => $dropoff_coords['lat'],
    'dropoff_lng'    => $dropoff_coords['lng'],
    'service_category'=> $category,
    'vehicle_type'   => $vehicle,
    'distance_km'    => $distance_km,
    'duration_mins'  => $duration_mins,
    'fare_estimate'  => $final_fare,
    'created_at'     => time()
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
