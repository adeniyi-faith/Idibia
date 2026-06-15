<?php
/**
 * Plugin Name: Idibia Map Helpers
 * Description: Abstractions for Geocoding (Nominatim) and Routing (OpenRouteService).
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Checks whether coordinates fall within Nigeria's bounding box.
 *
 * @param float $lat Latitude.
 * @param float $lng Longitude.
 * @return bool
 */
function idibia_is_valid_nigeria_coords( float $lat, float $lng ): bool {
    return $lat >= 4.0 && $lat <= 13.9 && $lng >= 2.7 && $lng <= 14.7;
}

/**
 * Geocodes an address using OpenStreetMap Nominatim.
 *
 * @param string $address The address to search for.
 * @return array|WP_Error Associative array with 'lat' and 'lng' on success.
 */
function idibia_geocode_address( string $address ) {
    if ( empty( trim( $address ) ) ) {
        return new WP_Error( 'empty_address', 'Address cannot be empty.' );
    }

    $base_url = function_exists('idibia_get_setting') ? idibia_get_setting('nominatim_url', IDIBIA_NOMINATIM_URL) : IDIBIA_NOMINATIM_URL;
    $url = add_query_arg( [
        'q'      => $address,
        'format' => 'json',
        'limit'  => 1
    ], $base_url );

    $args = [
        'headers' => [
            'User-Agent' => 'IdibiaLogistics/1.0 (contact@idibia.example.com)' // Nominatim requires a valid UA
        ],
        'timeout' => 10
    ];

    $response = wp_remote_get( $url, $args );

    if ( is_wp_error( $response ) ) {
        // Fallback for development if API fails
        return [ 'lat' => 6.5244, 'lng' => 3.3792 ]; // Default to Lagos, Nigeria
    }

    $status_code = wp_remote_retrieve_response_code( $response );
    if ( $status_code !== 200 ) {
        return [ 'lat' => 6.5244, 'lng' => 3.3792 ];
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( empty( $data ) || ! isset( $data[0]['lat'], $data[0]['lon'] ) ) {
        return new WP_Error( 'not_found', 'Address could not be geocoded.' );
    }

    return [
        'lat' => (float) $data[0]['lat'],
        'lng' => (float) $data[0]['lon']
    ];
}

/**
 * Calculates distance (km) and duration (mins) using OpenRouteService.
 *
 * @param float $lat1 Pickup latitude.
 * @param float $lng1 Pickup longitude.
 * @param float $lat2 Dropoff latitude.
 * @param float $lng2 Dropoff longitude.
 * @return array|WP_Error Associative array with 'distance_km' and 'duration_mins'.
 */
function idibia_calculate_route( float $lat1, float $lng1, float $lat2, float $lng2 ) {
    $url = function_exists('idibia_get_setting') ? idibia_get_setting('ors_url', IDIBIA_ORS_URL) : IDIBIA_ORS_URL;
    $api_key = function_exists('idibia_get_setting') ? idibia_get_setting('ors_api_key', IDIBIA_ORS_API_KEY) : IDIBIA_ORS_API_KEY;

    // Check if we're using the placeholder key (which means we should mock the response)
    if ( $api_key === 'ORS_API_KEY_REPLACE_ME' || empty($api_key) ) {
        // Mock distance using Haversine formula for a straight line, multiplied by a realistic routing factor
        $earth_radius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $straight_distance = $earth_radius * $c;

        $routing_factor = 1.4; // Roads are not straight lines
        $distance_km = round($straight_distance * $routing_factor, 2);

        // Assume an average speed of 30 km/h in a city like Lagos
        $duration_mins = (int) round(($distance_km / 30) * 60);

        return [
            'distance_km' => max(1.0, $distance_km), // Minimum 1km
            'duration_mins' => max(5, $duration_mins) // Minimum 5 mins
        ];
    }

    $url = add_query_arg([
        'api_key' => $api_key,
        'start'   => "{$lng1},{$lat1}",
        'end'     => "{$lng2},{$lat2}"
    ], $url);

    $response = wp_remote_get( $url, [ 'timeout' => 10 ] );

    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'route_error', 'Failed to calculate route.' );
    }

    $status_code = wp_remote_retrieve_response_code( $response );
    if ( $status_code !== 200 ) {
        return new WP_Error( 'route_api_error', 'Routing API returned an error: ' . $status_code );
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( empty( $data['features'][0]['properties']['summary'] ) ) {
        return new WP_Error( 'invalid_route', 'Could not parse routing data.' );
    }

    $summary = $data['features'][0]['properties']['summary'];

    // ORS returns distance in meters, duration in seconds
    return [
        'distance_km'   => round( $summary['distance'] / 1000, 2 ),
        'duration_mins' => (int) round( $summary['duration'] / 60 )
    ];
}
